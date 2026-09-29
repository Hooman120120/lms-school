<?php
/* مستندات، همکاران، معرفان، حضور و غیاب کارکنان، گزارش‌ها. */

$on = static fn(AdminLegacyAccount $a, string $code, string $flag): bool =>
    $a->perm($flag) && ($a->tree ? $a->oldSee($code) : true);
$doc = static fn(AdminLegacyAccount $a, string $code): bool => $on($a, $code, 'permision_viewdocument');
$ref = static fn(AdminLegacyAccount $a, string $code): bool => $on($a, $code, 'permision_viewcollague');
$emp = static fn(AdminLegacyAccount $a, string $code): bool => $on($a, $code, 'permision_viewemploy');

return [
'nodes' => [
    ['code'=>'documents','label'=>'مستندات','children'=>[
        ['code'=>'documents.new','label'=>'تازه‌ها','path'=>'admin/Doc/newdoc.php','paths'=>['admin/Doc/meta.php']],
        ['code'=>'documents.search','label'=>'جست‌وجوی مستندات','path'=>'admin/Doc/searchdoc.php'],
        ['code'=>'documents.announcements','label'=>'اطلاعیه‌ها و بخشنامه‌ها','path'=>'admin/Doc/announcements.php','children'=>[
            ['code'=>'documents.announcements.create','label'=>'ثبت اطلاعیه','path'=>'admin/Doc/announcement-create.php'],
            ['code'=>'documents.announcements.edit','label'=>'ویرایش اطلاعیه','path'=>'admin/Doc/announcement-edit.php'],
            ['code'=>'documents.announcements.publish','label'=>'انتشار یا توقف انتشار'],
            ['code'=>'documents.announcements.delete','label'=>'حذف اطلاعیه'],
        ]],
        ['code'=>'documents.special-letters','label'=>'نامه‌های اختصاصی','path'=>'admin/Doc/special-letters.php','children'=>[
            ['code'=>'documents.special-letters.send','label'=>'ثبت و ارسال نامه'],
        ]],
        ['code'=>'documents.employee','label'=>'مستندات کارکنان','path'=>'admin/Doc/employee-documents.php'],
    ]],
    ['code'=>'staff','label'=>'همکاران','children'=>[
        ['code'=>'staff.directory','label'=>'مشاهده همکاران','path'=>'admin/employ/staff-directory.php'],
        ['code'=>'staff.manage','label'=>'مدیریت همکاران','path'=>'admin/employ/listadmin.php','children'=>[
            ['code'=>'staff.manage.edit','label'=>'ویرایش همکار و دسترسی‌های او','path'=>'admin/employ/addemploy.php'],
            ['code'=>'staff.manage.toggle','label'=>'فعال یا غیرفعال کردن همکار'],
        ]],
        ['code'=>'staff.teachers','label'=>'مشاهده اساتید','path'=>'admin/employ/listteacher.php','children'=>[
            ['code'=>'staff.teachers.edit','label'=>'ویرایش استاد و دسترسی‌های او','path'=>'admin/employ/addteacher.php'],
            ['code'=>'staff.teachers.toggle','label'=>'فعال یا غیرفعال کردن استاد'],
            ['code'=>'staff.teachers.group-action','label'=>'اقدام گروهی روی دسترسی اساتید'],
        ]],
        ['code'=>'staff.create','label'=>'ثبت همکار','path'=>'admin/employ/addemploy.php'],
        ['code'=>'staff.teacher-create','label'=>'ثبت استاد','path'=>'admin/employ/addteacher.php'],
    ]],
    ['code'=>'referrers','label'=>'معرفان برون‌سازمانی','children'=>[
        ['code'=>'referrers.create','label'=>'ثبت معرف','path'=>'admin/Collague/add-collague.php'],
        ['code'=>'referrers.edit','label'=>'ویرایش معرف','path'=>'admin/Collague/edit-collague.php'],
        ['code'=>'referrers.inactive','label'=>'معرفان غیرفعال','path'=>'admin/Collague/unactive-collague.php'],
        ['code'=>'referrers.sub','label'=>'زیرمجموعه معرفان','path'=>'admin/Collague/sub-collague.php','paths'=>['admin/Collague/viewCollague.php'],'children'=>[
            ['code'=>'referrers.sub.manage','label'=>'فعال‌سازی، غیرفعال‌سازی و حذف معرف'],
            ['code'=>'referrers.sub.assign','label'=>'افزودن زیرمجموعه','path'=>'admin/Collague/subset.php'],
        ]],
        ['code'=>'referrers.report','label'=>'گزارش معرفان','path'=>'admin/Collague/report-collague.php','children'=>[
            ['code'=>'referrers.report.export','label'=>'خروجی اکسل','path'=>'admin/Collague/export-collague.php'],
        ]],
    ]],
    ['code'=>'attendance','label'=>'حضور و غیاب','children'=>[
        ['code'=>'attendance.report','label'=>'گزارش روزانه حضور و غیاب','path'=>'admin/attendance/report.php','children'=>[
            ['code'=>'attendance.report.export','label'=>'خروجی اکسل'],
        ]],
        ['code'=>'attendance.devices','label'=>'دستگاه‌ها و رکوردهای حضور','path'=>'admin/attendance/devices.php','children'=>[
            ['code'=>'attendance.devices.manage','label'=>'افزودن، ویرایش، غیرفعال‌سازی و حذف دستگاه'],
        ]],
        ['code'=>'attendance.import','label'=>'ورود حضور و غیاب از فایل اکسل','path'=>'admin/attendance/import.php','children'=>[
            ['code'=>'attendance.import.run','label'=>'بارگذاری و اجرای ورود'],
        ]],
        ['code'=>'attendance.rules','label'=>'قوانین حضور و غیاب','path'=>'admin/attendance/rules.php','children'=>[
            ['code'=>'attendance.rules.manage','label'=>'ذخیره و اجرای قوانین'],
        ]],
    ]],
    ['code'=>'reports','label'=>'گزارش‌ها','children'=>[
        ['code'=>'reports.students','label'=>'گزارش دانش‌آموزان','labels'=>['institute'=>'گزارش فراگیران'],'path'=>'admin/report/students.php','paths'=>['admin/course/get_courses_by_type.php'],'children'=>[
            ['code'=>'reports.students.toggle','label'=>'فعال یا غیرفعال کردن ثبت‌نام'],
            ['code'=>'reports.students.call','label'=>'تماس با دانش‌آموز یا والدین'],
            ['code'=>'reports.students.export','label'=>'خروجی اکسل'],
        ]],
        ['code'=>'reports.staff','label'=>'گزارش همکاران','path'=>'admin/report/colleague.php','children'=>[
            ['code'=>'reports.staff.call','label'=>'تماس'],
        ]],
    ]],
],
'convert' => [
    'documents.new' => fn($a) => $doc($a, 'documents.new'),
    'documents.search' => fn($a) => $doc($a, 'documents.search'),
    'documents.announcements@open' => fn($a) => $doc($a, 'documents.announcements'),
    'documents.announcements.create' => fn($a) => $doc($a, 'documents.announcements.create') || ($a->tree && $a->old('documents.announcements') && $a->perm('permision_viewdocument')),
    'documents.announcements.edit' => fn($a) => $doc($a, 'documents.announcements.edit') || ($a->tree && $a->old('documents.announcements') && $a->perm('permision_viewdocument')),
    'documents.special-letters' => fn($a) => $doc($a, 'documents.special-letters'),
    'documents.employee' => fn($a) => $doc($a, 'documents'),
    'staff.directory' => fn($a) => ($a->perm('permission_staff_view') || $a->perm('permision_viewemploy')) && ($a->tree ? $a->oldSee('staff.directory') : true),
    'staff.manage@open' => fn($a) => $emp($a, 'staff.manage'),
    'staff.manage.edit' => fn($a) => $emp($a, 'staff.manage') && (!$a->tree || $a->old('staff.manage.edit') || $a->old('staff.manage')),
    'staff.manage.toggle' => fn($a) => $emp($a, 'staff.manage') && (!$a->tree || $a->old('staff.manage.edit') || $a->old('staff.manage')),
    'staff.teachers' => fn($a) => $emp($a, 'staff.teachers'),
    'staff.create' => fn($a) => $emp($a, 'staff.create'),
    'staff.teacher-create' => fn($a) => $emp($a, 'staff.teacher-create'),
    'referrers.create' => fn($a) => $ref($a, 'referrers.create'),
    'referrers.edit' => fn($a) => $ref($a, 'referrers.edit'),
    'referrers.inactive' => fn($a) => $ref($a, 'referrers.inactive'),
    'referrers.sub' => fn($a) => $ref($a, 'referrers.sub'),
    'referrers.report' => fn($a) => $ref($a, 'referrers') && $a->perm('permision_reportcollague'),
    'attendance.report' => fn($a) => $a->can('attendance.report', ['administrator']),
    'attendance.devices' => fn($a) => $a->can('attendance.devices', ['administrator']),
    'attendance.import' => fn($a) => $a->can('attendance.import', ['administrator']),
    'attendance.rules' => fn($a) => $a->can('attendance.rules', ['administrator']),
    'reports.students' => fn($a) => $a->tree ? $a->oldSee('reports.students') : ($a->perm('permision_addcourse_student') && $a->perm('permision_reportstudent')),
    'reports.staff' => fn($a) => $a->tree ? $a->oldSee('reports.staff') : ($a->perm('permision_addcourse_student') && $a->perm('permision_reportstudent')),
],
];
