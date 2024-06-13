<?php
// 引入数据库连接文件
require_once 'db_connect.php';

// 检查是否传入了 id 参数
if (!isset($_GET['id'])) {
    die(json_encode(array('error' => '缺少参数 id')));
}

// 获取传入的 id 参数
$id = $_GET['id'];

// 查询数据库中对应 id 的帖子数据
$stmt = $conn->prepare("SELECT title, link, author, replies, zhuangtai, content, time, level, signature, views FROM posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // 帖子数据数组
    $post = array();
    while ($row = $result->fetch_assoc()) {
        $post['title'] = $row['title'];
        $post['link'] = $row['link'];
        $post['author'] = $row['author'];
        $post['replies'] = $row['replies'];
        $post['zhuangtai'] = $row['zhuangtai'];
        $post['content'] = $row['content'];
        $post['time'] = $row['time'];
        $post['level'] = $row['level'];
        $post['signature'] = $row['signature'];
        $post['views'] = $row['views'];
    }
    // 将帖子数据以 JSON 格式返回
    header('Content-Type: application/json');
    echo json_encode($post);
} else {
    // 没有找到对应帖子时返回错误信息
    echo json_encode(array('error' => '未找到对应帖子'));
}

$stmt->close();
$conn->close();
?>
