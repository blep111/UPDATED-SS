<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain");

$raw = file_get_contents("php://input");
if (!$raw) {
    echo "No request body received.";
    exit;
}

$data = json_decode($raw, true);
if (!isset($data['cookies']) || !isset($data['postLink'])) {
    echo "Invalid input data.";
    exit;
}

$cookiesList = array_filter(array_map('trim', explode("\n", $data['cookies'])));
$postLink = trim($data['postLink']);
$shareCount = isset($data['shareCount']) ? intval($data['shareCount']) : 1;

function cookieToToken($cookie) {
    $ch = curl_init("https://c2t.lara.rest/?cookie=" . urlencode($cookie));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $json = json_decode($res, true);
    return $json['access_token'] ?? null;
}

function sharePost($token, $link) {
    $url = "https://graph.facebook.com/me/feed";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'link' => $link,
            'published' => true,
            'access_token' => $token
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return "❌ cURL error while sharing.";
    }
    curl_close($ch);
    $json = json_decode($result, true);
    return isset($json['id'])
        ? "✅ Shared successfully (Post ID: {$json['id']})"
        : "❌ Failed to share.";
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
        usleep(200000); // small delay (0.2s)
    }
}

echo $output ?: "No valid cookies found or all failed.";
