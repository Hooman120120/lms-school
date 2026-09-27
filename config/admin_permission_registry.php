<?php
/*
 * The access screen is deliberately built from this same navigation map.
 * A node is a visible menu item, a page reached by that item, or an operation
 * on that page. Do not add generic headings that do not exist in admin-menu.
 */
return [
    ['code'=>'dashboard','label'=>'نمایه','path'=>'admin/index.php','children'=>[
        ['code'=>'dashboard.summary','label'=>'خلاصه وضعیت','children'=>[
            ['code'=>'dashboard.summary.overview','label'=>'چشم‌انداز کلی','children'=>[
                ['code'=>'dashboard.summary.overview.manager','label'=>'داشبورد مدیریتی','path'=>'admin/panel/manager.php'],
                ['code'=>'dashboard.summary.overview.desktop','label'=>'میزکار من','path'=>'admin/panel/desktop.php'],
            ]],
            ['code'=>'dashboard.summary.notifications','label'=>'اعلان‌ها','path'=>'admin/notifications/school_notifications.php'],
            ['code'=>'dashboard.summary.academic-archive','label'=>'بایگانی سال‌های تحصیلی','path'=>'admin/select-academic-year.php'],
            ['code'=>'dashboard.summary.cards','label'=>'کارت‌های خلاصه وضعیت','children'=>[
                ['code'=>'dashboard.summary.cards.courses','label'=>'پایه‌ها / دوره‌های در حال اجرا','path'=>'admin/panel/course_detail.php'],
                ['code'=>'dashboard.summary.cards.staff','label'=>'همکاران','path'=>'admin/panel/staff_management.php'],
                ['code'=>'dashboard.summary.cards.students','label'=>'دانش‌آموزان / فراگیران','path'=>'admin/panel/academic_year_students.php'],
                ['code'=>'dashboard.summary.cards.achievements','label'=>'افتخارآفرینان'],
                ['code'=>'dashboard.summary.cards.classes','label'=>'کلاس‌های برگزارشده'],
                ['code'=>'dashboard.summary.cards.quizzes','label'=>'آزمون‌های برگزارشده'],
                ['code'=>'dashboard.summary.cards.messages','label'=>'پیامک‌ها / اطلاع‌رسانی‌ها'],
            ]],
        ]],
        ['code'=>'dashboard.exam-system','label'=>'ورود به سامانه آزمون'],
        ['code'=>'dashboard.online-events','label'=>'ورود به سامانه برگزاری رویدادهای آنلاین'],
        ['code'=>'dashboard.notice-board','label'=>'تابلو اعلانات'],
        ['code'=>'dashboard.news-board','label'=>'تابلو اخبار'],
    ]],
    ['code'=>'public-relations','label'=>'روابط عمومی','legacy'=>'permision_viewcall','children'=>[
        ['code'=>'public-relations.safe-call','label'=>'تماس امن','path'=>'admin/Tel/safe_call.php','children'=>[
            ['code'=>'public-relations.safe-call.manage','label'=>'مدیریت کل تماس امن','legacy'=>'permision_managecall','children'=>[
                ['code'=>'public-relations.safe-call.manage.groups','label'=>'گروه‌های تماس','children'=>[
                    ['code'=>'public-relations.safe-call.manage.groups.create','label'=>'ایجاد گروه تماس جدید'],
                    ['code'=>'public-relations.safe-call.manage.groups.edit','label'=>'ویرایش یا حذف گروه تماس'],
                ]],
                ['code'=>'public-relations.safe-call.manage.exchange','label'=>'مرکز تبادل تماس'],
                ['code'=>'public-relations.safe-call.manage.assignment','label'=>'تخصیص شماره و کارشناس'],
            ]],
            ['code'=>'public-relations.safe-call.reports','label'=>'گزارش تماس‌ها و فایل صوتی','legacy'=>'reportcall_permission'],
        ]],
        ['code'=>'public-relations.safe-sms','label'=>'پیامک امن','path'=>'admin/Tel/safe_sms.php','children'=>[
            ['code'=>'public-relations.safe-sms.send','label'=>'ارسال پیامک امن'],
            ['code'=>'public-relations.safe-sms.history','label'=>'گزارش و سوابق پیامک'],
        ]],
    ]],
    ['code'=>'students','label'=>'دانش‌آموزان','legacy'=>'permision_viewstudent','children'=>[
        ['code'=>'students.definitive','label'=>'دانش‌آموزان قطعی','children'=>[
            ['code'=>'students.definitive.register','label'=>'ثبت‌نام','legacy'=>'permision_addstudent','path'=>'admin/Student/add.php'],
            ['code'=>'students.definitive.enrolment','label'=>'ثبت پایه / دوره','legacy'=>'permision_addcourse_student','path'=>'admin/Student/addcourse.php'],
            ['code'=>'students.definitive.edit','label'=>'ویرایش اطلاعات قطعی','legacy'=>'permision_editstudent','path'=>'admin/Student/edit-absolute.php'],
            ['code'=>'students.definitive.my-students','label'=>'دانش‌آموزان من','path'=>'admin/panel/mystudent.php'],
            ['code'=>'students.definitive.my-courses','label'=>'دانش‌آموزان من به تفکیک دوره','path'=>'admin/panel/mypersonalcourse.php'],
            ['code'=>'students.definitive.cancelled-support','label'=>'لغو پشتیبانی‌شده‌ها','path'=>'admin/panel/canceled_student_list.php'],
            ['code'=>'students.definitive.suspended-support','label'=>'تعلیقی‌ها','path'=>'admin/panel/suspension_list.php'],
            ['code'=>'students.definitive.course-report','label'=>'گزارش تفکیکی','legacy'=>'permision_reportstudent','path'=>'admin/Student/registration-Course-absolute.php'],
            ['code'=>'students.definitive.inactive','label'=>'غیرفعال‌ها','legacy'=>'permision_editstudent','path'=>'admin/Student/unactive-student.php'],
            ['code'=>'students.definitive.cancelled','label'=>'انصرافی‌ها','legacy'=>'permision_reportstudent','path'=>'admin/Student/canceled-student.php'],
            ['code'=>'students.definitive.suspended','label'=>'تعلیقی‌ها','legacy'=>'permision_reportstudent','path'=>'admin/Student/suspended-student.php'],
            ['code'=>'students.definitive.cancelled-operator','label'=>'لغو پشتیبانی‌شده‌ها','legacy'=>'permision_reportstudent','path'=>'admin/Student/canceled-op-student.php'],
        ]],
        ['code'=>'students.temporary','label'=>'دانش‌آموزان آزمایشی','children'=>[
            ['code'=>'students.temporary.register','label'=>'ثبت‌نام موقت','legacy'=>'permision_addstudent','path'=>'admin/Student/experimental-add.php'],
            ['code'=>'students.temporary.enrolment','label'=>'ثبت دانش‌آموز در دوره آزمایشی','legacy'=>'permision_addcourse_student','path'=>'admin/democourse/addstudent.php'],
            ['code'=>'students.temporary.edit','label'=>'ویرایش اطلاعات موقت','legacy'=>'permision_editstudent','path'=>'admin/Student/edit-experimental.php'],
            ['code'=>'students.temporary.report','label'=>'گزارش تفکیکی','legacy'=>'permision_reportstudent','path'=>'admin/Student/registration-Course-experimental.php'],
        ]],
    ]],
    ['code'=>'executive','label'=>'امور اجرایی','legacy'=>'administrator','children'=>[
        ['code'=>'executive.calendar','label'=>'تقویم اجرایی','legacy'=>'permission_calendar','path'=>'admin/panel/calendar_panel.php','children'=>[
            ['code'=>'executive.calendar.create','label'=>'ایجاد رویداد'],['code'=>'executive.calendar.edit','label'=>'ویرایش یا حذف رویداد'],
        ]],
        ['code'=>'executive.academic-calendar','label'=>'تقویم آموزشی','path'=>'admin/panel/academic-calendar.php'],
        ['code'=>'executive.daily-notices','label'=>'تابلو اعلان روزانه','legacy'=>'permission_news_notif','path'=>'admin/notifications/daily_notifications.php','children'=>[
            ['code'=>'executive.daily-notices.create','label'=>'ثبت اعلان روزانه'],['code'=>'executive.daily-notices.edit','label'=>'ویرایش یا حذف اعلان روزانه'],
        ]],
        ['code'=>'executive.news','label'=>'تابلو اخبار','legacy'=>'permission_news_notif','path'=>'admin/notifications/news_panel.php','children'=>[
            ['code'=>'executive.news.create','label'=>'ثبت خبر'],['code'=>'executive.news.edit','label'=>'ویرایش یا حذف خبر'],
        ]],
    ]],
    ['code'=>'courses','label'=>'مدیریت پایه‌ها و دوره‌ها','legacy'=>'permision_viewcourse','children'=>[
        ['code'=>'courses.main','label'=>'پایه‌ها / دوره‌ها','children'=>[
            ['code'=>'courses.main.create','label'=>'ایجاد پایه / دوره','legacy'=>'permision_viewcourse','path'=>'admin/course/add-Course.php'],
            ['code'=>'courses.main.demo-create','label'=>'ایجاد دوره آزمایشی','path'=>'admin/democourse/add-democourse.php'],
            ['code'=>'courses.main.active','label'=>'پایه‌ها / دوره‌های فعال','path'=>'admin/course/Active-course.php','children'=>[
                ['code'=>'courses.main.active.edit','label'=>'ویرایش پایه / دوره'],['code'=>'courses.main.active.delete','label'=>'حذف یا تغییر وضعیت','legacy'=>'permision_deletcourse'],
            ]],
            ['code'=>'courses.main.demo-active','label'=>'دوره‌های آزمایشی فعال','path'=>'admin/democourse/list-democourse.php'],
            ['code'=>'courses.main.archive','label'=>'بایگانی سال‌های قبل','legacy'=>'permision_viewcourse','path'=>'admin/course/unactive-course.php'],
            ['code'=>'courses.main.discount','label'=>'ایجاد کد تخفیف','legacy'=>'permision_discount','path'=>'admin/course/add-discount.php'],
        ]],
        ['code'=>'courses.handhug','label'=>'بغل‌دستی‌ها','children'=>[
            ['code'=>'courses.handhug.create','label'=>'ساخت محصول بغل‌دستی','path'=>'admin/handhug/add-handhug.php'],
            ['code'=>'courses.handhug.manage','label'=>'بغل‌دستی‌های فعال','path'=>'admin/handhug/handhug-management.php','children'=>[
                ['code'=>'courses.handhug.manage.edit','label'=>'ویرایش یا تغییر وضعیت محصول'],
            ]],
            ['code'=>'courses.handhug.archive','label'=>'بایگانی سال‌های قبل بغل‌دستی‌ها','path'=>'admin/handhug/disabled-handhugs.php'],
        ]],
    ]],
    ['code'=>'school-notifications','label'=>'اعلان‌های مدرسه','path'=>'admin/notifications/school_notifications.php','children'=>[
        ['code'=>'school-notifications.current','label'=>'اعلان‌های جاری'],['code'=>'school-notifications.history','label'=>'اعلان‌های خوانده‌شده و قدیمی'],
    ]],
    ['code'=>'tickets','label'=>'تیکت‌ها','legacy'=>'permision_viewsupport','children'=>[
        ['code'=>'tickets.referred','label'=>'منتظر ارجاع','legacy'=>'permision_refer','path'=>'admin/Tickets/Refer.php'],
        ['code'=>'tickets.inbox','label'=>'صندوق ورودی','path'=>'admin/Tickets/inbox.php','children'=>[
            ['code'=>'tickets.inbox.reply','label'=>'پاسخ به تیکت'],['code'=>'tickets.inbox.status','label'=>'تغییر وضعیت تیکت'],
        ]],
        ['code'=>'tickets.emergency','label'=>'تیکت‌های اورژانسی','path'=>'admin/Tickets/emergency.php'],
        ['code'=>'tickets.open','label'=>'کارتابل تیکت‌های باز','path'=>'admin/Tickets/openTicket.php'],
        ['code'=>'tickets.all','label'=>'کارتابل همه تیکت‌ها','legacy'=>'permision_allticket','path'=>'admin/Tickets/allticket.php'],
    ]],
    ['code'=>'documents','label'=>'مستندات','legacy'=>'permision_viewdocument','children'=>[
        ['code'=>'documents.new','label'=>'تازه‌ها','path'=>'admin/Doc/newdoc.php'],
        ['code'=>'documents.search','label'=>'جست‌وجوی مستندات','path'=>'admin/Doc/searchdoc.php'],
    ]],
    ['code'=>'staff','label'=>'همکاران','legacy'=>'permision_viewemploy','children'=>[
        ['code'=>'staff.directory','label'=>'مشاهده همکاران','legacy'=>'permission_staff_view','path'=>'admin/employ/staff-directory.php'],
        ['code'=>'staff.manage','label'=>'مدیریت همکاران','path'=>'admin/employ/listadmin.php','children'=>[
            ['code'=>'staff.manage.edit','label'=>'ویرایش یا غیرفعال‌سازی همکار'],
        ]],
        ['code'=>'staff.teachers','label'=>'مشاهده اساتید','path'=>'admin/employ/listteacher.php'],
        ['code'=>'staff.create','label'=>'ثبت همکار','path'=>'admin/employ/addemploy.php'],
        ['code'=>'staff.teacher-create','label'=>'ثبت استاد','path'=>'admin/employ/addteacher.php'],
    ]],
    ['code'=>'assigned-tasks','label'=>'کارهای محول‌شده','legacy'=>'permission_assigned_tasks','path'=>'admin/tasks/assigned-tasks.php','children'=>[
        ['code'=>'assigned-tasks.create','label'=>'تعریف و تخصیص کار'],['code'=>'assigned-tasks.status','label'=>'تغییر وضعیت کار'],
    ]],
    ['code'=>'referrers','label'=>'معرفان برون‌سازمانی','legacy'=>'permision_viewcollague','children'=>[
        ['code'=>'referrers.create','label'=>'ثبت معرف','path'=>'admin/Collague/add-collague.php'],
        ['code'=>'referrers.edit','label'=>'ویرایش معرف','path'=>'admin/Collague/edit-collague.php'],
        ['code'=>'referrers.inactive','label'=>'معرفان غیرفعال','path'=>'admin/Collague/unactive-collague.php'],
        ['code'=>'referrers.sub','label'=>'زیرمجموعه معرفان','path'=>'admin/Collague/sub-collague.php'],
    ]],
    ['code'=>'settings','label'=>'تنظیمات','legacy'=>'permision_viewsetting','children'=>[
        ['code'=>'settings.general','label'=>'تنظیمات عمومی','path'=>'admin/setting/setting.php'],
        ['code'=>'settings.levels','label'=>'سطوح دسترسی','path'=>'admin/setting/levels.php'],
        ['code'=>'settings.blacklist','label'=>'بلک‌لیست','path'=>'admin/setting/blacklist.php'],
        ['code'=>'settings.cards','label'=>'افزودن کارت بانکی','path'=>'admin/setting/credit-card.php'],
        ['code'=>'settings.messages','label'=>'پیام‌های آماده','path'=>'admin/setting/ready-message.php'],
    ]],
    ['code'=>'reports','label'=>'گزارش‌ها','legacy'=>'permision_reportstudent','children'=>[
        ['code'=>'reports.students','label'=>'گزارش دانش‌آموزان','path'=>'admin/report/students.php'],
        ['code'=>'reports.staff','label'=>'گزارش همکاران','legacy'=>'permision_reportcollague','path'=>'admin/report/colleague.php'],
    ]],
    ['code'=>'homework-approval','label'=>'تأیید تکالیف','legacy'=>'administrator','path'=>'admin/course/homework_check.php','children'=>[
        ['code'=>'homework-approval.review','label'=>'بررسی و تأیید تکلیف'],
    ]],
    ['code'=>'finance','label'=>'امور مالی','legacy'=>'permision_viewbill','children'=>[
        ['code'=>'finance.pending','label'=>'فاکتورهای منتظر تأیید','legacy'=>'permision_actfactor','path'=>'admin/Bill/active-factor.php','children'=>[
            ['code'=>'finance.pending.verify','label'=>'تأیید یا رد فاکتور'],['code'=>'finance.pending.receipt','label'=>'مشاهده فیش واریزی'],
        ]],
        ['code'=>'finance.unpaid','label'=>'فاکتورهای پرداخت‌نشده','legacy'=>'permision_notpay','path'=>'admin/Bill/factor-notpay.php'],
        ['code'=>'finance.returned','label'=>'فاکتورهای برگشتی','legacy'=>'permision_notpay','path'=>'admin/Bill/returned.php'],
        ['code'=>'finance.wallet','label'=>'شارژهای کیف پول منتظر تأیید','legacy'=>'permision_actwallet','modes'=>['institute'],'path'=>'admin/Bill/waitingWallet.php'],
        ['code'=>'finance.statement','label'=>'صورتحساب','legacy'=>'permision_bill','path'=>'admin/Bill/SearchBillView.php'],
        ['code'=>'finance.advanced-report','label'=>'گزارش مالی پیشرفته','legacy'=>'permision_bill','path'=>'admin/Bill/advanced-report.php'],
        ['code'=>'finance.installments','label'=>'موارد اقساطی','legacy'=>'installment_permission','path'=>'admin/Bill/payment.php'],
        ['code'=>'finance.monthly-payments','label'=>'واریز ماهانه همکاران','legacy'=>'permission_monthly_payment','path'=>'admin/finance/monthly-payments.php'],
        ['code'=>'finance.staff-documents','label'=>'مستندات همکاران','legacy'=>'permission_staff_documents','path'=>'admin/employ/staff-documents.php'],
        ['code'=>'finance.insurance','label'=>'بیمه افراد','legacy'=>'permission_insurance','path'=>'admin/employ/insurance.php'],
    ]],
];
