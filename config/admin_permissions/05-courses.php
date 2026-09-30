<?php
/*
 * مدیریت پایه‌ها: courses (پایه/دوره), their classes and content, demo courses
 * (فوق برنامه/دوره موقت), handhug groups. Teachers use the
 * same nodes; their old switches (teacher_perm_*) are converted here.
 */

$vc = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_viewcourse');
$del = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_deletcourse');
$tq = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_viewtest');
// A staff account (not a teacher) that could open a course list before.
$staffOn = static fn(AdminLegacyAccount $a, string $old): bool => !$a->isTeacher() && ($a->tree ? $a->oldSee($old) : true);
// A teacher with the course switch and, optionally, one more switch.
$teach = static fn(AdminLegacyAccount $a, ?string $switch = null): bool =>
    $a->isTeacher() && $a->col('teacher_perm_courses') && ($switch === null || $a->col($switch));
$active = static fn(AdminLegacyAccount $a): bool => $staffOn($a, 'courses.main.active');

// The same row actions on every course list.
$rowActions = static fn(string $base, bool $withEdit = true): array => array_values(array_filter([
    ['code'=>"$base.activation",'label'=>'فعال یا قطع کردن کلاس، مشاوره، آزمون و ثبت‌نام'],
    ['code'=>"$base.quiz-login",'label'=>'ورود به آزمون'],
    ['code'=>"$base.schedule-reset",'label'=>'بازنشانی زمان‌بندی'],
    ['code'=>"$base.delete",'label'=>'حذف دائم'],
]));
$rowRules = static fn(string $base, callable $open) => [
    "$base.activation" => fn($a) => $open($a) && $vc($a),
    "$base.quiz-login" => fn($a) => $open($a) && $tq($a),
    "$base.schedule-reset" => fn($a) => $open($a) && $vc($a),
    "$base.delete" => fn($a) => $open($a) && $del($a),
];
$A = 'courses.main.active';
$C = "$A.classes";
$demo = 'courses.main.demo-active';
$hh = 'courses.handhug';

