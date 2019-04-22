<?php
session_start();
require_once 'config.php';
$accesstoken = $_SESSION['accesstoken'];
$logout_url = "https://openapi.baidu.com/connect/2.0/logout?access_token=$accesstoken&next=$logout";

?>
<html>
<head>
    <meta charset="UTF-8">
    <title>index</title>
</head>
<body>
welcome```````
<hr>
<?php echo $_SESSION['nickname'] ?>
<hr>
<img src=" http://tb.himg.baidu.com/sys/portraitn/item/<?php echo $_SESSION['smallpic']?>" alt="">
<hr>
<a href="logout.php">loginout</a>
</body>

</html>