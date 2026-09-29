<?php
/*
 * One-time conversion to the permission tree (app_seeds 'permission_tree_v2').
 *
 * Before it, access came from three places: tree codes in
 * admin_user_permissions (accounts "on the tree"), dbadmin permission
 * columns (every other account, and a few columns for everyone), and
 * adminrole checks on pages. config/admin_permission_conversion.php holds,
 * for new tree nodes, a rule that answers "could this account use it
 * before?" using AdminLegacyAccount, whose methods reproduce the old checks.
 *
 * Rule keys: 'code' grants that node (and so everything below it);
 * 'code@open' says the account could open that page before, so the page's
 * nodes that have no rule of their own (sections and buttons that were not
 * restricted) are granted. A node with its own rule is decided by that rule.
 * School managers (adminrole 0) get '*'.
 */

/* Old registry: which codes an old dbadmin flag stood for on tree accounts. */
const ADMIN_OLD_FLAG_CODES = [
    'administrator' => ['executive', 'homework-approval'],
    'autodialer' => ['public-relations.safe-call.autodialer'],
    'installment_permission' => ['finance.installments'],
    'permision_actfactor' => ['finance.pending'],
    'permision_actwallet' => ['finance.wallet'],
    'permision_addcourse_student' => ['students.definitive.enrolment', 'students.temporary.enrolment'],
    'permision_addstudent' => ['students.definitive.register', 'students.temporary.register'],
    'permision_allticket' => ['tickets.all'],
    'permision_bill' => ['finance.statement', 'finance.advanced-report'],
    'permision_deletcourse' => ['courses.main.active.delete'],
    'permision_discount' => ['courses.main.discount'],
    'permision_editstudent' => ['students.definitive.edit', 'students.definitive.inactive', 'students.temporary.edit'],
    'permision_managecall' => ['public-relations.safe-call.manage', 'public-relations.safe-call.exchange', 'public-relations.safe-call.assignment'],
    'permision_notpay' => ['finance.unpaid', 'finance.returned'],
    'permision_refer' => ['tickets.referred'],
    'permision_reportcollague' => ['reports.staff'],
    'permision_reportstudent' => ['students.definitive.course-report', 'students.definitive.cancelled', 'students.definitive.suspended', 'students.definitive.cancelled-operator', 'students.temporary.report', 'reports'],
    'permision_viewbill' => ['finance'],
    'permision_viewcall' => ['public-relations'],
    'permision_viewcollague' => ['referrers'],
    'permision_viewcourse' => ['courses', 'courses.main.create', 'courses.main.archive'],
    'permision_viewdocument' => ['documents'],
    'permision_viewemploy' => ['staff'],
    'permision_viewstudent' => ['students'],
    'permision_viewsupport' => ['tickets'],
    'permission_assigned_tasks' => ['assigned-tasks'],
    'permission_calendar' => ['executive.calendar'],
    'permission_insurance' => ['finance.insurance'],
    'permission_monthly_payment' => ['finance.monthly-payments'],
    'permission_news_notif' => ['executive.daily-notices', 'executive.news'],
    'permission_staff_documents' => ['finance.staff-documents'],
    'permission_staff_view' => ['staff.directory'],
    'reportcall_permission' => ['public-relations.safe-call.reports'],
];

