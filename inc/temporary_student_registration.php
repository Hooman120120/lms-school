<?php
/** The existing temporary account workflow, shared with school extra-curricular enrolment. */
function TemporaryStudentRegistrationCan(bool $embedded = false): bool
{
    if ($embedded) {
        return inst_is('school') && AdminCanUsePermissionScope(
            'students.temporary.enrolment.register-student',
            ['permision_viewstudent', 'permision_addstudent']
        );
    }
    return Permision('permision_viewstudent') && Permision('permision_addstudent');
}

/** Resolve an existing school student without confusing a shared phone with an identity. */
function ResolveExtraProgramStudent(string $identifier): array
{
    global $conn;
    $exact = $conn->prepare('SELECT p.codemeli, l.role FROM dbpersonal p LEFT JOIN dblogin l ON l.username = p.codemeli WHERE p.codemeli = ? LIMIT 1');
    $exact->execute([$identifier]);
    $person = $exact->fetch(PDO::FETCH_ASSOC);
    if ($person) {
        return (int)($person['role'] ?? 0) === 1
            ? ['status' => 'found', 'username' => (string)$person['codemeli']]
            : ['status' => 'not-student'];
    }
    $phone = $conn->prepare('SELECT p.codemeli FROM dbpersonal p JOIN dblogin l ON l.username = p.codemeli WHERE p.tel1 = ? AND l.role = 1 LIMIT 2');
    $phone->execute([$identifier]);
    $students = $phone->fetchAll(PDO::FETCH_COLUMN);
    if (count($students) > 1) return ['status' => 'ambiguous'];
    return $students !== []
        ? ['status' => 'found', 'username' => (string)$students[0]]
        : ['status' => 'missing'];
}

