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

/* ------------------------------------------------------------------------- */
/*  سطوح / پایه‌ها — از جدول institution_levels                               */
/* ------------------------------------------------------------------------- */

if (!function_exists('inst_levels')) {
    /**
     * فهرست سطوح مؤسسهٔ جاری.
     *
     * @param string $kind            level | group | field
     * @param bool   $onlySelectable  فقط مواردی که در فرم ثبت‌نام فراگیر دیده می‌شوند
     * @return array  آرایه‌ای از سطرها (value, slug, label, group_slug, sort_order)
     */
    function inst_levels($kind = 'level', $onlySelectable = false)
    {
        static $cache = [];
        $key = inst_type() . '|' . $kind;
        if (!isset($cache[$key])) {
            $cache[$key] = [];
            global $conn;
            if ($conn instanceof PDO) {
                try {
                    $st = $conn->prepare(
                        'SELECT value, slug, label, group_slug, sort_order, selectable_for_person
                           FROM institution_levels
                          WHERE institution_type = ? AND kind = ? AND is_active = 1
                       ORDER BY sort_order, id'
                    );
                    $st->execute([inst_type(), $kind]);
                    $cache[$key] = $st->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    /* جدول هنوز ساخته نشده — فهرست خالی */
                }
            }
        }
        if (!$onlySelectable) {
            return $cache[$key];
        }
        $out = [];
        foreach ($cache[$key] as $row) {
            if ((int) $row['selectable_for_person'] === 1) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('inst_level_label')) {
    /**
     * برچسب یک مقدار ذخیره‌شده در dbpersonal.paye.
     * اگر مقدار پیدا نشد، خودِ مقدار برگردانده می‌شود تا دادهٔ قدیمی گم نشود.
     */
    function inst_level_label($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        foreach (inst_levels('level') as $row) {
            if ((string) $row['value'] === (string) $value) {
                return $row['label'];
            }
        }
        return (string) $value;
    }
}

if (!function_exists('inst_level_slug')) {
    /** اسلاگ (برچسب کاتالوگی) یک مقدار ذخیره‌شده در dbpersonal.paye. */
    function inst_level_slug($value)
    {
        foreach (inst_levels('level') as $row) {
            if ((string) $row['value'] === (string) $value) {
                return $row['slug'];
            }
        }
        return (string) $value;
    }
}

if (!function_exists('inst_level_options')) {
    /**
     * گزینه‌های <option> فرم ثبت‌نام فراگیر.
     *
     * @param mixed  $selected  مقدار انتخاب‌شده (dbpersonal.paye)
     * @param string $valueBy   value | slug — چه چیزی در value قرار گیرد
     */
    function inst_level_options($selected = null, $valueBy = 'value')
    {
        $html = '';
        foreach (inst_levels('level', true) as $row) {
            $val = $valueBy === 'slug' ? $row['slug'] : $row['value'];
            $sel = ((string) $selected !== '' && (string) $selected === (string) $val) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                   . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</option>' . "
";
        }
        return $html;
    }
}

if (!function_exists('inst_tag_options')) {
    /**
     * گزینه‌های برچسب‌گذاری دوره (codtbl_course.paye): پایه‌ها + دسته‌ها + رشته‌ها.
     * مقدار هر گزینه اسلاگ است، چون همین در ستون paye ذخیره می‌شود.
     *
     * @param array $selected اسلاگ‌های انتخاب‌شده
     */
    function inst_tag_options(array $selected = [])
    {
        $html = '';
        $groups = [
            'level' => t('grade_pl'),
            'group' => t('level'),
            'field' => t('field'),
        ];
        foreach ($groups as $kind => $groupLabel) {
            $rows = inst_levels($kind);
            if (!$rows) {
                continue;
            }
            $html .= '<optgroup label="' . htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') . '">' . "
";
            foreach ($rows as $row) {
                $sel = in_array($row['slug'], $selected, true) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($row['slug'], ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                       . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</option>' . "
";
            }
            $html .= '</optgroup>' . "
";
        }
        return $html;
    }
}

if (!function_exists('inst_fa_digits')) {
    /** تبدیل رقم‌های لاتین به فارسی، برای نمایش روی کارت‌های انتخاب پایه. */
    function inst_fa_digits($n)
    {
        return strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                                   '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}

if (!function_exists('inst_level_groups')) {
    /**
     * دسته‌ها با پایه‌های زیرمجموعه، برای تب‌های صفحهٔ انتخاب پایه (course.php).
     * پایه‌های بدون دسته زیر کلید '' جمع می‌شوند.
     */
    function inst_level_groups()
    {
        $out = [];
        foreach (inst_levels('group') as $g) {
            $out[$g['slug']] = ['label' => $g['label'], 'items' => []];
        }
        foreach (['level', 'field'] as $kind) {
            foreach (inst_levels($kind) as $row) {
                if ($kind === 'level' && (int) $row['selectable_for_person'] !== 1) {
                    continue;
                }
                $gs = $row['group_slug'];
                if ($gs === null || $gs === '' || !isset($out[$gs])) {
                    if ($kind === 'field') {
                        continue;
                    }
                    $gs = '';
                    if (!isset($out[$gs])) {
                        $out[$gs] = ['label' => t('grade_pl'), 'items' => []];
                    }
                }
                $out[$gs]['items'][] = $row;
            }
        }
        foreach (array_keys($out) as $k) {
            if (!$out[$k]['items']) {
                unset($out[$k]);
            }
        }
        return $out;
    }
}

/* ثابت‌ها برای جاهایی که فراخوانی تابع ممکن نیست */
if (!defined('INST_TYPE')) {
    define('INST_TYPE', inst_type());
}

if (!defined('__SCHOOL_NAME')) {
    $configuredSchoolName = trim((string) getenv('SCHOOL_NAME'));
    define('__SCHOOL_NAME', $configuredSchoolName !== '' ? $configuredSchoolName : t('org', 'مدرسه'));
}
