<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require dirname(__DIR__) . '/dbconfig.php';
require_once dirname(__DIR__) . '/inc/function.php';
$tree = AdminPermissionRegistry();
$conn->exec('CREATE TABLE IF NOT EXISTS admin_permission_nodes (code VARCHAR(120) NOT NULL PRIMARY KEY, parent_code VARCHAR(120) NULL, label VARCHAR(255) NOT NULL, legacy_column VARCHAR(100) NULL, modes VARCHAR(30) NOT NULL DEFAULT "school,institute", sort_order INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$conn->exec('CREATE TABLE IF NOT EXISTS admin_user_permissions (admin_username VARCHAR(50) NOT NULL, permission_code VARCHAR(120) NOT NULL, granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (admin_username, permission_code), KEY idx_permission_code (permission_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$upsert = $conn->prepare('INSERT INTO admin_permission_nodes(code,parent_code,label,legacy_column,modes,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE parent_code=VALUES(parent_code),label=VALUES(label),legacy_column=VALUES(legacy_column),modes=VALUES(modes),sort_order=VALUES(sort_order)');
$seed = function(array $nodes, ?string $parent = null) use (&$seed, $upsert) { foreach ($nodes as $position=>$node) { $upsert->execute([$node['code'],$parent,$node['label'],$node['legacy']??null,implode(',', $node['modes']??['school','institute']),$position]); if (!empty($node['children'])) $seed($node['children'],$node['code']); } };
$seed($tree);

/* Preserve grants created by the first tree version, where the two safe-call
   cards were incorrectly stored below the manage card instead of beside it. */
$movedCodes = [
    'public-relations.safe-call.manage.exchange' => 'public-relations.safe-call.exchange',
    'public-relations.safe-call.manage.assignment' => 'public-relations.safe-call.assignment',
];
$copyGrant = $conn->prepare('INSERT IGNORE INTO admin_user_permissions(admin_username, permission_code) SELECT admin_username, ? FROM admin_user_permissions WHERE permission_code = ?');
$deleteGrant = $conn->prepare('DELETE FROM admin_user_permissions WHERE permission_code = ?');
$deleteNode = $conn->prepare('DELETE FROM admin_permission_nodes WHERE code = ?');
foreach ($movedCodes as $oldCode => $newCode) {
    $copyGrant->execute([$newCode, $oldCode]);
    $deleteGrant->execute([$oldCode]);
    $deleteNode->execute([$oldCode]);
}

echo "Admin permission registry migrated.\n";
