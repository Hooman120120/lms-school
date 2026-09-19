<?php
function ActiveRegister_Student(){

    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result=$query->fetch();
    if ($result['act_register_student']==1) {
        return true;
    } else {
        return false;
    }
}
function ActiveRegister_Collague(){

    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result=$query->fetch();
    if ($result['act_register_collague']==1) {
        return true;
    } else {
        return false;
    }
}
function ActiveRegister_Student_two(){

    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result=$query->fetch();
    if ($result['act_register_student_two']==1) {
        return true;
    } else {
        return false;
    }
}
function ActiveRegister_Collague_two(){

    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result=$query->fetch();
    if ($result['act_register_collague_two']==1) {
        return true;
    } else {
        return false;
    }
}
function ActiveLogin(){
    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result=$query->fetch();
    if ($result['act_login']==1) {
        return true;
    } else {
        return false;
    }
}

function GetIp()
{
    global $ips;
    $ips = $_SERVER['REMOTE_ADDR'];
    return $ips;
}

function CheckBlacklist_ip($ip)
{
    global $conn;
    $sql = 'SELECT * FROM blacklist_ip WHERE ip_blackList_ip =?';
    $query = $conn->prepare($sql);
    $query->execute([$ip]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}

function GetOs()
{
    global $ops;
    $ops = $_SERVER['HTTP_USER_AGENT'];
    return $ops;
}

function GetUsername($user)
{
    global $conn;
    $sql = 'SELECT * FROM dblogin WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$user]);

    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}
function GetAdminUsername($user)
{
    global $conn;
    $sql = 'SELECT * FROM dbadmin WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([$user]);

    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }

}

function CheckAcount($user, $pass)
{
    global $conn;
    $sql = 'SELECT * FROM dblogin WHERE username=? AND password=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $pass]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}
function CheckAdminAcount($user, $pass)
{
    global $conn;
    $sql = 'SELECT * FROM dbadmin WHERE username=? AND password=?';
    $query = $conn->prepare($sql);
    $query->execute([$user, $pass]);
    if ($query->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function ActiveLogintwo(){
    global $conn;
    $sql = 'SELECT * FROM setting_public WHERE id =?';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result=$query->fetch();
    if ($result['act_login_two']==1) {
        return true;
    } else {
        return false;
    }
}

function CheckDbBlack($ip)
{
    global $conn;
    $sql = 'SELECT * FROM blacklist_ip WHERE ip_blackList_ip=?';
    $query = $conn->prepare($sql);
    $query->execute([$ip]);
    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }
}
function sessionid($username){
    global $conn;
    $sql = 'SELECT  * FROM dbh_login WHERE username=?  ORDER BY id DESC ';
    $query = $conn->prepare($sql);
    $query->execute([$username]);
    $result=$query->fetch();
    return $result;
}
function forgetPass($codemeli,$tel,$birthday){
    global $conn;
    $sql = 'SELECT  * FROM dbpersonal WHERE codemeli=? AND tel1=? AND birthdate=?  ';
    $query = $conn->prepare($sql);
    $query->execute([$codemeli,$tel,$birthday]);
    if($query->rowCount()>0){
        return true;
    }else{
        return false;
    }
}
function sessionAdmin($username){
    global $conn;

    // If we're impersonating, ALWAYS validate the session against the REAL admin user
    // (so your "JUST ONE session" check keeps working)
    $realUsername = $_SESSION['admin']['real_user'] ?? $username;

    $sql = 'SELECT * FROM dbh_admin WHERE username=? ORDER BY id DESC';
    $query = $conn->prepare($sql);
    $query->execute([$realUsername]);
    return $query->fetch();
}

function checkDakheli($dakheli)
{
    global $conn;
    $sql = 'SELECT * FROM dbadmin WHERE dakheli=?';
    $query = $conn->prepare($sql);
    $query->execute([$dakheli]);

    if ($query->rowCount() > 0) {
        return false;
    } else {
        return true;
    }

}