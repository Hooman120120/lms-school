<?php

include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

$currentUser = $_SESSION['admin']['user'];
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
$teacherCourseTable = 'course_teacher'; // ← your mapping table (id, codecourse, teacher_username)

$handhugStmt = $conn->prepare("SELECT * FROM codtbl_course WHERE codecourse = ?");
$handhugStmt->execute([$_GET['code']]);
$thishandhug = $handhugStmt->fetch(PDO::FETCH_OBJ);

if (isset($_POST['add-group'])) {

    $sql = "INSERT INTO handhug_groups
    (codecourse, group_name, is_active, link_room, supporter, max_users, creator)
    VALUES (?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        intval($_POST['codecourse']),
        $_POST['group_name'],
        intval($_POST['is_active']),
        $_POST['link_room'] ?? null,

        // supporter from hidden input
        $_POST['supporter'] ?? null,

        intval($_POST['max_users']),

        // creator from hidden input
        $_SESSION['admin']['user'] ?? 'system'
    ]);
}
if (isset($_POST['edit-group'])) {

    $sql = "UPDATE handhug_groups
            SET group_name = ?,
                link_room  = ?,
                max_users  = ?,
                is_active  = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_POST['group_name'],
        $_POST['link_room'] ?? null,
        intval($_POST['max_users']),
        intval($_POST['is_active']),
        intval($_POST['group_id'])
    ]);
    header("Location: handhug-group-manage.php?code=" . intval($_GET['code']));
}
function groupshow($conn)
{
    $role = (int)($_SESSION['admin']['adminrole'] ?? -1);
    $user = $_SESSION['admin']['user'] ?? '';
    $code = intval($_GET['code'] ?? 0);
    if (in_array($role, [0, 6, 51, 52])) {
        $sql = "
                                                                    SELECT *
                                                                    FROM handhug_groups
                                                                    WHERE codecourse = ?
                                                                    ORDER BY created_at DESC
                                                                ";
        $params = [$code];
    }
    elseif ($role === 1) {
        $sql = "
    SELECT DISTINCT hg.*
    FROM handhug_groups hg
    LEFT JOIN dbcourse dc
        ON dc.group_id = hg.id
       AND dc.suporter = ?
    WHERE hg.codecourse = ?
      AND (
            dc.suporter IS NOT NULL
            OR hg.supporter = ?
          )
    ORDER BY hg.created_at DESC
";

        $params = [
            $user, // for dc.suporter in JOIN
            $code, // course code
            $user  // for hg.supporter
        ];
    }
    else {
        return [];
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

$result = groupshow($conn);


if (isset($_GET['toggle_group'], $_GET['to'])) {

    $groupId = intval($_GET['toggle_group']);
    $to      = intval($_GET['to']);

    if ($to === 0 || $to === 1) {
        $stmt = $conn->prepare(
            "UPDATE handhug_groups SET is_active = ? WHERE id = ?"
        );
        $stmt->execute([$to, $groupId]);

        $_SESSION['message'] = $to
            ? 'گروه با موفقیت فعال شد'
            : 'گروه با موفقیت غیرفعال شد';
    }

    header("Location: handhug-group-manage.php?code=" . intval($_GET['code']));
}

$stmt = $conn->prepare("
    SELECT username
    FROM dbadmin
    WHERE adminrole = 1
      AND adminactive = 1
    ORDER BY fname, lname
");
$stmt->execute();
$supporters = $stmt->fetchAll(PDO::FETCH_OBJ);

if (isset($_POST['change-supporter'])) {

    $group_id  = intval($_POST['group_id']);
    $supporter = trim($_POST['supporter']);

    $check = $conn->prepare("
        SELECT 1
        FROM dbadmin
        WHERE username = ?
          AND adminrole = 1
          AND adminactive = 1
    ");
    $check->execute([$supporter]);

    if (!$check->fetch()) {
        die('Invalid supporter');
    }

    // Update group
    $stmt = $conn->prepare("
        UPDATE handhug_groups
        SET supporter = ?
        WHERE id = ?
    ");
    $stmt->execute([$supporter, $group_id]);

    header("Location: handhug-group-manage.php?code=" . intval($_GET['code']));
    exit;
}




include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> پایه های فعال | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!-- <div id="global-loader">
    <img src="<//?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div> -->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';

    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div style="padding:10px;">
                    <style>
                        .custom-tabs .nav-link {
                            border: none;
                            border-radius: 50px;
                            margin-right: 10px;
                            padding: 10px 25px;
                            font-weight: bold;
                            color: #555;
                            background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
                            transition: all 0.3s ease;
                            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.1);
                        }

                        .custom-tabs .nav-link:hover {
                            background: linear-gradient(145deg, #4e73df, #1cc88a);
                            color: #fff;
                            transform: translateY(-3px);
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2);
                        }

                        .custom-tabs .nav-link.active {
                            background: linear-gradient(145deg, #1cc88a, #36b9cc);
                            color: #fff;
                            font-size: 1.05rem;
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.25);
                        }
                    </style>
                    <ul class="nav nav-tabs mb-3 custom-tabs" id="courseTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="type1-tab" data-bs-toggle="tab" data-bs-target="#type1"
                                    type="button" role="tab">
                                گروه های
                                <?php echo $thishandhug->coursename  ?>
                            </button>
                        </li>
                    </ul>

                </div>

                <div class="row row-sm">
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']);
                    } ?>


                    <div class="tab-content custom-tab-pane">
                        <!-- Type 1 tab -->
                            <div class="tab-pane fade show active" id="type1" role="tabpanel">
                                <div class="col-lg-12">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <button type="button" style="margin-bottom: 20px;"
                                                                            class="btn btn-success"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#addGroupModal">
                                                + ایجاد گروه
                                            </button>

                                            <div class="table">
                                                <table class="table table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
                                                        <th>اقدامات</th>
                                                        <th>گروه</th>
                                                        <th>سرگروه</th>
                                                        <th>حداکثر تعداد دانش آموز</th>
                                                        <th>وضعیت گروه</th>
                                                        <?php if (Permision('administrator')) { ?>
                                                        <th>پشتیبان</th>
                                                        <?php } ?>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php

                                                    if (count($result) > 0) {
                                                        foreach ($result as $res) {  ?>
                                                            <tr class="align-middle text-center">
                                                                <td>
                                                                    <div class="dropdown">
                                                                        <button
                                                                                class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                                                type="button"
                                                                                data-bs-toggle="dropdown"
                                                                                aria-expanded="false">
                                                                            اقدام
                                                                            <i class="bi bi-caret-down-fill ms-1"></i>
                                                                        </button>

                                                                        <div class="dropdown-menu dropdown-menu-end text-start">

                                                                            <!-- Activate / Deactivate -->
                                                                            <?php if ($res->is_active == 1) { ?>
                                                                                <a class="dropdown-item text-warning"
                                                                                   href="?code=<?= intval($_GET['code']) ?>&toggle_group=<?= $res->id ?>&to=0"
                                                                                   onclick="return confirm('آیا از غیرفعال کردن این گروه مطمئن هستید؟')">
                                                                                    <i class="bi bi-toggle-off me-2"></i>
                                                                                    غیرفعال کردن
                                                                                </a>
                                                                            <?php } else { ?>
                                                                                <a class="dropdown-item text-success"
                                                                                   href="?code=<?= intval($_GET['code']) ?>&toggle_group=<?= $res->id ?>&to=1">
                                                                                    <i class="bi bi-toggle-on me-2"></i>
                                                                                    فعال کردن
                                                                                </a>
                                                                            <?php } ?>

                                                                            <hr class="dropdown-divider">

                                                                            <!-- Students list -->
                                                                            <a class="dropdown-item"
                                                                               href="<?= url(
                                                                                   'admin/handhug/add-handhug-users.php?code=' . intval($_GET['code']) .
                                                                                   '&group_id=' . intval($res->id)
                                                                               ) ?>">
                                                                                <i class="bi bi-people me-2"></i>
                                                                                اعضای گروه
                                                                            </a>

                                                                            <form method="post">
                                                                                <input type="hidden" name="change-supporter" value="1">
                                                                                <input type="hidden" name="group_id" value="<?= $res->id; ?>">

                                                                                <select name="supporter"
                                                                                        class="form-select"
                                                                                        required
                                                                                        onchange="this.form.submit()">
                                                                                    <option value="">تغییر پشتیبان</option>

                                                                                    <?php foreach ($supporters as $sup): ?>
                                                                                        <option value="<?= htmlspecialchars($sup->username) ?>">
                                                                                            <?= htmlspecialchars($sup->username) ?>
                                                                                        </option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </form>
                                                                            <hr class="dropdown-divider">

                                                                            <!-- Edit group -->
                                                                            <a class="dropdown-item text-info edit-group-btn"
                                                                               href="#"
                                                                               data-bs-toggle="modal"
                                                                               data-bs-target="#addGroupModal"

                                                                               data-id="<?= $res->id ?>"
                                                                               data-name="<?= htmlspecialchars($res->group_name) ?>"
                                                                               data-room="<?= htmlspecialchars($res->link_room) ?>"
                                                                               data-max="<?= $res->max_users ?>"
                                                                               data-active="<?= $res->is_active ?>"

                                                                            >
                                                                                <i class="bi bi-pencil-square me-2"></i>
                                                                                ویرایش گروه
                                                                            </a>



                                                                            <!-- Delete group -->
                                                                            <a class="dropdown-item text-danger"
                                                                               href="#"
                                                                               onclick="return confirm('آیا از حذف دائم این گروه مطمئن هستید؟')">
                                                                                <i class="bi bi-trash me-2"></i>
                                                                                حذف دائم گروه
                                                                            </a>

                                                                        </div>
                                                                    </div>
                                                                </td>
                                                              <td><?= $res->group_name ?> </td>
                                                              <td><?php if ($res->leader_username !==null ){ echo $res->group_name; } else{echo "فاقد سر گروه";}?> </td>
                                                              <td><?= $res->max_users; ?></td>
                                                              <td>    <?php if ($res->is_active == 1) { ?>
                                                                      <span class="badge bg-success fs-6">فعال</span>
                                                                  <?php } elseif ($res->is_active == 0) { ?>
                                                                      <span class="badge bg-danger">غیرفعال</span>
                                                                  <?php } else { ?>
                                                                      <span class="badge bg-warning text-dark">مشکل</span>
                                                                  <?php } ?></td>
                                                                <?php if (Permision('administrator')) { ?>
                                                                    <td ><?= $res->supporter ?></td>
                                                                <?php } ?>
                                                            </tr>
                                                        <?php }
                                                    } else {
                                                        echo '<tr><td colspan="10" class="text-center">هیچ گروهی یافت نشد.</td></tr>';
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form method="post" id="groupForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupModalTitle">ایجاد گروه جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="row g-3">

                        <!-- shared -->
                        <input type="hidden" name="codecourse" value="<?= intval($_GET['code'] ?? 0) ?>">
                        <input type="hidden" name="supporter" value="<?= $_SESSION['admin']['user'] ?>">
                        <input type="hidden" name="group_id" id="group_id">

                        <div class="col-md-6">
                            <label class="form-label">اسم گروه</label>
                            <input type="text" name="group_name" id="group_name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">لینک روم</label>
                            <input type="text" name="link_room" id="link_room" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">حداکثر کاربر</label>
                            <input type="number" name="max_users" id="max_users" class="form-control" value="5">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">وضعیت</label>
                            <select name="is_active" id="is_active" class="form-select">
                                <option value="1">فعال</option>
                                <option value="0">غیرفعال</option>
                            </select>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" name="add-group" id="groupSubmitBtn" class="btn btn-primary">
                        ثبت گروه
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        انصراف
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        document.querySelectorAll('.edit-group-btn').forEach(btn => {
            btn.addEventListener('click', function () {

                // Fill fields
                document.getElementById('group_id').value   = this.dataset.id;
                document.getElementById('group_name').value = this.dataset.name;
                document.getElementById('link_room').value  = this.dataset.room ?? '';
                document.getElementById('max_users').value  = this.dataset.max;
                document.getElementById('is_active').value  = this.dataset.active;

                // Switch modal to edit mode
                document.getElementById('groupModalTitle').innerText = 'ویرایش گروه';
                const submitBtn = document.getElementById('groupSubmitBtn');
                submitBtn.innerText = 'ذخیره تغییرات';
                submitBtn.name = 'edit-group';
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-warning');
            });
        });

        // Reset modal when closed (back to create mode)
        document.getElementById('addGroupModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('groupForm').reset();
            document.getElementById('group_id').value = '';

            document.getElementById('groupModalTitle').innerText = 'ایجاد گروه جدید';
            const submitBtn = document.getElementById('groupSubmitBtn');
            submitBtn.innerText = 'ثبت گروه';
            submitBtn.name = 'add-group';
            submitBtn.classList.remove('btn-warning');
            submitBtn.classList.add('btn-primary');
        });

    });
</script>

</body>

</html>