<?php
// 引入数据库连接文件
require_once 'db_connect.php';

// 查询数据库，倒序获取最新的 10 条数据
$query = "SELECT id, title, author, replies, zhuangtai, time FROM posts ORDER BY id DESC LIMIT 50";
$result = $conn->query($query);

if ($result) {
    $posts = array();
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    $result->free();
    
    // 将数据以 JSON 格式返回
    header('Content-Type: application/json');
    echo json_encode($posts);
} else {
    // 查询失败时返回错误信息
    echo json_encode(array('error' => '查询失败'));
}

$conn->close();
?>