function HandleTemporaryStudentRegistration(bool $embedded = false): array
{
    global $conn;
    $temporarySessionKey = $embedded ? 'extra-program-student-registration' : 'add-student2';
    $temporaryTokenKey = $embedded ? 'extra-program-student-token' : 'temporary-student-token';
    $temporaryChangeKey = $embedded ? 'temporary-change' : 'change';
    $temporaryReturnPath = $embedded ? 'admin/democourse/addstudent.php' : 'admin/Student/experimental-add.php';
    $isTemporaryPost = !$embedded || (($_POST['temporary_registration'] ?? '') === 'school-extracurricular');
    $temporaryPost = $isTemporaryPost ? $_POST : [];
    $allowed = TemporaryStudentRegistrationCan($embedded);
    $state = compact('embedded', 'allowed', 'temporarySessionKey', 'temporaryTokenKey', 'temporaryChangeKey', 'temporaryReturnPath');
    if (!$allowed) {
        $state['forbidden'] = $temporaryPost !== [];
        return $state;
    }
    if (isset($temporaryPost['search-tel']) || isset($temporaryPost['search-melli']) || isset($temporaryPost['submit-student'])) {
        $token = (string)($temporaryPost['token'] ?? '');
        if ($token === '' || !hash_equals((string)($_SESSION[$temporaryTokenKey] ?? ''), $token)) {
            return $state + ['erortoken' => 1];
        }
    }
    if (isset($_GET[$temporaryChangeKey])) {
        unset($_SESSION[$temporarySessionKey]);
        header('location: ' . url($temporaryReturnPath));
        die();
    }
    if (isset($temporaryPost['search-melli'])) {
        if (isset($_SESSION[$temporaryTokenKey]) and $_SESSION[$temporaryTokenKey] == $temporaryPost['token']) {
            if ($temporaryPost['codemeli'] != '') {
                $codemeli = trim($temporaryPost['codemeli']);
                $melliat = $temporaryPost['melliat'];
                if ($melliat == '01') {
                    if (check_national_code($codemeli)) {
                        if (!checkCodemeli($codemeli)) {
                            $_SESSION[$temporarySessionKey] = array(
                                "codemeli" => $temporaryPost['codemeli'],
                                "melliat" => 'ایرانی'
                            );
                        } else {
                            $havemeli = 1;
                        }
                    } else {
                        $erormeli = 1;
                    }
                } else {
                    if (!checkCodemeli($codemeli)) {
                        $_SESSION[$temporarySessionKey] = array(
                            "codemeli" => $temporaryPost['codemeli'],
                            "melliat" => 'اتباع'
                        );
                    } else {
                        $havemeli = 1;
                    }
                }
            }
        } else {
            $erortoken = 1;
        }
    }
    if (isset($temporaryPost['search-tel'])) {
        if (isset($_SESSION[$temporaryTokenKey]) and $_SESSION[$temporaryTokenKey] == $temporaryPost['token']) {
            if ($temporaryPost['tel'] != '') {
                if (isset($temporaryPost['tel-trust'])) {
                    if (strlen($temporaryPost['tel']) == 11) {
                        $_SESSION[$temporarySessionKey] =
                            array(
                                "tel" => $temporaryPost['tel'],
                                "tel-trust" => 1
                            );
                    } else {
                        $erormeli = 1;
                    }
                } else {
                    if (!checkTell2($temporaryPost['tel'])) {        // Check Tell
                        if (strlen($temporaryPost['tel']) == 11) {
                            $_SESSION[$temporarySessionKey] =
                                array(
                                    "tel" => $temporaryPost['tel']
                                );
                        }
                    } else {
                        $havetel = 1;
                    }
                }
            }
        }
    }
    $cities = null;
    if (isset($temporaryPost['state'])) {
        $state = $temporaryPost['state'];
        $res = $conn->query("SELECT * FROM city WHERE state_id=$state");
        $cities = array();
        while ($row = $res->fetch()) {
            $cities[] = $row;
        }
        $final = '';
        foreach ($cities as $city) {
            $id = $city['id'];
            $name = $city['name'];
            $final .= "<option value=$name>$name</option>";
        }
        die($final);
    }


    $result = $conn->query("SELECT * FROM `state`");

    $states = array();
    while ($row = $result->fetch()) {
        $states[] = $row;
    }
    if (isset($temporaryPost['submit-student']) and isset($_SESSION[$temporarySessionKey])) {
        if (
            $temporaryPost['fname-student'] != NULL and $temporaryPost['lname-student'] != NULL and $temporaryPost['paye-student'] != NULL and $temporaryPost['reshte-student'] != NULL and $temporaryPost['gender-student'] != NULL
        ) {
            $ip = GetIp();
            $os = GetOs();
            $generalGrades = inst_is('school') ? ['1', '2', '3', '4', '5', '6', '7', '8', '9'] : ['5', '6', '7', '8', '9'];
            if (in_array((string)$temporaryPost['paye-student'], $generalGrades, true)) {
                $temporaryPost['reshte-student'] = 'ندارد';
            } else {
                switch ($temporaryPost['reshte-student']) {
                    case '0':
                        $temporaryPost['reshte-student'] = "ریاضی";
                        break;
                    case '1':
                        $temporaryPost['reshte-student'] = "تجربی";
                        break;
                    case '2':
                        $temporaryPost['reshte-student'] = "انسانی";
                        break;
                    case "3":
                        $temporaryPost['reshte-student'] = 'فنی و حرفه ای';
                        break;
                }
            }
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $fnameStudent = trim($temporaryPost['fname-student']);
            $lnameStudent = trim($temporaryPost['lname-student']);
            $genderStudent = trim($temporaryPost['gender-student']);
            if (!checkTell2($_SESSION[$temporarySessionKey]['tel']) || isset($_SESSION[$temporarySessionKey]['tel-trust'])) {
                $codemeliValue = $_SESSION[$temporarySessionKey]['tel'];

                    // If checking against dbpersonal shows codemeli exists, append a unique timestamp/rand suffix
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM dbpersonal WHERE codemeli = ?");
                $checkStmt->execute([$codemeliValue]);
                if ($checkStmt->fetchColumn() > 0) {
                    // If it exists and starts with '09', change the prefix '09' to '90'
                    if (strpos($_SESSION[$temporarySessionKey]['tel'], '09') === 0) {
                        $codemeliValue = '90' . substr($_SESSION[$temporarySessionKey]['tel'], 2);
                    } else {
                        // Fallback prefix swap if it starts with '0'
                        $codemeliValue = '9' . substr($_SESSION[$temporarySessionKey]['tel'], 1);
                    }
                }
                hf_control_require_seat($conn, hf_control_login_id($codemeliValue), 'student');
                $insert = 'INSERT INTO dbpersonal (codemeli,fname,lname,gender,tel1,paye,reshtestudent,datecreated,timecreated,ipcreated,oscreated,creator) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
                $insert_student = $conn->prepare($insert);
                $insert_student->execute([$codemeliValue, $fnameStudent, $lnameStudent, $genderStudent, $_SESSION[$temporarySessionKey]['tel'], $temporaryPost['paye-student'], $temporaryPost['reshte-student'], $datenow, $timenow, $ip, $os, $_SESSION['admin']['user']]);

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
                $insert_user = 'INSERT INTO dblogin (username,password,`role`) VALUES (?,?,?)';
                $query_user = $conn->prepare($insert_user);
                $query_user->execute([$codemeliValue, $codemeliValue, 1]);
                $oesSync = cross_panel_sync_oes_student(
                    (string)$codemeliValue,
                    $fnameStudent,
                    $lnameStudent,
                    (string)$codemeliValue
                );
                if (empty($oesSync['ok'])) {
                    $_SESSION['cross_panel_warning'] = (string)($oesSync['message'] ?? 'ساخت حساب دانش‌آموز در سامانه آزمون انجام نشد');
                }
                $text = $codemeliValue . ';' . $codemeliValue;
                SendSms($_SESSION[$temporarySessionKey]['tel'], $text, 171697, $codemeliValue);
                if ($embedded) {
                    $_SESSION['add-course2'] = ['tel' => $codemeliValue];
                }
                unset($_SESSION[$temporarySessionKey]);
                $verify = 100;
            }
        }

    }
    foreach (['states', 'verify', 'erormeli', 'havemeli', 'havetel', 'erortoken', 'timeout'] as $key) {
        if (isset($$key)) $state[$key] = $$key;
    }
    return $state;
}

function RenderTemporaryStudentRegistration(array $state): void
{
    if (empty($state['allowed'])) return;
    extract($state, EXTR_SKIP);
    require BASE_PATH . '/admin/Student/temporary-registration-form.php';
}
