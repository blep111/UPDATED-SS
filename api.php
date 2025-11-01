<?php
error_reporting(0);
header("Content-Type: text/plain");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['cookies']) || !isset($data['postLink'])) {
    http_response_code(400);
    die("Invalid input");
}

$cookiesList = array_filter(array_map('trim', explode("\n", $data['cookies'])));
$postLink = trim($data['postLink']);
$shareCount = isset($data['shareCount']) ? intval($data['shareCount']) : 1;

function cookieToToken($cookie) {
    $ch = curl_init("https://c2t.lara.rest/?cookie=" . urlencode($cookie));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    return $json['access_token'] ?? null;
}

function sharePost($token, $link) {
    $url = "https://graph.facebook.com/me/feed";
    $postData = http_build_query([
        'link' => $link,
        'published' => true,
        'access_token' => $token
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($result, true);
    return isset($json['id']) ? "✅ Shared successfully (Post ID: {$json['id']})" : "❌ Failed to share";
}

$output = "";
foreach ($cookiesList as $cookie) {
    $token = cookieToToken($cookie);
    if (!$token) {
        $output .= "❌ Invalid or suspended cookie, skipped.\n";
        continue;
    }

    for ($i = 1; $i <= $shareCount; $i++) {
        $output .= sharePost($token, $postLink) . "\n";
        usleep(300000); // small delay for stability (0.3s)
    }
}

echo $output ?: "No valid cookies found or failed to share.";
