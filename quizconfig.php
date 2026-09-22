<?php



define('DB_HOST1', 'localhost');
define('DB_USER1', 'root');
define('DB_PASS1', '');
define('DB_NAME1', 'myhona_quiz');


try {

    $ponn = new PDO('mysql:host=' . DB_HOST1 . ';dbname=' . DB_NAME1, DB_USER1, DB_PASS1);
    $ponn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ponn->query("SET NAMES UTF8");

} catch (PDOException $e) {

//    echo $e->getMessage(); 
    echo 'خطای غیر منتظره رخ داده است';
}