/* Old registry (before permission_tree_v2): the codes that were each admin page's gate on tree accounts. */
const ADMIN_OLD_PATH_CODES = [
    'admin/Bill/SearchBillView.php' => ['finance.statement'],
    'admin/Bill/active-factor.php' => ['finance.pending'],
    'admin/Bill/advanced-report.php' => ['finance.advanced-report'],
    'admin/Bill/factor-notpay.php' => ['finance.unpaid'],
    'admin/Bill/payment-archive.php' => ['finance.installments.page-6d426d3c19'],
    'admin/Bill/payment-history.php' => ['finance.installments.page-f4b5d171d1'],
    'admin/Bill/payment.php' => ['finance.installments'],
    'admin/Bill/paymentinstallments.php' => ['finance.installments.details'],
    'admin/Bill/returned.php' => ['finance.returned'],
    'admin/Bill/waitingWallet.php' => ['school-notifications.financial.wallet', 'institute-notifications.financial.wallet', 'finance.wallet'],
    'admin/Collague/add-collague.php' => ['referrers.create'],
    'admin/Collague/edit-collague.php' => ['referrers.edit'],
    'admin/Collague/sub-collague.php' => ['referrers.sub'],
    'admin/Collague/unactive-collague.php' => ['referrers.inactive'],
    'admin/Doc/announcement-create.php' => ['documents.announcements.create'],
    'admin/Doc/announcement-edit.php' => ['documents.announcements.edit'],
    'admin/Doc/announcements.php' => ['documents.announcements'],
    'admin/Doc/newdoc.php' => ['documents.new'],
    'admin/Doc/searchdoc.php' => ['documents.search'],
    'admin/Doc/special-letters.php' => ['documents.special-letters'],
    'admin/Student/add-group.php' => ['students.definitive.register.bulk'],
    'admin/Student/add.php' => ['students.definitive.register'],
    'admin/Student/addcourse.php' => ['students.definitive.enrolment'],
    'admin/Student/cancel_operator_history.php' => ['students.definitive.cancelled-operator.page-316c9bdf60'],
    'admin/Student/canceled-op-student.php' => ['students.definitive.cancelled-operator'],
    'admin/Student/canceled-student.php' => ['students.definitive.cancelled'],
    'admin/Student/edit-absolute.php' => ['students.definitive.edit'],
    'admin/Student/edit-experimental.php' => ['students.temporary.edit'],
    'admin/Student/experimental-add.php' => ['students.temporary.register'],
    'admin/Student/registration-Course-absolute.php' => ['students.definitive.course-report'],
    'admin/Student/registration-Course-experimental.php' => ['students.temporary.report'],
    'admin/Student/suspended-student.php' => ['students.definitive.suspended'],
    'admin/Student/suspension_history.php' => ['students.definitive.suspended.page-9fad65c1f6'],
    'admin/Student/unactive-student.php' => ['students.definitive.inactive'],
    'admin/Tel/assign-phone.php' => ['public-relations.safe-call.assignment'],
    'admin/Tel/bulk_sms.php' => ['public-relations.safe-sms.send'],
    'admin/Tel/hamtel.php' => ['public-relations.safe-call.exchange'],
    'admin/Tel/managecall.php' => ['public-relations.safe-call.manage'],
    'admin/Tel/reportcall.php' => ['public-relations.safe-call.reports'],
    'admin/Tel/safe_call.php' => ['public-relations.safe-call'],
    'admin/Tel/safe_sms.php' => ['public-relations.safe-sms'],
    'admin/Tel/tel-group-archive.php' => ['public-relations.safe-call.manage.groups.archive'],
    'admin/Tickets/Refer.php' => ['tickets.referred'],
    'admin/Tickets/allticket.php' => ['tickets.all'],
    'admin/Tickets/emergency.php' => ['tickets.emergency'],
    'admin/Tickets/inbox.php' => ['tickets.inbox'],
    'admin/Tickets/openTicket.php' => ['tickets.open'],
    'admin/attendance/devices.php' => ['attendance.devices'],
    'admin/attendance/import.php' => ['attendance.import'],
    'admin/attendance/report.php' => ['attendance.report'],
    'admin/attendance/rules.php' => ['attendance.rules'],
    'admin/autodialer/autodialer-black-list.php' => ['public-relations.safe-call.autodialer.blacklist'],
    'admin/autodialer/autodialer-log-report.php' => ['public-relations.safe-call.autodialer.reports'],
    'admin/autodialer/campaign-management.php' => ['public-relations.safe-call.autodialer.campaigns'],
    'admin/autodialer/contact-management.php' => ['public-relations.safe-call.autodialer.contacts'],
    'admin/autodialer/group-management.php' => ['public-relations.safe-call.autodialer.groups'],
    'admin/autodialer/voice-management.php' => ['public-relations.safe-call.autodialer.voices'],
    'admin/course/Active-course.php' => ['courses.main.active'],
    'admin/course/add-Course.php' => ['courses.main.create', 'courses.main.active.edit'],
    'admin/course/add-discount.php' => ['courses.main.discount'],
    'admin/course/attendance.php' => ['courses.main.demo-active.page-255d67bce4'],
    'admin/course/homework_check.php' => ['homework-approval'],
    'admin/course/homework_check_history.php' => ['homework-approval.page-ecad153f10'],
    'admin/course/homeworks.php' => ['homework-approval.page-ecad153f10.page-a8696be892'],
    'admin/course/unactive-course.php' => ['courses.main.archive'],
    'admin/democourse/add-democourse.php' => ['courses.main.demo-create'],
    'admin/democourse/addstudent.php' => ['students.temporary.enrolment'],
    'admin/democourse/list-democourse.php' => ['courses.main.demo-active'],
    'admin/employ/addemploy.php' => ['staff.manage.edit', 'staff.create'],
    'admin/employ/addteacher.php' => ['staff.teacher-create'],
    'admin/employ/download-document.php' => ['finance.staff-documents.page-610cdeb542'],
    'admin/employ/insurance.php' => ['finance.insurance'],
    'admin/employ/listadmin.php' => ['staff.manage'],
    'admin/employ/listteacher.php' => ['staff.teachers'],
    'admin/employ/staff-directory.php' => ['staff.directory'],
    'admin/employ/staff-documents.php' => ['finance.staff-documents'],
    'admin/finance/monthly-payments.php' => ['finance.monthly-payments'],
    'admin/finance/referrer-commission.php' => ['finance.referrer-commission'],
    'admin/handhug/add-handhug.php' => ['courses.handhug.create', 'courses.handhug.manage.edit'],
    'admin/handhug/disabled-handhugs.php' => ['courses.handhug.archive'],
    'admin/handhug/handhug-management.php' => ['courses.handhug.manage'],
    'admin/index.php' => ['dashboard'],
    'admin/notifications/NoOperator_student_op_ticket.php' => ['dashboard.summary.overview.desktop.page-1938484300.page-bd5ee72b25'],
    'admin/notifications/daily_notifications.php' => ['executive.daily-notices'],
    'admin/notifications/new_student_tickets.php' => ['dashboard.summary.overview.desktop.page-1938484300.page-86bedc87c2'],
    'admin/notifications/new_students.php' => ['dashboard.summary.overview.desktop.page-1938484300.page-2994e3de80'],
    'admin/notifications/news_panel.php' => ['executive.news'],
    'admin/notifications/notif_rule_management.php' => ['school-notifications.information.rules', 'institute-notifications.information.rules'],
    'admin/notifications/notification_view.php' => ['school-notifications.information.operator', 'institute-notifications.information.operator'],
    'admin/notifications/notifications.php' => ['school-notifications.information.my-calls', 'institute-notifications.information.my-calls'],
    'admin/notifications/operator_notif.php' => ['dashboard.summary.overview.desktop.page-1938484300'],
    'admin/notifications/reactivate_tickets.php' => ['tickets.reactivate-requests'],
    'admin/notifications/school_notifications.php' => ['school-notifications', 'institute-notifications'],
    'admin/notifications/studentoperator_changes_notif.php' => ['dashboard.summary.overview.desktop.page-1938484300.page-92fd2798a7'],
    'admin/notifications/suspended_student_op_ticket.php' => ['dashboard.summary.overview.desktop.page-1938484300.page-c05d6fa040'],
    'admin/notifications/teachers_notif.php' => ['dashboard.summary.overview.desktop.page-1938484300.page-e432461257'],
    'admin/notifications/thread_list.php' => ['school-notifications.information.tickets', 'institute-notifications.information.tickets'],
    'admin/notifications/unread_sms.php' => ['public-relations.safe-sms.unread', 'school-notifications.information.sms', 'institute-notifications.information.sms'],
    'admin/notifications/verify_by_manager.php' => ['school-notifications.financial.invoices', 'institute-notifications.financial.invoices'],
    'admin/panel/academic-calendar.php' => ['executive.academic-calendar'],
    'admin/panel/academic_year_students.php' => ['dashboard.summary.cards.students'],
    'admin/panel/all_users_management.php' => ['dashboard.summary.overview.desktop.page-9429aecc1d'],
    'admin/panel/alltypeusers.php' => ['dashboard.summary.overview.desktop.page-1a5467cd1e'],
    'admin/panel/calendar_panel.php' => ['executive.calendar'],
    'admin/panel/canceled_student_list.php' => ['students.definitive.cancelled-support'],
    'admin/panel/course_detail.php' => ['dashboard.summary.cards.courses'],
    'admin/panel/desktop.php' => ['dashboard.summary.overview.desktop'],
    'admin/panel/late_supports.php' => ['dashboard.summary.overview.desktop.page-2e7b3fc155'],
    'admin/panel/manager.php' => ['dashboard.summary.overview.manager'],
    'admin/panel/mypersonalcourse.php' => ['students.definitive.my-courses'],
    'admin/panel/mystudent.php' => ['students.definitive.my-students'],
    'admin/panel/mystudent_status.php' => ['dashboard.summary.overview.desktop.page-433bcbe86e'],
    'admin/panel/myusers-seller.php' => ['dashboard.summary.overview.desktop.seller-users'],
    'admin/panel/permanet-users-sales.php' => ['dashboard.summary.overview.manager.page-25d387505a', 'dashboard.summary.overview.desktop.sales-permanent'],
    'admin/panel/reactivate_experimental.php' => ['dashboard.summary.overview.manager.page-68e9102f5d'],
    'admin/panel/resell-students.php' => ['dashboard.summary.overview.desktop.page-8477ff94be'],
    'admin/panel/staff_management.php' => ['dashboard.summary.cards.staff'],
    'admin/panel/suspension_list.php' => ['students.definitive.suspended-support'],
    'admin/panel/temp-users-sales.php' => ['dashboard.summary.overview.manager.page-b9857fa208', 'dashboard.summary.overview.desktop.sales-temporary'],
    'admin/panel/today_supports.php' => ['dashboard.summary.overview.desktop.page-22ec7ae990'],
    'admin/panel/tomorrow_supports.php' => ['dashboard.summary.overview.desktop.page-c3f5236e5f'],
    'admin/report/colleague.php' => ['reports.staff'],
    'admin/report/students.php' => ['reports.students'],
    'admin/select-academic-year.php' => ['dashboard.summary.academic-archive'],
    'admin/setting/blacklist.php' => ['settings.blacklist'],
    'admin/setting/credit-card.php' => ['finance.cards'],
    'admin/setting/edit-ready-message.php' => ['dashboard.summary.overview.manager.ready-messages.edit'],
    'admin/setting/levels.php' => ['settings.levels'],
    'admin/setting/ready-message.php' => ['dashboard.summary.overview.manager.ready-messages'],
    'admin/setting/setting.php' => ['settings.general'],
    'admin/tasks/assigned-tasks.php' => ['assigned-tasks'],
    'admin/wallet/dashboard.php' => ['finance.statement.page-305781e9e2'],
    'admin/wallet/exchange.php' => ['finance.statement.page-99db970aaa'],
    'admin/wallet/searchwalletview.php' => ['finance.statement.page-1781825a6a'],
];

