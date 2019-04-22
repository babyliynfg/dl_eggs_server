<?php
/**
 * Created by PhpStorm.
 * User: tao
 * Date: 2016-09-14
 * Time: 17:59
 */
session_start();
require_once 'config.php';
$accesstoken = $_SESSION['accesstoken'];
$logout_url = "https://openapi.baidu.com/connect/2.0/logout?access_token=$accesstoken&next=$logout";
session_unset();

header("Location:$logout_url");