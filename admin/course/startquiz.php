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
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* End Check Session JUST ONE Session
if (!Permision('permision_viewtest')) {
    header('location:' . url("admin/index.php"));
    die();
}

?>


<script>
    window.onload = function() {
        document.getElementById("member_signup").submit();
    };
</script>
<form method="post" action="https://my.dast-garmi.ir/admin/login.php" id="member_signup">

    <?php
    $person = retAdmin($_SESSION['admin']['user']);
    ?>
    <input class="form-control" name="username"
           aria-describedby="basic-addon2"
           type="hidden" value="<?php echo $person->username ?>" required>

    <input class="form-control" name="password"
           aria-describedby="basic-addon2"
           type="hidden" value="<?php echo $person->password ?>" required>

    <input type="hidden" class="btn btn-primary" name="admin_login">
</form>