/** An account as the old permission checks saw it. */
final class AdminLegacyAccount
{
    public bool $tree;

    /** @param array<string,mixed> $row dbadmin row; @param string[] $oldCodes its admin_user_permissions rows */
    public function __construct(public array $row, public array $oldCodes)
    {
        $this->tree = $oldCodes !== [];
    }

    public function role(int ...$roles): bool
    {
        $role = $this->row['adminrole'] ?? null;
        return $role !== null && $role !== '' && in_array((int)$role, $roles, true);
    }

    public function roleIs(?int $role): bool
    {
        $mine = $this->row['adminrole'] ?? null;
        return $role === null ? ($mine === null || $mine === '') : ($mine !== null && $mine !== '' && (int)$mine === $role);
    }

    public function isTeacher(): bool { return $this->role(3); }

    /** School post (school_admin_roles.role_key), e.g. manager, grade_deputy. */
    public function post(string ...$keys): bool
    {
        return in_array((string)($this->row['school_role_key'] ?? ''), $keys, true);
    }

    /** A dbadmin column is set (old flags, can_impersonate, teacher_perm_* ...). */
    public function col(string $column): bool
    {
        return !empty($this->row[$column]);
    }

    /** Old tree: the code itself or an ancestor of it was granted. */
    public function old(string $code): bool
    {
        foreach ($this->oldCodes as $granted) {
            if ($granted === $code || str_starts_with($code, $granted . '.')) return true;
        }
        return false;
    }

