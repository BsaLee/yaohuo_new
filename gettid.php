<?php

include 'db_connect.php';

// 初始化 cURL
function fetchHTML($url, $cookie) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    $html = curl_exec($ch);
    curl_close($ch);

    // 去除 UTF-8 BOM
    $bom = pack('H*', 'EFBBBF');
    $html = preg_replace("/^$bom/", '', $html);

    return $html;
}

// 从数据库中顺序读取 zhuangtai 列为空的一条数据
$sql = "SELECT id, link, title, author, views, replies FROM posts WHERE zhuangtai IS NULL LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $post_id = $row["id"];
        $link = $row["link"];
        $title = $row["title"];
        $author = $row["author"];
        $views = $row["views"];
        $replies = $row["replies"];

        $url = "https://www.yaohuo.me" . $link . "?sid=sid写这里也可以不写,前提是cookie写了";

        $cookie = "cookie写这里";

        $html = fetchHTML($url, $cookie);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);

        // 查找节点
        $viewsNode = $xpath->query('/html/body/div[4]/div[1]/span[2]')->item(0);
        $contentNodes = $xpath->query('/html/body/div[4]/div[3]');
        $levelNode = $xpath->query('/html/body/div[5]/span[1]/span[3]/span[1]')->item(0);
        $signatureNode = $xpath->query('/html/body/div[5]/span[4]/span[2]/u')->item(0);

        $giftNode = $xpath->query('/html/body/div[5]/div[1]/span[1]')->item(0);
        $isGift = $giftNode && strpos(trim($giftNode->textContent), '礼金') !== false;

        if ($isGift) {
            $viewsNode = $xpath->query('/html/body/div[5]/div[2]/span[2]')->item(0);
            $contentNodes = $xpath->query('/html/body/div[5]/div[4]');
            $levelNode = $xpath->query('/html/body/div[7]/span[1]/span[3]/span[1]')->item(0);
            $signatureNode = $xpath->query('/html/body/div[7]/span[4]/span[2]/u')->item(0);
        }

        $giftNode = $xpath->query('/html/body/div[5]/span')->item(0);
        $isGift = $giftNode && strpos(trim($giftNode->textContent), '悬赏') !== false;

        if ($isGift) {
            $viewsNode = $xpath->query('/html/body/div[5]/div[1]/span[2]')->item(0);
            $contentNodes = $xpath->query('/html/body/div[5]/div[3]');
            $levelNode = $xpath->query('/html/body/div[7]/span[1]/span[3]/span[1]')->item(0);
            $signatureNode = $xpath->query('/html/body/div[7]/span[4]/span[2]/u')->item(0);
        }

        $content = '';
        if ($contentNodes->length > 0) {
            foreach ($contentNodes as $contentNode) {
                foreach ($contentNode->childNodes as $node) {
                    if ($node->nodeType === XML_TEXT_NODE) {
                        $content .= $node->textContent;
                    }
                    if ($node->nodeName === 'img') {
                        $src = $node->getAttribute('src');
                        $alt = $node->getAttribute('alt');
                        $content .= '<img src="' . $src . '" alt="' . $alt . '">';
                    }
                    if ($node->nodeName === 'a') {
                        $href = $node->getAttribute('href');
                        $linkText = $node->textContent;
                        $content .= '<a href="' . $href . '">' . $linkText . '</a>';
                    }
                    if ($node->nodeName === 'video') {
                        $src = $node->getAttribute('src');
                        $content .= '<video controls src="' . $src . '"></video>';
                    }
                    if ($node->nodeName === 'br') {
                        $content .= "\n";
                    }
                }
            }
        }

        // 检查并处理是否包含“点击下载”
        $downloadNode = $xpath->query('/html/body/div[4]/div[3]/div/div/span[2]/span[1]/a')->item(0);
        if ($downloadNode && strpos($downloadNode->textContent, '点击下载') !== false) {
            foreach ($downloadNode->childNodes as $node) {
                if ($node->nodeType === XML_TEXT_NODE) {
                    $content .= $node->textContent;
                }
                if ($node->nodeName === 'a') {
                    $href = $node->getAttribute('href');
                    $linkText = $node->textContent;
                    $content .= '<a href="' . $href . '">' . $linkText . '</a>';
                }
            }
        }

        $views = 0;
        if ($viewsNode) {
            preg_match('/\d+/', trim($viewsNode->textContent), $matches);
            $views = !empty($matches) ? (int)$matches[0] : 0;
        }

        $level = $levelNode ? trim($levelNode->textContent) : '';
        if (!$level) {
            $levelNode = $xpath->query('/html/body/div[6]/span[1]/span[3]/span[1]')->item(0);
            $level = $levelNode ? trim($levelNode->textContent) : '';
        }

        $signature = $signatureNode ? trim($signatureNode->textContent) : '';
        if (!$signature) {
            $signatureNode = $xpath->query('/html/body/div[6]/span[4]/span[2]/u')->item(0);
            $signature = $signatureNode ? trim($signatureNode->textContent) : '';
        }

        $time = '';
        foreach ($xpath->query('//span[@class="DateAndTime"]') as $timeNode) {
            $time = $timeNode->textContent;
        }

        $update_sql = "UPDATE posts SET content = ?, views = ?, time = ?, zhuangtai = '已处理', level = ?, signature = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sisssi", $content, $views, $time, $level, $signature, $post_id);

        if ($stmt->execute()) {
            echo "记录更新成功<br>";
            echo "内容：" . $content . "<br>";
            echo "阅读数：" . $views . "<br>";
            echo "时间：" . $time . "<br>";
            echo "等级：" . $level . "<br>";
            echo "签名：" . $signature . "<br>";

            $json_message = json_encode([
                'appToken' => 'AT_6S9d3gZMeZ9G4xMm9xkdXjXdTwHXrmAw',
                'content' => '<h1>[妖火新帖] ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><br/><p style="color:red;">作者: ' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '<br/>阅读数: ' . htmlspecialchars($views, ENT_QUOTES, 'UTF-8') . '<br/>回复数: ' . htmlspecialchars($replies, ENT_QUOTES, 'UTF-8') . '</p><br/><p>' . $content . '</p>',
                'summary' => '[YH] ' . mb_substr($title, 0, 20),
                'contentType' => 2,
                'topicIds' => [30818],
                'url' => 'http://yh.luqiaob.com/neirong.html?id=' . $post_id,
                'verifyPay' => false,
                'verifyPayType' => 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $ch = curl_init('https://wxpusher.zjiecode.com/api/send/message');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_message);
            $response = curl_exec($ch);

            if ($response === false) {
                echo "消息发送失败: " . curl_error($ch) . "<br>";
            } else {
                $response_data = json_decode($response, true);
                if ($response_data['code'] !== 1000) {
                    echo "消息发送失败: " . htmlspecialchars($response_data['msg'], ENT_QUOTES, 'UTF-8') . "<br>";
                } else {
                    echo "消息发送成功<br>";
                }
            }
            curl_close($ch);
        } else {
            echo "更新记录时出错: " . $conn->error;
        }
    }
} else {
    echo "0 条结果";
}

$conn->close();

?>
