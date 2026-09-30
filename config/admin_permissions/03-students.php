<?php
/* فراگیران: registration, enrolment, editing, the student lists and the student record. */

$vs = static fn(AdminLegacyAccount $a): bool => $a->perm('permision_viewstudent');
$d = 'students.definitive';
$t = 'students.temporary';
$r = 'students.record';
$editChildren = static fn(string $base): array => [
    ['code'=>"$base.account",'label'=>'ویرایش اطلاعات حساب و مشخصات'],
    ['code'=>"$base.activity",'label'=>'فعال یا غیرفعال کردن حساب'],
    ['code'=>"$base.picture",'label'=>'تغییر عکس'],
    ['code'=>"$base.parents",'label'=>'اطلاعات والدین'],
    ['code'=>"$base.house",'label'=>'اطلاعات محل زندگی و مدرسه'],
    ['code'=>"$base.bank",'label'=>'اطلاعات بانکی (شبا و کارت)'],
    ['code'=>"$base.resend-password",'label'=>'ارسال دوبارهٔ رمز با پیامک'],
];
$listChildren = static fn(string $base): array => [
    ['code'=>"$base.toggle",'label'=>'فعال یا غیرفعال کردن ثبت‌نام در پایه'],
    ['code'=>"$base.call",'label'=>'تماس با فراگیر یا والدین'],
];

