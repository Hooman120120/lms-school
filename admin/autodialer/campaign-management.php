<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once 'autodialer_functions.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}

if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}

// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

if (!AdminCanSee('public-relations.safe-call.autodialer.campaigns')) {
    echo "دسترسی ندارید.";
    die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

$q = trim((string)($_GET['q'] ?? ''));
$campaigns = autodialerCampaignsSearch($q, '', 0, 50);

$contacts = autodialerContactsSearch(0, 500);
$groups = autodialerGroupsSearch(0, 500);
$announcements = autodialerAnnouncementsSearch('');
if (!is_array($announcements)) $announcements = [];
if (!is_array($contacts)) $contacts = [];
if (!is_array($groups)) $groups = [];

$groupMap = [];
foreach ($groups as $g) {
    $gid = (string)($g->_id ?? ($g->id ?? ''));
    if ($gid !== '') $groupMap[$gid] = (string)($g->name ?? $gid);
}

$annMap = [];
foreach ($announcements as $a) {
    $aid = (string)($a->_id ?? ($a->id ?? ''));
    if ($aid !== '') $annMap[$aid] = (string)($a->name ?? $aid);
}
function findCampaignById(array $campaigns, string $id)
{
    foreach ($campaigns as $c) {
        if ((string)($c->_id ?? '') === $id) return $c;
    }
    return null;
}
function campaignLogContext(string $name, array $groupIds, array $numbers, string $announcementId, array $groupMap, array $annMap): string
{
    $groupNames = [];
    foreach ($groupIds as $gid) {
        $gid = trim((string)$gid);
        if ($gid === '') continue;
        $groupNames[] = $groupMap[$gid] ?? $gid;
    }

    $voiceName = $announcementId !== '' ? ($annMap[$announcementId] ?? $announcementId) : '-';

    $numbers = array_values(array_filter(array_map('trim', $numbers)));
    $nCount = count($numbers);

    // keep logs short-ish
    $groupsStr = $groupNames ? implode(',', array_slice($groupNames, 0, 8)) : '-';
    if (count($groupNames) > 8) $groupsStr .= ',...';

    $numsStr = $numbers ? implode(',', array_slice($numbers, 0, 8)) : '-';
    if ($nCount > 8) $numsStr .= ',...';

    return "name={$name} | voice={$voiceName} | groups={$groupsStr} | numbers({$nCount})={$numsStr}";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_campaign') {
    AdminRequire('public-relations.safe-call.autodialer.campaigns.create');

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    // hardcoded/hidden inputs (still validate)
    $trunkManagerId = trim((string)($_POST['trunk_manager_id'] ?? ''));
    $interfaceContext = trim((string)($_POST['interface_context'] ?? ''));
    $interfaceText = trim((string)($_POST['interface_text'] ?? ''));
    $try = (string)($_POST['try'] ?? '1');
    $tryInterval = (string)($_POST['try_interval'] ?? '600');

    // from pickers
    $numbersRaw = (string)($_POST['selectedContactNumbers'] ?? '');
    $numbers = array_values(array_filter(array_map('trim', preg_split('/\R+/', $numbersRaw))));

    $groupsRaw = (string)($_POST['selectedGroupIds'] ?? '');
    $groups = array_values(array_filter(array_map('trim', explode(',', $groupsRaw))));

    $announcement = trim((string)($_POST['announcement'] ?? '')); // announcement _id (optional)
    if ($announcement === '') {
        $_SESSION['flash_error'] = 'باید فایل صوتی را انتخاب کنید.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // start (jalali datepicker + time)
    $startJ = trim((string)($_POST['start_jdate'] ?? ''));
    $startT = trim((string)($_POST['start_time'] ?? ''));

// ensure HH:MM -> HH:MM:00
    if (preg_match('/^\d{2}:\d{2}$/', $startT)) {
        $startT .= ':00';
    }
    $start = simotelDateTimeFromJalali($startJ, $startT ?: '00:00');
    if ($start === '') {
        $_SESSION['flash_error'] = 'زمان شروع را درست وارد کنید.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // end = start + 10 days, same clock
    $end = '';
    $dt = DateTime::createFromFormat('Y-m-d H:i', $start);
    if ($dt instanceof DateTime) {
        $dt->modify('+10 days');
        $end = $dt->format('Y-m-d H:i');
    }

    if ($name === '' || $trunkManagerId === '') {
        $_SESSION['flash_error'] = 'نام کمپین و Trunk Manager الزامی است.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // IMPORTANT: Simotel expects numbers array, groups array (ids), try/try_interval as strings is ok.
    $payload = [
        "name" => $name,
        "trunk_manager_id" => $trunkManagerId,
        "interface_context" => $interfaceContext,
        "interface_text" => $interfaceText,
        "numbers" => $numbers,
        "groups" => $groups,
        "try_interval" => $tryInterval,
        "try" => $try,
        "start" => $start,   // "YYYY-MM-DD HH:MM"
        "end" => $end,       // start + 10 days
        "announcement" => $announcement,
        "description" => $description,
        "count" => 3, // if you want fixed, keep it here; otherwise add an input
    ];

    $res = autodialerCampaignAdd($payload);

    if (!empty($res['ok'])) {
        $logCtx = campaignLogContext($name, $groups, $numbers, $announcement, $groupMap, $annMap);
        autodialerLog($_SESSION['admin']['user'], 'campaign add | ' . $logCtx, 'campaign');
        $_SESSION['flash_success'] = 'کمپین با موفقیت ایجاد شد.';
    } else {
        $msg = 'خطا در ایجاد کمپین. کد: ' . ($res['httpcode'] ?? 0);
        if (!empty($res['error'])) $msg .= ' | ' . $res['error'];
        $logCtx = campaignLogContext($name, $groups, $numbers, $announcement, $groupMap, $annMap);
        autodialerLog($_SESSION['admin']['user'], 'campaign add failed | ' . $logCtx, 'campaign');
        $_SESSION['flash_error'] = $msg;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_campaign') {
    AdminRequire('public-relations.safe-call.autodialer.campaigns.delete');
    $id = trim((string)($_POST['id'] ?? ''));

    if ($id === '') {
        $_SESSION['flash_error'] = 'شناسه کمپین نامعتبر است.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Always check latest status from API
    $all = autodialerCampaignsSearch('', '', 0, 200);
    if (!is_array($all)) $all = [];

    $found = findCampaignById($all, $id);

    if (!$found) {
        $_SESSION['flash_error'] = 'کمپین پیدا نشد.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $foundName = (string)($found->name ?? $id);
    $foundGroups = is_array($found->groups ?? null) ? $found->groups : [];
    $foundNumbers = is_array($found->numbers ?? null) ? $found->numbers : [];
    $foundAnn = (string)($found->announcement ?? '');
    $logCtx = campaignLogContext($foundName, $foundGroups, $foundNumbers, $foundAnn, $groupMap, $annMap);

    $status = strtolower(trim((string)($found->status ?? '')));

    if ($status !== 'waiting') {
        $_SESSION['flash_error'] = 'این کمپین قابل حذف نیست. وضعیت: ' . ($found->status ?? 'unknown');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // OK to delete
    $res = autodialerCampaignRemove($id);

    if (!empty($res['ok'])) {
        autodialerLog($_SESSION['admin']['user'], 'campaign remove | ' . $logCtx, 'campaign');
        $_SESSION['flash_success'] = 'کمپین حذف شد.';
    } else {
        $msg = 'خطا در حذف کمپین. کد: ' . ($res['httpcode'] ?? 0);
        if (!empty($res['error'])) $msg .= ' | ' . $res['error'];
        autodialerLog($_SESSION['admin']['user'], 'campaign remove failed | ' . $logCtx, 'campaign');
        $_SESSION['flash_error'] = $msg;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
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
    <title>مدیریت تماس های گروهی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">


<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm safe-call-menu">
                    <?php if (!empty($_SESSION['flash_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_success']); ?>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_error']); ?>
                    <?php endif; ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center">
                                <div class="card-title mb-0 fs-5 font-weight-bold col-12 col-md-6 pb-2">
                                    لیست کمپین ها (تماس های گروهی)
                                </div>
                                <div class="col-12 col-md-6 text-md-left">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVoiceModal">
                                        افزودن تماس گروهی
                                    </button>
                                </div>
                                <p class="col-12">ایجاد تماس گروهی خودکار در این قسمت صورت میگیرد</p>
                                <p class="col-12 bg-danger badge-pill text-white">در صورت نیاز به کنسل کردن campaign آن را قبل از اجرا شدن حذف کنید در غیر این صورت امکان کنسل کمپین وجود ندارد.</p>
                                <p class="col-12">میتوانید فایل صدای انتخابی , گروه انتخاب شده و اطلاعات دیگر را در بخش جزئیات هر campaign مشاهده کنید.</p>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>نام</th>
                                            <th>تعداد</th>
                                            <th>شروع تماس</th>
                                            <th>پایان تماس</th>
                                            <th>وضعیت</th>
                                            <th>توضیحات</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <?php if (!empty($campaigns)): ?>
                                            <?php $i = 1; foreach ($campaigns as $c):
                                                $id = (string)($c->_id ?? '');
                                                $name = (string)($c->name ?? '');
                                                $count = (int)($c->count ?? 0);
                                                $start = (string)($c->start ?? '');
                                                $end = (string)($c->end ?? '');
                                                $status = (string)($c->status ?? '');
                                                $desc = (string)($c->description ?? '');
                                                ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo (int)$count; ?></td>
                                                    <td><?php echo htmlspecialchars($start, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($end, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td ><div class="badge-pill text-center text-white
                                                    <?php if ($status == 'completed'){
                                                        echo 'bg-success';}
                                                        elseif ($status == 'waiting'){
                                                        echo 'bg-warning';}
                                                        elseif ($status == 'running'){
                                                            echo 'bg-info';}
                                                        else
                                                        {echo 'bg-danger';}  ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></div></td>
                                                    <td><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <?php $isWaiting = (strtolower((string)$status) === 'waiting'); ?>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('کمپین حذف شود؟');">
                                                            <input type="hidden" name="action" value="remove_campaign">
                                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" <?php echo $isWaiting ? '' : 'disabled'; ?>>
                                                                حذف
                                                            </button>
                                                        </form>
                                                        <?php
                                                        $cid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
                                                        $payload = htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                        ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-info"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#campaignDetailsModal"
                                                                data-campaign="<?php echo $payload; ?>">
                                                            جزئیات
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" class="text-center">موردی یافت نشد</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>

                        </div>
                    </div>

                </div><!-- row -->
            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>

 <div class="modal fade" id="addVoiceModal" tabindex="-1" aria-labelledby="addVoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVoiceModalLabel">افزودن تماس گروهی</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="add_campaign">
                <input type="hidden" name="trunk_manager_id" value="6989c3de35da8c491f3527de">
                <input type="hidden" name="interface_context" value="">
                <input type="hidden" name="interface_text" value="">
                <input type="hidden" name="try" value="1">
                <input type="hidden" name="try_interval" value="600">
                <input type="hidden" name="end_jdate" value="">
                <input type="hidden" name="end_time" value="">
                <input type="hidden" name="selectedContactNumbers" id="selectedContactNumbers" value="">
                <input type="hidden" name="selectedGroupIds" id="selectedGroupIds" value="">
                <input type="hidden" name="announcement" id="selectedAnnouncementId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نام کمپین</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">زمان شروع</label>
                        <div class="row g-2">
                            <div class="col-7">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                    <input class="form-control fc-datepicker" type="text" name="start_jdate" placeholder="1404-12-02" required>
                                </div>
                            </div>
                            <div class="col-5">
                                <input class="form-control" type="time" name="start_time" placeholder="15:17:00" required>
                            </div>
                        </div>
                    </div>


                    <div class="accordion" id="campaignSelectAccordion">

                        <!-- Contacts -->
<!--                        <div class="accordion-item">-->
<!--                            <h2 class="accordion-header" id="headingCampaignContacts">-->
<!--                                <button class="accordion-button collapsed" type="button"-->
<!--                                        data-bs-toggle="collapse" data-bs-target="#collapseCampaignContacts"-->
<!--                                        aria-expanded="false" aria-controls="collapseCampaignContacts">-->
<!--                                    انتخاب مخاطبین <span class="ms-2 text-muted small" id="campaignContactsCount">(0)</span>-->
<!--                                </button>-->
<!--                            </h2>-->
<!--                            <div id="collapseCampaignContacts" class="accordion-collapse collapse"-->
<!--                                 aria-labelledby="headingCampaignContacts" data-bs-parent="#campaignSelectAccordion">-->
<!--                                <div class="accordion-body">-->
<!---->
<!--                                    <div class="mb-3">-->
<!--                                        <input type="text" id="campaignContactSearch" class="form-control form-control-sm" placeholder="جستجوی مخاطب...">-->
<!--                                    </div>-->
<!---->
<!--                                    <div id="campaignContactList" class="d-flex flex-wrap gap-2">-->
<!--                                        --><?php //foreach ($contacts as $c):
//                                            $cname = htmlspecialchars((string)($c->name ?? ''), ENT_QUOTES, 'UTF-8');
//                                            $cnumRaw = (string)($c->number ?? '');
//                                            $cnum = htmlspecialchars($cnumRaw, ENT_QUOTES, 'UTF-8');
//                                            $label = trim($cname) !== '' ? ($cname . ' - ' . $cnum) : $cnum;
//                                            ?>
<!--                                            <div class="campaign-contact-pill btn btn-outline-success btn-sm"-->
<!--                                                 data-number="--><?php //echo $cnum; ?><!--">-->
<!--                                                --><?php //echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
<!--                                            </div>-->
<!--                                        --><?php //endforeach; ?>
<!--                                    </div>-->
<!---->
<!--                                </div>-->
<!--                            </div>-->
<!--                        </div>-->

                        <!-- Groups -->
                        <div class="accordion-item mt-2">
                            <h2 class="accordion-header" id="headingCampaignGroups">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseCampaignGroups"
                                        aria-expanded="false" aria-controls="collapseCampaignGroups">
                                    انتخاب گروه‌ها <span class="ms-2 text-muted small" id="campaignGroupsCount">(0)</span>
                                </button>
                            </h2>
                            <div id="collapseCampaignGroups" class="accordion-collapse collapse"
                                 aria-labelledby="headingCampaignGroups" data-bs-parent="#campaignSelectAccordion">
                                <div class="accordion-body">

                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary group-filter-btn active" data-keys="">
                                            همه
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary group-filter-btn" data-keys="هوش">
                                            هوش
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary group-filter-btn" data-keys="قهرمان">
                                            قهرمان
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary group-filter-btn"
                                                data-keys="ابتدایی,اول,دوم,سوم,چهارم,پنجم,ششم,شش,پنج,چهار,سه,دو">
                                            ابتدایی
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary group-filter-btn"
                                                data-keys="متوسطه اول,هفتم,هشتم,نهم,نه,هشت,هفت">
                                            متوسطه اول
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary group-filter-btn"
                                                data-keys="متوسطه دوم,دهم,یازدهم,دوازدهم,ده,یازده,دوازده">
                                            متوسطه دوم
                                        </button>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" id="campaignGroupSearch" class="form-control form-control-sm" placeholder="جستجوی گروه...">
                                    </div>
                                    <div id="campaignGroupList" class="d-flex flex-wrap gap-2">
                                        <?php foreach ($groups as $g):
                                            $gidRaw = (string)($g->_id ?? ($g->id ?? ''));
                                            $gid = htmlspecialchars($gidRaw, ENT_QUOTES, 'UTF-8');
                                            $gname = htmlspecialchars((string)($g->name ?? ''), ENT_QUOTES, 'UTF-8');
                                            $gdesc = htmlspecialchars((string)($g->description ?? ''), ENT_QUOTES, 'UTF-8');
                                            $glabel = trim($gdesc) !== '' ? ($gname . ' - ' . $gdesc) : $gname;
                                            ?>
                                            <div class="campaign-group-pill btn btn-outline-primary btn-sm"
                                                 data-id="<?php echo $gid; ?>">
                                                <?php echo htmlspecialchars($glabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <!-- Announcements -->
                        <div class="accordion-item mt-2">
                            <h2 class="accordion-header" id="headingCampaignAnnouncements">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseCampaignAnnouncements"
                                        aria-expanded="false" aria-controls="collapseCampaignAnnouncements">
                                    انتخاب پیام صوتی <span class="ms-2 text-muted small" id="announcementCount">(0)</span>
                                </button>
                            </h2>

                            <div id="collapseCampaignAnnouncements" class="accordion-collapse collapse"
                                 aria-labelledby="headingCampaignAnnouncements" data-bs-parent="#campaignSelectAccordion">
                                <div class="accordion-body">
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary ann-filter-btn" data-keys="">
                                            همه
                                        </button>

                                        <!-- نمونه‌ها (replace keywords with what you need) -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary ann-filter-btn" data-keys="ابتدایی,اول,دوم,سوم,چهارم,پنجم,ششم">
                                            ابتدایی
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary ann-filter-btn" data-keys="متوسطه اول,هفتم,هشتم,نهم">
                                            متوسطه اول
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary ann-filter-btn" data-keys="متوسطه دوم,دهم,یازدهم,دوازدهم">
                                            متوسطه دوم
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary ann-filter-btn" data-keys="هوش">
                                            هوش
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-secondary ann-filter-btn" data-keys="قهرمان">
                                            قهرمان
                                        </button>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" id="announcementSearch" class="form-control form-control-sm" placeholder="جستجوی پیام صوتی...">
                                    </div>

                                    <div id="announcementList" class="d-flex flex-wrap gap-2">
                                        <?php foreach ($announcements as $a):
                                            $aidRaw = (string)($a->_id ?? ($a->id ?? ''));        // RAW for data-id
                                            $aidAttr = htmlspecialchars($aidRaw, ENT_QUOTES, 'UTF-8'); // safe for attribute
                                            $aname = htmlspecialchars((string)($a->name ?? ''), ENT_QUOTES, 'UTF-8');
                                            $adesc = htmlspecialchars((string)($a->description ?? ''), ENT_QUOTES, 'UTF-8');
                                            $label = trim($adesc) !== '' ? ($aname) : $aname;
                                            ?>
                                            <div class="announcement-pill btn btn-outline-warning btn-sm"
                                                 data-id="<?php echo $aidAttr; ?>">
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endforeach; ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">پیام صوتی انتخاب‌شده <span class="text-danger">*</span></label>
                        <input type="text" id="announcementRequiredField" class="form-control form-control-sm" required readonly
                               placeholder="یک پیام صوتی انتخاب کنید">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">توضیحات</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <button type="submit" class="btn btn-primary">ثبت</button>
            </div>
        </form>
    </div>
</div>

 <div class="modal fade" id="campaignDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg d-flex" >
        <div style="background-color: white;">
            <div class="modal-header">
                <h5 class="modal-title">جزئیات کمپین</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <!-- Summary -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">نام</div>
                                <div class="fw-semibold" id="d_name"></div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">وضعیت</div>
                                <span class="badge bg-info" id="d_status"></span>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">شروع</div>
                                <div class="fw-semibold" id="d_start"></div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">پایان</div>
                                <div class="fw-semibold" id="d_end"></div>
                            </div>


                            <div class="col-md-4">
                                <div class="text-muted small">تعداد</div>
                                <div class="fw-semibold" id="d_count"></div>
                            </div>


                            <div class="col-md-12">
                                <div class="text-muted small">پیام صوتی</div>
                                <div class="fw-semibold" id="d_announcement"></div>
                            </div>

                            <div class="col-md-12">
                                <div class="text-muted small">توضیحات</div>
                                <div class="fw-semibold" id="d_desc"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Groups -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">گروه‌ها</div>
                        </div>
                        <div id="d_groups" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <!-- Numbers (LTR) -->
<!--                <div class="card">-->
<!--                    <div class="card-body">-->
<!--                        <div class="fw-semibold mb-2"> شماره‌های تست</div>-->
<!--                        <pre id="d_numbers"-->
<!--                             class="mb-0 p-2 bg-light border rounded"-->
<!--                             style="white-space:pre-wrap; direction:ltr; text-align:left;"></pre>-->
<!--                    </div>-->
<!--                </div>-->

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>
<script>
    /** Global maps for modal details */
    const GROUP_MAP = <?php echo json_encode($groupMap, JSON_UNESCAPED_UNICODE); ?>;
    const ANN_MAP   = <?php echo json_encode($annMap, JSON_UNESCAPED_UNICODE); ?>;

    (function () {
        // ===== Contacts + Groups picker =====
        const selectedNumbers = new Set();
        const selectedGroups  = new Set();

        const numbersInput  = document.getElementById('selectedContactNumbers');
        const groupsInput   = document.getElementById('selectedGroupIds');
        const contactsCount = document.getElementById('campaignContactsCount');
        const groupsCount   = document.getElementById('campaignGroupsCount');

        function syncPickers() {
            if (numbersInput)  numbersInput.value = Array.from(selectedNumbers).join("\n");
            if (groupsInput)   groupsInput.value  = Array.from(selectedGroups).join(",");
            if (contactsCount) contactsCount.textContent = `(${selectedNumbers.size})`;
            if (groupsCount)   groupsCount.textContent   = `(${selectedGroups.size})`;
        }

        // Toggle contact pills
        document.querySelectorAll('.campaign-contact-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const num = pill.getAttribute('data-number');
                if (!num) return;

                if (selectedNumbers.has(num)) {
                    selectedNumbers.delete(num);
                    pill.classList.remove('active');
                } else {
                    selectedNumbers.add(num);
                    pill.classList.add('active');
                }
                syncPickers();
            });
        });

        // Toggle group pills
        document.querySelectorAll('.campaign-group-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const id = pill.getAttribute('data-id');
                if (!id) return;

                if (selectedGroups.has(id)) {
                    selectedGroups.delete(id);
                    pill.classList.remove('active');
                } else {
                    selectedGroups.add(id);
                    pill.classList.add('active');
                }
                syncPickers();
            });
        });

        // Search filters
        const contactSearch = document.getElementById('campaignContactSearch');
        if (contactSearch) {
            contactSearch.addEventListener('input', () => {
                const q = contactSearch.value.trim().toLowerCase();
                document.querySelectorAll('#campaignContactList .campaign-contact-pill').forEach(pill => {
                    pill.style.display = pill.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        }

        const groupSearch = document.getElementById('campaignGroupSearch');
        if (groupSearch) {
            groupSearch.addEventListener('input', () => {
                const q = groupSearch.value.trim().toLowerCase();
                document.querySelectorAll('#campaignGroupList .campaign-group-pill').forEach(pill => {
                    pill.style.display = pill.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        }

        syncPickers();
    })();

    (function () {
        const form = document.querySelector('#addVoiceModal form'); // the modal form
        const selectedAnnouncement = document.getElementById('selectedAnnouncementId');
        const requiredField = document.getElementById('announcementRequiredField');
        const countEl = document.getElementById('announcementCount');
        const search = document.getElementById('announcementSearch');

        if (!form || !selectedAnnouncement || !requiredField) return;

        function setCount() {
            if (!countEl) return;
            countEl.textContent = selectedAnnouncement.value ? '(1)' : '(0)';
        }

        function clearActive() {
            document.querySelectorAll('.announcement-pill').forEach(p => p.classList.remove('active'));
        }

        function setRequiredField(text) {
            requiredField.value = text || '';
        }

        function validateAnnouncement() {
            if (!selectedAnnouncement.value) {
                requiredField.setCustomValidity('انتخاب پیام صوتی الزامی است.');
            } else {
                requiredField.setCustomValidity('');
            }
        }

        // Enforce on submit (blocks POST)
        form.addEventListener('submit', function (e) {
            validateAnnouncement();
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.reportValidity();
            }
        });

        // Click pills
        document.querySelectorAll('.announcement-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const id = pill.getAttribute('data-id');
                if (!id) return;

                const isActive = pill.classList.contains('active');
                clearActive();

                if (isActive) {
                    selectedAnnouncement.value = '';
                    setRequiredField('');
                } else {
                    pill.classList.add('active');
                    selectedAnnouncement.value = id;
                    setRequiredField(pill.textContent.trim());
                }

                validateAnnouncement();
                setCount();
            });
        });

        // Search
        if (search) {
            search.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                document.querySelectorAll('#announcementList .announcement-pill').forEach(pill => {
                    pill.style.display = pill.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        }

        // Initial state
        validateAnnouncement();
        setCount();
    })();

    (function () {
        // ===== Campaign Details Modal =====
        const modal = document.getElementById('campaignDetailsModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;

            const raw = btn.getAttribute('data-campaign');
            if (!raw) return;

            let c;
            try { c = JSON.parse(raw); } catch (e) { return; }

            const setText = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = (val ?? '').toString();
            };

            setText('d_name', c.name);
            setText('d_status', c.status);
            setText('d_start', c.start);
            setText('d_end', c.end);
            setText('d_try', c.try);
            setText('d_try_interval', c.try_interval);
            setText('d_count', c.count);
            setText('d_trunk', c.trunk_manager_id);
            setText('d_desc', c.description);

            // Announcement name
            const annId = (c.announcement ?? '').toString();
            setText('d_announcement', annId ? (ANN_MAP[annId] || annId) : '-');

            // Groups pills
            const groupsWrap = document.getElementById('d_groups');
            if (groupsWrap) {
                groupsWrap.innerHTML = '';
                const gs = Array.isArray(c.groups) ? c.groups : [];

                if (gs.length) {
                    gs.forEach(gid => {
                        const name = GROUP_MAP[gid] || gid;
                        const pill = document.createElement('span');
                        pill.className = 'badge bg-primary';
                        pill.textContent = name;
                        groupsWrap.appendChild(pill);
                    });
                } else {
                    const t = document.createElement('span');
                    t.className = 'text-muted';
                    t.textContent = '-';
                    groupsWrap.appendChild(t);
                }
            }

            // Numbers
            const nums = Array.isArray(c.numbers) ? c.numbers.join("\n") : '';
            const numbersEl = document.getElementById('d_numbers');
            if (numbersEl) numbersEl.textContent = nums || '-';
        });
    })();

    (function () {
        const searchInput = document.getElementById('campaignGroupSearch');
        const pills = Array.from(document.querySelectorAll('#campaignGroupList .campaign-group-pill'));
        const btns = Array.from(document.querySelectorAll('.group-filter-btn'));

        if (!pills.length) return;

        let activeKeys = []; // keywords from selected button

        function norm(s) {
            return (s || '').toString().trim().toLowerCase();
        }

        function parseKeys(csv) {
            return csv.split(',')
                .map(k => norm(k))
                .filter(k => k.length > 0);
        }

        function matchesAnyKey(text, keys) {
            if (!keys || keys.length === 0) return true; // no filter => show all
            return keys.some(k => text.includes(k));
        }

        function applyFilters() {
            const q = searchInput ? norm(searchInput.value) : '';
            pills.forEach(pill => {
                const txt = norm(pill.textContent);

                const okButton = matchesAnyKey(txt, activeKeys);          // OR
                const okSearch = (q === '') ? true : txt.includes(q);     // contains
                pill.style.display = (okButton && okSearch) ? '' : 'none';
            });
        }

        // Button click: set activeKeys only (do NOT modify search input)
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                btns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                activeKeys = parseKeys(btn.getAttribute('data-keys') || '');
                applyFilters();
            });
        });

        // Typing search refines current button filter
        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        // default
        const first = btns.find(b => b.classList.contains('active')) || btns[0];
        activeKeys = first ? parseKeys(first.getAttribute('data-keys') || '') : [];
        applyFilters();
    })();

    (function () {
        const searchInput = document.getElementById('announcementSearch');
        const pills = Array.from(document.querySelectorAll('#announcementList .announcement-pill'));
        const btns = Array.from(document.querySelectorAll('.ann-filter-btn'));
        const hint = document.getElementById('announcementHint');

        if (!pills.length) return;

        let activeKeys = [];
        let userActivated = false; // becomes true after typing or clicking a filter

        function norm(s) { return (s || '').toString().trim().toLowerCase(); }
        function parseKeys(csv) {
            return (csv || '').split(',').map(norm).filter(k => k.length > 0);
        }
        function matchesAnyKey(text, keys) {
            if (!keys || keys.length === 0) return true;
            return keys.some(k => text.includes(k));
        }

        function hideAll() {
            pills.forEach(p => p.style.display = 'none');
            if (hint) hint.style.display = '';
        }

        function applyFilters() {
            const q = searchInput ? norm(searchInput.value) : '';

            // show results only after user action
            if (!userActivated) {
                hideAll();
                return;
            }

            // optional: require minimum typing when no filter is selected
            // if (activeKeys.length === 0 && q.length < 2) { hideAll(); return; }

            pills.forEach(pill => {
                const txt = norm(pill.textContent);
                const okBtn = matchesAnyKey(txt, activeKeys);      // OR keywords
                const okTxt = (q === '') ? true : txt.includes(q); // typed contains
                pill.style.display = (okBtn && okTxt) ? '' : 'none';
            });

            if (hint) hint.style.display = 'none';
        }

        // typing => activate
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                userActivated = true;
                // if user types manually, don't force active button state
                applyFilters();
            });
        }

        // filter buttons => activate
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                userActivated = true;

                btns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                activeKeys = parseKeys(btn.getAttribute('data-keys') || '');
                applyFilters();
            });
        });

        // default state: hide all
        hideAll();
    })();
</script>
</body>
</html>
