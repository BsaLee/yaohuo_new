<?php
$servername = "mysql_g2";
$username = "xx";
$password = "xx";
$dbname = "xx";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符集为 utf8mb4
mysqli_set_charset($conn, "utf8mb4");
?>
