<?php
/*
 * The access screen is deliberately built from this same navigation map.
 * A node is a visible menu item, a page reached by that item, or an operation
 * on that page. Do not add generic headings that do not exist in admin-menu.
 */
$registry = [
    ['code'=>'dashboard','label'=>'نمایه','path'=>'admin/index.php','children'=>[
        ['code'=>'dashboard.calendar','label'=>'تقویم نمایه','children'=>[
            ['code'=>'dashboard.calendar.executive','label'=>'رویدادهای تقویم اجرایی'],
            ['code'=>'dashboard.calendar.academic','label'=>'رویدادهای تقویم آموزشی'],
        ]],
        ['code'=>'dashboard.summary','label'=>'خلاصه وضعیت','children'=>[
            ['code'=>'dashboard.summary.overview','label'=>'چشم‌انداز کلی','children'=>[
                ['code'=>'dashboard.summary.overview.manager','label'=>'داشبورد مدیریتی','path'=>'admin/panel/manager.php','children'=>[
                    ['code'=>'dashboard.summary.overview.manager.page-b9857fa208','label'=>'گزارش ثبت‌نام‌های موقت فروش','path'=>'admin/panel/temp-users-sales.php','children'=>[
                        ['code'=>'dashboard.summary.overview.manager.page-b9857fa208.today','label'=>'ثبت‌های امروز','children'=>[
                            ['code'=>'dashboard.summary.overview.manager.page-b9857fa208.today.own','label'=>'فقط ثبت‌های خود اپراتور'],
                            ['code'=>'dashboard.summary.overview.manager.page-b9857fa208.today.all','label'=>'ثبت‌های همهٔ اپراتورها'],
                        ]],
                        ['code'=>'dashboard.summary.overview.manager.page-b9857fa208.period','label'=>'ثبت‌های بازهٔ سال','children'=>[
                            ['code'=>'dashboard.summary.overview.manager.page-b9857fa208.period.own','label'=>'فقط ثبت‌های خود اپراتور'],
                            ['code'=>'dashboard.summary.overview.manager.page-b9857fa208.period.all','label'=>'ثبت‌های همهٔ اپراتورها'],
                        ]],
                    ]],
                    ['code'=>'dashboard.summary.overview.manager.page-25d387505a','label'=>'گزارش ثبت‌نام‌های قطعی فروش','path'=>'admin/panel/permanet-users-sales.php','children'=>[
                        ['code'=>'dashboard.summary.overview.manager.page-25d387505a.today','label'=>'ثبت‌های امروز','children'=>[
                            ['code'=>'dashboard.summary.overview.manager.page-25d387505a.today.own','label'=>'فقط ثبت‌های خود اپراتور'],
                            ['code'=>'dashboard.summary.overview.manager.page-25d387505a.today.all','label'=>'ثبت‌های همهٔ اپراتورها'],
                        ]],
                        ['code'=>'dashboard.summary.overview.manager.page-25d387505a.period','label'=>'ثبت‌های بازهٔ سال','children'=>[
                            ['code'=>'dashboard.summary.overview.manager.page-25d387505a.period.own','label'=>'فقط ثبت‌های خود اپراتور'],
                            ['code'=>'dashboard.summary.overview.manager.page-25d387505a.period.all','label'=>'ثبت‌های همهٔ اپراتورها'],
                        ]],
                    ]],
                    ['code'=>'dashboard.summary.overview.manager.page-68e9102f5d','label'=>'درخواست بازگردانی ثبت‌نام‌های موقت','path'=>'admin/panel/reactivate_experimental.php','children'=>[
                        ['code'=>'dashboard.summary.overview.manager.page-68e9102f5d.own','label'=>'فقط درخواست‌های ثبت‌شده توسط خود اپراتور'],
                        ['code'=>'dashboard.summary.overview.manager.page-68e9102f5d.all','label'=>'درخواست‌های همهٔ اپراتورها'],
                    ]],
                ]],
                ['code'=>'dashboard.summary.overview.desktop','label'=>'میزکار من','path'=>'admin/panel/desktop.php','children'=>[
                    ['code'=>'dashboard.summary.overview.desktop.sales-temporary','label'=>'ثبت‌نام‌های موقت خود اپراتور','path'=>'admin/panel/temp-users-sales.php'],
                    ['code'=>'dashboard.summary.overview.desktop.sales-permanent','label'=>'ثبت‌نام‌های قطعی خود اپراتور','path'=>'admin/panel/permanet-users-sales.php'],
                    ['code'=>'dashboard.summary.overview.desktop.seller-users','label'=>'فهرست ثبت‌نام‌های فروش به تفکیک اپراتور','path'=>'admin/panel/myusers-seller.php','children'=>[
                        ['code'=>'dashboard.summary.overview.desktop.seller-users.own','label'=>'فقط ثبت‌نام‌های خود اپراتور'],
                        ['code'=>'dashboard.summary.overview.desktop.seller-users.others','label'=>'ثبت‌نام‌های اپراتورهای دیگر'],
                    ]],
                    ['code'=>'dashboard.summary.overview.desktop.page-8477ff94be','label'=>'پیگیری فروش ثبت‌نام‌ها','path'=>'admin/panel/resell-students.php','children'=>[
                        ['code'=>'dashboard.summary.overview.desktop.page-8477ff94be.own','label'=>'فقط پیگیری‌های خود اپراتور'],
                        ['code'=>'dashboard.summary.overview.desktop.page-8477ff94be.all','label'=>'پیگیری‌های همهٔ اپراتورها'],
                    ]],
                    ['code'=>'dashboard.summary.overview.desktop.page-1938484300','label'=>'اعلان‌های اپراتور','path'=>'admin/notifications/operator_notif.php','children'=>[
                        ['code'=>'dashboard.summary.overview.desktop.page-1938484300.page-86bedc87c2','label'=>'درخواست‌های مربوط به ثبت‌نام جدید','path'=>'admin/notifications/new_student_tickets.php','children'=>[
                            ['code'=>'dashboard.summary.overview.desktop.page-1938484300.page-86bedc87c2.own','label'=>'درخواست‌های ثبت‌شده یا ارجاع‌شده به خود اپراتور'],
                            ['code'=>'dashboard.summary.overview.desktop.page-1938484300.page-86bedc87c2.all','label'=>'درخواست‌های همهٔ اپراتورها'],
                        ]],
                    ]],
                ]],
            ]],
            ['code'=>'dashboard.summary.notifications','label'=>'نمایش دکمهٔ اعلانات (هشدار و چشمک‌زن)'],
            ['code'=>'dashboard.summary.academic-archive','label'=>'نمایش دکمهٔ بایگانی سال‌های تحصیلی','path'=>'admin/select-academic-year.php','auto_scan'=>false],
            ['code'=>'dashboard.summary.cards','label'=>'کارت‌های خلاصه وضعیت','children'=>[
                ['code'=>'dashboard.summary.cards.courses','label'=>'پایه‌های در حال اجرا','labels'=>['institute'=>'دوره‌های برگزارشده'],'path'=>'admin/panel/course_detail.php'],
                ['code'=>'dashboard.summary.cards.staff','label'=>'همکاران','path'=>'admin/panel/staff_management.php'],
                ['code'=>'dashboard.summary.cards.students','label'=>'دانش‌آموزان','labels'=>['institute'=>'فراگیران کل'],'path'=>'admin/panel/academic_year_students.php'],
                ['code'=>'dashboard.summary.cards.achievements','label'=>'افتخارآفرینان کل','modes'=>['institute']],
                ['code'=>'dashboard.summary.cards.classes','label'=>'کلاس‌های برگزارشده'],
                ['code'=>'dashboard.summary.cards.quizzes','label'=>'آزمون‌های برگزارشده'],
                ['code'=>'dashboard.summary.cards.messages','label'=>'پیامک‌های ارسالی امسال','modes'=>['school']],
                ['code'=>'dashboard.summary.cards.learners-current','label'=>'فراگیران','modes'=>['institute']],
                ['code'=>'dashboard.summary.cards.announcements','label'=>'اطلاع‌رسانی‌ها','modes'=>['institute']],
            ]],
        ]],
        ['code'=>'dashboard.exam-system','label'=>'ورود به سامانه آزمون'],
        ['code'=>'dashboard.online-events','label'=>'ورود به سامانه برگزاری رویدادهای آنلاین'],
        ['code'=>'dashboard.notice-board','label'=>'تابلو اعلانات'],
        ['code'=>'dashboard.news-board','label'=>'تابلو اخبار'],
    ]],
    ['code'=>'public-relations','label'=>'روابط عمومی','legacy'=>'permision_viewcall','children'=>[
        ['code'=>'public-relations.safe-call','label'=>'تماس امن','path'=>'admin/Tel/safe_call.php','children'=>[
            /* These four cards are siblings on safe_call.php.  A card reached
               from this page must never become the parent of another card. */
            ['code'=>'public-relations.safe-call.manage','label'=>'مدیریت کل تماس امن','legacy'=>'permision_managecall','path'=>'admin/Tel/managecall.php','auto_scan'=>false,'children'=>[
                ['code'=>'public-relations.safe-call.manage.status-lists','label'=>'فهرست تماس‌ها بر اساس وضعیت','children'=>[
                    ['code'=>'public-relations.safe-call.manage.status-lists.uncalled','label'=>'تماس‌های گرفته‌نشده'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.unanswered','label'=>'عدم پاسخ'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.finished','label'=>'تماس‌های خاتمه‌یافته'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.duplicate','label'=>'مخاطبین تکراری'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.callback','label'=>'نیاز به تماس مجدد'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.wrong','label'=>'شماره‌های اشتباه'],
                    ['code'=>'public-relations.safe-call.manage.status-lists.all','label'=>'همه مخاطبین من'],
                ]],
                ['code'=>'public-relations.safe-call.manage.groups','label'=>'مدیریت گروه‌های تماس','children'=>[
                    ['code'=>'public-relations.safe-call.manage.groups.create','label'=>'ایجاد گروه تماس جدید'],
                    ['code'=>'public-relations.safe-call.manage.groups.edit','label'=>'ویرایش گروه تماس'],
                    ['code'=>'public-relations.safe-call.manage.groups.archive','label'=>'مشاهده آرشیو گروه‌ها','path'=>'admin/Tel/tel-group-archive.php'],
                    ['code'=>'public-relations.safe-call.manage.groups.delete','label'=>'حذف گروه تماس'],
                ]],
                ['code'=>'public-relations.safe-call.manage.import','label'=>'افزودن و ورود فایل مخاطبان'],
                ['code'=>'public-relations.safe-call.manage.total-statistics','label'=>'آمار کلی تماس‌ها','children'=>[
                    ['code'=>'public-relations.safe-call.manage.total-statistics.export','label'=>'خروجی اکسل گزارش گروه'],
                    ['code'=>'public-relations.safe-call.manage.total-statistics.delete','label'=>'حذف گروه و گزارش‌های آن'],
                ]],
                ['code'=>'public-relations.safe-call.manage.operator-statistics','label'=>'آمار انفرادی تماس‌ها','children'=>[
                    ['code'=>'public-relations.safe-call.manage.operator-statistics.breakdown','label'=>'آمار اپراتور به تفکیک گروه'],
                    ['code'=>'public-relations.safe-call.manage.operator-statistics.export','label'=>'خروجی اکسل اپراتور'],
                ]],
                ['code'=>'public-relations.safe-call.manage.contacts','label'=>'مدیریت و گزارش مخاطبان','children'=>[
                    ['code'=>'public-relations.safe-call.manage.contacts.view','label'=>'مشاهده جزئیات مخاطب'],
                    ['code'=>'public-relations.safe-call.manage.contacts.report','label'=>'مشاهده گزارش مخاطب'],
                    ['code'=>'public-relations.safe-call.manage.contacts.edit','label'=>'ویرایش جزئیات مخاطب'],
                    ['code'=>'public-relations.safe-call.manage.contacts.delete','label'=>'حذف مخاطب'],
                ]],
                ['code'=>'public-relations.safe-call.manage.phone-search','label'=>'جست‌وجوی تلفن و بررسی مخاطب'],
            ]],
            ['code'=>'public-relations.safe-call.exchange','label'=>'مرکز تبادل تماس','legacy'=>'permision_managecall','path'=>'admin/Tel/hamtel.php'],
            ['code'=>'public-relations.safe-call.assignment','label'=>'تخصیص شماره به کارشناس','legacy'=>'permision_managecall','path'=>'admin/Tel/assign-phone.php'],
            ['code'=>'public-relations.safe-call.reports','label'=>'گزارش تماس و فایل صوتی','legacy'=>'reportcall_permission','path'=>'admin/Tel/reportcall.php','children'=>[
                ['code'=>'public-relations.safe-call.reports.search','label'=>'جست‌وجو و فیلتر گزارش تماس'],
                ['code'=>'public-relations.safe-call.reports.download','label'=>'دانلود فایل صوتی تماس'],
            ]],
            ['code'=>'public-relations.safe-call.autodialer','label'=>'تماس خودکار (Auto Dialer)','legacy'=>'autodialer','children'=>[
                ['code'=>'public-relations.safe-call.autodialer.contacts','label'=>'مدیریت مخاطب','path'=>'admin/autodialer/contact-management.php'],
                ['code'=>'public-relations.safe-call.autodialer.blacklist','label'=>'لیست سیاه','path'=>'admin/autodialer/autodialer-black-list.php'],
                ['code'=>'public-relations.safe-call.autodialer.groups','label'=>'مدیریت گروه مخاطب','path'=>'admin/autodialer/group-management.php'],
                ['code'=>'public-relations.safe-call.autodialer.voices','label'=>'مدیریت فایل صوتی','path'=>'admin/autodialer/voice-management.php'],
                ['code'=>'public-relations.safe-call.autodialer.campaigns','label'=>'مدیریت کمپین','path'=>'admin/autodialer/campaign-management.php'],
                ['code'=>'public-relations.safe-call.autodialer.reports','label'=>'گزارش تماس خودکار','path'=>'admin/autodialer/autodialer-log-report.php'],
            ]],
        ]],
        ['code'=>'public-relations.safe-sms','label'=>'پیامک امن','path'=>'admin/Tel/safe_sms.php','children'=>[
            ['code'=>'public-relations.safe-sms.search','label'=>'جست‌وجو و گزارش پیامک‌ها'],
            ['code'=>'public-relations.safe-sms.unread','label'=>'پیامک‌های خوانده‌نشده','path'=>'admin/notifications/unread_sms.php'],
            ['code'=>'public-relations.safe-sms.send','label'=>'ارسال پیامک گروهی','path'=>'admin/Tel/bulk_sms.php'],
            ['code'=>'public-relations.safe-sms.export','label'=>'خروجی اکسل پیامک‌ها'],
        ]],
    ]],
    ['code'=>'students','label'=>'دانش‌آموزان','legacy'=>'permision_viewstudent','children'=>[
        ['code'=>'students.definitive','label'=>'دانش‌آموزان قطعی','children'=>[
            ['code'=>'students.definitive.register','label'=>'ثبت‌نام','legacy'=>'permision_addstudent','path'=>'admin/Student/add.php'],
            ['code'=>'students.definitive.enrolment','label'=>'ثبت پایه','legacy'=>'permision_addcourse_student','path'=>'admin/Student/addcourse.php'],
            ['code'=>'students.definitive.edit','label'=>'ویرایش اطلاعات قطعی','legacy'=>'permision_editstudent','path'=>'admin/Student/edit-absolute.php'],
            ['code'=>'students.definitive.my-students','label'=>'دانش‌آموزان من','path'=>'admin/panel/mystudent.php'],
            ['code'=>'students.definitive.my-courses','label'=>'دانش‌آموزان من به تفکیک پایه','path'=>'admin/panel/mypersonalcourse.php'],
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
    ['code'=>'courses','label'=>'مدیریت پایه‌ها','legacy'=>'permision_viewcourse','children'=>[
        ['code'=>'courses.main','label'=>'پایه‌ها','children'=>[
            ['code'=>'courses.main.create','label'=>'ایجاد پایه','legacy'=>'permision_viewcourse','path'=>'admin/course/add-Course.php'],
            ['code'=>'courses.main.demo-create','label'=>'ایجاد دوره آزمایشی','path'=>'admin/democourse/add-democourse.php'],
            ['code'=>'courses.main.active','label'=>'پایه‌های فعال','path'=>'admin/course/Active-course.php','children'=>[
                ['code'=>'courses.main.active.edit','label'=>'ویرایش پایه'],['code'=>'courses.main.active.delete','label'=>'حذف یا تغییر وضعیت','legacy'=>'permision_deletcourse'],
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
    ['code'=>'school-notifications','label'=>'اعلان‌های مدرسه','modes'=>['school'],'path'=>'admin/notifications/school_notifications.php','auto_scan'=>false,'children'=>[
        ['code'=>'school-notifications.categories','label'=>'اعلان‌های پایه‌ها','children'=>[
            ['code'=>'school-notifications.categories.new_student','label'=>'دانش‌آموز جدید'],
            ['code'=>'school-notifications.categories.attendance','label'=>'حضور و غیاب جدید'],
            ['code'=>'school-notifications.categories.exam','label'=>'آزمون و نمره جدید'],
            ['code'=>'school-notifications.categories.discipline','label'=>'مورد انضباطی جدید'],
            ['code'=>'school-notifications.categories.executive_calendar','label'=>'تغییر تقویم اجرایی'],
            ['code'=>'school-notifications.categories.academic_calendar','label'=>'تغییر تقویم آموزشی'],
        ]],
        ['code'=>'school-notifications.current','label'=>'دریافت‌های جدید دسته‌های مجاز'],
        ['code'=>'school-notifications.history','label'=>'آرشیو خوانده‌شده'],
        ['code'=>'school-notifications.open','label'=>'مشاهدهٔ جزئیات اعلان'],
        ['code'=>'school-notifications.mark-read','label'=>'علامت‌گذاری اعلان به‌عنوان خوانده‌شده'],
        ['code'=>'school-notifications.mark-all','label'=>'خواندن همهٔ اعلان‌ها'],
        ['code'=>'school-notifications.information','label'=>'اعلان‌های اطلاع‌رسانی','children'=>[
            ['code'=>'school-notifications.information.rules','label'=>'کنترل قوانین اعلان تماس','path'=>'admin/notifications/notif_rule_management.php'],
            ['code'=>'school-notifications.information.tickets','label'=>'تیکت‌های اعلان تماس','path'=>'admin/notifications/thread_list.php'],
            ['code'=>'school-notifications.information.operator','label'=>'اعلان تماس مشاوران','labels'=>['institute'=>'اعلان تماس پشتیبان‌ها'],'path'=>'admin/notifications/notification_view.php'],
            ['code'=>'school-notifications.information.my-calls','label'=>'اعلان تماس دانش‌آموزان من','path'=>'admin/notifications/notifications.php'],
            ['code'=>'school-notifications.information.email','label'=>'نمایش اعلان ایمیل‌های جدید (به‌زودی)'],
            ['code'=>'school-notifications.information.sms','label'=>'پیامک‌های جدید','path'=>'admin/notifications/unread_sms.php'],
        ]],
        ['code'=>'school-notifications.financial','label'=>'اعلان‌های مالی','children'=>[
            ['code'=>'school-notifications.financial.invoices','label'=>'فاکتورهای منتظر تأیید','path'=>'admin/notifications/verify_by_manager.php'],
            ['code'=>'school-notifications.financial.wallet','label'=>'تأیید شارژ کیف پول','path'=>'admin/Bill/waitingWallet.php'],
        ]],
    ]],
    ['code'=>'tickets','label'=>'تیکت‌ها','legacy'=>'permision_viewsupport','children'=>[
        ['code'=>'tickets.reactivate-requests','label'=>'درخواست‌های بازگردانی ثبت‌نام موقت','path'=>'admin/notifications/reactivate_tickets.php','children'=>[
            ['code'=>'tickets.reactivate-requests.own','label'=>'درخواست‌های ثبت‌شده یا ارجاع‌شده به خود اپراتور'],
            ['code'=>'tickets.reactivate-requests.all','label'=>'درخواست‌های همهٔ اپراتورها'],
        ]],
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
            ['code'=>'finance.pending.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.pending.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.pending.verify','label'=>'تأیید یا رد فاکتور'],['code'=>'finance.pending.receipt','label'=>'مشاهده فیش واریزی'],
        ]],
        ['code'=>'finance.unpaid','label'=>'فاکتورهای پرداخت‌نشده','legacy'=>'permision_notpay','path'=>'admin/Bill/factor-notpay.php','children'=>[
            ['code'=>'finance.unpaid.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.unpaid.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.unpaid.installment-create','label'=>'ایجاد پروندهٔ قسط برای فاکتور پرداخت‌نشده'],
        ]],
        ['code'=>'finance.returned','label'=>'فاکتورهای مستردشده','legacy'=>'permision_notpay','path'=>'admin/Bill/returned.php','children'=>[
            ['code'=>'finance.returned.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.returned.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.returned.manage','label'=>'ثبت و تأیید واریزی یا ویرایش فاکتور'],
        ]],
        ['code'=>'finance.wallet','label'=>'شارژهای کیف پول منتظر تأیید','legacy'=>'permision_actwallet','modes'=>['institute'],'path'=>'admin/Bill/waitingWallet.php'],
        ['code'=>'finance.cards','label'=>'مدیریت شماره کارت‌های بانکی','path'=>'admin/setting/credit-card.php','children'=>[
            ['code'=>'finance.cards.create','label'=>'افزودن شماره کارت'],
            ['code'=>'finance.cards.edit','label'=>'ویرایش شماره کارت'],
            ['code'=>'finance.cards.delete','label'=>'حذف شماره کارت'],
        ]],
        ['code'=>'finance.statement','label'=>'صورتحساب','legacy'=>'permision_bill','path'=>'admin/Bill/SearchBillView.php','children'=>[
            ['code'=>'finance.statement.own','label'=>'فقط فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.statement.all','label'=>'فاکتورهای ثبت‌شده توسط همهٔ ایجادکنندگان'],
        ]],
        ['code'=>'finance.advanced-report','label'=>'گزارش مالی پیشرفته','legacy'=>'permision_bill','path'=>'admin/Bill/advanced-report.php','children'=>[
            ['code'=>'finance.advanced-report.own','label'=>'فقط گزارش فاکتورهای ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.advanced-report.all','label'=>'گزارش فاکتورهای همهٔ ایجادکنندگان'],
        ]],
        ['code'=>'finance.installments','label'=>'موارد اقساطی','legacy'=>'installment_permission','path'=>'admin/Bill/payment.php','children'=>[
            ['code'=>'finance.installments.own','label'=>'فقط پرونده‌های قسط ثبت‌شده توسط خود کاربر'],
            ['code'=>'finance.installments.all','label'=>'پرونده‌های قسط ثبت‌شده توسط همهٔ ایجادکنندگان'],
            ['code'=>'finance.installments.edit','label'=>'ویرایش یا حذف پروندهٔ قسط'],
            ['code'=>'finance.installments.details','label'=>'مشاهدهٔ جزئیات و اقساط هر پرونده','path'=>'admin/Bill/paymentinstallments.php'],
        ]],
        ['code'=>'finance.monthly-payments','label'=>'واریز ماهانه همکاران','legacy'=>'permission_monthly_payment','path'=>'admin/finance/monthly-payments.php'],
        ['code'=>'finance.staff-documents','label'=>'مستندات همکاران','legacy'=>'permission_staff_documents','path'=>'admin/employ/staff-documents.php'],
        ['code'=>'finance.insurance','label'=>'بیمه افراد','legacy'=>'permission_insurance','path'=>'admin/employ/insurance.php'],
    ]],
];


// Both entry pages use the same sections and operations. Keep their grants separate.
$copyNotificationNode = static function (array $node) use (&$copyNotificationNode): array {
    $node['code'] = str_replace('school-notifications', 'institute-notifications', $node['code']);
    if (isset($node['children'])) $node['children'] = array_map($copyNotificationNode, $node['children']);
    return $node;
};
foreach ($registry as $position => $node) {
    if ($node['code'] !== 'school-notifications') continue;
    $instituteNotifications = $copyNotificationNode($node);
    $instituteNotifications['label'] = 'اعلان‌های آموزشگاه';
    $instituteNotifications['modes'] = ['institute'];
    $instituteNotifications['path'] = 'admin/notifications/institute_notifications.php';
    array_splice($registry, $position + 1, 0, [$instituteNotifications]);
    break;
}
return $registry;
