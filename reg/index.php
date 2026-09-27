<?php
define('BASE_PATH', __DIR__);
//define('CURRENT_DOMAIN', currentDomain().'/my' ); // این بخش در هنگام تحویل پروژه تغییر یابد
define('DISPLAY_ERROR', true); // این قسمت در هنگام تحویل پروژه فالس شود
define('PERMISION_FILE', '0600'); // سطح دسترسی فایل های بارگزاری شده
define('DB_HOST', 'localhost');
define('DB_USER', 'myhona_account');
define('DB_PASS', 'N@staran1362@#');
define('DB_NAME', 'myhona_myhonar');

//include_once 'language/fa_IR.php'; // فایل زبان انتخاب شود

try {

    $conn = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES UTF8");

} catch (PDOException $e) {

//    echo $e->getMessage();
    echo 'خطای غیر منتظره رخ داده است';
}
if(isset($_POST['btn'])){

    $code=$_POST['national-code'];
    if(check_national_code($code)){
        if(chuser($code)){
            $sql='INSERT INTO checkjoin (codemeli) VALUES (?)';
            $query=$conn->prepare($sql);
            $query->execute([$code]);
            $success=1;

        }else{
            $eror='شما قبلا کد ملی خود را ثبت کرده اید';

        }

    }else{
        $eror='لطفاً کد ملی خود را صحیح وارد کنید';
  
    }


}
function check_national_code($value)
{
    if (!preg_match('/^[0-9]{10}$/', $value)) {
        return (bool)false;
    }
    for ($i = 0; $i < 10; $i++)
        if (preg_match('/^' . $i . '{10}$/', $value)) {
            return (bool)false;
        }
    for ($i = 0, $sum = 0; $i < 9; $i++)
        $sum += ((10 - $i) * intval(substr($value, $i, 1)));
    $ret = $sum % 11;
    $parity = intval(substr($value, 9, 1));
    if (($ret < 2 && $ret == $parity) || ($ret >= 2 && $ret == 11 - $parity)) {
        return (bool)true;
    }
    return (bool)false;
}
function chuser($code){
    global $conn;
    $sql='SELECT codemeli FROM checkjoin WHERE codemeli=?';
    $query=$conn->prepare($sql);
    $query->execute([$code]);
    if($query->rowCount() > 0 ){
        return false;
    }else{
        return true;
    }
}

?>

<!doctype html>
<html lang="en" dir="rtl">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>ثبت کد ملی</title>
    <!-- style css -->
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css">
    <!-- end style css -->
</head>
<body>
        <section class="background">
            <img src="images/id%20card.png" alt="id cart">
            <?php if(isset($success)){ ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                کد ملی شما با موفقیت ثبت شد
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php } ?>
            <?php if(isset($eror)){ ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $eror ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>
            <form  method="post">
                <label for="national-code">دانش آموز گرامی لطفا کد ملی خود را وارد نمایید :</label>
                <input type="number" dir="rtl" name="national-code" id="national-code" required="" placeholder="لطفا کد ملی خود را وارد کنید ..."/>
                <input type="submit" value="ارسال" name="btn"/>
            </form>
        </section>
        <script src="js/main.js"></script>
        <script src="node_modules/jquery/dist/jquery.min.js"></script>
        <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>