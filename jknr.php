<?php

// 包含数据库连接信息
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

    // 转换编码为 UTF-8
    $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    
    return $html;
}

// 从数据库中顺序读取 zhuangtai 列为空的一条数据
$sql = "SELECT id, link, title, author, views, replies FROM posts WHERE zhuangtai IS NULL LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // 输出数据
    while ($row = $result->fetch_assoc()) {
        $post_id = $row["id"];
        $link = $row["link"];
        $title = $row["title"];
        $author = $row["author"];
        $views = $row["views"];
        $replies = $row["replies"];

        // 拼接 URL
        $url = "https://www.yaohuo.me" . $link . "?sid=xxxx";

        // 设置要发送的 cookie
        $cookie = "sidyaohuo=xxxx; sidwww=xxxx;";

        // 使用 cURL 获取页面内容
        $html = fetchHTML($url, $cookie);

        // 使用DOMDocument解析HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);

        // 使用DOMXPath实现类似getElementsByClassName的功能
        $xpath = new DOMXPath($dom);

        // 检查特定元素内容是否为“礼金”
        $giftNode = $xpath->query('/html/body/div[5]/div[1]/span[1]')->item(0);
        $isGift = ($giftNode && trim($giftNode->textContent) == '礼金');

        if ($isGift) {
            // 使用新的路径获取数据
            $viewsNode = $xpath->query('/html/body/div[5]/div[2]/span[2]')->item(0);
            $contentNodes = $xpath->query('/html/body/div[5]/div[4]');
            $levelNode = $xpath->query('/html/body/div[7]/span[1]/span[3]/span[1]')->item(0);
            $signatureNode = $xpath->query('/html/body/div[7]/span[4]/span[2]/u')->item(0);
        } else {
            // 使用默认路径获取数据
            $viewsNode = $xpath->query('/html/body/div[4]/div[1]/span[2]')->item(0);
            $contentNodes = $xpath->query('/html/body/div[4]/div[3]');
            $levelNode = $xpath->query('/html/body/div[5]/span[1]/span[3]/span[1]')->item(0);
            $signatureNode = $xpath->query('/html/body/div[5]/span[4]/span[2]/u')->item(0);
        }

        // 获取帖子正文内容
        $content = '';
        if ($contentNodes->length > 0) {
            foreach ($contentNodes as $contentNode) {
                foreach ($contentNode->childNodes as $node) {
                    if ($node->nodeType === XML_TEXT_NODE) {
                        $content .= $node->textContent;
                    }
                    if ($node->nodeName === 'img') {
                        // 获取图片的 src 和 alt 属性
                        $src = $node->getAttribute('src');
                        $alt = $node->getAttribute('alt');
                        // 构建 img 标签
                        $content .= '<img src="' . $src . '" alt="' . $alt . '">';
                    }
                    if ($node->nodeName === 'br') {
                        $content .= "\n";
                    }
                }
            }
        }

        // 获取阅读数
        $views = 0;
        if ($viewsNode) {
            $viewsText = trim($viewsNode->textContent);
            preg_match('/\d+/', $viewsText, $matches);
            if (!empty($matches)) {
                $views = (int) $matches[0];
            }
        }

        // 获取等级
        $level = '';
        if ($levelNode) {
            $level = trim($levelNode->textContent);
        } else {
            // 如果没有读取到等级，则尝试从新的路径读取
            $levelNode = $xpath->query('/html/body/div[6]/span[1]/span[3]/span[1]')->item(0);
            if ($levelNode) {
                $level = trim($levelNode->textContent);
            }
        }

        // 获取签名
        $signature = '';
        if ($signatureNode) {
            $signature = trim($signatureNode->textContent);
        } else {
            // 尝试从新的路径读取签名
            $signatureNode = $xpath->query('/html/body/div[6]/span[4]/span[2]/u')->item(0);
            if ($signatureNode) {
                $signature = trim($signatureNode->textContent);
            }
        }

        // 获取时间
        $timeNodes = $xpath->query('//span[@class="DateAndTime"]');
        $time = '';
        foreach ($timeNodes as $timeNode) {
            $time = $timeNode->textContent;
        }

        // 将提取的信息存储到数据库
        $update_sql = "UPDATE posts SET content = ?, views = ?, time = ?, zhuangtai = '已处理', level = ?, signature = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sisssi", mb_convert_encoding($content, 'UTF-8', 'UTF-8'), $views, mb_convert_encoding($time, 'UTF-8', 'UTF-8'), mb_convert_encoding($level, 'UTF-8', 'UTF-8'), mb_convert_encoding($signature, 'UTF-8', 'UTF-8'), $post_id);
        if ($stmt->execute()) {
            echo "记录更新成功<br>";
            echo "内容：" . $content . "<br>";
            echo "阅读数：" . $views . "<br>";
            echo "时间：" . $time . "<br>";
            echo "等级：" . $level . "<br>";
            echo "签名：" . $signature . "<br>";

            // 构造推送消息数据，确保所有字符串已转换为UTF-8
            $json_message = json_encode([
                'appToken' => 'xxxx', // 替换为实际的appToken
                'content' => '<h1>[妖火新帖] ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><br/><p style="color:red;">作者: ' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '<br/>阅读数: ' . htmlspecialchars($views, ENT_QUOTES, 'UTF-8') . '<br/>回复数: ' . htmlspecialchars($replies, ENT_QUOTES, 'UTF-8') . '</p><br/><p>' . $content . '</p>',
                'summary' => '[YH] ' . mb_substr($title, 0, 20),
                'contentType' => 2,
                'topicIds' => [123], // 替换为实际的topicId
                'url' => 'http://yh.luqiaob.com/neirong.html?id=' . $post_id,
                'verifyPay' => false,
                'verifyPayType' => 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // 发送数据到指定接口
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