    /** Old tree: the code, an ancestor or a descendant was granted (old AdminHasMenuNavigationPermission). */
    public function oldSee(string $code): bool
    {
        if ($this->old($code)) return true;
        foreach ($this->oldCodes as $granted) if (str_starts_with($granted, $code . '.')) return true;
        return false;
    }

    /** Old Permision($flag). */
    public function perm(string $flag): bool
    {
        if ($flag === 'permision_viewsetting') return false;
        $mapped = ADMIN_OLD_FLAG_CODES[$flag] ?? [];
        if ($mapped !== [] && $this->tree) {
            foreach ($mapped as $code) {
                if ($flag === 'administrator' ? $this->old($code) : $this->oldSee($code)) return true;
            }
            return false;
        }
        return $this->col($flag);
    }

    /** Old AdminCanUsePermissionScope($code, $flags). */
    public function can(string $code, array $flags = []): bool
    {
        if ($this->tree) return $this->oldSee($code);
        foreach ($flags as $flag) if (!$this->perm($flag)) return false;
        return $flags !== [];
    }

    /** Old AdminMenuCan($code, $legacyAllowed). */
    public function menu(string $code, bool $legacyAllowed): bool
    {
        return $this->tree ? $this->oldSee($code) : $legacyAllowed;
    }

