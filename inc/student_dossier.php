<?php
/** Shared dossier policy: an administrator's saved record is read only for its owner. */
function StudentDossierLocked($person): bool
{
    $editor = trim((string)($person->updator ?? ''));
    if ($editor !== '' && $editor !== '0' && $editor !== (string)$person->codemeli) return true;
    $creator = trim((string)($person->creator ?? ''));
    if ($creator === '' || $creator === '0' || $creator === (string)$person->codemeli) return false;
    global $conn;
    $stmt = $conn->prepare('SELECT 1 FROM dbadmin WHERE username = ? LIMIT 1');
    $stmt->execute([$creator]);
    return (bool)$stmt->fetchColumn();
}

function StudentAvatar($person): string
{
    $pic = (string)($person->pic ?? '');
    if (preg_match('~^/?uploads-student/student-profile/[a-zA-Z0-9_.-]+$~', $pic)) {
        $relative = ltrim($pic, '/');
        if (is_file(BASE_PATH . '/' . $relative)) return url($relative);
        // Older uploads were stored at the domain root.
        if (is_file(rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/' . $relative)) return '/' . $relative;
    }
    return url('assets/img/users/1.jpg');
}

function StudentDossierPhoto(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 500 * 1024) {
        throw new RuntimeException('تصویر JPG یا PNG با حجم حداکثر ۵۰۰ کیلوبایت انتخاب کنید.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (!$info || !isset($types[$info[2]]) || $info[0] > 6000 || $info[1] > 6000) {
        throw new RuntimeException('فایل تصویر معتبر نیست.');
    }
    $dir = BASE_PATH . '/uploads-student/student-profile';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('ذخیره تصویر ممکن نیست.');
    $relative = 'uploads-student/student-profile/' . bin2hex(random_bytes(16)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], BASE_PATH . '/' . $relative)) throw new RuntimeException('ذخیره تصویر ممکن نیست.');
    return '/' . $relative;
}

function StudentDossierSections(): array
{
    $sections = [
        'account' => ['اطلاعات حساب کاربری', [
            'fname'=>'نام', 'lname'=>'نام خانوادگی', 'birthdate'=>'تاریخ تولد', 'gender'=>'جنسیت',
            'melliat'=>'ملیت', 'tel1'=>'شماره همراه اصلی', 'tel_home'=>'تلفن منزل', 'email'=>'پست الکترونیکی',
            'bio'=>'درباره خودتان', 'hand'=>'دست غالب', 'shift'=>'شیفت']],
        'parents' => ['اطلاعات خانواده', [
            'fathername'=>'نام پدر', 'famili_pedar'=>'نام خانوادگی پدر', 'codemeli_pedar'=>'کد ملی پدر',
            'shenasname_pedar'=>'شماره شناسنامه پدر', 'birthday_pedar'=>'تاریخ تولد پدر', 'job_pedar'=>'شغل پدر',
            'education_pedar'=>'تحصیلات پدر', 'hayat_pedar'=>'وضعیت حیات پدر', 'tel3'=>'تلفن پدر',
            'name_madar'=>'نام مادر', 'famili_madar'=>'نام خانوادگی مادر', 'codemeli_madar'=>'کد ملی مادر',
            'shenasname_madar'=>'شماره شناسنامه مادر', 'birthdate_madar'=>'تاریخ تولد مادر', 'job_madar'=>'شغل مادر',
            'education_madar'=>'تحصیلات مادر', 'hayat_madar'=>'وضعیت حیات مادر', 'tel2'=>'تلفن مادر',
            'living_condition'=>'وضعیت زندگی', 'bime_status'=>'وضعیت بیمه', 'all_baby'=>'تعداد فرزندان']],
        'house' => ['اطلاعات سکونت و تحصیلی', [
            'state'=>'استان', 'city'=>'شهر', 'life_rosta'=>'روستا', 'address'=>'نشانی', 'zipcode'=>'کد پستی',
            'type_life'=>'مالکیت منزل', 'life_status'=>'وضعیت منزل', 'life_baft'=>'بافت منزل', 'life_metr'=>'متراژ منزل',
            'nameschool'=>'نام مدرسه', 'modir_name'=>'نام مدیر', 'typeschool'=>'نوع مدرسه', 'area'=>'منطقه',
            'paye'=>'پایه تحصیلی', 'reshtestudent'=>'رشته تحصیلی']],
        'picture' => ['تصویر پروفایل', []],
    ];
    if (inst_type() !== 'school') $sections['bank'] = ['اطلاعات حساب بانکی', [
        'sheba'=>'شماره شبا', 'name_sheba'=>'صاحب شبا', 'bank_sheba'=>'نام بانک', 'card_bank'=>'شماره کارت', 'name_card'=>'صاحب کارت']];
    return $sections;
}

function StudentDossierEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function StudentDossierChoices(): array
{
    $same = static fn(array $values): array => array_combine($values, $values);
    return [
        'gender'=>$same(['خانم','آقا']), 'melliat'=>$same(['ایرانی','اتباع']),
        'hand'=>$same(['چپ دست','راست دست']), 'shift'=>['0'=>'صبح','1'=>'بعدازظهر','2'=>'گردشی'],
        'education_pedar'=>$same(['بی سواد','زیر دیپلم','دیپلم','کاردانی','کارشناسی','کارشناسی ارشد','دکتری']),
        'education_madar'=>$same(['بی سواد','زیر دیپلم','دیپلم','کاردانی','کارشناسی','کارشناسی ارشد','دکتری']),
        'hayat_pedar'=>$same(['در قید حیات','فوت شده','شهید','مفقود']),
        'hayat_madar'=>$same(['در قید حیات','فوت شده','شهید','مفقود']),
        'living_condition'=>$same(['زندگی با پدر و مادر','زندگی با پدر','زندگی با مادر']),
        'bime_status'=>$same(['دارم','ندارم']),
        'type_life'=>$same(['آپارتمان','خانه ویلایی','کوچ نشین','باغ','سایر']),
        'life_status'=>$same(['شخصی','استیجاری','سازمانی','سایر']),
        'life_baft'=>$same(['شهری','روستایی','عشایر','سایر']),
        'typeschool'=>$same(['دولتی','هیئت امنایی','غیرانتفاعی','نمونه دولتی','تیزهوشان','شاهد','آزاد','مدارس اقلیت های مذهبی']),
        'reshtestudent'=>$same(['ندارد','ریاضی','تجربی','انسانی','فنی و حرفه ای']),
    ];
}
