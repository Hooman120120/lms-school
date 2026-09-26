```php
<?php

include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';


// ---------------------------------------------------------
// بررسی IP در Blacklist
// ---------------------------------------------------------

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}


// ---------------------------------------------------------
// بررسی لاگین بودن ادمین
// ---------------------------------------------------------

if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}


// ---------------------------------------------------------
// Protecting against SESSION Hijaking
// ---------------------------------------------------------

if (
    $_SESSION['admin']['ip'] !== GetIp()
    and
    $_SESSION['admin']['os'] != GetOs()
) {
    session_unset();

    header('location: ../login.php');

    die();
}


// ---------------------------------------------------------
// Check Session JUST ONE Session
// ---------------------------------------------------------

$re = sessionAdmin($_SESSION['admin']['user']);

if (session_id() != $re['session_id']) {

    session_unset();

    header('location: ' . url('admin/login.php'));

    die();
}


// ---------------------------------------------------------
// Access to documents section
// ---------------------------------------------------------

if (!Permision('permision_viewdocument')) {

    header('location: ' . url('admin/index.php'));

    die();
}


// ---------------------------------------------------------
// دریافت اطلاعات همکاران و مدارک و سمت از دیتابیس
// ---------------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        d.idadmin,
        d.username,
        d.fname,
        d.lname,
        d.school_role_id,

        r.role_key,
        r.title AS school_role_title,

        d.document_file,
        d.identity_file,
        d.resume_file

    FROM dbadmin AS d

    LEFT JOIN school_admin_roles AS r
        ON r.id = d.school_role_id

    WHERE d.adminactive = 1

    ORDER BY d.idadmin DESC
");

$stmt->execute();

$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);


include_once '../../temp.html';

?>

<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

    <meta
        http-equiv="content-type"
        content="text/html;charset=UTF-8"
    >

    <meta charset="utf-8">

    <meta
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
        name="viewport"
    >

    <meta
        name="description"
        content="وضعیت مدارک همکاران"
    >

    <title>
        وضعیت مدارک همکاران | <?php echo __TITLE ?>
    </title>

    <?php include_once '../link.php'; ?>

</head>


<body class="main-body leftmenu">


<!-- Loader -->

<div id="global-loader">

    <img
        src="<?php echo url('assets/img/media/loader.gif') ?>"
        class="loader-img"
        alt="درحال بارگذاری"
    >

</div>

<!-- End Loader -->


<div class="page">


    <?php

    include_once '../menu.php';

    include_once '../header.php';

    ?>


    <!-- Main Content -->

    <div class="main-content side-content pt-0">

        <div class="container-fluid">

            <div class="inner-body">


                <div class="row row-sm">

                    <div class="col-lg-12">


                        <div class="card custom-card">

                            <div class="card-body">


                                <!-- عنوان صفحه -->

                                <div class="row mb-4">

                                    <div class="col-12">


                                        <h6 class="main-content-label mb-1">

                                            وضعیت مدارک همکاران

                                        </h6>


                                        <p class="text-muted card-sub-title mb-0">

                                            بررسی وضعیت مدارک تحصیلی، کارت ملی و رزومه همکاران

                                        </p>


                                    </div>

                                </div>


                                <!-- جدول -->

                                <div class="table-responsive">


                                    <table
                                        class="table text-nowrap text-md-nowrap table-bordered mg-b-0"
                                    >


                                        <thead>

                                        <tr>

                                            <th>
                                                ردیف
                                            </th>

                                            <th>
                                                نام همکار
                                            </th>

                                            <th>
                                                سمت
                                            </th>

                                            <th>
                                                مدرک تحصیلی
                                            </th>

                                            <th>
                                                کارت ملی
                                            </th>

                                            <th>
                                                رزومه
                                            </th>

                                        </tr>

                                        </thead>


                                        <tbody>


                                        <?php if (empty($admins)): ?>


                                            <!-- هیچ همکاری وجود ندارد -->

                                            <tr>

                                                <td
                                                    colspan="6"
                                                    class="text-center text-muted"
                                                >

                                                    هیچ همکاری برای نمایش وجود ندارد.

                                                </td>

                                            </tr>


                                        <?php else: ?>


                                            <?php foreach ($admins as $index => $admin): ?>


                                                <?php

                                                // -------------------------------------------------
                                                // نام کامل همکار
                                                // -------------------------------------------------

                                                $fullName = trim(
                                                    ($admin['fname'] ?? '')
                                                    . ' '
                                                    .
                                                    ($admin['lname'] ?? '')
                                                );


                                                if ($fullName === '') {

                                                    $fullName =
                                                        $admin['username']
                                                        ??
                                                        'بدون نام';

                                                }


                                                // -------------------------------------------------
                                                // بررسی وجود فایل‌ها
                                                // -------------------------------------------------

                                                $hasDocument =
                                                    !empty($admin['document_file']);


                                                $hasIdentity =
                                                    !empty($admin['identity_file']);


                                                $hasResume =
                                                    !empty($admin['resume_file']);


                                                // -------------------------------------------------
                                                // ساخت مسیر فایل‌ها
                                                //
                                                // DB فقط نام فایل را ذخیره می‌کند.
                                                // فایل‌ها داخل storage/admin_documents هستند.
                                                // -------------------------------------------------

                                                $documentUrl = '';

                                                if ($hasDocument) {

                                                    $documentUrl = url(
                                                        'storage/admin_documents/'
                                                        .
                                                        rawurlencode(
                                                            basename(
                                                                $admin['document_file']
                                                            )
                                                        )
                                                    );

                                                }


                                                $identityUrl = '';

                                                if ($hasIdentity) {

                                                    $identityUrl = url(
                                                        'storage/admin_documents/'
                                                        .
                                                        rawurlencode(
                                                            basename(
                                                                $admin['identity_file']
                                                            )
                                                        )
                                                    );

                                                }


                                                $resumeUrl = '';

                                                if ($hasResume) {

                                                    $resumeUrl = url(
                                                        'storage/admin_documents/'
                                                        .
                                                        rawurlencode(
                                                            basename(
                                                                $admin['resume_file']
                                                            )
                                                        )
                                                    );

                                                }

                                                ?>


                                                <tr>


                                                    <!-- ردیف -->

                                                    <td>

                                                        <?php

                                                        echo $index + 1;

                                                        ?>

                                                    </td>


                                                    <!-- نام همکار -->

                                                    <td>

                                                        <?php

                                                        echo htmlspecialchars(
                                                            $fullName,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );

                                                        ?>

                                                    </td>


                                                    <!-- سمت -->

                                                    <td>

                                                        <?php if (!empty($admin['school_role_title'])): ?>

                                                            <?php

                                                            echo htmlspecialchars(
                                                                $admin['school_role_title'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            );

                                                            ?>

                                                        <?php else: ?>

                                                            <span class="text-muted">

                                                                بدون سمت

                                                            </span>

                                                        <?php endif; ?>

                                                    </td>


                                                    <!-- مدرک تحصیلی -->

                                                    <td>


                                                        <?php if ($hasDocument): ?>


                                                            <a
                                                                href="<?php echo htmlspecialchars(
                                                                    $documentUrl,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ); ?>"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-sm btn-success"
                                                            >

                                                                <i class="fa fa-eye"></i>

                                                                نمایش

                                                            </a>


                                                        <?php else: ?>


                                                            <span class="badge bg-danger">

                                                                موجود نیست

                                                            </span>


                                                        <?php endif; ?>


                                                    </td>


                                                    <!-- کارت ملی -->

                                                    <td>


                                                        <?php if ($hasIdentity): ?>


                                                            <a
                                                                href="<?php echo htmlspecialchars(
                                                                    $identityUrl,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ); ?>"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-sm btn-success"
                                                            >

                                                                <i class="fa fa-eye"></i>

                                                                نمایش

                                                            </a>


                                                        <?php else: ?>


                                                            <span class="badge bg-danger">

                                                                موجود نیست

                                                            </span>


                                                        <?php endif; ?>


                                                    </td>


                                                    <!-- رزومه -->

                                                    <td>


                                                        <?php if ($hasResume): ?>


                                                            <a
                                                                href="<?php echo htmlspecialchars(
                                                                    $resumeUrl,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ); ?>"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-sm btn-success"
                                                            >

                                                                <i class="fa fa-eye"></i>

                                                                نمایش

                                                            </a>


                                                        <?php else: ?>


                                                            <span class="badge bg-danger">

                                                                موجود نیست

                                                            </span>


                                                        <?php endif; ?>


                                                    </td>


                                                </tr>


                                            <?php endforeach; ?>


                                        <?php endif; ?>


                                        </tbody>


                                    </table>

                                </div>

                                <!-- End Table -->


                            </div>

                        </div>


                    </div>

                </div>


            </div>

        </div>

    </div>

    <!-- End Main Content -->


    <!-- Footer -->

    <?php include '../../footers.php'; ?>


</div>


</body>

</html>
```
