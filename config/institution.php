<?php
/**
 * پیکربندی نوع مؤسسه
 * ---------------------------------------------------------------------------
 * سامانه دو حالت دارد:
 *     school    → مدرسه   (رفتار فعلی و پیش‌فرض)
 *     institute → آموزشگاه / دانشگاه
 *
 * فقط «لایهٔ نمایش» بین این دو فرق می‌کند. نام ستون‌های دیتابیس، نام متغیرها و
 * منطق کوئری‌ها در هر دو حالت یکی است و نباید تغییر کند.
 *
 * این فایل از dbconfig.php و بلافاصله بعد از ساخته شدن $conn لود می‌شود.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

/** مسیر فایل‌های زبان */
if (!defined('LANG_PATH')) {
    define('LANG_PATH', BASE_PATH . '/language');
}

/**
 * برای قفل کردن دستی نوع مؤسسه (مثلاً در محیط تست) این ثابت را پیش از لود شدن
 * این فایل تعریف کنید؛ آن وقت دیتابیس خوانده نمی‌شود.
 *
 *     define('INST_TYPE_OVERRIDE', 'institute');
 */

/* ------------------------------------------------------------------------- */
/*  تشخیص نوع مؤسسه                                                          */
/* ------------------------------------------------------------------------- */

if (!function_exists('inst_type')) {
    /**
     * نوع مؤسسهٔ فعلی: 'school' یا 'institute'
     *
     * اگر ستون institution_type هنوز به setting_public اضافه نشده باشد یا
     * دیتابیس در دسترس نباشد، حالت 'school' برگردانده می‌شود تا سامانه دقیقاً
     * مثل قبل کار کند.
     */
    function inst_type()
    {
        static $type = null;
        if ($type !== null) {
            return $type;
        }

        if (defined('INST_TYPE_OVERRIDE')) {
            $type = INST_TYPE_OVERRIDE === 'institute' ? 'institute' : 'school';
            return $type;
        }

        $type = 'school';

        global $conn;
        if ($conn instanceof PDO) {
            try {
                $st  = $conn->query('SELECT institution_type FROM setting_public ORDER BY id LIMIT 1');
                $val = $st ? $st->fetchColumn() : false;
                if ($val === 'institute') {
                    $type = 'institute';
                }
            } catch (PDOException $e) {
                // ستون هنوز ساخته نشده یا دیتابیس در دسترس نیست → حالت مدرسه
            }
        }

        return $type;
    }
}

if (!function_exists('inst_is')) {
    /** آیا سامانه در حالت داده شده است؟  inst_is('institute') */
    function inst_is($type)
    {
        return inst_type() === $type;
    }
}

/* ------------------------------------------------------------------------- */
/*  قابلیت‌هایی که بین دو حالت فرق می‌کنند                                    */
/* ------------------------------------------------------------------------- */

if (!function_exists('inst_features')) {
    /**
     * تفاوت‌های رفتاری (نه واژگانی) دو حالت.
     * هر ماژولی که در یک حالت اصلاً وجود ندارد، اینجا خاموش می‌شود.
     */
    function inst_features()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $all = [
            'school' => [
                'parent_guardian'  => true,  // ماژول ولی دانش‌آموز
                'report_card'      => true,  // کارنامه
                'discipline'       => true,  // نمرهٔ انضباط
                'fixed_grade_enum' => true,  // پایهٔ ۱ تا ۱۲ به صورت لیست ثابت
                'academic_year'    => true,  // سال تحصیلی (در مقابل ترم)
                'shift'            => true,  // شیفت صبح / بعدازظهر
            ],
            'institute' => [
                'parent_guardian'  => false, // فراگیر معمولاً بزرگسال است
                'report_card'      => false,
                'discipline'       => false,
                'fixed_grade_enum' => false, // دوره‌ها آزاد تعریف می‌شوند
                'academic_year'    => false, // به جایش ترم
                'shift'            => false,
            ],
        ];

        $map = $all[inst_type()];
        return $map;
    }
}

if (!function_exists('inst_feature')) {
    /** آیا این قابلیت در حالت فعلی روشن است؟  inst_feature('report_card') */
    function inst_feature($name)
    {
        $f = inst_features();
        return isset($f[$name]) ? (bool)$f[$name] : false;
    }
}

/* ------------------------------------------------------------------------- */
/*  واژه‌نامه                                                                 */
/* ------------------------------------------------------------------------- */

if (!function_exists('inst_terms')) {
    /** کل واژه‌نامهٔ حالت فعلی به صورت آرایه */
    function inst_terms()
    {
        static $terms = null;
        if ($terms !== null) {
            return $terms;
        }

        $all   = require LANG_PATH . '/terms.fa.php';
        $terms = isset($all[inst_type()]) ? $all[inst_type()] : $all['school'];
        return $terms;
    }
}

if (!function_exists('t')) {
    /**
     * یک واژه از واژه‌نامه.
     *
     *     t('student')      → دانش‌آموز / فراگیر
     *     t('grade_pl')     → پایه‌ها / دوره‌ها
     *     t('grade_ez')     → پایهٔ / دورهٔ
     *
     * اگر کلید نبود، خودِ کلید برگردانده می‌شود تا صفحه خالی نماند و جای خالی
     * توی UI پیدا باشد.
     */
    function t($key, $default = null)
    {
        $terms = inst_terms();
        if (isset($terms[$key])) {
            return $terms[$key];
        }
        return $default !== null ? $default : $key;
    }
}

if (!function_exists('te')) {
    /** مثل t() ولی برای چاپ مستقیم داخل HTML (escape شده) */
    function te($key, $default = null)
    {
        return htmlspecialchars(t($key, $default), ENT_QUOTES, 'UTF-8');
    }
}

/* ------------------------------------------------------------------------- */
/*  جمله‌ها                                                                   */
/* ------------------------------------------------------------------------- */

if (!function_exists('inst_messages')) {
    /** کل جمله‌های حالت فعلی به صورت آرایه */
    function inst_messages()
    {
        static $msgs = null;
        if ($msgs !== null) {
            return $msgs;
        }

        $all  = require LANG_PATH . '/messages.fa.php';
        $msgs = isset($all[inst_type()]) ? $all[inst_type()] : $all['school'];
        return $msgs;
    }
}

if (!function_exists('m')) {
    /**
     * یک جملهٔ کامل.
     *
     *     m('grade.access_enabled')
     *     m('student.not_found', [':name' => $fullname])
     *
     * هرگز جمله را از کنار هم گذاشتن t() ها نسازید — کسرهٔ اضافه و جمع در دو
     * حالت فرق می‌کند و نتیجه غلط می‌شود.
     */
    function m($key, array $vars = [])
    {
        $msgs = inst_messages();
        $out  = isset($msgs[$key]) ? $msgs[$key] : $key;

        if (!empty($vars)) {
            $out = strtr($out, $vars);
        }
        return $out;
    }
}

/* ------------------------------------------------------------------------- */
/*  در اختیار گذاشتن واژه‌نامه برای جاوااسکریپت                               */
/* ------------------------------------------------------------------------- */

if (!function_exists('inst_terms_json')) {
    /**
     * برای تزریق در header.php:
     *     window.TERMS     = <?= inst_terms_json() ?>
     *     window.INST_TYPE = "<?= inst_type() ?>"
     */
    function inst_terms_json()
    {
        return json_encode(inst_terms(), JSON_UNESCAPED_UNICODE);
    }
}

/* ثابت‌ها برای جاهایی که فراخوانی تابع ممکن نیست */
if (!defined('INST_TYPE')) {
    define('INST_TYPE', inst_type());
}
