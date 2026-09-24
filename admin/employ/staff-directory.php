<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';

if (!isset($_SESSION['admin'])) { header('location: ' . url('admin/login.php')); exit; }
if (($_SESSION['admin']['ip'] ?? '') !== GetIp() && ($_SESSION['admin']['os'] ?? '') != GetOs()) { session_unset(); header('location: '.url('admin/login.php')); exit; }
$sessionRow=sessionAdmin($_SESSION['admin']['user']); if(session_id()!=($sessionRow['session_id']??null)){session_unset();header('location: '.url('admin/login.php'));exit;}
if (!Permision('permission_staff_view') && !Permision('permision_viewemploy')) {
    http_response_code(403); exit('مجوز مشاهده همکاران برای شما تعریف نشده است.');
}

$search = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT a.username,a.fname,a.lname,a.mobile,a.dakheli,a.adminactive,r.title AS role_title
        FROM dbadmin a LEFT JOIN school_admin_roles r ON r.id=a.school_role_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (a.username LIKE ? OR a.fname LIKE ? OR a.lname LIKE ? OR a.mobile LIKE ?)';
    $like = '%' . $search . '%'; $params = [$like,$like,$like,$like];
}
$sql .= ' ORDER BY a.adminactive DESC,a.lname,a.fname';
$stmt=$conn->prepare($sql); $stmt->execute($params); $staff=$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مشاهده همکاران | <?= __TITLE ?></title><?php include '../link.php'; ?></head>
<body class="main-body leftmenu"><div class="page"><?php include '../menu.php'; include '../header.php'; ?>
<div class="main-content side-content pt-0"><div class="container-fluid"><div class="inner-body">
<div class="page-header"><div><h2 class="main-content-title tx-24">فهرست همکاران</h2><p class="text-muted">مشاهده اطلاعات عمومی همکاران و سمت مدرسه</p></div></div>
<div class="card custom-card"><div class="card-body">
<form class="row mb-3" method="get"><div class="col-md-8"><input class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="جستجو بر اساس نام، نام کاربری یا موبایل"></div><div class="col-md-4"><button class="btn btn-primary">جستجو</button></div></form>
<div class="table-responsive"><table class="table table-bordered table-hover text-center"><thead><tr><th>#</th><th>نام و نام خانوادگی</th><th>نام کاربری</th><th>سمت</th><th>موبایل</th><th>داخلی</th><th>وضعیت</th></tr></thead><tbody>
<?php foreach($staff as $i=>$row): ?><tr><td><?= $i+1 ?></td><td><?= htmlspecialchars(trim($row['fname'].' '.$row['lname'])) ?></td><td><?= htmlspecialchars($row['username']) ?></td><td><?= htmlspecialchars($row['role_title'] ?: 'تعیین‌نشده') ?></td><td><?= htmlspecialchars((string)$row['mobile']) ?></td><td><?= htmlspecialchars((string)$row['dakheli']) ?></td><td><span class="badge badge-<?= $row['adminactive'] ? 'success' : 'secondary' ?>"><?= $row['adminactive'] ? 'فعال' : 'غیرفعال' ?></span></td></tr><?php endforeach; ?>
<?php if(!$staff): ?><tr><td colspan="7">همکاری یافت نشد.</td></tr><?php endif; ?>
</tbody></table></div></div></div></div></div></div></div><?php include '../../footers.php'; ?></body></html>