    /** Old AdminRecordVisibility($code, $legacyAll): 'all' | 'own' | null. */
    public function scope(string $code, bool $legacyAll): ?string
    {
        if (!$this->tree) return $legacyAll ? 'all' : 'own';
        if ($this->old($code) || $this->old($code . '.all')) return 'all';
        if ($this->old($code . '.own')) return 'own';
        return null;
    }

    /** Old TeacherPermission($perm): only narrows teachers; others always passed. */
    public function teacher(string $perm): bool
    {
        return !$this->isTeacher() || $this->col($perm);
    }
}

/** @return string[] the new grant set for one account. */
function AdminConvertAccount(AdminLegacyAccount $account, array $rules): array
{
    if ($account->roleIs(0)) return [ADMIN_PERMISSION_ALL];
    $index = AdminPermissionIndex();
    $granted = [];
    $open = [];
    foreach ($rules as $key => $rule) {
        if (str_ends_with($key, '@open')) {
            $code = substr($key, 0, -5);
            if (isset($index[$code]) && $rule($account)) $open[$code] = true;
        } elseif (isset($index[$key]) && $rule($account)) {
            $granted[$key] = true;
        }
    }
    // A node without a rule follows its parent: granted with it, or granted
    // because the parent page could be opened. Parents come before children.
    // A section that holds nodes with rules is only opened, never granted
    // whole, so those nodes keep their own answer.
    $ruled = [];
    foreach ($rules as $key => $_) {
        $code = str_ends_with($key, '@open') ? substr($key, 0, -5) : $key;
        for ($node = $index[$code]['parent'] ?? null; $node !== null; $node = $index[$node]['parent'] ?? null) $ruled[$node] = true;
    }
    foreach ($index as $code => $meta) {
        // A node with its own rule (a grant or an @open rule) is decided by that rule alone.
        if (isset($rules[$code]) || isset($rules[$code . '@open']) || isset($granted[$code]) || $meta['parent'] === null) continue;
        $parent = $meta['parent'];
        if (!isset($granted[$parent]) && !isset($open[$parent])) continue;
        if (isset($ruled[$code])) $open[$code] = true; else $granted[$code] = true;
    }
    // Accounts on the old tree could open a page only through its old gate.
    // A page that gate covered and this account did not pass keeps nothing,
    // neither the page nor anything inside it (sub-pages are checked on their own).
    if ($account->tree) {
        foreach ($index as $code => $meta) {
            $oldGate = [];
            foreach ($meta['paths'] as $path) foreach (ADMIN_OLD_PATH_CODES[$path] ?? [] as $oldCode) $oldGate[] = $oldCode;
            if ($oldGate === []) continue;
            $passed = false;
            foreach ($oldGate as $oldCode) if ($account->oldSee($oldCode)) { $passed = true; break; }
            if ($passed) continue;
            foreach (array_keys($granted) as $held) {
                if ($held !== $code && !str_starts_with($held, $code . '.')) continue;
                $page = $held;
                for ($node = $held; $node !== $code; $node = $index[$node]['parent']) {
                    if ($index[$node]['paths'] !== []) { $page = null; break; }
                }
                if ($page !== null) unset($granted[$held]);
            }
        }
    }
    // Keep the top-most granted node of each branch.
    $result = [];
    foreach ($granted as $code => $_) {
        $covered = false;
        for ($node = $index[$code]['parent']; $node !== null; $node = $index[$node]['parent'] ?? null) {
            if (isset($granted[$node])) { $covered = true; break; }
        }
        if (!$covered) $result[] = $code;
    }
    sort($result);
    return $result;
}

