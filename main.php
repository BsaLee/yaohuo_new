<?php
// 引入数据库连接文件
require_once 'db_connect.php';

$url = "https://www.yaohuo.me/bbs/book_list.aspx?gettotal=2024&action=new&sid=sid写这里也可以不写,前提是cookie写了";

// 初始化cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过SSL证书验证

$html = curl_exec($ch);
if ($html === false) {
    die("请求失败: " . curl_error($ch));
}
curl_close($ch);

// 添加UTF-8 BOM
$html = "\xEF\xBB\xBF" . $html;

libxml_use_internal_errors(true); // 禁止显示HTML解析错误

$dom = new DOMDocument();
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// 准备解析帖子信息
$posts = [];
$nodes = $xpath->query("//div[contains(@class, 'listdata')]");
foreach ($nodes as $node) {
    $post = [];
    $aTags = $node->getElementsByTagName('a');
    $brTags = $node->getElementsByTagName('br');

    if ($aTags->length > 0) {
        $post['title'] = trim($aTags->item(0)->textContent);
        $post['link'] = $aTags->item(0)->getAttribute('href');
    }

    if ($brTags->length > 0 && $brTags->item(0)->nextSibling) {
        $authorText = $brTags->item(0)->nextSibling->textContent;
        $post['author'] = trim(explode('/', $authorText)[0]);
    } else {
        $post['author'] = '未知作者';
    }

    if ($aTags->length > 1) {
        $stats = explode('回/', $aTags->item(1)->textContent);
        $post['replies'] = trim($stats[0]);
        $post['views'] = rtrim(trim($stats[1]), '阅');
    } else {
        $post['replies'] = 0;
        $post['views'] = 0;
    }

    // 对标题和作者进行编码转换
    $post['title'] = mb_convert_encoding($post['title'], 'UTF-8', 'UTF-8');
    $post['author'] = mb_convert_encoding($post['author'], 'UTF-8', 'UTF-8');

    $posts[] = $post;
}

// 将解析后的数据写入数据库
foreach ($posts as $post) {
    // 检查链接是否已存在于数据库中
    $existing_link = $conn->prepare("SELECT link FROM posts WHERE link = ?");
    $existing_link->bind_param("s", $post['link']);
    $existing_link->execute();
    $existing_link->store_result();
    if ($existing_link->num_rows == 0) {
        $stmt = $conn->prepare(
            "INSERT INTO posts (title, link, author, replies, views) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssii", $post['title'], $post['link'], $post['author'], $post['replies'], $post['views']);

        if ($stmt->execute() === false) {
            echo "插入失败: " . $stmt->error . "<br>";
        } else {
            echo "插入成功: " . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . "<br>";
        }
        $stmt->close();
    } else {
        echo "链接已存在，不执行插入操作: " . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . "<br>";
    }
    $existing_link->close();
}

$conn->close();
?>
