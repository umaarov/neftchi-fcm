<?php
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

$apiUrl = "https://api.pfl.uz/v1/web/news?clubId=7";
$lastIdFile = "last_id.txt";

echo "Checking PFL API for new Neftchi news...\n";

$response = file_get_contents($apiUrl);
if ($response === false) {
    die("Error: Could not reach the API.\n");
}

$data = json_decode($response, true);
if (!isset($data['data']['list'][0])) {
    die("Error: No news found in the API response.\n");
}

$latestArticle = $data['data']['list'][0];
$latestId = (int)$latestArticle['id'];
$title = $latestArticle['contents']['title'];
$urlSlug = $latestArticle['contents']['url'];
$articleUrl = "v1/web/news/" . $urlSlug;

$lastSeenId = file_exists($lastIdFile) ? (int)trim(file_get_contents($lastIdFile)) : 0;
if ($latestId > $lastSeenId) {
    echo "New article detected! ID: $latestId - $title\n";
    $credentialsJson = getenv('FIREBASE_CREDENTIALS');
    if (!$credentialsJson) {
        die("Error: FIREBASE_CREDENTIALS environment variable is missing!\n");
    }

    $serviceAccount = json_decode($credentialsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: FIREBASE_CREDENTIALS is not valid JSON!\n");
    }

    $factory = (new Factory)->withServiceAccount($serviceAccount);
    $messaging = $factory->createMessaging();

    $message = CloudMessage::fromArray([
        'topic' => 'news',
        'notification' => [
            'title' => "FC Neftchi: Yangi xabar",
            'body' => $title
        ],
        'data' => [
            'title' => "FC Neftchi: Yangi xabar",
            'body' => $title,
            'articleUrl' => $articleUrl
        ],
    ]);

    try {
        $messaging->send($message);
        echo "Push notification broadcasted successfully!\n";
        file_put_contents($lastIdFile, $latestId);
        echo "Updated $lastIdFile with ID: $latestId\n";

    } catch (Exception $e) {
        die("Error sending notification: " . $e->getMessage() . "\n");
    }
} else {
    echo "No new articles. Latest ID:  $lastSeenId.\n";
}