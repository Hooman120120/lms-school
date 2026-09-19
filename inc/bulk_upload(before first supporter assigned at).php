<?php
include_once '../../inc/jdf.php';
include_once '../../vendor/autoload.php';
include_once '../../quizconfig.php';
include_once 'function-quiz.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: admin/login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: admin/login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
function gregorianDateTimeToJalali($datetime)
{
    if (empty($datetime)) return '';

    // split into date + time
    $parts = explode(' ', $datetime);
    $date  = $parts[0];
    $time  = $parts[1] ?? '';

    [$gy, $gm, $gd] = explode('-', $date);

    // use jdf.php function (already exists)
    $jalali = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd, '/');

    return $jalali . ($time ? ' ' . $time : '');
}


if (isset($_POST['upload'])) {
    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;
    $errors   = [];

    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === 0) {
        $file = $_FILES['excel_file']['tmp_name'];

        try {
            // Load Excel
            $spreadsheet = IOFactory::load($file);
            $worksheet   = $spreadsheet->getActiveSheet();

            $rowNumber = 0;
            $seenCodemelis = []; // track codemelis in this upload
            $allRows = [];

            // --- First pass: validate & check duplicates ---
            foreach ($worksheet->getRowIterator() as $row) {
                $rowNumber++;
                if ($rowNumber == 1) continue; // skip header

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $data = [];
                foreach ($cellIterator as $cell) {
                    $data[] = trim((string)$cell->getValue());
                }

                $codemeli = $data[0] ?? '';
                $rowHasError = false;

                // Duplicate check inside Excel
                if (in_array($codemeli, $seenCodemelis)) {
                    $errors[] = "کد ملی ردیف $rowNumber تکراری است (داخل فایل)";
                    $rowHasError = true;
                } else {
                    $seenCodemelis[] = $codemeli;
                }
                //  Change birthdate to gregorian
                [$jy, $jm, $jd] = explode("-", $data[8]);
                $gregorian = jalali_to_gregorian($jy, $jm, $jd, '-');
                // Controlling shift
                switch ($data[17]) {
                    case 'صبح':
                        $shift = 0;
                        break;
                    case 'بعدازظهر':
                        $shift = 1;
                        break;
                    case 'گردشی':
                        $shift = 2;
                        break;
                    default:
                        $errors[] = "شیفت دانش آموز ردیف $rowNumber نامعتبر است. مقادیر معتبر : (صبح,بعدازظهر,گردشی)";
                        $rowHasError = true;
                }
                //check tel1
                if (checkTell($data[4])) {
                    $errors[] = "شماره تماس دانش آموز ردیف $rowNumber تکراری است";
                    $rowHasError = true;
                } elseif (strlen($data[4]) != 11) {
                    $errors[] = "شماره تماس دانش آموز ردیف $rowNumber نامعتبر است";
                    $rowHasError = true;
                }
                //check tel2
                if (checkTell($data[5])) {
                    $errors[] = "شماره تماس مادر/رابط ردیف $rowNumber تکراری است";
                    $rowHasError = true;
                } elseif (strlen($data[5]) != 11) {
                    $errors[] = "شماره تماس مادر/رابط ردیف $rowNumber نامعتبر است";
                    $rowHasError = true;
                }
                //check tel3
                if (checkTell($data[6])) {
                    $errors[] = "شماره تماس پدر ردیف $rowNumber تکراری است";
                    $rowHasError = true;
                } elseif (strlen($data[6]) != 11) {
                    $errors[] = "شماره تماس پدر ردیف $rowNumber نامعتبر است";
                    $rowHasError = true;
                }
                //check melliat
                switch ($data[7]) {
                    case "ایرانی":
                        $melliat = 'ایرانی';
                        break;
                    case 'اتباع':
                        $melliat = 'اتباع';
                        break;
                    default:
                        $errors[] = "ملیت دانش آموز ردیف $rowNumber نامعتبر است. مقادیر معتبر : (ایرانی,اتباع)";
                        $rowHasError = true;
                }
                //check gender 
                switch ($data[16]) {
                    case "آقا":
                        $gender = "آقا";
                        break;
                    case "خانم":
                        $gender = "خانم";
                        break;
                    default:
                        $errors[] = "جنسیت دانش آموز ردیف $rowNumber نامعتبر است. مقادیر معتبر : (آقا,خانم)";
                        $rowHasError = true;
                }
                //check type_vip
                $vips = explode(" | ", $data[18]);
                foreach ($vips as $vip) {
                    $sql = 'SELECT * FROM type_vip WHERE name = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$vip]);
                    if ($stmt->rowCount() > 0) {
                        continue;
                    } else {
                        $errors[] = "نوع دانش آموز ردیف $rowNumber دارای مقدار نامعتبری است که در سیستم وجود ندارد ";
                        $rowHasError = true;
                        break;
                    }
                }

                // Map Excel columns → DB columns (vip removed)
                $columns = [
                    'codemeli'      => $codemeli,
                    'fname'         => $data[1] ?? '',
                    'lname'         => $data[2] ?? '',
                    'fathername'    => $data[3] ?? '',
                    'tel1'          => $data[4] ?? '',
                    'tel2'          => $data[5] ?? '',
                    'tel3'          => $data[6] ?? '',
                    'melliat'       => $melliat ?? 'خطا',
                    'birthdate'     => $gregorian ?? '',
                    'state'         => $data[9] ?? '',
                    'city'          => $data[10] ?? '',
                    'nameschool'    => $data[11] ?? '',
                    'typeschool'    => $data[12] ?? '',
                    'paye'          => $data[13] ?? '',
                    'reshtestudent' => !empty($data[14]) ? $data[14] : 'ندارد',
                    'area'          => $data[15] ?? '',
                    'gender'        => $gender ?? 'خطا',
                    'shift'         => $shift ?? '',
                    'type_vip'      => $data[18] ?? '',
                    'moaref'        => $data[19] ?? ''
                ];
                // Empty fields check
                foreach ($columns as $colName => $value) {
                    if ($value === '' and $colName != 'moaref') {
                        $errors[] = "ردیف $rowNumber ستون $colName خالیه";
                        $rowHasError = true;
                    }
                }

                // Validate codemeli format
                if (!check_national_code($columns['codemeli'])) {
                    $errors[] = "فرمت کد ملی ردیف $rowNumber اشتباه است";
                    $rowHasError = true;
                }

                // Duplicate in DB
                if (checkCodemeli($columns['codemeli'])) {
                    $errors[] = "کد ملی ردیف $rowNumber تکراری است";
                    $rowHasError = true;
                }

                if ($rowHasError) {
                    $skipped++;
                } else {
                    $allRows[] = $columns;
                }
            }

            // ❌ If there are any errors, don't insert
            if (!empty($errors)) {
                $_SESSION['bulk_signup_report'] = [
                    'inserted' => $inserted,
                    'updated'  => $updated,
                    'skipped'  => $skipped,
                    'errors'   => $errors
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            // --- Insert into DB ---
            try {
                $conn->beginTransaction();

                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/doc/groupadd';
                $fileName = uniqid() . '_' . basename($_FILES['excel_file']['name']);
                $uploadPath = $uploadDir . '/' . $fileName;
                $upload_path_for_db = '/assets/doc/groupadd/' . $fileName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                // Bulk upload record
                $uploadedBy = $_SESSION['admin']['user'] ?? 'system';
                $insertBulk = $conn->prepare("
                    INSERT INTO bulk_uploads (uploaded_by, filename, file_path)
                    VALUES (?, ?, ?)
                ");
                $insertBulk->execute([$uploadedBy, $_FILES['excel_file']['name'], $upload_path_for_db]);
                $bulkUploadId = $conn->lastInsertId();

                if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadPath)) {
                    // Insert students (vip removed)
                    $insert = $conn->prepare("
                    INSERT INTO dbpersonal
                        (codemeli, fname, lname, fathername, tel1, tel2, tel3, melliat, birthdate,
                         state, city, nameschool, typeschool, paye, reshtestudent, area, gender, shift, datecreated, timecreated, ipcreated, oscreated, creator, moaref, bulk_upload_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    date_default_timezone_set("Iran");
                    $datecreated = date("Y-m-d");
                    $timecreated = date("H:i:s");
                    $ipcreated   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $oscreated   = PHP_OS;
                    $creator     = "bulk_upload";
                    // inserting dbpersonal
                    foreach ($allRows as $row) {
                        $insert->execute([
                            $row['codemeli'],
                            $row['fname'],
                            $row['lname'],
                            $row['fathername'],
                            $row['tel1'],
                            $row['tel2'],
                            $row['tel3'],
                            $row['melliat'],
                            $row['birthdate'],
                            $row['state'],
                            $row['city'],
                            $row['nameschool'],
                            $row['typeschool'],
                            $row['paye'],
                            $row['reshtestudent'],
                            $row['area'],
                            $row['gender'],
                            $row['shift'],
                            $datecreated,
                            $timecreated,
                            $ipcreated,
                            $oscreated,
                            $creator,
                            $row['moaref'],
                            $bulkUploadId
                        ]);

                        $personalCurrentId = $conn->lastInsertId();
                        $typeVips = [];
                        $vips = explode(' | ', $row['type_vip']);
                        $sql = "SELECT * FROM type_vip WHERE name = ?";
                        $stmt = $conn->prepare($sql);
                        foreach ($vips as $vip) {
                            $stmt->execute([$vip]);
                            $result = $stmt->fetch(PDO::FETCH_OBJ);
                            $typeVips[] = $result->id;
                        }


                        $sqll = "INSERT INTO dbpersonal_type_vip (dbpersonal_id,type_vip_id) VALUES (?,?)";
                        $stmtt = $conn->prepare($sqll);
                        foreach ($typeVips as $vip) {
                            $stmtt->execute([
                                $personalCurrentId,
                                $vip
                            ]);
                        }

                        $inserted++;
                    }


                    $insert_user = 'INSERT INTO dblogin (username,password,`role`) VALUES (?,?,?)';
                    $query_user = $conn->prepare($insert_user);
                    //inserting dblogin
                    foreach ($allRows as $row) {
                        $rand1 = rand(1, 10);
                        switch ($rand1) {
                            case 1:
                                $ch = 'ug';
                                break;
                            case 2:
                                $ch = 'af';

                                break;
                            case 3:
                                $ch = 'wv';
                                break;
                            case 4:
                                $ch = 'qb';
                                break;
                            case 5:
                                $ch = 'je';
                                break;
                            case 6:
                                $ch = 'ad';
                                break;
                            case 7:
                                $ch = 'df';
                                break;
                            case 8:
                                $ch = 'yg';
                                break;
                            case 9:
                                $ch = 'iu';
                                break;
                            case 10:
                                $ch = 'nk';
                                break;
                        }
                        $rand2 = rand(1000, 9999);
                        $pass = $ch . $rand2;
                        $query_user->execute([
                            $row['codemeli'],
                            $pass,
                            1
                        ]);
                        $text = 'کد ملی' . ';' . $pass;
                        SendSms($row['tel1'], $text, 171697, $row['codemeli']);
                    }
                }
                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "خطای تراکنش: " . $e->getMessage();
            }

            // Store report
            $_SESSION['bulk_signup_report'] = [
                'inserted' => $inserted,
                'updated'  => $updated,
                'skipped'  => $skipped,
                'errors'   => $errors
            ];

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $errors[] = "خطا در خواندن فایل اکسل: " . $e->getMessage();
        }
    }
}

// --- Handle delete request ---
if (isset($_POST['delete_bulk_id'])) {
    $bulkId = (int) $_POST['delete_bulk_id'];

    try {
        $conn->beginTransaction();

        // 1) Delete all students linked to this bulk_upload
        $stmt = $conn->prepare("DELETE FROM dbpersonal WHERE bulk_upload_id = ?");
        $stmt->execute([$bulkId]);

        // 2) Soft delete the bulk_upload record
        $deletedBy = $_SESSION['admin']['user'] ?? 'system';
        $stmt = $conn->prepare("
            UPDATE bulk_uploads 
            SET deleted_at = NOW(), deleted_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$deletedBy, $bulkId]);

        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "خطا در حذف: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// Bulk signing up courses for students
// if (isset($_POST['course_signup'])) {

//     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
//         $file = $_FILES['csv_file']['tmp_name'];
//         $handle = fopen($file, "r");

//         $inserted = 0;
//         $skipped = 0;
//         $errors = [];

//         $rowNumber = 0;

//         while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
//             $rowNumber++;

//             // Skip header row
//             if ($rowNumber == 1 && preg_match('/codemeli/i', $data[0])) {
//                 continue;
//             }

//             // Map CSV fields
//             $codemeli = trim($data[0] ?? '');
//             $codecourse = trim($data[1] ?? '');
//             $activityuser = !empty($data[2]) ? $data[2] : null;
//             $numfactor = !empty($data[3]) ? $data[3] : null;
//             $suporter = !empty($data[4]) ? $data[4] : null;
//             $type_process = !empty($data[5]) ? $data[5] : null;
//             $color = !empty($data[6]) ? $data[6] : null;
//             $color_wait = !empty($data[7]) ? $data[7] : null;
//             $color_detail = !empty($data[8]) ? $data[8] : null;

//             // --- Validation ---
//             if (empty($codemeli)) {
//                 $errors[] = "ردیف $rowNumber: کد ملی خالی است";
//                 continue;
//             }
//             if (empty($codecourse)) {
//                 $errors[] = "ردیف $rowNumber: کد دوره خالی است";
//                 continue;
//             }

//             // Lookup student codemeli in dbpersonal
//             $stmt = $conn->prepare("SELECT codemeli FROM dbpersonal WHERE codemeli = ?");
//             $stmt->execute([$codemeli]);
//             $student = $stmt->fetch(PDO::FETCH_ASSOC);

//             if (!$student) {
//                 $errors[] = "ردیف $rowNumber: کد ملی $codemeli در سیستم یافت نشد";
//                 continue;
//             }

//             $username = $student['codemeli']; // use codemeli as username in dbcourse

//             // Check course exists by codecourse
//             $stmt = $conn->prepare("SELECT codecourse FROM codtbl_course WHERE codecourse = ?");
//             $stmt->execute([$codecourse]);
//             $course = $stmt->fetch(PDO::FETCH_ASSOC);

//             if (!$course) {
//                 $errors[] = "ردیف $rowNumber: کد دوره $codecourse معتبر نیست";
//                 continue;
//             }

//             // Check duplicate enrollment
//             $stmt = $conn->prepare("SELECT COUNT(*) FROM dbcourse WHERE username = ? AND codecourse = ?");
//             $stmt->execute([$username, $codecourse]);
//             $exists = $stmt->fetchColumn();

//             if ($exists) {
//                 $errors[] = "ردیف $rowNumber: دانشجو با کد ملی $codemeli قبلاً در این دوره ثبت‌نام شده است";
//                 $skipped++;
//                 continue;
//             }

//             // --- Insert enrollment ---
//             $stmt = $conn->prepare("
//                 INSERT INTO dbcourse 
//                 (`username`, `codecourse`, `activityuser`, `numfactor`, `suporter`, `type_process`, `color`, `color_wait`, `color_detail`)
//                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
//             ");
//             $success = $stmt->execute([
//                 $username,
//                 $codecourse,
//                 $activityuser,
//                 $numfactor,
//                 $suporter,
//                 $type_process,
//                 $color,
//                 $color_wait,
//                 $color_detail
//             ]);

//             if ($success) {
//                 $inserted++;
//             } else {
//                 $errors[] = "ردیف $rowNumber: خطا در ذخیره‌سازی";
//             }
//         }

//         fclose($handle);

//         // Save results in session (PRG pattern)
//         $_SESSION['bulk_course_report'] = [
//             'inserted' => $inserted,
//             'skipped' => $skipped,
//             'errors' => $errors
//         ];

//         header("Location: " . $_SERVER['PHP_SELF']);
//         exit;
//     }
// }
if (isset($_POST['confirm_invoice'])) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {

        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $fileName = $_FILES['excel_file']['name'];

        // فقط اکسل مجاز
        $allowedExtensions = ['xls', 'xlsx', 'xlsm'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            die("فایل آپلود شده اکسل نیست!");
        }
        // Load Excel
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $records = 0;
        $inserted = 0;
        $skipped = 0;
        $errors = [];


        for ($row = 2; $row <= $highestRow; $row++) { // فرض می‌کنیم ردیف 1 سرصفحه

            $codemeli      = trim($sheet->getCell('A' . $row)->getValue() ?? '');
            $codecourse    = trim($sheet->getCell('B' . $row)->getValue() ?? '');
            $supporter  = trim($sheet->getCell('C' . $row)->getValue() ?? '');
            $amount = trim($sheet->getCell('D' . $row)->getValue() ?? '');
            !empty($amount) ?: $amount = 0;
            if (empty($codemeli) && empty($codecourse) && empty($supporter) && empty($amount)) {
                continue;
            }
            $records++;

            // --- Validation ---
            if (empty($codemeli)) {
                $errors[] = "ردیف $row: کد ملی خالی است";
                $skipped++;
                continue;
            }
            if (empty($codecourse)) {
                $errors[] = "ردیف $row: کد دوره خالی است";
                $skipped++;
                continue;
            }
            if (empty($supporter)) {
                $errors[] = "ردیف $row: ستون پشتیبان خالی است";
                $skipped++;
                continue;
            }
            $codes = explode("|", $codecourse);
            // Lookup student codemeli in dbpersonal
            $stmt = $conn->prepare("SELECT codemeli FROM dbpersonal WHERE codemeli = ?");
            $stmt->execute([$codemeli]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $errors[] = "ردیف $row: کد ملی $codemeli در سیستم یافت نشد";
                $skipped++;
                continue;
            }

            $username = $student['codemeli'];

            // Check course exists
            $isCourseNotValid = false;
            foreach ($codes as $code) {
                // Check course exists
                $stmt = $conn->prepare("SELECT codecourse FROM codtbl_course WHERE codecourse = ?");
                $stmt->execute([$code]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$course) {
                    $errors[] = "ردیف $row: کد دوره $code معتبر نیست ";
                    $skipped++;
                    $isCourseNotValid = true;
                    break;
                }
            }
            if ($isCourseNotValid) {
                continue;
            }
            // Check duplicate enrollment
            $duplicate = false;
            foreach ($codes as $code) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM dbcourse WHERE username = ? AND codecourse = ?");
                $stmt->execute([$username, $code]);
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    $errors[] = "ردیف $row: دانش آموز با کد ملی $codemeli قبلاً در دوره با کد $code  ثبت‌نام شده است";
                    $skipped++;
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }

            $stmt = $conn->prepare("SELECT username FROM dbadmin WHERE username = ?");
            $stmt->execute([$supporter]);
            $support = $stmt->fetchColumn();

            if (!$support) {
                $errors[] = "ردیف $row: نام پشتیبان $support معتبر نیست";
                $skipped++;
                continue;
            }
            // --- Insert enrollment ---
            // جمع مبالغ

            $total = 0;
            foreach ($codes as $code) {
                $fee = getfee($code);
                if ($fee != 0) {
                    $total = $total + $fee;
                }
            }

            if ($amount > $total) {
                $errors[] = "ردیف $row : مبلغ واریزی نمی تواند بیشتر از جمع مبالغ دوره باشد ";
                $skipped++;
                continue;
            }

            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $firstAssignedAt = $datenow . ' ' . $timenow;
            $ip = GetIp();
            $os = GetOs();
            try {
                $conn->beginTransaction();

                $add = 'INSERT INTO dbfactor (username,sumitem,subtotal,factorfee,wallet,datecreate,timecreate,factoradder,ip ,os) VALUES (?,?,?,?,?,?,?,?,?,?)';
                $inadd = $conn->prepare($add);
                $inadd->execute([$username, $total, $total, $total, 0, $datenow, $timenow, $_SESSION['admin']['user'], $ip, $os]);
                $factorId = $conn->lastInsertId();
                $thisFactor = queryFactor($factorId);
                if ($_POST['confirm_invoice'] == 'no') {


                    if ($thisFactor->sumitem >= $amount and $amount != 0) {
                        $discount = $thisFactor->sumitem - $amount;
                        $upm = 'UPDATE dbfactor SET discountfee=?, wage=?,subtotal=?,factorfee=?,status=?,metod=?,dateresponse=?,timeresponse=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=?';
                        $extry = $conn->prepare($upm);
                        $extry->execute([$discount, 0, $amount, $amount, 'منتظر پرداخت', 'کارت به کارت', $datenow, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $factorId]);
                    }

                    $stmt = $conn->prepare("
                   INSERT INTO dbcourse (`username`, `codecourse`, `numfactor`, `suporter`, `first_suporter_assigned_at`)
                        VALUES (?, ?, ?, ?, ?)
                        ");
                    foreach ($codes as $code) {
                        $success = $stmt->execute([
                            $username,
                            $code,
                            $factorId,
                            $supporter,
                            $firstAssignedAt
                        ]);
                    }


                    if ($success) {
                        $inserted++;
                    } else {
                        $errors[] = "ردیف $row: خطا در ذخیره‌سازی";
                    }
                } else {
                    if ($amount == 0) {
                        // واریزی نداشته و روش پرداخت رایگان است
                        $upm = 'UPDATE dbfactor SET discountfee=?,subtotal=?,factorfee=?,status=?,metod=?,dateresponse=?,timeresponse=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=?';
                        $extry = $conn->prepare($upm);
                        $extry->execute([$thisFactor->sumitem, 0, 0, 'موفق', 'رایگان', $datenow, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $factorId]);
                        //
                        $stmt = $conn->prepare("
                        INSERT INTO dbcourse (`username`, `codecourse`, `numfactor`, `suporter`, `first_suporter_assigned_at`)
                            VALUES (?, ?, ?, ?, ?)
                            ");
                        foreach ($codes as $code) {
                            $success = $stmt->execute([
                                $username,
                                $code,
                                $factorId,
                                $supporter,
                                $firstAssignedAt
                            ]);
                        }
                        $coup = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
                        $exco = $conn->prepare($coup);
                        $exco->execute([1, $factorId]);
                        if (checkUserquiz($thisFactor->username)) {
                            $person = retPerson($thisFactor->username);
                            $login = MD5(passUser($thisFactor->username));
                            CreateUserByApi(['aid' => 1, 'username' => $thisFactor->username, 'password' => $login, 'active' => 1, 'expiration_date' => '3021-03-21', 'fname' => $person->fname, 'lname' => $person->lname]);
                            // $inquiz = 'INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
                            // $qin = $ponn->prepare($inquiz);
                            // $qin->execute([1, $thisFactor->username, $login, 1, '3021-03-21', $person->fname, $person->lname]);
                        }
                        $cou = 'SELECT * FROM dbcourse WHERE numfactor=?';
                        $qpo = $conn->prepare($cou);
                        $qpo->execute([$factorId]);
                        $rew = $qpo->fetchAll(PDO::FETCH_OBJ);
                        foreach ($rew as $tbt) {
                            if (checkGroupAdd($tbt->codecourse, $tbt->username)) {
                                $ugid = retIdGroupByApi($tbt->codecourse);
                                $uid = GetUserIdByApi($tbt->username);
                                AddUserToGroupByApi($ugid, $uid);
                                // $add = 'INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                                // $qad = $ponn->prepare($add);
                                // $qad->execute([$ugid, $uid]);
                            }
                        }
                    } else {
                        // واریزی داشته و تخفیف آن باید محاسبه گردد و تایید واریزی صورت گیرد
                        $discount = $thisFactor->sumitem - $amount;
                        $update = 'UPDATE dbfactor SET discountfee=?,subtotal=?,factorfee=?, status=?,metod=?,dateresponse=?,timeresponse=? ,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
                        $qupdate = $conn->prepare($update);
                        $qupdate->execute([$discount, $amount, $amount, 'موفق', 'کارت به کارت', $datenow, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $factorId]);
                        //
                        $stmt = $conn->prepare("
                       INSERT INTO dbcourse (`username`, `codecourse`, `numfactor`, `suporter`, `first_suporter_assigned_at`)
                        VALUES (?, ?, ?, ?, ?)
                        ");
                        foreach ($codes as $code) {
                            $success = $stmt->execute([
                                $username,
                                $code,
                                $factorId,
                                $supporter,
                                $firstAssignedAt
                            ]);
                        }
                        $courseA = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
                        $queryA = $conn->prepare($courseA);
                        $queryA->execute([1, $factorId]);
                        // Register Quiz
                        if (checkUserquiz($thisFactor->username)) {
                            $person = retPerson($thisFactor->username);
                            $login = MD5(passUser($thisFactor->username));
                            CreateUserByApi(['aid' => 1, 'username' => $thisFactor->username, 'password' => $login, 'active' => 1, 'expiration_date' => '3021-03-21', 'fname' => $person->fname, 'lname' => $person->lname]);
                            // $inquiz = 'INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
                            // $qin = $ponn->prepare($inquiz);
                            // $qin->execute([1, $thisFactor->username, $login, 1, '3021-03-21', $person->fname, $person->lname]);
                        }
                        $cou = 'SELECT * FROM dbcourse WHERE numfactor=?';
                        $qpo = $conn->prepare($cou);
                        $qpo->execute([$factorId]);
                        $rew = $qpo->fetchAll(PDO::FETCH_OBJ);
                        foreach ($rew as $tbt) {
                            if (checkGroupAdd($tbt->codecourse, $tbt->username)) {
                                $ugid = retIdGroupByApi($tbt->codecourse);
                                $uid = GetUserIdByApi($tbt->username);
                                AddUserToGroupByApi($ugid, $uid);
                                // $add = 'INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                                // $qad = $ponn->prepare($add);
                                // $qad->execute([$ugid, $uid]);
                            }
                        }
                    }
                }

                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "خطای تراکنش: " . $e->getMessage();
                $skipped++;
            }
        }

        // ذخیره نتیجه در سشن
        $_SESSION['bulk_course_report'] = [
            'records' => $records,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors
        ];

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file']) && isset($_POST['course_signup'])) { 

    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $fileName = $_FILES['excel_file']['name'];

    // فقط اکسل مجاز
    $allowedExtensions = ['xls', 'xlsx', 'xlsm'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        die("فایل آپلود شده اکسل نیست!");
    }
    // Load Excel
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestRow();
    $records = 0;
    $inserted = 0;
    $skipped = 0;
    $errors = [];


    for ($row = 2; $row <= $highestRow; $row++) { // فرض می‌کنیم ردیف 1 سرصفحه

        $codemeli      = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        $codecourse    = trim($sheet->getCell('B' . $row)->getValue() ?? '');
        $supporter  = trim($sheet->getCell('C' . $row)->getValue() ?? ''); // needs to edit


        if (empty($codemeli) && empty($codecourse) && empty($supporter)) {
            continue;
        }
        $records++;

        // --- Validation ---
        if (empty($codemeli)) {
            $errors[] = "ردیف $row: کد ملی خالی است";
            $skipped++;
            continue;
        }
        if (empty($codecourse)) {
            $errors[] = "ردیف $row: کد دوره خالی است";
            $skipped++;
            continue;
        }
        if (empty($supporter)) {
            $errors[] = "ردیف $row: ستون پشتیبان خالی است";
            $skipped++;
            continue;
        }
        $codes = explode("|", $codecourse);
        // Lookup student codemeli in dbpersonal
        $stmt = $conn->prepare("SELECT codemeli FROM dbpersonal WHERE codemeli = ?");
        $stmt->execute([$codemeli]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $errors[] = "ردیف $row: کد ملی $codemeli در سیستم یافت نشد";
            $skipped++;
            continue;
        }

        $username = $student['codemeli'];

        // Check course exists
        $isCourseNotValid = false;
        foreach ($codes as $code) {
            // Check course exists
            $stmt = $conn->prepare("SELECT codecourse FROM course_demo WHERE codecourse = ?");
            $stmt->execute([$code]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                $errors[] = "ردیف $row: کد دوره $code معتبر نیست ";
                $skipped++;
                $isCourseNotValid = true;
                break;
            }
        }
        if ($isCourseNotValid) {
            continue;
        }
        // Check duplicate enrollment
        $duplicate = false;
        foreach ($codes as $code) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM dbcourse_demo WHERE username = ? AND codecourse = ?");
            $stmt->execute([$username, $code]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $errors[] = "ردیف $row: دانش آموز با کد ملی $codemeli قبلاً در دوره با کد $code  ثبت‌نام شده است";
                $skipped++;
                $duplicate = true;
                break;
            }
        }
        if ($duplicate) {
            continue;
        }

        $stmt = $conn->prepare("SELECT username FROM dbadmin WHERE username = ?");
        $stmt->execute([$supporter]);
        $support = $stmt->fetchColumn();

        if (!$support) {
            $errors[] = "ردیف $row: نام پشتیبان $support معتبر نیست";
            $skipped++;
            continue;
        }
        // --- Insert enrollment ---

        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("
                   INSERT INTO dbcourse_demo (`username`, `codecourse`, `useradder`)
                        VALUES (?, ?, ?)
                        ");
            foreach ($codes as $code) {
                $success = $stmt->execute([
                    $username,
                    $code,
                    $supporter,
                ]);
            }

            if ($success) {
                $inserted++;
            } else {
                $errors[] = "ردیف $row: خطا در ذخیره‌سازی";
            }

            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "خطای تراکنش: " . $e->getMessage();
            $skipped++;
        }
    }

    // ذخیره نتیجه در سشن
    $_SESSION['bulk_course_report'] = [
        'records' => $records,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors
    ];

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
