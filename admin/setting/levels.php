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
    header('location: ' . url('admin/login.php'));
    die();
}
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (true) { // retired section: not in the permission tree
    header('location:' . url('admin/index.php'));
    die();
}

$type = inst_type();

/* ------------------------------ افزودن ------------------------------ */
if (isset($_POST['add-level'])) {
    $kind       = in_array($_POST['kind'], ['level', 'group', 'field'], true) ? $_POST['kind'] : 'level';
    $slug       = trim($_POST['slug']);
    $label      = trim($_POST['label']);
    $value      = ($kind === 'level' && $_POST['value'] !== '') ? (int) $_POST['value'] : null;
    $group      = ($_POST['group_slug'] !== '') ? $_POST['group_slug'] : null;
    $sort       = (int) $_POST['sort_order'];
    $selectable = isset($_POST['selectable_for_person']) ? 1 : 0;
    if ($slug === '' || $label === '') {
        $message = m('levels.err_required');
    } elseif ($kind === 'level' && $value === null) {
        $message = m('levels.err_value_required');
    } else {
        try {
            $ins = $conn->prepare(
                'INSERT INTO institution_levels
                    (institution_type, kind, value, slug, label, group_slug, sort_order, selectable_for_person)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $ins->execute([$type, $kind, $value, $slug, $label, $group, $sort, $selectable]);
            $message = m('levels.added');
        } catch (PDOException $e) {
            $message = m('levels.err_duplicate');
        }
    }
}

/* ------------------------------ ویرایش گروهی ------------------------------ */
if (isset($_POST['save-levels'])) {
    $ids = isset($_POST['id']) ? $_POST['id'] : [];
    $up  = $conn->prepare(
        'UPDATE institution_levels
            SET label = ?, value = ?, group_slug = ?, sort_order = ?, is_active = ?, selectable_for_person = ?
          WHERE id = ? AND institution_type = ?'
    );
    foreach ($ids as $id) {
        $id  = (int) $id;
        $val = (isset($_POST['value'][$id]) && $_POST['value'][$id] !== '') ? (int) $_POST['value'][$id] : null;
        $grp = (isset($_POST['group_slug'][$id]) && $_POST['group_slug'][$id] !== '') ? $_POST['group_slug'][$id] : null;
        $up->execute([
            trim($_POST['label'][$id]),
            $val,
            $grp,
            (int) $_POST['sort_order'][$id],
            isset($_POST['is_active'][$id]) ? 1 : 0,
            isset($_POST['selectable_for_person'][$id]) ? 1 : 0,
            $id,
            $type,
        ]);
    }
    $message = m('levels.saved');
}

/* ------------------------------ حذف ------------------------------ */
if (isset($_POST['delete-level'])) {
    $id   = (int) $_POST['delete-level'];
    $used = 0;
    $chk  = $conn->prepare('SELECT value FROM institution_levels WHERE id = ? AND institution_type = ?');
    $chk->execute([$id, $type]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['value'] !== null) {
        $cnt = $conn->prepare('SELECT COUNT(*) FROM dbpersonal WHERE paye = ?');
        $cnt->execute([$row['value']]);
        $used = (int) $cnt->fetchColumn();
    }
    if ($used > 0) {
        $message = m('levels.err_in_use', [':n' => inst_fa_digits($used)]);
    } else {
        $del = $conn->prepare('DELETE FROM institution_levels WHERE id = ? AND institution_type = ?');
        $del->execute([$id, $type]);
        $message = m('levels.deleted');
    }
}

/* ------------------------------ خواندن ------------------------------ */
$rows = [];
$st   = $conn->prepare('SELECT * FROM institution_levels WHERE institution_type = ? ORDER BY kind, sort_order, id');
$st->execute([$type]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $rows[$r['kind']][] = $r;
}

/* شمارش فراگیران هر پایه، برای آن که سطرِ در حال استفاده ناخواسته حذف نشود */
$usage = [];
foreach ($conn->query('SELECT paye, COUNT(*) n FROM dbpersonal GROUP BY paye')->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $usage[(string) $u['paye']] = $u['n'];
}

$groupChoices = isset($rows['group']) ? $rows['group'] : [];

$kindLabels = [
    'level' => m('levels.kind_level'),
    'group' => m('levels.kind_group'),
    'field' => m('levels.kind_field'),
];

/* کلیدها صریح نوشته شده‌اند تا اعتبارسنج واژه‌نامه بتواند آن‌ها را ببیند */
$kindHints = [
    'level' => m('levels.hint_level'),
    'group' => m('levels.hint_group'),
    'field' => m('levels.hint_field'),
];

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <title><?php echo m('levels.page_title') ?> | <?php echo __TITLE ?></title>
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
            <div class="inner-body">

                <?php if (isset($message)) { ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <button aria-label="Close" class="close" data-dismiss="alert" type="button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <?php echo $message ?>
                    </div>
                <?php } ?>

                <div class="alert alert-warning">
                    <?php echo m('levels.intro') ?>
                </div>

                <div class="row row-sm">
                    <div class="col-xl-8">
                        <form method="post">
                            <?php foreach (['level', 'group', 'field'] as $kind) { ?>
                                <div class="card custom-card mb-3">
                                    <div class="card-body">
                                        <h6 class="main-content-label mb-1"><?php echo $kindLabels[$kind] ?></h6>
                                        <p class="text-muted card-sub-title"><?php echo $kindHints[$kind] ?></p>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered text-nowrap mb-0">
                                                <thead>
                                                <tr>
                                                    <th><?php echo m('levels.col_label') ?></th>
                                                    <th><?php echo m('levels.col_slug') ?></th>
                                                    <?php if ($kind === 'level') { ?>
                                                        <th><?php echo m('levels.col_value') ?></th>
                                                        <th><?php echo m('levels.col_group') ?></th>
                                                    <?php } ?>
                                                    <th><?php echo m('levels.col_sort') ?></th>
                                                    <th><?php echo m('levels.col_active') ?></th>
                                                    <?php if ($kind === 'level') { ?>
                                                        <th><?php echo m('levels.col_selectable') ?></th>
                                                        <th><?php echo m('levels.col_usage') ?></th>
                                                    <?php } ?>
                                                    <th></th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($rows[$kind])) { ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center text-muted">
                                                            <?php echo m('levels.empty') ?>
                                                        </td>
                                                    </tr>
                                                <?php }
                                                foreach ((isset($rows[$kind]) ? $rows[$kind] : []) as $r) {
                                                    $id = (int) $r['id']; ?>
                                                    <tr>
                                                        <td>
                                                            <input type="hidden" name="id[]" value="<?php echo $id ?>">
                                                            <input class="form-control" type="text"
                                                                   name="label[<?php echo $id ?>]"
                                                                   value="<?php echo htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') ?>">
                                                        </td>
                                                        <td>
                                                            <code><?php echo htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8') ?></code>
                                                        </td>
                                                        <?php if ($kind === 'level') { ?>
                                                            <td style="width:90px;">
                                                                <input class="form-control" type="number"
                                                                       name="value[<?php echo $id ?>]"
                                                                       value="<?php echo htmlspecialchars((string) $r['value'], ENT_QUOTES, 'UTF-8') ?>">
                                                            </td>
                                                            <td>
                                                                <select class="form-control"
                                                                        name="group_slug[<?php echo $id ?>]">
                                                                    <option value="">&mdash;</option>
                                                                    <?php foreach ($groupChoices as $g) { ?>
                                                                        <option value="<?php echo htmlspecialchars($g['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                                                            <?php echo $r['group_slug'] === $g['slug'] ? 'selected' : '' ?>>
                                                                            <?php echo htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                        </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                        <?php } ?>
                                                        <td style="width:90px;">
                                                            <input class="form-control" type="number"
                                                                   name="sort_order[<?php echo $id ?>]"
                                                                   value="<?php echo (int) $r['sort_order'] ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="is_active[<?php echo $id ?>]"
                                                                <?php echo (int) $r['is_active'] === 1 ? 'checked' : '' ?>>
                                                        </td>
                                                        <?php if ($kind === 'level') { ?>
                                                            <td class="text-center">
                                                                <input type="checkbox"
                                                                       name="selectable_for_person[<?php echo $id ?>]"
                                                                    <?php echo (int) $r['selectable_for_person'] === 1 ? 'checked' : '' ?>>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php
                                                                $n = isset($usage[(string) $r['value']]) ? $usage[(string) $r['value']] : 0;
                                                                echo $n > 0
                                                                    ? '<span class="badge badge-info">' . inst_fa_digits($n) . '</span>'
                                                                    : '<span class="text-muted">۰</span>';
                                                                ?>
                                                            </td>
                                                        <?php } ?>
                                                        <td class="text-center">
                                                            <button type="submit" name="delete-level"
                                                                    value="<?php echo $id ?>"
                                                                    class="btn btn-sm btn-danger"
                                                                    onclick="return confirm(<?php echo htmlspecialchars(json_encode(m('levels.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="text-left mb-4">
                                <button type="submit" name="save-levels" class="btn btn-primary">
                                    <i class="fa fa-save"></i> <?php echo m('levels.save_all') ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <form method="post">
                                    <h6 class="main-content-label mb-1"><?php echo m('levels.add_title') ?></h6>
                                    <p class="text-muted card-sub-title"><?php echo m('levels.add_hint') ?></p>

                                    <label class="mt-2"><?php echo m('levels.col_kind') ?></label>
                                    <select class="form-control" name="kind">
                                        <option value="level"><?php echo $kindLabels['level'] ?></option>
                                        <option value="group"><?php echo $kindLabels['group'] ?></option>
                                        <option value="field"><?php echo $kindLabels['field'] ?></option>
                                    </select>

                                    <label class="mt-2"><?php echo m('levels.col_label') ?></label>
                                    <input class="form-control" type="text" name="label" required>

                                    <label class="mt-2"><?php echo m('levels.col_slug') ?></label>
                                    <input class="form-control" type="text" name="slug" required>
                                    <small class="text-muted"><?php echo m('levels.slug_hint') ?></small>

                                    <label class="mt-2"><?php echo m('levels.col_value') ?></label>
                                    <input class="form-control" type="number" name="value">
                                    <small class="text-muted"><?php echo m('levels.value_hint') ?></small>

                                    <label class="mt-2"><?php echo m('levels.col_group') ?></label>
                                    <select class="form-control" name="group_slug">
                                        <option value="">&mdash;</option>
                                        <?php foreach ($groupChoices as $g) { ?>
                                            <option value="<?php echo htmlspecialchars($g['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?php echo htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php } ?>
                                    </select>

                                    <label class="mt-2"><?php echo m('levels.col_sort') ?></label>
                                    <input class="form-control" type="number" name="sort_order" value="0">

                                    <div class="mt-3">
                                        <label>
                                            <input type="checkbox" name="selectable_for_person" checked>
                                            <?php echo m('levels.col_selectable') ?>
                                        </label>
                                    </div>

                                    <div class="text-left mt-3">
                                        <button type="submit" name="add-level" class="btn btn-success">
                                            <i class="fa fa-plus"></i> <?php echo m('levels.add_btn') ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>
</body>
</html>
