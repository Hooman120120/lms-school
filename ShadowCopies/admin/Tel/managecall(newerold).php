<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//if (!Permision('permision_managecall')) {
//    header('location: ' . url('admin/index.php'));
//    die();
//}

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM tel_db WHERE id =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);

            break;
        case '02':
            $swr = 'SELECT * FROM tel_db WHERE dakheli =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
    }
}
if(isset($_GET['delgroup'])){
    $id=intval($_GET['delgroup']);
    $desql='DELETE FROM tel_db WHERE `group`=?';
    $qsel=$conn->prepare($desql);
    $qsel->execute([$id]);
    header('location: managecall.php');
}
if(isset($_GET['deluser'])){
    $id=intval($_GET['deluser']);
    $desql='DELETE FROM tel_db WHERE `id`=?';
    $qsel=$conn->prepare($desql);
    $qsel->execute([$id]);
    header('location: managecall.php');
}

//manage calls جستجوی تلفن

if (isset($_GET['call-mob11'])) {
    $call = retTelDb($_GET['call-mob11']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob11'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-mob22'])) {
    $call = retTelDb($_GET['call-mob22']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob22'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-mob33'])) {
    $call = retTelDb($_GET['call-mob33']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob33'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-static11'])) {
    $call = retTelDb($_GET['call-static11']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-static11'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-static22'])) {
    $call = retTelDb($_GET['call-static22']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-static22'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-static33'])) {
    $call = retTelDb($_GET['call-static33']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-static33'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $in = 'INSERT INTO tel_group (namegroup) VALUES (?)';
        $qin = $conn->prepare($in);
        $qin->execute([$_POST['namegroup']]);

    }
}
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $del = 'DELETE FROM tel_group WHERE id=?';
    $qdel = $conn->prepare($del);
    $qdel->execute([$id]);
    header('location: add-group.php');
    die();
}
if (isset($_POST['save-update'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $code=intval($_POST['id']);
        $upd='UPDATE tel_group SET namegroup=? WHERE id=?';
        $actupd=$conn->prepare($upd);
        $actupd->execute([$_POST['namegroup'],$code]);
        header('location: add-group.php');
        die();
    }
}

if (isset($_POST['search'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $_SESSION['uncall'] = $_POST['group'];
        header('location: managecall.php');
    }
}
if (isset($_GET['newgroup'])) {
    unset($_SESSION['uncall']);
    header('location: ' . url('admin/Tel/managecall.php'));
    die();
}


//uncall  section
if (isset($_GET['uncall-mob1'])) {
    $call = retTelDb($_GET['uncall-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['uncall-mob1'],
            "box" => 'mob1',
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['uncall-mob2'])) {
    $call = retTelDb($_GET['uncall-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['uncall-mob2'],
            "box" => 'mob2',
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

if (isset($_GET['uncall-mob3'])) {
    $call = retTelDb($_GET['uncall-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['uncall-mob3'],
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "box" => 'mob3',
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

if (isset($_GET['uncall-static1'])) {
    $call = retTelDb($_GET['uncall-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['uncall-static1'],
            "box" => 'static1',
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

if (isset($_GET['uncall-static2'])) {
    $call = retTelDb($_GET['uncall-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['uncall-static2'],
            "box" => 'static2',
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

if (isset($_GET['uncall-static3'])) {
    $call = retTelDb($_GET['uncall-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['uncall-static3'],
            "box" => 'static3',
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
//uncall section ends here
//-----------------------------------------------------------------------
//unresponse section starts here

if (isset($_GET['unresponsecall-mob1'])) {
    $call = retTelDb($_GET['unresponsecall-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['unresponsecall-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 5,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['unresponsecall-mob2'])) {
    $call = retTelDb($_GET['unresponsecall-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['unresponsecall-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 5,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['unresponsecall-mob3'])) {
    $call = retTelDb($_GET['unresponsecall-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['unresponsecall-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => 5,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['unresponsecall-static1'])) {
    $call = retTelDb($_GET['unresponsecall-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['unresponsecall-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 5,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['unresponsecall-static2'])) {
    $call = retTelDb($_GET['unresponsecall-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['unresponsecall-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 5,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['unresponsecall-static3'])) {
    $call = retTelDb($_GET['unresponsecall-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['unresponsecall-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 5,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
//unresponse section ends here
//-----------------------------------------------------------------------
//call-finish starts here

if (isset($_GET['callfinish-mob1'])) {
    $call = retTelDb($_GET['callfinish-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['callfinish-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 2,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['callfinish-mob2'])) {
    $call = retTelDb($_GET['callfinish-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['callfinish-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 2,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['callfinish-mob3'])) {
    $call = retTelDb($_GET['callfinish-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['callfinish-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => 2,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['callfinish-static1'])) {
    $call = retTelDb($_GET['callfinish-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['callfinish-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 2,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['callfinish-static2'])) {
    $call = retTelDb($_GET['callfinish-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['callfinish-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 2,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['callfinish-static3'])) {
    $call = retTelDb($_GET['callfinish-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['callfinish-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 2,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
//call-finish ends here
//-----------------------------------------------------------------------
//dublicateTel starts here

if (isset($_GET['duplicatecall-mob1'])) {
    $call = retTelDb($_GET['duplicatecall-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['duplicatecall-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 4,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['duplicatecall-mob2'])) {
    $call = retTelDb($_GET['duplicatecall-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['duplicatecall-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 4,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['duplicatecall-mob3'])) {
    $call = retTelDb($_GET['duplicatecall-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['duplicatecall-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => 4,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['duplicatecall-static1'])) {
    $call = retTelDb($_GET['duplicatecall-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['duplicatecall-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 4,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['duplicatecall-static2'])) {
    $call = retTelDb($_GET['duplicatecall-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['duplicatecall-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],

            "act" => 4,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['duplicatecall-static3'])) {
    $call = retTelDb($_GET['duplicatecall-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['duplicatecall-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 4,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
//duplicate ends here
//-----------------------------------------------------------------------
//begin نیاز به تماس مجدد  start heres

if (isset($_GET['begincall-mob1'])) {
    $call = retTelDb($_GET['begincall-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['begincall-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 1,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['begincall-mob2'])) {
    $call = retTelDb($_GET['begincall-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['begincall-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 1,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        //        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['begincall-mob3'])) {
    $call = retTelDb($_GET['begincall-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['begincall-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => 1,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        //        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['begincall-static1'])) {
    $call = retTelDb($_GET['begincall-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['begincall-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 1,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        //        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['begincall-static2'])) {
    $call = retTelDb($_GET['begincall-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['begincall-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],

            "act" => 1,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        //        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['begincall-static3'])) {
    $call = retTelDb($_GET['begincall-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['begincall-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 1,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        //        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
//begin نیاز به تماس مجدد  ENDS here
//-----------------------------------------------------------------------
//wrong شماره های اشتباه  starts here


if (isset($_GET['wrongcall-mob1'])) {
    $call = retTelDb($_GET['wrongcall-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['wrongcall-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 3,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['wrongcall-mob2'])) {
    $call = retTelDb($_GET['wrongcall-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['wrongcall-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 3,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['wrongcall-mob3'])) {
    $call = retTelDb($_GET['wrongcall-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['wrongcall-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => 3,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['wrongcall-static1'])) {
    $call = retTelDb($_GET['wrongcall-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['wrongcall-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 3,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['wrongcall-static2'])) {
    $call = retTelDb($_GET['wrongcall-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['wrongcall-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],

            "act" => 3,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}
if (isset($_GET['wrongcall-static3'])) {
    $call = retTelDb($_GET['wrongcall-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['wrongcall-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => 3,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

//wrong شماره های اشتباه  ENDS here
//-----------------------------------------------------------------------
//anytel همه مخاطبین  starts here

if (isset($_GET['anytelcall-mob1'])) {
    $call = retTelDb($_GET['anytelcall-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['anytelcall-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['anytelcall-mob2'])) {
    $call = retTelDb($_GET['anytelcall-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['anytelcall-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['anytelcall-mob3'])) {
    $call = retTelDb($_GET['anytelcall-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['anytelcall-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['anytelcall-static1'])) {
    $call = retTelDb($_GET['anytelcall-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['anytelcall-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['anytelcall-static2'])) {
    $call = retTelDb($_GET['anytelcall-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['anytelcall-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['anytelcall-static3'])) {
    $call = retTelDb($_GET['anytelcall-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['anytelcall-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

    }
}

//importtel

if (isset($_POST['add-number'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $group = $_POST['group'];
        $agent = $_SERVER["HTTP_USER_AGENT"];
        $mor = NULL;
        if (preg_match('/MSIE (\d+\.\d+);/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Chrome[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Edge\/\d+/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Firefox[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'application/vnd.ms-excel';
        } else if (preg_match('/OPR[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Safari[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        }
        if ($_FILES['fileToUpload']['type'] != $mor) {
            echo "<script>alert('   فایل  مجاز برای  بارگذاری CSV ');</script>";

        } else {

            if (isset($_POST['delete-old'])) {
                $dele = 'DELETE FROM tel_db WHERE `group`=?';
                $act = $conn->prepare($dele);
                $act->execute([$group]);

            }
            $target_dir='../../assets/doc/';
            $hashFile = bin2hex(random_bytes(8));
            $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);

            if (

                move_uploaded_file($_FILES['fileToUpload']['tmp_name'],$target_file ) ) {
                $CSVfp = fopen($target_file, "r");
                if ($CSVfp !== FALSE) {
                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 1000000, ",");

                        if ($data) {


                            $sql = "INSERT INTO tel_db (`group`,`fname`,`lname`,`guidance`,`state`,`city`,`mob1`,`mob2`,`mob3`,`tel1`,`tel2`,`tel3`,`dakheli`)
VALUES ('" . $group . " ','" . $data[0] . "', '" . $data[1] . "','" . $data[2] . "','" . $data[3] . "','" . $data[4] . "','" . $data[5] . "','" . $data[6] . "','" . $data[7] . "','" . $data[8] . "','" . $data[9] . "','" . $data[10] . "','" . $data[11] . "');\r\n\n";
                            $ex = $conn->exec($sql);

                        }
                    }
                }
                fclose($CSVfp);
                unlink($target_file);
                $ok=1;

            }
        }
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> مدیریت تماس امن |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!--            section 0             -->
                <div class="card custom-card">
                    <div class="form-group mb-3 m-4 ">
                        <label for="operationSelect" class="h5 mb-4">
                            نوع گزارش تماس را انتخاب کنید
                        </label>
                        <select id="operationSelect"
                                class="form-select"
                                onchange="Choose_operation(this.value)">
                            <option value=""> لطفاً نوع لیست تماس را انتخاب کنید </option>
                            <option value="uncall">تماس‌های گرفته نشده</option>
                            <option value="unresponse">عدم پاسخ</option>
                            <option value="callfinish">تماس های خاتمه یافته</option>
                            <option value="duplicatecall">مخاطبین تکراری</option>
                            <option value="begincall">نیاز به تماس مجدد</option>
                            <option value="wrongcall">شماره های اشتباه</option>
                            <option value="anytelcall">همه مخاطبین من</option>

                        </select>
                    </div>

                    <div id="uncallSection" style="display: none;"><!-- hidden by default -->
                        <div class="card-body">
                            <div class="row">
                                <div class="col-9">
                                    <div>
                                        <h6 class="main-content-label mb-1">تماس های گرفته نشده</h6>
                                        <p class="text-muted card-sub-title">تماس های برقرار نشده تا کنون</p>
                                    </div>
                                </div>
                                <div class="col-3 text-left">
                                    <a href="?newgroup" class="btn btn-sm btn-primary">
                                        <i class="fe fe-refresh-cw"></i>گروه مخاطب جدید
                                    </a>
                                </div>
                            </div>

                            <section class="section">
                                <div class="row">
                                    <div class="card">
                                        <?php if (!isset($_SESSION['uncall'])) { ?>
                                            <form method="post" data-parsley-validate="">
                                                <div class="row">
                                                    <div class="col-xl-6 mb-2">
                                                        <label for="sens" class="form-label mg-t-15">
                                                            انتخاب گروه مخاطبان
                                                            <span style="color: red">*</span>
                                                        </label>
                                                        <select class="form-control select2"
                                                                name="group"
                                                                aria-describedby="basic-addon2" required>
                                                            <option label="گروه "></option>
                                                            <?php
                                                            $sql = 'SELECT * FROM tel_group ';
                                                            $query = $conn->prepare($sql);
                                                            $query->execute();
                                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                            if ($query->rowCount() > 0) {
                                                                foreach ($result as $res) { ?>
                                                                    <option value="<?php echo $res->id ?>">
                                                                        <?php echo $res->namegroup ?>
                                                                    </option>
                                                                <?php }
                                                            } ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $token ?>">

                                                <div class="text-center mg-10">
                                                    <button type="submit" class="btn btn-success" name="search">
                                                        <i class="fa fa-database"></i>
                                                        انتخاب گروه مخاطبین
                                                    </button>
                                                </div>
                                            </form>
                                        <?php } else { ?>
                                            <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                            <div class="header-message">
                                                نام گروه مخاطبین:
                                                <span class="text-warning">
                                <?php echo $group['namegroup'] ?>
                            </span>
                                            </div>
                                            <div class="header-message">
                                                تعداد کل این مخاطبین :
                                                <span class="text-primary">
                                <?php
                                $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                $wdr = $conn->prepare($count);
                                $wdr->execute([0, $_SESSION['admin']['dakheli'], $_SESSION['uncall']]);
                                $recount = $wdr->fetch();
                                echo $recount['cou'];
                                ?>
                            </span>
                                            </div>

                                            <table  class=" call-report-table table text-nowrap text-md-nowrap table-bordered ">
                                                <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>راهنما</th>
                                                    <th>استان</th>
                                                    <th>شهر</th>
                                                    <th>اقدام</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $sql1 = 'SELECT * FROM tel_db WHERE metod=? AND act=? AND dakheli=? AND `group`=?';
                                                $query1 = $conn->prepare($sql1);
                                                $query1->execute([0, 0, $_SESSION['admin']['dakheli'], $_SESSION['uncall']]);
                                                $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                                if ($query1->rowCount() > 0) {
                                                    foreach ($resy as $ret) { ?>
                                                        <tr>
                                                            <td><?php echo $ret->id ?></td>
                                                            <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                            <td><?php echo $ret->guidance ?></td>
                                                            <td><?php echo $ret->state ?></td>
                                                            <td><?php echo $ret->city ?></td>
                                                            <td>
                                                                <div class="text-wrap">
                                                                    <div class="text-center">
                                                                        <div class="dropdown">
                                                                            <button aria-expanded="false"
                                                                                    aria-haspopup="true"
                                                                                    class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                    data-bs-toggle="dropdown"
                                                                                    type="button">
                                                                                اقدام
                                                                                <i class="fas fa-caret-down mr-1"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu tx-8">
                                                                                <a class="dropdown-item"
                                                                                   href="?uncall-mob1=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-call"></i>تماس موبایل 1
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                   href="?uncall-mob2=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-call"></i>تماس موبایل 2
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                   href="?uncall-mob3=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-call"></i>تماس موبایل 3
                                                                                </a>
                                                                                <hr>
                                                                                <a class="dropdown-item"
                                                                                   href="?uncall-static1=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-outgoing"></i>تماس ثابت 1
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                   href="?uncall-static2=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-outgoing"></i>تماس ثابت 2
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                   href="?uncall-static3=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-outgoing"></i>تماس ثابت 3
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php }
                                                } ?>
                                                </tbody>
                                            </table>
                                        <?php } ?>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                   <div id="unresponseSection" style="display: none;"><!-- hidden by default -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <div>
                                    <h6 class="main-content-label mb-1">تماس های عدم پاسخ</h6>
                                    <p class="text-muted card-sub-title"> تماس های پاسخ داده نشده
                                    </p>
                                </div>
                            </div>
                            <div class="col-3 text-left">
                                <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                            class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                            </div>
                        </div>
                        <section class="section">
                            <div class="row">
                                <div class="card">
                                    <?php if (!isset($_SESSION['uncall'])) { ?>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-xl-6 mb-2">
                                                    <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                        مخاطبان
                                                        <span
                                                                style="color: red">*</span></label>
                                                    <select class="form-control select2 "
                                                            name="group"
                                                            aria-describedby="basic-addon2" required>
                                                        <option label="گروه ">
                                                        </option>
                                                        <?php
                                                        $sql = 'SELECT * FROM tel_group ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {


                                                                ?>
                                                                <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                            <?php }
                                                        }
                                                        ?>

                                                    </select>
                                                </div>

                                            </div>

                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $token ?>">

                                            <div class="text-center mg-10">
                                                <button type="submit" class="btn btn-success "
                                                        name="search"><i
                                                            class="fa fa-database"></i>
                                                    انتخاب گروه مخاطبین
                                                </button>
                                            </div>
                                        </form>
                                        <!--                                        end step 1-->
                                    <?php } else { ?>
                                        <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                        <div class="header-message">نام گروه مخاطبین: <span
                                                    class="text-warning"><?php echo $group['namegroup'] ?></span>
                                        </div>
                                        <div class="header-message">
                                            تعداد کل این مخاطبین  :
                                            <span
                                                    class="text-primary"><?php
                                                $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                                $wdr = $conn->prepare($count);
                                                $wdr->execute([5,$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                $recount = $wdr->fetch();
                                                echo $recount['cou'];
                                                ?></span>
                                        </div>

                                        <table  class="call-report-table table text-nowrap text-md-nowrap table-bordered "
                                        >
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>راهنما</th>
                                                <th>استان</th>
                                                <th>شهر</th>
                                                <th>اقدام</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql1 = 'SELECT * FROM tel_db WHERE  act=? AND dakheli=? AND `group`=?';
                                            $query1 = $conn->prepare($sql1);
                                            $query1->execute([5, $_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                            $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                            if ($query1->rowCount() > 0) {
                                                foreach ($resy as $ret) {

                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>
                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php echo $ret->guidance ?></td>
                                                        <td><?php echo $ret->state ?></td>
                                                        <td><?php echo $ret->city ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?unresponsecall-mob1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?unresponsecall-mob2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?unresponsecall-mob3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?unresponsecall-static1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?unresponsecall-static2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?unresponsecall-static3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>
                                                                            <hr>
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('telreport.php?id='+'<?php echo $ret->id ?>','تغییر وضعیت'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i> مشاهده گزارش

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                        </td>

                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>


                                    <?php } ?>


                                </div>
                            </div>
                        </section>
                    </div>
                   </div>

                   <div id="call-finishSection" style="display: none;"><!-- hidden by default -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <div>
                                    <h6 class="main-content-label mb-1">تماس های خاتمه یافته</h6>
                                    <p class="text-muted card-sub-title"> تماس های پایان یافته
                                    </p>
                                </div>
                            </div>
                            <div class="col-3 text-left">
                                <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                            class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                            </div>
                        </div>
                        <section class="section">
                            <div class="row">
                                <div class="card">
                                    <?php if (!isset($_SESSION['uncall'])) { ?>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-xl-6 mb-2">
                                                    <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                        مخاطبان
                                                        <span
                                                                style="color: red">*</span></label>
                                                    <select class="form-control select2 "
                                                            name="group"
                                                            aria-describedby="basic-addon2" required>
                                                        <option label="گروه ">
                                                        </option>
                                                        <?php
                                                        $sql = 'SELECT * FROM tel_group ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {


                                                                ?>
                                                                <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                            <?php }
                                                        }
                                                        ?>

                                                    </select>
                                                </div>

                                            </div>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $token ?>">

                                            <div class="text-center mg-10">
                                                <button type="submit" class="btn btn-success "
                                                        name="search"><i
                                                            class="fa fa-database"></i>
                                                    انتخاب گروه مخاطبین
                                                </button>
                                            </div>
                                        </form>
                                        <!--                                        end step 1-->
                                    <?php } else { ?>
                                        <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                        <div class="header-message">نام گروه مخاطبین: <span
                                                    class="text-warning"><?php echo $group['namegroup'] ?></span>
                                        </div>
                                        <div class="header-message">
                                            تعداد کل این مخاطبین  :
                                            <span
                                                    class="text-primary"><?php
                                                $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                                $wdr = $conn->prepare($count);
                                                $wdr->execute([2,$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                $recount = $wdr->fetch();
                                                echo $recount['cou'];
                                                ?></span>
                                        </div>

                                        <table  class="call-report-table table text-nowrap text-md-nowrap table-bordered"
                                        >
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>راهنما</th>
                                                <th>استان</th>
                                                <th>شهر</th>
                                                <th>اقدام</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql1 = 'SELECT * FROM tel_db WHERE  act=? AND dakheli=? AND `group`=?';
                                            $query1 = $conn->prepare($sql1);
                                            $query1->execute([2, $_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                            $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                            if ($query1->rowCount() > 0) {
                                                foreach ($resy as $ret) {

                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>
                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php echo $ret->guidance ?></td>
                                                        <td><?php echo $ret->state ?></td>
                                                        <td><?php echo $ret->city ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?callfinish-mob1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?callfinish-mob2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?callfinish-mob3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?callfinish-static1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?callfinish-static2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?callfinish-static3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>
                                                                            <hr>
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('telreport.php?id='+'<?php echo $ret->id ?>','تغییر وضعیت'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i> مشاهده گزارش

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                        </td>

                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>


                                    <?php } ?>


                                </div>
                            </div>
                        </section>
                    </div>
                   </div>

                  <div id="duplicatecallSection" style="display: none;"><!-- hidden by default -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <div>
                                    <h6 class="main-content-label mb-1">مخاطبین تکراری</h6>
                                    <p class="text-muted card-sub-title"> شماره تکراری ثبت شده بود
                                    </p>
                                </div>
                            </div>
                            <div class="col-3 text-left">
                                <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                            class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                            </div>
                        </div>
                        <section class="section">
                            <div class="row">
                                <div class="card">
                                    <?php if (!isset($_SESSION['uncall'])) { ?>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-xl-6 mb-2">
                                                    <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                        مخاطبان
                                                        <span
                                                                style="color: red">*</span></label>
                                                    <select class="form-control select2 "
                                                            name="group"
                                                            aria-describedby="basic-addon2" required>
                                                        <option label="گروه ">
                                                        </option>
                                                        <?php
                                                        $sql = 'SELECT * FROM tel_group ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {


                                                                ?>
                                                                <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                            <?php }
                                                        }
                                                        ?>

                                                    </select>
                                                </div>

                                            </div>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $token ?>">

                                            <div class="text-center mg-10">
                                                <button type="submit" class="btn btn-success "
                                                        name="search"><i
                                                            class="fa fa-database"></i>
                                                    انتخاب گروه مخاطبین
                                                </button>
                                            </div>
                                        </form>
                                        <!--                                        end step 1-->
                                    <?php } else { ?>
                                        <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                        <div class="header-message">نام گروه مخاطبین: <span
                                                    class="text-warning"><?php echo $group['namegroup'] ?></span>
                                        </div>
                                        <div class="header-message">
                                            تعداد کل این مخاطبین  :
                                            <span
                                                    class="text-primary"><?php
                                                $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                                $wdr = $conn->prepare($count);
                                                $wdr->execute([4,$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                $recount = $wdr->fetch();
                                                echo $recount['cou'];
                                                ?></span>
                                        </div>

                                        <table  class="call-report-table table text-nowrap text-md-nowrap table-bordered">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>راهنما</th>
                                                <th>استان</th>
                                                <th>شهر</th>
                                                <th>اقدام</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql1 = 'SELECT * FROM tel_db WHERE  act=? AND dakheli=? AND `group`=?';
                                            $query1 = $conn->prepare($sql1);
                                            $query1->execute([4, $_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                            $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                            if ($query1->rowCount() > 0) {
                                                foreach ($resy as $ret) {

                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>
                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php echo $ret->guidance ?></td>
                                                        <td><?php echo $ret->state ?></td>
                                                        <td><?php echo $ret->city ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?duplicatecall-mob1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?duplicatecall-mob2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?duplicatecall-mob3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?duplicatecall-static1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?duplicatecall-static2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?duplicatecall-static3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>
                                                                            <hr>
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('telreport.php?id='+'<?php echo $ret->id ?>','تغییر وضعیت'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i> مشاهده گزارش

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                        </td>

                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>


                                    <?php } ?>


                                </div>
                            </div>
                        </section>
                    </div>
                   </div>

                    <div id="beginSection" style="display: none;"><!-- hidden by default -->
                        <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <div>
                                    <h6 class="main-content-label mb-1">نیاز به تماس مجدد</h6>
                                    <p class="text-muted card-sub-title"> تماس های نیاز به برقراری مجدد
                                    </p>
                                </div>
                            </div>
                            <div class="col-3 text-left">
                                <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                            class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                            </div>
                        </div>
                        <section class="section">
                            <div class="row">
                                <div class="card">
                                    <?php if (!isset($_SESSION['uncall'])) { ?>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-xl-6 mb-2">
                                                    <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                        مخاطبان
                                                        <span style="color: red">*</span></label>
                                                    <select class="form-control select2 " name="group"
                                                            aria-describedby="basic-addon2" required>
                                                        <option label="گروه ">
                                                        </option>
                                                        <?php
                                                        $sql = 'SELECT * FROM tel_group ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {


                                                                ?>
                                                                <option value="<?php echo $res->id ?>">
                                                                    <?php echo $res->namegroup ?>
                                                                </option>
                                                            <?php }
                                                        }
                                                        ?>

                                                    </select>
                                                </div>

                                            </div>

                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $token ?>">

                                            <div class="text-center mg-10">
                                                <button type="submit" class="btn btn-success " name="search"><i
                                                            class="fa fa-database"></i>
                                                    انتخاب گروه مخاطبین
                                                </button>
                                            </div>
                                        </form>
                                        <!--                                        end step 1-->
                                    <?php } else { ?>
                                        <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                        <div class="header-message">نام گروه مخاطبین: <span
                                                    class="text-warning"><?php echo $group['namegroup'] ?></span>
                                        </div>
                                        <div class="header-message">
                                            تعداد کل این مخاطبین :
                                            <span class="text-primary"><?php
                                                $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                                $wdr = $conn->prepare($count);
                                                $wdr->execute([1, $_SESSION['admin']['dakheli'], $_SESSION['uncall']]);
                                                $recount = $wdr->fetch();
                                                echo $recount['cou'];
                                                ?></span>
                                        </div>

                                        <table  class="call-report-table table text-nowrap text-md-nowrap table-bordered">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>راهنما</th>
                                                <th>استان</th>
                                                <th>شهر</th>
                                                <th>نوع دانش آموز</th>
                                                <th>تاریخ تماس بعدی</th>
                                                <th>اقدام</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql1 = 'SELECT * FROM tel_db INNER JOIN ( SELECT * FROM tel_detail d1 WHERE NOT EXISTS ( SELECT 1 FROM tel_detail d2 WHERE d2.dbtel = d1.dbtel AND ( d2.datecreated > d1.datecreated OR (d2.datecreated = d1.datecreated AND d2.timecreated > d1.timecreated) ) ) ) AS latest_detail ON tel_db.id = latest_detail.dbtel WHERE tel_db.act = ? AND tel_db.dakheli = ? AND tel_db.`group` = ? ORDER BY tel_db.nextcall;';
                                            $query1 = $conn->prepare($sql1);
                                            $query1->execute([1, $_SESSION['admin']['dakheli'], $_SESSION['uncall']]);
                                            $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                            if ($query1->rowCount() > 0) {
                                                foreach ($resy as $ret) {

                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>
                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php echo $ret->guidance ?></td>
                                                        <td><?php echo $ret->state ?></td>
                                                        <td><?php echo $ret->city ?></td>
                                                        <td><?php echo $ret->txt2 ?></td>
                                                        <td><?php
                                                            $dateno = $ret->nextcall;
                                                            $dateno = explode('-', $dateno);
                                                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                            $dateno = implode('/', $dateno);
                                                            echo $dateno ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown" type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?begincall-mob1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?begincall-mob2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?begincall-mob3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?begincall-static1=<?php echo $ret->id ?>">
                                                                                <i
                                                                                        class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?begincall-static2=<?php echo $ret->id ?>">
                                                                                <i
                                                                                        class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?begincall-static3=<?php echo $ret->id ?>">
                                                                                <i
                                                                                        class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>
                                                                            <hr>
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('telreport.php?id='+'<?php echo $ret->id ?>','تغییر وضعیت'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i>
                                                                                مشاهده گزارش

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                        </td>

                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>


                                    <?php } ?>


                                </div>
                            </div>
                        </section>
                    </div>
                   </div>

                    <div id="wrongSection" style="display: none;"><!-- hidden by default -->
                        <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <div>
                                    <h6 class="main-content-label mb-1">شماره های اشتباه</h6>
                                    <p class="text-muted card-sub-title"> شماره اشتباه و یا فرد مورد نظر نبود
                                    </p>
                                </div>
                            </div>
                            <div class="col-3 text-left">
                                <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                            class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                            </div>
                        </div>
                        <section class="section">
                            <div class="row">
                                <div class="card">
                                    <?php if (!isset($_SESSION['uncall'])) { ?>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-xl-6 mb-2">
                                                    <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                        مخاطبان
                                                        <span
                                                                style="color: red">*</span></label>
                                                    <select class="form-control select2 "
                                                            name="group"
                                                            aria-describedby="basic-addon2" required>
                                                        <option label="گروه ">
                                                        </option>
                                                        <?php
                                                        $sql = 'SELECT * FROM tel_group ';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute();
                                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                        if ($query->rowCount() > 0) {
                                                            foreach ($result as $res) {


                                                                ?>
                                                                <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                            <?php }
                                                        }
                                                        ?>

                                                    </select>
                                                </div>

                                            </div>

                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $token ?>">


                                            <div class="text-center mg-10">
                                                <button type="submit" class="btn btn-success "
                                                        name="search"><i
                                                            class="fa fa-database"></i>
                                                    انتخاب گروه مخاطبین
                                                </button>
                                            </div>
                                        </form>
                                        <!--                                        end step 1-->
                                    <?php } else { ?>
                                        <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                        <div class="header-message">نام گروه مخاطبین: <span
                                                    class="text-warning"><?php echo $group['namegroup'] ?></span>
                                        </div>
                                        <div class="header-message">
                                            تعداد کل این مخاطبین  :
                                            <span
                                                    class="text-primary"><?php
                                                $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                                $wdr = $conn->prepare($count);
                                                $wdr->execute([3,$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                $recount = $wdr->fetch();
                                                echo $recount['cou'];
                                                ?></span>
                                        </div>

                                        <table  class="call-report-table table text-nowrap text-md-nowrap table-bordered"
                                        >
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>راهنما</th>
                                                <th>استان</th>
                                                <th>شهر</th>
                                                <th>اقدام</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql1 = 'SELECT * FROM tel_db WHERE  act=? AND dakheli=? AND `group`=?';
                                            $query1 = $conn->prepare($sql1);
                                            $query1->execute([3, $_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                            $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                            if ($query1->rowCount() > 0) {
                                                foreach ($resy as $ret) {

                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>
                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php echo $ret->guidance ?></td>
                                                        <td><?php echo $ret->state ?></td>
                                                        <td><?php echo $ret->city ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?wrongcall-mob1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?wrongcall-mob2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?wrongcall-mob3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?wrongcall-static1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?wrongcall-static2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?wrongcall-static3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>
                                                                            <hr>
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('telreport.php?id='+'<?php echo $ret->id ?>','تغییر وضعیت'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i> مشاهده گزارش

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                        </td>

                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>


                                    <?php } ?>


                                </div>
                            </div>
                        </section>
                    </div>



                </div>

                    <div id="anytelSection" style="display: none;"><!-- hidden by default -->
                        <div class="card-body">
                            <div class="row">
                                <div class="col-9">
                                    <div>
                                        <h6 class="main-content-label mb-1">انتخاب گروه مخاطبین</h6>
                                        <p class="text-muted card-sub-title"> همه مخاطبین من
                                        </p>
                                    </div>
                                </div>
                                <div class="col-3 text-left">
                                    <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                                class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                                </div>
                            </div>
                            <section class="section">
                                <div class="row">
                                    <div class="card">
                                        <?php if (!isset($_SESSION['uncall'])) { ?>
                                            <form method="post" data-parsley-validate="">
                                                <div class="row">
                                                    <div class="col-xl-6 mb-2">
                                                        <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                            مخاطبان
                                                            <span
                                                                    style="color: red">*</span></label>
                                                        <select class="form-control select2 "
                                                                name="group"
                                                                aria-describedby="basic-addon2" required>
                                                            <option label="گروه ">
                                                            </option>
                                                            <?php
                                                            $sql = 'SELECT * FROM tel_group ';
                                                            $query = $conn->prepare($sql);
                                                            $query->execute();
                                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                            if ($query->rowCount() > 0) {
                                                                foreach ($result as $res) {


                                                                    ?>
                                                                    <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                                <?php }
                                                            }
                                                            ?>

                                                        </select>
                                                    </div>

                                                </div>

                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $token ?>">

                                                <div class="text-center mg-10">
                                                    <button type="submit" class="btn btn-success "
                                                            name="search"><i
                                                                class="fa fa-database"></i>
                                                        انتخاب گروه مخاطبین
                                                    </button>
                                                </div>
                                            </form>
                                            <!--                                        end step 1-->
                                        <?php } else { ?>
                                            <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                            <div class="header-message">نام گروه مخاطبین: <span
                                                        class="text-warning"><?php echo $group['namegroup'] ?></span>
                                            </div>
                                            <div class="header-message">
                                                تعداد کل مخاطبین من :
                                                <span
                                                        class="text-primary"><?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE dakheli=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?></span>
                                            </div>

                                            <table  class="call-report-table table text-nowrap text-md-nowrap table-bordered"
                                            >
                                                <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>راهنما</th>
                                                    <th>استان</th>
                                                    <th>شهر</th>
                                                    <th>وضعیت</th>
                                                    <th>اقدام</th>

                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $sql1 = 'SELECT * FROM tel_db WHERE  dakheli=? AND `group`=?';
                                                $query1 = $conn->prepare($sql1);
                                                $query1->execute([$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                                if ($query1->rowCount() > 0) {
                                                    foreach ($resy as $ret) {

                                                        ?>
                                                        <tr>
                                                            <td><?php echo $ret->id ?></td>
                                                            <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                            <td><?php echo $ret->guidance ?></td>
                                                            <td><?php echo $ret->state ?></td>
                                                            <td><?php echo $ret->city ?></td>
                                                            <td><?php
                                                                switch ($ret->act) {
                                                                    case '0':
                                                                        $act = 'تماس گرفته نشده ';
                                                                        break;
                                                                    case '1':
                                                                        $act = 'نیاز به تماس مجدد';
                                                                        break;
                                                                    case '2':
                                                                        $act = 'تماس پایان یافته';
                                                                        break;
                                                                    case '3':
                                                                        $act = 'شماره اشتباه';
                                                                        break;
                                                                    case '4':
                                                                        $act = 'شماره تکراری';
                                                                        break;
                                                                    case '5':
                                                                        $act = 'عدم پاسخ';
                                                                        break;
                                                                }
                                                                echo $act;
                                                                ?></td>
                                                            <td>
                                                                <div class="text-wrap">
                                                                    <div class="text-center">
                                                                        <div class="dropdown">
                                                                            <button aria-expanded="false"
                                                                                    aria-haspopup="true"
                                                                                    class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                    data-bs-toggle="dropdown"
                                                                                    type="button">
                                                                                اقدام <i
                                                                                        class="fas fa-caret-down mr-1"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu tx-8">
                                                                                <a class="dropdown-item  "
                                                                                   href="?anytelcall-mob1=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-call"></i>تماس
                                                                                    موبایل 1
                                                                                </a>
                                                                                <a class="dropdown-item  "
                                                                                   href="?anytelcall-mob2=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-call"></i>تماس
                                                                                    موبایل 2
                                                                                </a>

                                                                                <a class="dropdown-item  "
                                                                                   href="?anytelcall-mob3=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-call"></i>تماس
                                                                                    موبایل 3
                                                                                </a>
                                                                                <hr>
                                                                                <a class="dropdown-item text-info "
                                                                                   href="?anytelcall-static1=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-outgoing"></i>تماس
                                                                                    ثابت 1
                                                                                </a>
                                                                                <a class="dropdown-item text-info "
                                                                                   href="?anytelcall-static2=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-outgoing"></i>تماس
                                                                                    ثابت 2
                                                                                </a>
                                                                                <a class="dropdown-item text-info "
                                                                                   href="?anytelcall-static3=<?php echo $ret->id ?>">
                                                                                    <i class="fe fe-phone-outgoing"></i>تماس
                                                                                    ثابت 3
                                                                                </a>
                                                                                <hr>
                                                                                <div class="dropdown-item text-success "
                                                                                     onclick="window.open('telreport.php?id='+'<?php echo $ret->id ?>','تغییر وضعیت'
                                                                                             ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-check-square"></i>
                                                                                    مشاهده گزارش

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>


                                                            </td>

                                                        </tr>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                                </tbody>
                                            </table>


                                        <?php } ?>


                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>


                    </div>


                        <!--                first section                 -->
                <?php if (Permision('permision_addcall')) { ?>
                <div>
                    <div class="row row-sm">
                        <?php if (!isset($_GET['edit'])) { ?>
                            <div class="col-lg-6" >
                                <div class="card custom-card" >
                                    <div class="card-body" >
                                        <div class="col-10">
                                            <div>
                                                <h6 class="main-content-label mb-1">ایجاد گروه تماس جدید </h6>
                                                <p class="text-muted card-sub-title">
                                                </p>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">

                                            <div class="col-xl-12">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام گروه تماس:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="namegroup"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="">
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $token ?>">
                                                </div>
                                            </div>
                                            <div class="mt-3 text-center">
                                                <button type="submit"
                                                        name="save"
                                                        class="btn btn-success  mr-1">
                                                    ثبت و ایجاد گروه تماس
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="col-lg-6" >
                                <div class="card custom-card"  >
                                    <div class="card-body" >
                                        <div class="col-10">
                                            <div>
                                                <h6 class="main-content-label mb-1">ویرایش گروه تماس </h6>
                                                <p class="text-muted card-sub-title">
                                                </p>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <?php
                                            $irancel = 'SELECT * FROM tel_group WHERE id=?';
                                            $rightel = $conn->prepare($irancel);
                                            $rightel->execute([$_GET['edit']]);
                                            if ($rightel->rowCount() > 0) {
                                                $talia = $rightel->fetch();

                                                ?>
                                                <div class="col-xl-12">
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                نام گروه تماس:
                                                                <span
                                                                        class="tx-danger">*</span></div>
                                                        </div>
                                                        <input class="form-control" name="namegroup"
                                                               aria-describedby="basic-addon2"
                                                               type="text" required
                                                               placeholder="" value="<?php echo $talia['namegroup'] ?>">
                                                        <input class="form-control" name="token" type="hidden"
                                                               value="<?php echo $token ?>">
                                                        <input class="form-control" name="id" type="hidden"
                                                               value="<?php echo $_GET['edit'] ?>">
                                                    </div>
                                                </div>
                                                <div class="mt-3 text-center">
                                                    <button type="submit"
                                                            name="save-update"
                                                            class="btn btn-warning  mr-1">
                                                        ثبت و ویرایش گروه تماس
                                                    </button>
                                                </div>
                                            <?php } ?>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php }

                        ?>
                        <div class="col-lg-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">گروه های ایجاد شده </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>

                                    <!-- فیلترها بالاى جدول -->
                                    <div class="mb-3 d-flex flex-wrap justify-content-end">
                                        <!-- جستجو -->
                                        <input type="text"
                                               id="groupTableSearch"
                                               class="form-control mb-2 mb-sm-0"
                                               style="max-width: 250px;"
                                               placeholder="جستجو در نام / کد گروه...">

                                        <!-- دکمه نمایش همه -->
                                        <button id="groupShowAllBtn"
                                                class="btn btn-secondary ms-sm-2 mb-2 mb-sm-0">
                                            نمایش همه
                                        </button>
                                    </div>

                                    <!-- جدول با اسکرول افقی برای جلوگیری از بیرون زدن -->
                                    <div class="table-responsive">
                                        <table id="groupTable" class="table table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>کد گروه</th>
                                                <th>نام گروه</th>
                                                <th>اقدامات</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql = 'SELECT * FROM tel_group ';
                                            $query = $conn->prepare($sql);
                                            $query->execute();
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            if ($query->rowCount() > 0) {
                                                foreach ($result as $ret) {
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>
                                                        <td><?php echo htmlentities($ret->namegroup) ?></td>
                                                        <td>
                                                            <a href="?del=<?php echo $ret->id ?>"
                                                               onclick="return confirm('با حذف گروه تمام مخاطبین گروه هم حذف میشوند!!')"
                                                               type="button"
                                                               class="btn ripple btn-danger btn-sm">
                                                                حذف
                                                            </a>
                                                            <a href="?edit=<?php echo $ret->id ?>"
                                                               type="button"
                                                               class="btn ripple btn-warning btn-sm">
                                                                ویرایش
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php }
                                            }
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-9">
                                    <div>
                                        <h6 class="main-content-label mb-1">افزودن فایل شماره تماس</h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($ok) and $ok == 1) { ?>
                                <div class="alert alert-success" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                            type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                    فایل جدید با موفقیت اضافه شد

                                </div>
                            <?php } ?>

                            <hr>
                            <p class="header-message">
                                لطفا فایل شماره تماس های خود را با فرمت csv مطابق فایل نمونه بارگذاری نمایید
                            </p>
                            <p>
                                <a href="<?php echo url('assets/doc/test.xlsx') ?>" class="btn btn-success ripple"> دانلود فایل نمونه</a>
                                <a href="<?php echo url('assets/doc/s2.pdf') ?>" class="btn btn-info ripple"> دانلود فایل راهنما</a>
                            </p>
                            <hr>

                            <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                <div class="card-body border ">
                                    <div class="row">
                                        <div class="col-xl-6">

                                            <div class="input-group file-browser">


                                                <input type="text"
                                                       class="form-control border-left-0 browse-file"
                                                       placeholder=" فایل شماره های خود را بارگذاری کنید*"
                                                       readonly="">
                                                <label class="input-group-btn">
														<span class="btn btn-primary">
															انتخاب فایل CSV<input type="file"
                                                                                  name="fileToUpload"
                                                                                  style="display: none;" required>
														</span>
                                                </label>
                                            </div>
                                            <div class="text-muted">
                                                حداکثر سایز مجاز 2 مگابایت می باشد
                                            </div>


                                        </div>
                                        <div class="col-xl-6">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    انتخاب گروه مخاطبان:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                                <select class="form-control select2 "
                                                        name="group"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="گروه ">
                                                    </option>
                                                    <?php
                                                    $sql = 'SELECT * FROM tel_group ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute();
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result as $res) {


                                                            ?>
                                                            <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                        <?php }
                                                    }
                                                    ?>

                                                </select>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $token ?>">

                                            </div>
                                        </div>
                                    </div>
                                    <p class="pd-t-20">
                                        <label class="custom-switch mg-t-20">
                                            <input type="checkbox" name="delete-old" class="custom-switch-input">
                                            <span class="custom-switch-indicator"></span>
                                            <span class="custom-switch-description">شماره های قبلی گروه انتخابی را پاک و این لیست را جایگزین نمایید</span>
                                        </label>
                                    </p>
                                    <div class="mt-3 text-center">
                                        <button type="submit" name="add-number"
                                                class="btn btn-warning  mr-1">
                                            افزودن فایل شماره تماس
                                        </button>

                                    </div>


                                </div>


                            </form>
                        </div>

                    </div>

                </div>
                <?php } ?>
<!--                2th section            -->
                <?php if (Permision('permision_managecall')) { ?>
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card " >
                            <div class="card-body" >
                                <!-- فیلترها بالاى جدول -->
                                <h6 class="main-content-label mb-1">آمار کلی تماس ها</h6>

                                <div class="mb-3 d-flex flex-wrap justify-content-end">
                                    <!-- جستجو -->
                                    <input type="text"
                                           id="tableSearch"
                                           class="form-control mb-2 mb-sm-0"
                                           style="max-width: 250px;"
                                           placeholder="جستجو در گروه مخاطبان...">

                                    <!-- دکمه نمایش همه -->
                                    <button id="showAllBtn"
                                            class=" btn-purple ms-sm-2 mb-2 mb-sm-0" style="border-radius: 5px; border: none;">
                                        نمایش همه
                                    </button>
                                </div>

                                <table id="select2" class="table table-bordered mg-b-0">
                                    <thead>
                                    <tr>
                                        <th>گزارشات اکسل</th>
                                        <th>گروه مخاطبان</th>
                                        <th>کل مخاطبان</th>
                                        <th>گرفته نشده</th>
                                        <th>عدم پاسخ</th>
                                        <th>تماس مجدد</th>
                                        <th>خاتمه یافته</th>
                                        <th>تکراری</th>
                                        <th>اشتباه</th>
                                        <th>حذف</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    // همه گروه‌ها (فقط یک بار از دیتابیس می‌گیریم؛ فیلتر سمت JS)
                                    $sql = 'SELECT * FROM tel_group';
                                    $query = $conn->prepare($sql);
                                    $query->execute();
                                    $result = $query->fetchAll(PDO::FETCH_OBJ);

                                    if ($query->rowCount() > 0) {
                                        foreach ($result as $res) {
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="text-wrap">
                                                        <div class="text-center">
                                                            <div class="dropdown">
                                                                <button aria-expanded="false"
                                                                        aria-haspopup="true"
                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                        data-bs-toggle="dropdown"
                                                                        type="button">
                                                                    انتخاب گزارش <i class="fas fa-caret-down mr-1"></i>
                                                                </button>
                                                                <div class="dropdown-menu tx-13">
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?gexportAll=<?php echo $res->id ?>" target="_blank">
                                                                        <i class="fe fe-phone"></i>
                                                                        کل مخاطبان
                                                                    </a>
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?guncall=<?php echo $res->id ?>">
                                                                        <i class="fe fe-phone-outgoing"></i>
                                                                        گرفته نشده
                                                                    </a>
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?gbegin=<?php echo $res->id ?>">
                                                                        <i class="fe fe-phone-call"></i>
                                                                        تماس مجدد
                                                                    </a>
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?gunres=<?php echo $res->id ?>">
                                                                        <i class="fe fe-phone-missed"></i>
                                                                        عدم پاسخ
                                                                    </a>
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?gfinish=<?php echo $res->id ?>">
                                                                        <i class="fe fe-phone-missed"></i>
                                                                        خاتمه یافته
                                                                    </a>
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?gdublicate=<?php echo $res->id ?>">
                                                                        <i class="fe fe-phone-off"></i>
                                                                        مخاطبین تکراری
                                                                    </a>
                                                                    <a class="dropdown-item"
                                                                       href="exportTel.php?gwrong=<?php echo $res->id ?>">
                                                                        <i class="fe fe-phone-off"></i>
                                                                        شماره اشتباه
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td><?php echo $res->namegroup ?></td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([$res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([0, $res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([5, $res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([1, $res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([2, $res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([4, $res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                    $wdr = $conn->prepare($count);
                                                    $wdr->execute([3, $res->id]);
                                                    $recount = $wdr->fetch();
                                                    echo $recount['cou'];
                                                    ?>
                                                </td>

                                                <td>
                                                    <a href="?delgroup=<?php echo $res->id ?>"
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('درصورت حذف همه اطلاعات و گزارشات این گروه حذف خواهد شد')">
                                                        <i class="fe fe-x"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                    ?>
                                    </tbody>
                                </table>


                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="card custom-card" >
                            <div class="card-body"  >
                                <div class="col-10" >
                                    <div>
                                        <h6 class="main-content-label mb-1">آمار انفرادی تماس ها</h6>
                                        </p>
                                    </div>
                                </div>

                                <!-- فیلترها بالاى جدول -->
                                <div class="mb-3 d-flex flex-wrap justify-content-end">
                                    <!-- جستجو -->
                                    <input type="text"
                                           id="individualTableSearch"
                                           class="form-control mb-2 mb-sm-0"
                                           style="max-width: 250px;"
                                           placeholder="جستجو در نام / داخلی...">

                                    <!-- دکمه نمایش همه -->
                                    <button id="individualShowAllBtn"
                                            class=" btn-purple ms-sm-2 mb-2 mb-sm-0" style="background-color: deepskyblue; border-radius: 5px; border: none;">
                                        نمایش همه
                                    </button>
                                </div>

                                <div class="table">
                                    <table id="individualCallsTable" class="table table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>گزارشات اکسل</th>
                                            <th>نام | داخلی</th>
                                            <th>کل مخاطبان</th>
                                            <th>گرفته نشده</th>
                                            <th>عدم پاسخ</th>
                                            <th>تماس مجدد</th>
                                            <th>خاتمه یافته</th>
                                            <th>مخاطبین تکراری</th>
                                            <th>شماره اشتباه</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbadmin ORDER BY dakheli ASC';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result as $res) {
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown"
                                                                            type="button">
                                                                        انتخاب گزارش <i class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-13">
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?exportAll=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone"></i>
                                                                            کل مخاطبان
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?uncall=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone-outgoing"></i>
                                                                            گرفته نشده
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?unres=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone-outgoing"></i>
                                                                            عدم پاسخ
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?begin=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone-call"></i>
                                                                            تماس مجدد
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?finish=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone-missed"></i>
                                                                            خاتمه یافته
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?dublicate=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone-off"></i>
                                                                            مخاطبین تکراری
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="exportTel.php?wrong=<?php echo $res->dakheli ?>">
                                                                            <i class="fe fe-phone-off"></i>
                                                                            شماره اشتباه
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $res->fname . ' ' . $res->lname .' ( '.$res->dakheli .' )';?></td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE `dakheli`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([$res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([0, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([5, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([1, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([2, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([4, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=?  AND `dakheli`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([3, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-12">
                                    <div>
                                        <h6 class="main-content-label mb-1">جستجوی تلفن</h6>
                                        <p class="text-muted card-sub-title">یافتن تلفن جهت بررسی های بیشتر
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="main-header-center">

                                        <div class="input-group">
                                            <div class="input-group-btn search-panel">
                                                <select class="form-control select2" name="category" required>
                                                    <option label="دسته بندی ها">
                                                    </option>
                                                    <option value="01">
                                                        ردیف تلفن
                                                    </option>
                                                    <option value="02">
                                                        داخلی پشتیبان
                                                    </option>


                                                </select>
                                            </div>
                                            <input type="text" name="txt" class="form-control"
                                                   placeholder="جستجوی مخاطب" required>
                                            <button class="btn search-btn"><i class="fe fe-search"></i></button>
                                        </div>
                                        <div class="main-header-right">
                                            <div class="dropdown header-search">
                                                <button type="submit" name="search"
                                                        class="nav-link icon header-search">
                                                    <i class="fe fe-search header-icons"></i>
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                </form>

                                <div class="table pd-t-15">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>

                                            <th>ردیف</th>
                                            <th>نام</th>
                                            <th>گروه</th>
                                            <th>وضعیت</th>
                                            <th>پشتیبان</th>
                                            <th>اقدامات</th>
                                            <th>برقراری تماس</th>


                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (isset($recive)) {
                                            if ($wer->rowCount() > 0) {
                                                foreach ($recive as $ret) {
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>

                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php $group = retTelGroup($ret->group);
                                                            echo $group['namegroup']; ?></td>
                                                        <td><?php
                                                            switch ($ret->act) {
                                                                case '0':
                                                                    $act = 'تماس گرفته نشده ';
                                                                    break;
                                                                case '1':
                                                                    $act = 'نیاز به تماس مجدد';
                                                                    break;
                                                                case '2':
                                                                    $act = 'تماس پایان یافته';
                                                                    break;
                                                                case '3':
                                                                    $act = 'شماره اشتباه';
                                                                    break;
                                                                case '4':
                                                                    $act = 'شماره تکراری';
                                                                    break;
                                                                case '5':
                                                                    $act = 'عدم پاسخ';
                                                                    break;
                                                            }
                                                            echo $act;
                                                            ?></td>
                                                        <td><?php echo $ret->dakheli; ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-13">
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('de-view.php?id='+'<?php echo $ret->id ?>','مشاهده جزئیات مخاطب'
                                                                                         ,'width=1000,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-eye"></i>
                                                                            مشاهده جزئیات مخاطب
                                                                            </div>
                                                                            <div class="dropdown-item  "
                                                                                 onclick="window.open('re-view.php?id='+'<?php echo $ret->id ?>','مشاهده گزارش مخاطب'
                                                                                         ,'width=1000,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-bar-chart"></i>
                                                                                مشاهده گزارش اقدامات
                                                                            </div>
                                                                            <div class="dropdown-item text-warning "
                                                                                 onclick="window.open('de-edit.php?id='+'<?php echo $ret->id ?>','ویرایش جزئیات مخاطب'
                                                                                         ,'width=1000,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit"></i>
                                                                              ویرایش جزئیات مخاطب
                                                                            </div>

                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?deluser=<?php echo $ret->id ?>"  onclick="return confirm('آیا با حذف این مخاطب موافقید؟')"
                                                                            ><i
                                                                                        class="fe fe-x-square"></i>
                                                                                حذف مخاطب </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            تماس با <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?call-mob11=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-mob22=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?call-mob33=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?call-static11=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?call-static22=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?call-static33=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                        }
                                        ?>


                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>


            </div>
                <?php } ?>
        </div>
    </div>
</div>

<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->
    <script>
        // helper to init search + show-all + pagination on any table
        function initSearchableTable(searchInputId, showAllBtnId, tableId, pageSize = 7) {
            const searchInput = document.getElementById(searchInputId);
            const showAllBtn  = document.getElementById(showAllBtnId);
            const table       = document.getElementById(tableId);

            if (!searchInput || !showAllBtn || !table) {
                return; // silently skip if something is missing
            }

            const tbody = table.querySelector('tbody');
            const rows  = Array.from(tbody.querySelectorAll('tr'));

            // pagination state
            let showAllMode   = false;      // first load = empty
            let currentPage   = 1;
            let filteredRows  = [];

            // create pager container just after table
            const pager = document.createElement('div');
            pager.className = 'table-pager d-flex justify-content-between align-items-center mt-2';
            table.parentNode.insertBefore(pager, table.nextSibling);

            function applyPagination() {
                const totalRows  = filteredRows.length;
                const totalPages = totalRows ? Math.ceil(totalRows / pageSize) : 1;

                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                // hide all rows
                rows.forEach(row => {
                    row.style.display = 'none';
                });

                // show only current page rows
                const start = (currentPage - 1) * pageSize;
                const end   = start + pageSize;
                filteredRows.slice(start, end).forEach(row => {
                    row.style.display = '';
                });

                // build pager UI
                pager.innerHTML = '';

                const info = document.createElement('span');
                if (totalRows === 0) {
                    info.textContent = 'هیچ ردیفی برای نمایش نیست';
                } else {
                    info.textContent = 'صفحه ' + currentPage + ' از ' + totalPages + ' ( ' + totalRows + ' ردیف )';
                }
                pager.appendChild(info);

                if (totalPages > 1) {
                    const controls = document.createElement('div');

                    const prevBtn = document.createElement('button');
                    prevBtn.type = 'button';
                    prevBtn.className = 'btn btn-sm btn-light ms-1';
                    prevBtn.textContent = 'قبلی';
                    prevBtn.disabled = currentPage === 1;
                    prevBtn.addEventListener('click', function () {
                        if (currentPage > 1) {
                            currentPage--;
                            applyPagination();
                        }
                    });

                    const nextBtn = document.createElement('button');
                    nextBtn.type = 'button';
                    nextBtn.className = 'btn btn-sm btn-light ms-1';
                    nextBtn.textContent = 'بعدی';
                    nextBtn.disabled = currentPage === totalPages;
                    nextBtn.addEventListener('click', function () {
                        if (currentPage < totalPages) {
                            currentPage++;
                            applyPagination();
                        }
                    });

                    controls.appendChild(prevBtn);
                    controls.appendChild(nextBtn);
                    pager.appendChild(controls);
                }
            }

            function applyFiltersAndPagination() {
                const term = searchInput.value.trim().toLowerCase();

                filteredRows = [];

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    let visible = false;

                    if (term) {
                        // live search: only rows containing the term
                        visible = text.includes(term);
                    } else if (showAllMode) {
                        // show all if button was pressed and no search term
                        visible = true;
                    } else {
                        // initial state: empty
                        visible = false;
                    }

                    if (visible) {
                        filteredRows.push(row);
                    }
                });

                currentPage = 1; // reset to first page whenever filter changes
                applyPagination();
            }

            // live search
            searchInput.addEventListener('input', function () {
                applyFiltersAndPagination();
            });

            // show all
            showAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showAllMode = true;
                searchInput.value = '';
                applyFiltersAndPagination();
            });

            // start with empty table
            applyFiltersAndPagination();
        }

        document.addEventListener('DOMContentLoaded', function () {
            // table 1: group stats
            initSearchableTable('tableSearch', 'showAllBtn', 'select2', 7);

            // table 2: individual stats
            initSearchableTable('individualTableSearch', 'individualShowAllBtn', 'individualCallsTable', 7);

            // table 3: گروه‌های ایجاد شده
            initSearchableTable('groupTableSearch', 'groupShowAllBtn', 'groupTable', 7);
        });
    </script>

        <script>
            const OPERATION_KEY = 'operationSelectValue';

            function initCallReportTables(rootElement) {
                if (!$.fn.DataTable) {
                    console.error('DataTables plugin not loaded');
                    return;
                }

                $(rootElement).find('table.call-report-table').each(function () {
                    if ($.fn.dataTable && $.fn.dataTable.isDataTable(this)) {
                        return;
                    }

                    $(this).DataTable({
                        language: {
                            searchPlaceholder: "جستجو کردن...",
                            sSearch: "",
                            lengthMenu: "_MENU_ موارد/صفحه"
                        },
                        responsive: false,
                        autoWidth: false,
                        scrollX: true
                    });
                });
            }

            function Choose_operation(operation) {
                const sections = {
                    uncall:        document.getElementById('uncallSection'),
                    unresponse:    document.getElementById('unresponseSection'),
                    callfinish:    document.getElementById('call-finishSection'),
                    duplicatecall: document.getElementById('duplicatecallSection'),
                    begincall:     document.getElementById('beginSection'),
                    wrongcall:     document.getElementById('wrongSection'),
                    anytelcall:    document.getElementById('anytelSection'),
                };

                // hide all
                Object.values(sections).forEach(sec => {
                    if (sec) sec.style.display = 'none';
                });

                // show selected
                const activeSection = sections[operation];
                if (activeSection) {
                    activeSection.style.display = 'block';
                    initCallReportTables(activeSection);
                }

                // persist selection
                localStorage.setItem(OPERATION_KEY, operation || '');
            }

            document.addEventListener('DOMContentLoaded', function () {
                const select = document.getElementById('operationSelect');
                if (!select) return;

                const saved = localStorage.getItem(OPERATION_KEY);

                if (saved) {
                    select.value = saved;
                    Choose_operation(saved);
                } else {
                    // default state (no section)
                    Choose_operation(select.value || '');
                }
            });
        </script>




</body>
</html>