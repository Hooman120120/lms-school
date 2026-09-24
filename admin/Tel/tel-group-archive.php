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

// Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

include_once '../../temp.html';

/*
|--------------------------------------------------------------------------
| دریافت انتخاب‌های قبلی، فقط برای selected ماندن اگر لازم شد
|--------------------------------------------------------------------------
*/
$selectedOperators = isset($_GET['operators']) && is_array($_GET['operators']) ? array_values($_GET['operators']) : [];
$selectedGroups    = isset($_GET['groups']) && is_array($_GET['groups']) ? array_values($_GET['groups']) : [];

/*
|--------------------------------------------------------------------------
| لیست اپراتورها
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        idadmin,
        fname,
        lname,
        dakheli
    FROM dbadmin WHERE adminrole IN (1,6)
    ORDER BY dakheli ASC
";
$query = $conn->prepare($sql);
$query->execute();
$operators = $query->fetchAll(PDO::FETCH_OBJ);

/*
|--------------------------------------------------------------------------
| لیست گروه‌ها
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        id,
        namegroup
    FROM tel_group
    ORDER BY namegroup ASC
";
$query = $conn->prepare($sql);
$query->execute();
$groups = $query->fetchAll(PDO::FETCH_OBJ);
?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
        <meta name="description" content="e">
        <meta name="author" content="hamsepar.ir">
        <title>آرشیو تماس‌ها | <?php echo __TITLE ?></title>
        <style>
            .select-box-list,
            .select-box-list2 {
                max-height: 360px;
                overflow-y: auto;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 10px;
                background: #fafafa;
            }


            .select-item {
                cursor: pointer;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 10px 12px;
                margin-bottom: 8px;
                background: #fff;
                transition: all 0.15s ease-in-out;
                user-select: none;
            }

            .select-item:hover {
                border-color: #0d6efd;
                background: #f0f7ff;
            }

            .select-item.selected {
                background: #0d6efd;
                border-color: #0d6efd;
                color: #fff;
                box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
            }

            .select-item.selected .text-muted {
                color: rgba(255, 255, 255, 0.8) !important;
            }

            .selected-preview-box {
                border: 1px dashed #ced4da;
                border-radius: 10px;
                padding: 12px;
                background: #fcfcfc;
            }

            .selected-chip {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 5px 10px;
                border-radius: 30px;
                background: #e9f5ff;
                color: #0d6efd;
                font-size: 13px;
                margin: 3px;
            }

            .selected-chip.group-chip {
                background: #eafaf1;
                color: #198754;
            }

            .empty-selected-text {
                font-size: 13px;
                color: #6c757d;
            }
        </style>

        <?php include_once '../link.php'; ?>
    </head>

<body class="main-body leftmenu">

    <div id="global-loader">
        <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
    </div>

<div class="page">

<?php
include_once '../menu.php';
include_once '../header.php';
?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="row row-sm">

                <div class="col-lg-12 mt-4">
                    <div class="card custom-card">
                        <div class="card-body">

                            <h6 class="main-content-label mb-3">
                                خروجی اکسل آرشیو تماس‌ها
                            </h6>

                            <p class="text-muted mb-4">
                                اپراتورها و گروه‌های مورد نظر را انتخاب کنید، سپس خروجی اکسل بگیرید.
                            </p>

                            <form method="get" action="excel.php" id="archiveExportForm">
                                <input type="hidden" name="export_custom_archive" value="1">
                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">انتخاب اپراتور</label>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="groupSearchInput2" placeholder="جستجوی گروه...">
                                        </div>

                                        <div class="select-box-list2">
                                            <?php foreach ($operators as $op): ?>
                                                <?php
                                                $opDakheli = (string)$op->dakheli;
                                                $opName = trim(($op->fname ?? '') . ' ' . ($op->lname ?? ''));

                                                if ($opName === '') {
                                                    $opName = 'بدون نام';
                                                }

                                                $isSelected = in_array($opDakheli, $selectedOperators, true);
                                                ?>

                                                <div class="select-item operator-item <?php echo $isSelected ? 'selected' : ''; ?>"
                                                     data-title="<?php echo htmlspecialchars($opName . ' (' . $opDakheli . ')'); ?>">

                                                    <input type="checkbox"
                                                           class="d-none select-check"
                                                           name="operators[]"
                                                           value="<?php echo htmlspecialchars($opDakheli); ?>"
                                                        <?php echo $isSelected ? 'checked' : ''; ?>>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-bold">
                                                                داخلی: <?php echo htmlspecialchars($opDakheli); ?>
                                                            </div>

                                                        </div>

                                                        <span class="badge bg-light text-dark selected-icon">
                                انتخاب
                            </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <small class="text-muted d-block mt-2">
                                            برای انتخاب یا حذف انتخاب، فقط روی مورد کلیک کنید.
                                        </small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">انتخاب گروه مخاطبان</label>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="groupSearchInput" placeholder="جستجوی گروه...">
                                        </div>

                                        <div class="select-box-list">
                                            <?php foreach ($groups as $grp): ?>
                                                <?php
                                                $grpId = (string)$grp->id;
                                                $grpName = $grp->namegroup ?? 'بدون نام';
                                                $isSelected = in_array($grpId, $selectedGroups, true);
                                                ?>

                                                <div class="select-item group-item <?php echo $isSelected ? 'selected' : ''; ?>"
                                                     data-title="<?php echo htmlspecialchars($grpName); ?>">

                                                    <input type="checkbox"
                                                           class="d-none select-check"
                                                           name="groups[]"
                                                           value="<?php echo htmlspecialchars($grpId); ?>"
                                                        <?php echo $isSelected ? 'checked' : ''; ?>>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-bold">
                                                                <?php echo htmlspecialchars($grpName); ?>
                                                            </div>
                                                        </div>

                                                        <span class="badge bg-light text-dark selected-icon">
                                انتخاب
                            </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <small class="text-muted d-block mt-2">
                                            می‌توانید یک یا چند گروه را انتخاب کنید.
                                        </small>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <div class="selected-preview-box">

                                            <div class="row g-3">

                                                <div class="col-md-6">
                                                    <div class="fw-bold mb-2">
                                                        اپراتورهای انتخاب‌شده:
                                                        <span class="badge bg-primary" id="operatorsCount">0</span>
                                                    </div>

                                                    <div id="selectedOperatorsList">
                                                        <span class="empty-selected-text">هیچ اپراتوری انتخاب نشده است.</span>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="fw-bold mb-2">
                                                        گروه‌های انتخاب‌شده:
                                                        <span class="badge bg-success" id="groupsCount">0</span>
                                                    </div>

                                                    <div id="selectedGroupsList">
                                                        <span class="empty-selected-text">هیچ گروهی انتخاب نشده است.</span>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-12 d-flex flex-wrap gap-2 mt-4">

                                        <button type="submit" class="btn btn-success">
                                            خروجی اکسل
                                        </button>

                                        <a href="tel-group-archive.php" class="btn btn-secondary">
                                            پاک کردن انتخاب‌ها
                                        </a>

                                    </div>

                                </div>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const selectItems = document.querySelectorAll('.select-item');

            const selectedOperatorsList = document.getElementById('selectedOperatorsList');
            const selectedGroupsList = document.getElementById('selectedGroupsList');

            const operatorsCount = document.getElementById('operatorsCount');
            const groupsCount = document.getElementById('groupsCount');

            const groupSearch = document.getElementById('groupSearchInput');
            const groupItems = document.querySelectorAll('.select-box-list .group-item');

            const operatorSearch = document.getElementById('groupSearchInput2');
            const operatorItems = document.querySelectorAll('.select-box-list2 .operator-item');

            function updateSelectedPreview() {

                let operatorSelected = document.querySelectorAll('.operator-item.selected');
                let groupSelected = document.querySelectorAll('.group-item.selected');

                selectedOperatorsList.innerHTML = '';
                selectedGroupsList.innerHTML = '';

                operatorsCount.textContent = operatorSelected.length;
                groupsCount.textContent = groupSelected.length;

                if (operatorSelected.length === 0) {
                    selectedOperatorsList.innerHTML = '<span class="empty-selected-text">هیچ اپراتوری انتخاب نشده است.</span>';
                } else {
                    operatorSelected.forEach(function (item) {

                        let title = item.getAttribute('data-title');

                        let chip = document.createElement('span');
                        chip.className = 'selected-chip';
                        chip.textContent = title;

                        selectedOperatorsList.appendChild(chip);

                    });
                }

                if (groupSelected.length === 0) {
                    selectedGroupsList.innerHTML = '<span class="empty-selected-text">هیچ گروهی انتخاب نشده است.</span>';
                } else {
                    groupSelected.forEach(function (item) {

                        let title = item.getAttribute('data-title');

                        let chip = document.createElement('span');
                        chip.className = 'selected-chip group-chip';
                        chip.textContent = title;

                        selectedGroupsList.appendChild(chip);

                    });
                }
            }

            selectItems.forEach(function (item) {

                item.addEventListener('click', function () {

                    const checkbox = item.querySelector('.select-check');

                    checkbox.checked = !checkbox.checked;

                    item.classList.toggle('selected', checkbox.checked);

                    updateSelectedPreview();

                });

            });

            updateSelectedPreview();

            // group search
            groupSearch.addEventListener('input', function () {

                const term = this.value.trim().toLowerCase();

                groupItems.forEach(function(item) {

                    const title = item.getAttribute('data-title').toLowerCase();

                    if (title.includes(term)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }

                });

            });

            // operator search
            operatorSearch.addEventListener('input', function () {

                const term = this.value.trim().toLowerCase();

                operatorItems.forEach(function(item) {

                    const title = item.getAttribute('data-title').toLowerCase();

                    if (title.includes(term)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }

                });

            });

        });


    </script>

<?php include '../../footers.php'; ?>
</div>

</body>
</html>