return [
'nodes' => [
    ['code'=>'courses','label'=>'مدیریت پایه‌ها','labels'=>['institute'=>'مدیریت دوره‌ها'],'children'=>[
        ['code'=>'courses.main','label'=>'پایه‌ها','labels'=>['institute'=>'دوره‌ها'],'children'=>[
            ['code'=>'courses.main.create','label'=>'ایجاد پایه','labels'=>['institute'=>'ایجاد دوره فعال'],'path'=>'admin/course/add-Course.php'],
            ['code'=>'courses.main.active','label'=>'پایه‌های فعال','labels'=>['institute'=>'دوره‌های فعال'],'path'=>'admin/course/Active-course.php','children'=>array_merge([
                ['code'=>"$A.tab-skill",'label'=>'زبانهٔ پایه‌های مهارتی'],
                ['code'=>"$A.tab-olympiad",'label'=>'زبانهٔ پایه‌های المپیاد'],
                ['code'=>"$A.online-class",'label'=>'ورود به کلاس آنلاین','path'=>'admin/course/login_skyroom.php'],
                ['code'=>"$A.edit",'label'=>'ویرایش پایه','path'=>'admin/course/add-Course.php'],
                ['code'=>"$A.students",'label'=>'فهرست دانش‌آموزان پایه','path'=>'admin/course/list-student.php','children'=>[
                    ['code'=>"$A.students.export",'label'=>'خروجی اکسل دانش‌آموزان پایه','path'=>'admin/course/export-student.php'],
                ]],
                ['code'=>"$A.supporter",'label'=>'انتخاب پشتیبان دانش‌آموز','path'=>'admin/course/select-suporter.php'],
                ['code'=>"$A.teachers",'label'=>'تخصیص استاد به کلاس و درس','path'=>'admin/course/teacher-assign.php','paths'=>['admin/course/dars-teachers.php']],
                ['code'=>$C,'label'=>'مدیریت کلاس‌ها','path'=>'admin/course/list-class.php','children'=>[
                    ['code'=>"$C.create",'label'=>'ایجاد کلاس'],
                    ['code'=>"$C.edit",'label'=>'ویرایش کلاس'],
                    ['code'=>"$C.activation",'label'=>'فعال یا قطع کردن کلاس، مشاوره و آزمون کلاس'],
                    ['code'=>"$C.toggle",'label'=>'فعال یا غیرفعال کردن کلاس'],
                    ['code'=>"$C.detail",'label'=>'جزئیات کلاس','path'=>'admin/course/class-detail.php'],
                    ['code'=>"$C.students",'label'=>'دانش‌آموزان کلاس','path'=>'admin/course/assign-student.php','children'=>[
                        ['code'=>"$C.students.assign",'label'=>'افزودن دانش‌آموز به کلاس'],
                        ['code'=>"$C.students.import",'label'=>'افزودن با فایل اکسل'],
                        ['code'=>"$C.students.remove",'label'=>'حذف دانش‌آموز از کلاس'],
                        ['code'=>"$C.students.tickets",'label'=>'پیام استاد به دانش‌آموز و والدین'],
                    ]],
                    ['code'=>"$C.attendance",'label'=>'حضور و غیاب','path'=>'admin/course/attendance.php','paths'=>['admin/course/list-attendance.php'],'children'=>[
                        ['code'=>"$C.attendance.manage",'label'=>'ثبت، ویرایش و حذف جلسه'],
                        ['code'=>"$C.attendance.export",'label'=>'خروجی اکسل حضور و غیاب','path'=>'admin/course/export-attendance.php'],
                    ]],
                    ['code'=>"$C.register",'label'=>'دفتر کلاسی','path'=>'admin/course/class-register.php','paths'=>['admin/course/class-register-excel.php', 'admin/course/class-register-notification.php'],'children'=>[
                        ['code'=>"$C.register.full",'label'=>'مدیریت کامل دفتر (ویرایش و حذف ستون‌ها، ثبت‌ها و موارد انضباطی)'],
                    ]],
                    ['code'=>"$C.homeworks",'label'=>'تکالیف','path'=>'admin/course/homeworks.php','children'=>[
                        ['code'=>"$C.homeworks.create",'label'=>'ثبت تکلیف'],
                        ['code'=>"$C.homeworks.edit",'label'=>'ویرایش تکلیف'],
                        ['code'=>"$C.homeworks.delete",'label'=>'حذف تکلیف'],
                        ['code'=>"$C.homeworks.answer",'label'=>'ثبت پاسخ استاد'],
                    ]],
                    ['code'=>"$C.lesson-plans",'label'=>'طرح درس','path'=>'admin/course/lesson-plans.php','children'=>[
                        ['code'=>"$C.lesson-plans.manage",'label'=>'ثبت، ویرایش و حذف طرح درس خود استاد'],
                    ]],
                ]],
                ['code'=>"$A.content",'label'=>'محتوای آموزشی','paths'=>['admin/course/get_chapters.php'],'children'=>[
                    ['code'=>"$A.content.dars",'label'=>'درس‌ها و لینک کلاس','path'=>'admin/course/dars.php','children'=>[
                        ['code'=>"$A.content.dars.edit",'label'=>'ثبت و ویرایش درس و لینک کلاس'],
                        ['code'=>"$A.content.dars.delete",'label'=>'حذف درس'],
                    ]],
                    ['code'=>"$A.content.lessons",'label'=>'فصل‌ها','path'=>'admin/course/lesson.php'],
                    ['code'=>"$A.content.sub-chapters",'label'=>'زیرفصل‌ها','path'=>'admin/course/sub-chapters.php'],
                    ['code'=>"$A.content.movies",'label'=>'فیلم‌ها','path'=>'admin/course/movie.php'],
                    ['code'=>"$A.content.docs",'label'=>'جزوه‌ها و پیوست‌ها','path'=>'admin/course/addon-doc.php'],
                    ['code'=>"$A.content.teacher-upload",'label'=>'بارگذاری محتوا توسط استاد','paths'=>['admin/course/teacher-content.php', 'admin/course/teacher-upload-content.php']],
                ]],
            ], $rowActions($A))],
            ['code'=>'courses.main.demo-create','label'=>'ایجاد فوق برنامه','labels'=>['institute'=>'ایجاد دوره موقت'],'path'=>'admin/democourse/add-democourse.php','children'=>[
                ['code'=>'courses.main.demo-create.edit','label'=>'ویرایش فوق برنامه'],
            ]],
            ['code'=>$demo,'label'=>'کلاس‌های فوق برنامه در حال اجرا','labels'=>['institute'=>'دوره‌های موقت'],'path'=>'admin/democourse/list-democourse.php','children'=>array_merge([
                ['code'=>"$demo.tab-skill",'label'=>'زبانهٔ فوق برنامه‌های مهارتی'],
                ['code'=>"$demo.students",'label'=>'فهرست دانش‌آموزان فوق برنامه','path'=>'admin/democourse/list-demo-student.php','children'=>[
                    ['code'=>"$demo.students.remove",'label'=>'حذف دانش‌آموز از فوق برنامه'],
                ]],
                ['code'=>"$demo.movies",'label'=>'فیلم‌ها','path'=>'admin/democourse/movie.php'],
                ['code'=>"$demo.docs",'label'=>'جزوه‌ها','path'=>'admin/democourse/addon-doc.php'],
                ['code'=>"$demo.quiz",'label'=>'آزمون فوق برنامه','path'=>'admin/course/add-quiz.php','paths'=>['admin/course/absent.php', 'admin/course/list-result.php', 'admin/test/absent.php', 'admin/test/list-result.php'],'children'=>[
                    ['code'=>"$demo.quiz.manage",'label'=>'ثبت و حذف آزمون و پاسخنامه','path'=>'admin/course/add-pasokhname.php'],
                    ['code'=>"$demo.quiz.export",'label'=>'خروجی اکسل غایبان و نتایج','paths'=>['admin/course/export-absent.php', 'admin/test/export-absent.php']],
                ]],
            ], $rowActions($demo))],
            ['code'=>'courses.main.archive','label'=>'بایگانی سال‌های قبل','path'=>'admin/course/unactive-course.php','children'=>array_merge([
                ['code'=>'courses.main.archive.tab-skill','label'=>'زبانهٔ پایه‌های مهارتی'],
                ['code'=>'courses.main.archive.tab-olympiad','label'=>'زبانهٔ پایه‌های المپیاد'],
            ], $rowActions('courses.main.archive'))],
            ['code'=>'courses.main.discount','label'=>'ایجاد کد تخفیف','path'=>'admin/course/add-discount.php','children'=>[
                ['code'=>'courses.main.discount.create','label'=>'ثبت کد تخفیف'],
                ['code'=>'courses.main.discount.delete','label'=>'حذف کد تخفیف'],
            ]],
        ]],
        ['code'=>'courses.handhug','label'=>'بغل‌دستی‌ها','children'=>[
            ['code'=>'courses.handhug.create','label'=>'ساخت محصول بغل‌دستی','path'=>'admin/handhug/add-handhug.php'],
            ['code'=>'courses.handhug.manage','label'=>'بغل‌دستی‌های فعال','path'=>'admin/handhug/handhug-management.php','children'=>array_merge([
                ['code'=>'courses.handhug.manage.edit','label'=>'ویرایش محصول','path'=>'admin/handhug/add-handhug.php'],
            ], $rowActions('courses.handhug.manage'))],
            ['code'=>'courses.handhug.archive','label'=>'بایگانی سال‌های قبل بغل‌دستی‌ها','path'=>'admin/handhug/disabled-handhugs.php','children'=>$rowActions('courses.handhug.archive')],
            ['code'=>'courses.handhug.groups','label'=>'گروه‌های بغل‌دستی','path'=>'admin/handhug/handhug-group-manage.php','children'=>[
                ['code'=>'courses.handhug.groups.manage','label'=>'ایجاد، ویرایش و فعال‌سازی گروه و تغییر پشتیبان'],
                ['code'=>'courses.handhug.groups.members','label'=>'اعضای گروه و سرگروه','path'=>'admin/handhug/add-handhug-users.php'],
            ]],
            ['code'=>'courses.handhug.list','label'=>'فهرست اعضای بغل‌دستی','path'=>'admin/handhug/handhug-list.php','children'=>[
                ['code'=>'courses.handhug.list.all','label'=>'همهٔ اعضا'],
                ['code'=>'courses.handhug.list.own','label'=>'فقط اعضای تحت پشتیبانی خود'],
            ]],
        ]],
    ]],
],
'convert' => array_merge(
    $rowRules($A, fn($a) => $active($a)),
    $rowRules($demo, fn($a) => $staffOn($a, $demo)),
    $rowRules('courses.main.archive', fn($a) => $vc($a) && $staffOn($a, 'courses.main.archive')),
    $rowRules('courses.handhug.manage', fn($a) => $staffOn($a, 'courses.handhug.manage')),
    $rowRules('courses.handhug.archive', fn($a) => $staffOn($a, 'courses.handhug.archive')),
    [
    'courses.main.create' => fn($a) => $vc($a) && ($a->tree ? $a->oldSee('courses.main.create') : true),
    "$A@open" => fn($a) => $active($a) || $teach($a),
    "$A.tab-skill" => fn($a) => $a->col('skill_permission'),
    "$A.tab-olympiad" => fn($a) => $a->col('olympiad_permission'),
    "$A.online-class" => fn($a) => ($active($a) && $vc($a)) || $teach($a, 'teacher_perm_online_class'),
    "$A.edit" => fn($a) => $vc($a) && ($a->tree ? $a->old("$A.edit") || $a->old($A) : true),
    "$A.students" => fn($a) => $active($a) && $vc($a),
    "$A.supporter" => fn($a) => $vc($a),
    "$A.teachers" => fn($a) => !$a->isTeacher() && $vc($a),
    "$C@open" => fn($a) => $active($a) || ($teach($a) && ($a->col('teacher_perm_content') || $a->col('teacher_perm_students')
        || $a->col('teacher_perm_homework') || $a->col('teacher_perm_attendance'))),
    "$C.create" => fn($a) => $active($a) && $vc($a),
    "$C.edit" => fn($a) => $active($a) && $vc($a),
    "$C.activation" => fn($a) => $active($a) && $vc($a),
    "$C.toggle" => fn($a) => $active($a) && $del($a),
    "$C.detail" => fn($a) => $active($a) || $teach($a),
    "$C.students@open" => fn($a) => $active($a) || $teach($a, 'teacher_perm_students'),
    "$C.students.assign" => fn($a) => ($active($a) && $vc($a)) || $teach($a, 'teacher_perm_students'),
    "$C.students.import" => fn($a) => ($active($a) && $vc($a)) || $teach($a, 'teacher_perm_students'),
    "$C.students.remove" => fn($a) => ($active($a) && $vc($a)) || $teach($a, 'teacher_perm_students'),
    "$C.students.tickets" => fn($a) => $teach($a, 'teacher_perm_students') && $a->col('teacher_perm_messages'),
    "$C.attendance@open" => fn($a) => $active($a) || $staffOn($a, $demo) || $teach($a, 'teacher_perm_attendance'),
    "$C.attendance.manage" => fn($a) => ($active($a) && $vc($a)) || $teach($a, 'teacher_perm_attendance'),
    "$C.attendance.export" => fn($a) => $vc($a),
    "$C.register@open" => fn($a) => $teach($a, 'teacher_perm_attendance') || (!$a->isTeacher() && ($vc($a) || $a->col('register_class_permission'))),
    "$C.register.full" => fn($a) => $teach($a, 'teacher_perm_attendance')
        || (!$a->isTeacher() && ($vc($a) || $a->col('register_class_permission')) && ($a->post('manager', 'general_deputy', 'grade_deputy') || $a->col('register_class_permission'))),
    "$C.homeworks" => fn($a) => $active($a) || $teach($a, 'teacher_perm_homework'),
    "$C.lesson-plans@open" => fn($a) => $active($a) || $teach($a),
    "$C.lesson-plans.manage" => fn($a) => $teach($a),
    "$A.content.dars@open" => fn($a) => $active($a) || $teach($a, 'teacher_perm_content'),
    "$A.content.dars.delete" => fn($a) => $del($a) || $a->role(2),
    "$A.content.lessons" => fn($a) => $active($a) || $teach($a, 'teacher_perm_content'),
    "$A.content.sub-chapters" => fn($a) => $active($a) || $teach($a, 'teacher_perm_content'),
    "$A.content.movies" => fn($a) => $vc($a),
    "$A.content.docs" => fn($a) => $vc($a),
    "$A.content.teacher-upload" => fn($a) => $teach($a, 'teacher_perm_content'),

    'courses.main.demo-create@open' => fn($a) => $vc($a) && ($a->tree ? $a->oldSee('courses.main.demo-create') : true),
    'courses.main.demo-create.edit' => fn($a) => $vc($a) && ($a->tree ? $a->oldSee('courses.main.demo-create') || $a->oldSee($demo) : true),
    "$demo@open" => fn($a) => $staffOn($a, $demo),
    "$demo.tab-skill" => fn($a) => $a->col('skill_permission'),
    "$demo.students@open" => fn($a) => $staffOn($a, $demo),
    "$demo.students.remove" => fn($a) => $staffOn($a, $demo) && $vc($a),
    "$demo.movies" => fn($a) => $vc($a),
    "$demo.docs" => fn($a) => $vc($a),
    "$demo.quiz@open" => fn($a) => $staffOn($a, $demo),
    "$demo.quiz.manage" => fn($a) => $tq($a),
    "$demo.quiz.export" => fn($a) => $tq($a),
    'courses.main.archive@open' => fn($a) => $vc($a) && $staffOn($a, 'courses.main.archive'),
    'courses.main.archive.tab-skill' => fn($a) => $a->col('skill_permission'),
    'courses.main.archive.tab-olympiad' => fn($a) => $a->col('olympiad_permission'),
    'courses.main.discount' => fn($a) => $a->perm('permision_discount'),

    'courses.handhug.create' => fn($a) => $vc($a) && ($a->tree ? $a->oldSee('courses.handhug.create') : true),
    'courses.handhug.manage@open' => fn($a) => $staffOn($a, 'courses.handhug.manage'),
    'courses.handhug.manage.edit' => fn($a) => $vc($a) && ($a->tree ? $a->old('courses.handhug.manage.edit') || $a->old('courses.handhug.manage') : true),
    'courses.handhug.archive@open' => fn($a) => $staffOn($a, 'courses.handhug.archive'),
    'courses.handhug.groups@open' => fn($a) => $staffOn($a, 'courses.handhug.manage') || $staffOn($a, 'courses.handhug.archive'),
    'courses.handhug.groups.manage' => fn($a) => ($staffOn($a, 'courses.handhug.manage') || $staffOn($a, 'courses.handhug.archive')) && $a->perm('administrator'),
    'courses.handhug.groups.members' => fn($a) => $staffOn($a, 'courses.handhug.manage') || $staffOn($a, 'courses.handhug.archive'),
    'courses.handhug.list@open' => fn($a) => $staffOn($a, 'courses.handhug.manage') || $staffOn($a, 'courses.handhug.archive'),
    'courses.handhug.list.all' => fn($a) => $a->role(0, 6, 51, 52),
    'courses.handhug.list.own' => fn($a) => $a->role(1),

    ]
),
];