/** @return array<string, string[]> username => new grants, for every account (no writes). */
function AdminConvertAllAccounts(): array
{
    global $conn;
    $rules = require BASE_PATH . '/config/admin_permission_conversion.php';
    $old = [];
    foreach ($conn->query('SELECT admin_username, permission_code FROM admin_user_permissions')->fetchAll(PDO::FETCH_NUM) as [$user, $code]) {
        if ($code === '__none__') { $old[$user] ??= []; $old[$user][] = '__none__'; continue; }
        $old[$user][] = (string)$code;
    }
    $result = [];
    try {
        $rows = $conn->query('SELECT d.*, r.role_key AS school_role_key FROM dbadmin d LEFT JOIN school_admin_roles r ON r.id = d.school_role_id')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = $conn->query('SELECT * FROM dbadmin')->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($rows as $row) {
        $user = (string)$row['username'];
        $account = new AdminLegacyAccount($row, array_values(array_diff($old[$user] ?? [], ['__none__'])));
        if (isset($old[$user]) && !$account->tree) $account->tree = true; // saved empty on the tree
        $result[$user] = AdminConvertAccount($account, $rules);
    }
    return $result;
}

/* Runs once per panel database, on the first request after deploy. */
function AdminPermissionTreeV2Seed(): void
{
    global $conn;
    static $checked = false;
    if ($checked || !($conn instanceof PDO)) return;
    $checked = true;
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS app_seeds (name VARCHAR(64) NOT NULL PRIMARY KEY, done_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($conn->query("SELECT 1 FROM app_seeds WHERE name = 'permission_tree_v2'")->fetchColumn()) return;
        $conn->exec('CREATE TABLE IF NOT EXISTS admin_user_permissions (admin_username VARCHAR(50) NOT NULL, permission_code VARCHAR(120) NOT NULL, granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (admin_username, permission_code), KEY idx_permission_code (permission_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $conn->exec('CREATE TABLE IF NOT EXISTS admin_user_permissions_v1 LIKE admin_user_permissions');
        if (!$conn->query('SELECT 1 FROM admin_user_permissions_v1 LIMIT 1')->fetchColumn()) {
            $conn->exec('INSERT INTO admin_user_permissions_v1 SELECT * FROM admin_user_permissions');
        }
        $converted = AdminConvertAllAccounts();
        $conn->beginTransaction();
        if ($conn->query("SELECT 1 FROM app_seeds WHERE name = 'permission_tree_v2' FOR UPDATE")->fetchColumn()) {
            $conn->rollBack();
            return;
        }
        $conn->exec('DELETE FROM admin_user_permissions');
        $insert = $conn->prepare('INSERT INTO admin_user_permissions (admin_username, permission_code) VALUES (?, ?)');
        foreach ($converted as $user => $codes) foreach ($codes as $code) $insert->execute([$user, $code]);
        $conn->exec("INSERT INTO app_seeds (name) VALUES ('permission_tree_v2')");
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('permission_tree_v2: ' . $e->getMessage());
    }
}

/*
 * The old permission columns of dbadmin, read only by the conversion above.
 * Once the panel is converted they are copied to dbadmin_permission_columns_v1
 * (username + the columns, to redo a conversion if ever needed) and dropped.
 * adminrole, school_role_id and per_skyroom (the Skyroom room role) stay.
 */
function AdminLegacyPermissionColumns(): array
{
    global $conn;
    return $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dbadmin'
          AND (COLUMN_NAME LIKE 'permision\_%' OR COLUMN_NAME LIKE 'permission\_%'
               OR COLUMN_NAME LIKE '%\_permission' OR COLUMN_NAME LIKE 'teacher\_perm\_%'
               OR COLUMN_NAME IN ('administrator', 'can_impersonate', 'autodialer'))")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/* Runs once per panel database, after AdminPermissionTreeV2Seed(). */
function AdminDropLegacyPermissionColumns(): void
{
    global $conn;
    static $checked = false;
    if ($checked || !($conn instanceof PDO)) return;
    $checked = true;
    try {
        if (!$conn->query("SELECT 1 FROM app_seeds WHERE name = 'permission_tree_v2'")->fetchColumn()) return;
        if ($conn->query("SELECT 1 FROM app_seeds WHERE name = 'permission_columns_dropped'")->fetchColumn()) return;
        if (!$conn->query("SELECT GET_LOCK('permission_columns_dropped', 0)")->fetchColumn()) return;
        try {
            if ($conn->query("SELECT 1 FROM app_seeds WHERE name = 'permission_columns_dropped'")->fetchColumn()) return;
            $columns = array_map(static fn($c) => '`' . str_replace('`', '', (string)$c) . '`', AdminLegacyPermissionColumns());
            if ($columns) {
                if (!$conn->query("SHOW TABLES LIKE 'dbadmin_permission_columns_v1'")->fetchColumn()) {
                    $conn->exec('CREATE TABLE dbadmin_permission_columns_v1 ENGINE=InnoDB AS SELECT username, ' . implode(', ', $columns) . ' FROM dbadmin');
                }
                $conn->exec('ALTER TABLE dbadmin DROP COLUMN ' . implode(', DROP COLUMN ', $columns));
            }
            $conn->exec("INSERT INTO app_seeds (name) VALUES ('permission_columns_dropped')");
        } finally {
            $conn->query("SELECT RELEASE_LOCK('permission_columns_dropped')");
        }
    } catch (Throwable $e) {
        error_log('permission_columns_dropped: ' . $e->getMessage());
    }
}