return [
'nodes' => [
    ['code'=>'students','label'=>'دانش‌آموزان','labels'=>['institute'=>'فراگیران'],'children'=>[
        ['code'=>'students.definitive','label'=>'دانش‌آموزان قطعی','labels'=>['institute'=>'فراگیران قطعی'],'children'=>[
            ['code'=>'students.definitive.register','label'=>'ثبت‌نام','path'=>'admin/Student/add.php','children'=>[
                ['code'=>'students.definitive.register.bulk','label'=>'ثبت‌نام دسته‌جمعی با فایل','path'=>'admin/Student/add-group.php'],
            ]],
            ['code'=>'students.definitive.enrolment','label'=>'ثبت پایه','labels'=>['institute'=>'ثبت دوره'],'path'=>'admin/Student/addcourse.php','paths'=>['admin/Student/get_classes.php'],'children'=>[
                ['code'=>'students.definitive.enrolment.bulk','label'=>'ایجاد پایه برای گروهی از دانش‌آموزان','labels'=>['institute'=>'ایجاد دوره برای گروهی از فراگیران']],
            ]],
            ['code'=>'students.definitive.edit','label'=>'ویرایش اطلاعات قطعی','labels'=>['school'=>'ویرایش اطلاعات'],'path'=>'admin/Student/edit-absolute.php','paths'=>['admin/Student/dossier.php'],'children'=>$editChildren('students.definitive.edit')],
            ['code'=>'students.definitive.my-students','label'=>'دانش‌آموزان من','labels'=>['institute'=>'فراگیران من'],'path'=>'admin/panel/mystudent.php'],
            ['code'=>'students.definitive.my-courses','label'=>'فراگیران نفر دوره','modes'=>['institute'],'path'=>'admin/panel/mypersonalcourse.php'],
            ['code'=>'students.definitive.cancelled-support','label'=>'لغو پشتیبانی‌شده‌های من','path'=>'admin/panel/canceled_student_list.php'],
            ['code'=>'students.definitive.suspended-support','label'=>'تعلیقی‌های من','path'=>'admin/panel/suspension_list.php'],
            ['code'=>'students.definitive.course-report','label'=>'گزارش تفکیکی','path'=>'admin/Student/registration-Course-absolute.php','children'=>$listChildren('students.definitive.course-report')],
            ['code'=>'students.definitive.inactive','label'=>'غیرفعال‌ها','path'=>'admin/Student/unactive-student.php','children'=>[
                ['code'=>'students.definitive.inactive.activate','label'=>'فعال کردن دوباره'],
            ]],
            ['code'=>'students.definitive.cancelled','label'=>'انصرافی‌ها','path'=>'admin/Student/canceled-student.php'],
            ['code'=>'students.definitive.suspended','label'=>'تعلیقی‌ها','path'=>'admin/Student/suspended-student.php','children'=>[
                ['code'=>'students.definitive.suspended.unsuspend','label'=>'رفع تعلیق'],
                ['code'=>'students.definitive.suspended.review','label'=>'تأیید یا رد درخواست تعلیق'],
                ['code'=>'students.definitive.suspended.refer','label'=>'ارجاع درخواست به پشتیبان'],
                ['code'=>'students.definitive.suspended.history','label'=>'تاریخچهٔ تعلیق‌ها','path'=>'admin/Student/suspension_history.php'],
            ]],
            ['code'=>'students.definitive.cancelled-operator','label'=>'لغو پشتیبانی‌شده‌ها','path'=>'admin/Student/canceled-op-student.php','children'=>[
                ['code'=>'students.definitive.cancelled-operator.activate','label'=>'فعال کردن دوبارهٔ پشتیبانی'],
                ['code'=>'students.definitive.cancelled-operator.review','label'=>'تأیید یا رد درخواست لغو پشتیبانی'],
                ['code'=>'students.definitive.cancelled-operator.history','label'=>'تاریخچهٔ لغو پشتیبانی','path'=>'admin/Student/cancel_operator_history.php'],
            ]],
        ]],
        ['code'=>'students.temporary','label'=>'دانش‌آموزان آزمایشی','labels'=>['institute'=>'فراگیران موقت'],'children'=>[
            ['code'=>'students.temporary.register','label'=>'ثبت‌نام موقت','modes'=>['institute'],'path'=>'admin/Student/experimental-add.php'],
            ['code'=>'students.temporary.enrolment','label'=>'ثبت دانش‌آموز در دوره آزمایشی','labels'=>['school'=>'ثبت فوق برنامه'],'path'=>'admin/democourse/addstudent.php','children'=>[
                ['code'=>'students.temporary.enrolment.register-student','label'=>'ثبت‌نام موقت دانش‌آموز برای فوق برنامه','modes'=>['school']],
                ['code'=>'students.temporary.enrolment.excel','label'=>'ثبت با فایل اکسل'],
            ]],
            ['code'=>'students.temporary.edit','label'=>'ویرایش اطلاعات موقت','modes'=>['institute'],'path'=>'admin/Student/edit-experimental.php','children'=>$editChildren('students.temporary.edit')],
            ['code'=>'students.temporary.report','label'=>'گزارش تفکیکی','labels'=>['school'=>'گزارش تفکیکی فوق برنامه'],'path'=>'admin/Student/registration-Course-experimental.php','children'=>$listChildren('students.temporary.report')],
        ]],
        ['code'=>'students.record','label'=>'پروندهٔ دانش‌آموز','labels'=>['institute'=>'پروندهٔ فراگیر'],'children'=>[
            ['code'=>'students.record.films','label'=>'گزارش مشاهدهٔ فیلم‌ها','path'=>'admin/Student/log-viewFilm.php'],
            ['code'=>'students.record.attendance','label'=>'گزارش حضور و غیاب','path'=>'admin/Student/log-viewattendance.php'],
            ['code'=>'students.record.docs','label'=>'گزارش مشاهدهٔ جزوه‌ها','path'=>'admin/Student/log-viewdoc.php'],
            ['code'=>'students.record.homework','label'=>'گزارش تکالیف','path'=>'admin/Student/log-viewhomework.php','children'=>[
                ['code'=>'students.record.homework.all-classes','label'=>'تکالیف همهٔ کلاس‌های پایه'],
            ]],
            ['code'=>'students.record.quizzes','label'=>'گزارش آزمون‌ها','path'=>'admin/Student/log-viewquiz.php'],
            ['code'=>'students.record.history','label'=>'تاریخچه و پروندهٔ پشتیبانی','path'=>'admin/Student/student-history.php','children'=>[
                ['code'=>'students.record.history.manage','label'=>'ثبت و حذف وضعیت‌ها، حذف پیام و ویرایش پاسخ آزمون'],
                ['code'=>'students.record.history.staff-messages','label'=>'مشاهدهٔ پیام‌های کارشناسان دیگر'],
            ]],
            ['code'=>'students.record.profile','label'=>'مشاهدهٔ مشخصات','path'=>'admin/Student/viewProfile.php'],
            ['code'=>'students.record.class-profile','label'=>'پروندهٔ دانش‌آموز در کلاس','path'=>'admin/Student/student_profile.php'],
        ]],
        ['code'=>'students.export','label'=>'خروجی اکسل فهرست دانش‌آموزان','labels'=>['institute'=>'خروجی اکسل فهرست فراگیران'],'path'=>'admin/Student/export-student.php'],
    ]],
],
'convert' => [
    "$d.register@open" => fn($a) => $vs($a) && $a->perm('permision_addstudent'),
    "$d.register.bulk" => fn($a) => $vs($a) && $a->can("$d.register.bulk", ['permision_addstudent', 'excel_permission']),
    "$d.enrolment@open" => fn($a) => $a->perm('permision_addcourse_student'),
    "$d.enrolment.bulk" => fn($a) => $a->perm('permision_addcourse_student') && $a->can("$d.enrolment.bulk", ['permision_addcourse_student', 'excel_permission']),
    "$d.edit" => fn($a) => $vs($a) && $a->perm('permision_editstudent') && ($a->tree ? $a->oldSee("$d.edit") : true),
    "$d.my-students" => fn($a) => $a->menu("$d.my-students", $a->role(1)) ,
    "$d.my-courses" => fn($a) => $a->menu("$d.my-courses", $a->role(1)),
    "$d.cancelled-support" => fn($a) => $a->menu("$d.cancelled-support", $a->role(1)),
    "$d.suspended-support" => fn($a) => $a->menu("$d.suspended-support", $a->role(1)),
    "$d.course-report" => fn($a) => $a->perm('permision_addcourse_student') && ($a->tree ? $a->oldSee("$d.course-report") : true),
    "$d.inactive" => fn($a) => $a->perm('permision_editstudent') && ($a->tree ? $a->oldSee("$d.inactive") : true),
    "$d.cancelled" => fn($a) => $a->perm('permision_editstudent') && ($a->tree ? $a->oldSee("$d.cancelled") : $a->perm('permision_reportstudent')),
    "$d.suspended@open" => fn($a) => $a->perm('administrator') && ($a->tree ? $a->oldSee("$d.suspended") : true),
    "$d.suspended.refer" => fn($a) => false, // manager only
    "$d.cancelled-operator" => fn($a) => $a->perm('administrator') && ($a->tree ? $a->oldSee("$d.cancelled-operator") : true),
    "$t.register" => fn($a) => $vs($a) && $a->perm('permision_addstudent') && ($a->tree ? $a->oldSee("$t.register") : true),
    "$t.enrolment@open" => fn($a) => $a->perm('permision_addcourse_student') && ($a->tree ? $a->oldSee("$t.enrolment") : true),
    "$t.enrolment.register-student" => fn($a) => $a->perm('permision_addcourse_student') && ($a->tree ? $a->oldSee("$t.enrolment") : true),
    "$t.enrolment.excel" => fn($a) => $a->perm('permision_addcourse_student') && $a->perm('excel_permission'),
    "$t.edit" => fn($a) => $vs($a) && $a->perm('permision_editstudent') && ($a->tree ? $a->oldSee("$t.edit") : true),
    "$t.report" => fn($a) => $a->perm('permision_addcourse_student') && ($a->tree ? $a->oldSee("$t.report") : true),
    "$r.films" => fn($a) => $a->perm('permision_addcourse_student'),
    "$r.attendance" => fn($a) => $a->perm('permision_addcourse_student'),
    "$r.docs" => fn($a) => $a->perm('permision_addcourse_student'),
    "$r.homework@open" => fn($a) => $a->perm('permision_addcourse_student'),
    "$r.homework.all-classes" => fn($a) => $a->perm('permision_addcourse_student') && $a->role(0, 51, 52),
    "$r.quizzes" => fn($a) => $a->perm('permision_addcourse_student'),
    "$r.history@open" => fn($a) => true, // no check before
    "$r.history.manage" => fn($a) => $a->perm('history_permission'),
    "$r.history.staff-messages" => fn($a) => $a->perm('history_permission') || $a->perm('permision_manage_chat'),
    "$r.profile" => fn($a) => $a->perm('permision_editstudent'),
    "$r.class-profile" => fn($a) => $a->teacher('teacher_perm_students'),
    'students.export' => fn($a) => $a->perm('permision_addcourse_student') && $a->perm('permision_reportstudent'),
],
];
