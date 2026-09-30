<?php
require_once __DIR__ . '/student_dossier.php';
$dossierCode = $dossierAdmin ? trim((string)($_GET['Id'] ?? $_SESSION['edit-student']['codemeli'] ?? '')) : (string)$_SESSION['auth']['user'];
$data = retPerson($dossierCode);
if (!$data || typeUser($dossierCode) != 1) { http_response_code(404); exit('دانش‌آموز یافت نشد.'); }
$sections = StudentDossierSections();
$choices = StudentDossierChoices();
$locked = !$dossierAdmin && StudentDossierLocked($data);
$error = '';
if (empty($_SESSION['dossier_token'])) $_SESSION['dossier_token'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['dossier_token'], (string)($_POST['token'] ?? ''))) { http_response_code(403); exit('خطای امنیتی'); }
    $section = (string)($_POST['section'] ?? '');
    if (!isset($sections[$section])) { http_response_code(400); exit('بخش نامعتبر'); }
    if ($dossierAdmin) AdminRequire('students.definitive.edit.' . $section);
    $newPhoto = null;
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('SELECT * FROM dbpersonal WHERE codemeli = ? FOR UPDATE');
        $stmt->execute([$dossierCode]);
        $current = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$current || (!$dossierAdmin && StudentDossierLocked($current))) {
            $conn->rollBack(); http_response_code(403); exit('پرونده توسط مدیر ثبت شده و فقط قابل مشاهده است.');
        }
        $values = []; $set = [];
        foreach ($sections[$section][1] as $field => $label) {
            $value = trim((string)($_POST[$field] ?? ''));
            if (isset($choices[$field]) && $value !== '' && !array_key_exists($value, $choices[$field]) && $value !== (string)($current->$field ?? '')) throw new RuntimeException('گزینه «' . $label . '» معتبر نیست.');
            if (mb_strlen($value) > ($field === 'address' || $field === 'bio' ? 2000 : 255)) throw new RuntimeException('مقدار «' . $label . '» بیش از حد طولانی است.');
            if (in_array($field, ['birthdate','birthday_pedar','birthdate_madar'], true) && $value !== '') {
                if (!preg_match('~^(\d{4})/(\d{1,2})/(\d{1,2})$~', $value, $parts) || (int)$parts[2] < 1 || (int)$parts[2] > 12 || (int)$parts[3] < 1 || (int)$parts[3] > 31) throw new RuntimeException('تاریخ شمسی را به صورت ۱۴۰۰/۰۱/۰۱ با اعداد انگلیسی وارد کنید.');
                $date = jalali_to_gregorian((int)$parts[1], (int)$parts[2], (int)$parts[3]);
                if (!checkdate($date[1], $date[2], $date[0]) || gregorian_to_jalali($date[0], $date[1], $date[2]) !== [(int)$parts[1], (int)$parts[2], (int)$parts[3]]) throw new RuntimeException('تاریخ معتبر نیست.');
                $value = implode('-', $date);
            }
            if (in_array($field, ['fname','lname','tel1'], true) && $value === '') throw new RuntimeException('نام، نام خانوادگی و تلفن اصلی الزامی است.');
            if ($field === 'tel1' && (!preg_match('/^09[0-9]{9}$/', $value) || ($value !== (string)$current->tel1 && checkTellWithoume($value, $dossierCode)))) throw new RuntimeException('شماره همراه معتبر و غیرتکراری وارد کنید.');
            if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('پست الکترونیکی معتبر نیست.');
            if ($field === 'sheba' && $value !== '' && !preg_match('/^[0-9]{24}$/', $value)) throw new RuntimeException('شبا باید ۲۴ رقم و بدون IR باشد.');
            if ($field === 'card_bank' && $value !== '' && !bankCardCheck($value)) throw new RuntimeException('شماره کارت معتبر نیست.');
            $set[] = '`' . $field . '` = ?'; $values[] = $value === '' ? null : $value;
        }
        if ($section === 'picture') {
            $newPhoto = StudentDossierPhoto($_FILES['pic'] ?? []);
            $set[] = 'pic = ?'; $values[] = $newPhoto;
        }
        $set[] = 'updator = ?'; $values[] = $dossierAdmin ? $_SESSION['admin']['user'] : $dossierCode;
        $set[] = 'dateupdate = ?'; $values[] = date('Y-m-d');
        $set[] = 'timeupdate = ?'; $values[] = date('H:i:s');
        $values[] = $dossierCode;
        $conn->prepare('UPDATE dbpersonal SET ' . implode(', ', $set) . ' WHERE codemeli = ?')->execute($values);
        $conn->commit();
        if (!$dossierAdmin && $section === 'account') { $_SESSION['auth']['name'] = trim($_POST['fname']); $_SESSION['auth']['famili'] = trim($_POST['lname']); }
        if (!$dossierAdmin && $section === 'house') $_SESSION['auth']['paye'] = $_POST['paye'] ?? '';
        $_SESSION['dossier_success'] = 'اطلاعات پرونده ذخیره شد.';
        header('Location: ' . ($dossierAdmin ? url('admin/Student/dossier.php') . '?Id=' . urlencode($dossierCode) : url('Profile/user.php')) . '#' . $section); exit;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        if ($newPhoto && is_file(BASE_PATH . $newPhoto)) unlink(BASE_PATH . $newPhoto);
        $error = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'ذخیره اطلاعات انجام نشد. دوباره تلاش کنید.';
        error_log('Student dossier save: ' . $e->getMessage());
    }
}
function dossierDisplay($field, $value): string {
    if (in_array($field, ['birthdate','birthday_pedar','birthdate_madar'], true) && $value && strtotime($value)) return jdate('Y/m/d', strtotime($value), '', '', 'en');
    return (string)$value;
}
if (isset($_GET['pdf'])) {
    require_once BASE_PATH . '/vendor/autoload.php';
    $pdf = new \Mpdf\Mpdf(['mode'=>'utf-8', 'format'=>'A4', 'default_font'=>'xbriyaz', 'autoScriptToLang'=>true, 'autoLangToFont'=>true, 'tempDir'=>sys_get_temp_dir() . '/lms-dossier-mpdf']);
    $pdf->SetDirectionality('rtl');
    $html = '<h1>پرونده دانش‌آموزی</h1><p>' . StudentDossierEscape($data->fname . ' ' . $data->lname) . ' — کد ملی: ' . StudentDossierEscape($dossierCode) . '</p>';
    $photo = ltrim((string)($data->pic ?? ''), '/');
    if (preg_match('~^uploads-student/student-profile/[a-zA-Z0-9_.-]+$~', $photo)) {
        $path = is_file(BASE_PATH . '/' . $photo) ? BASE_PATH . '/' . $photo : rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/' . $photo;
        if (is_file($path)) $html .= '<img style="width:90px" src="' . StudentDossierEscape($path) . '">';
    }
    foreach ($sections as [$title, $fields]) {
        if (!$fields) continue;
        $html .= '<h2>' . $title . '</h2><table width="100%" style="border-collapse:collapse">';
        foreach ($fields as $field => $label) $html .= '<tr><td style="border:1px solid #ddd;padding:7px;width:35%">' . $label . '</td><td style="border:1px solid #ddd;padding:7px">' . StudentDossierEscape(dossierDisplay($field, $data->$field ?? '')) . '</td></tr>';
        $html .= '</table>';
    }
    $pdf->SetFooter('صفحه {PAGENO} از {nbpg}'); $pdf->WriteHTML($html);
    while (ob_get_level()) ob_end_clean();
    $pdf->Output('student-dossier-' . preg_replace('/[^0-9]/', '', $dossierCode) . '.pdf', 'D'); exit;
}
$base = $dossierAdmin ? url('admin/Student/dossier.php') . '?Id=' . urlencode($dossierCode) . '&' : url('Profile/user.php') . '?';
include BASE_PATH . '/temp.html';
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>پرونده دانش‌آموزی</title><?php include BASE_PATH . '/inc/link.php'; ?></head>
<body class="main-body leftmenu"><div class="page"><?php include BASE_PATH . ($dossierAdmin ? '/admin/menu.php' : '/menu.php'); include BASE_PATH . ($dossierAdmin ? '/admin/header.php' : '/header.php'); ?>
<div class="main-content side-content pt-0"><div class="main-container container-fluid"><div class="inner-body"><div class="card custom-card mt-4"><div class="card-body">
<h3>پرونده دانش‌آموزی <?= StudentDossierEscape($data->fname . ' ' . $data->lname) ?></h3>
<img src="<?= StudentDossierEscape(StudentAvatar($data)) ?>" alt="تصویر پروفایل" style="width:80px;height:80px;object-fit:cover;border-radius:50%">
<p>کد ملی: <?= StudentDossierEscape($dossierCode) ?></p>
<div class="d-flex flex-wrap gap-3 mb-4 align-items-center">
<?php if (!$dossierAdmin): ?><span>دوره‌های ثبت‌نامی: <strong><?= (int)($id ?? 0) ?></strong></span><span>وضعیت حساب: <?= isset($courseregister) && $courseregister == 1 ? 'غیرفعال' : 'فعال' ?></span><span>نوع حساب: <?= StudentDossierEscape(t('student')) ?></span><?php endif; ?>
<a class="btn btn-primary" href="<?= StudentDossierEscape($dossierAdmin ? url('admin/Student/student-history.php') . '?Id=' . urlencode($dossierCode) : url('Profile/student-history.php')) ?>">سوابق</a>
<a class="btn btn-outline-primary" href="<?= StudentDossierEscape($base . 'pdf=1') ?>">خروجی PDF پرونده</a></div>
<?php if ($locked): ?><div class="alert alert-info">پرونده توسط مدیر ثبت شده است. اطلاعات برای شما فقط قابل مشاهده است.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= StudentDossierEscape($error) ?></div><?php endif; ?>
<?php if (!empty($_SESSION['dossier_success'])): ?><div class="alert alert-success"><?= StudentDossierEscape($_SESSION['dossier_success']) ?></div><?php unset($_SESSION['dossier_success']); endif; ?>
<nav class="nav main-nav-line p-3 tabs-menu bg-gray-100" role="tablist">
<?php foreach ($sections as $key => [$label]): ?><a class="nav-link <?= $key === 'account' ? 'active' : '' ?>" data-bs-toggle="tab" href="#<?= $key ?>"><?= $label ?></a><?php endforeach; ?></nav>
<div class="tab-content pt-4">
<?php foreach ($sections as $key => [$title,$fields]): $editable = !$locked && (!$dossierAdmin || AdminCan('students.definitive.edit.' . $key)); ?>
<div class="tab-pane <?= $key === 'account' ? 'active' : '' ?>" id="<?= $key ?>"><form method="post" enctype="multipart/form-data">
<input type="hidden" name="token" value="<?= StudentDossierEscape($_SESSION['dossier_token']) ?>"><input type="hidden" name="section" value="<?= $key ?>">
<fieldset <?= $editable ? '' : 'disabled' ?>><div class="row">
<?php foreach ($fields as $field => $label): ?><div class="col-md-6 form-group"><label for="field-<?= $field ?>"><?= $label ?><?= in_array($field,['birthdate','birthday_pedar','birthdate_madar'],true) ? ' (شمسی؛ سال/ماه/روز، اعداد انگلیسی)' : '' ?></label>
<?php if ($field === 'paye'): ?><select id="field-paye" name="paye" class="form-control"><option value="">انتخاب کنید</option><?= inst_level_options($data->paye ?? null) ?></select>
<?php elseif (isset($choices[$field])): $options = $choices[$field]; $saved = (string)($data->$field ?? ''); if ($saved !== '' && !array_key_exists($saved, $options)) $options[$saved] = $saved; ?><select id="field-<?= $field ?>" class="form-control" name="<?= $field ?>"><option value="">انتخاب کنید</option><?php foreach ($options as $value => $text): ?><option value="<?= StudentDossierEscape($value) ?>" <?= $saved === (string)$value ? 'selected' : '' ?>><?= StudentDossierEscape($text) ?></option><?php endforeach; ?></select>
<?php else: ?><input id="field-<?= $field ?>" class="form-control" type="<?= $field === 'email' ? 'email' : 'text' ?>" name="<?= $field ?>" value="<?= StudentDossierEscape(dossierDisplay($field, $data->$field ?? '')) ?>" <?= in_array($field,['fname','lname','tel1'],true) ? 'required' : '' ?>><?php endif; ?></div><?php endforeach; ?>
<?php if ($key === 'picture'): ?><div class="col-12 form-group"><img src="<?= StudentDossierEscape(StudentAvatar($data)) ?>" alt="تصویر پروفایل" style="max-width:160px"><p>JPG یا PNG؛ حداکثر ۵۰۰ کیلوبایت</p><?php if ($editable): ?><input class="form-control" type="file" name="pic" accept="image/jpeg,image/png" required><?php endif; ?></div><?php endif; ?>
</div><?php if ($editable): ?><button class="btn btn-success" type="submit">ذخیره <?= $title ?></button><?php endif; ?></fieldset></form></div><?php endforeach; ?>
</div></div></div></div></div></div></div><?php include BASE_PATH . '/footers.php'; ?>
<script>const selected = document.querySelector('a[data-bs-toggle="tab"][href="' + location.hash.replace(/[^#a-z]/g,'') + '"]'); if(selected && window.bootstrap) new bootstrap.Tab(selected).show();</script>
</body></html>
