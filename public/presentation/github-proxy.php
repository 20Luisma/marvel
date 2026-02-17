<?php
declare(strict_types=1);

/**
 * 🛰️ GitHub API Proxy for TFM Presentation
 * Bypasses rate limits by using the server-side API Key.
 */

// Load environment to get the token
require_once __DIR__ . '/../../vendor/autoload.php';
$container = require_once __DIR__ . '/../../src/bootstrap.php';

$githubToken = getenv('GITHUB_API_KEY') ?: ($_ENV['GITHUB_API_KEY'] ?? '');

if (empty($githubToken)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'GitHub API Key not configured in .env']);
    exit;
}

$repoOwner = '20Luisma';
$repoName = 'marvel';
$action = $_GET['action'] ?? 'repo';

$urls = [
    'repo' => "https://api.github.com/repos/$repoOwner/$repoName",
    'user' => "https://api.github.com/users/$repoOwner",
    'commits' => "https://api.github.com/repos/$repoOwner/$repoName/commits?per_page=1",
    'releases' => "https://api.github.com/repos/$repoOwner/$repoName/releases",
    'tags' => "https://api.github.com/repos/$repoOwner/$repoName/tags"
];

$targetUrl = $urls[$action] ?? $urls['repo'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Clean-Marvel-TFM-Proxy');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token $githubToken",
    "Accept: application/vnd.github.v3+json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Pass through the Link header for pagination (important for total commits count)
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
if ($action === 'commits') {
    curl_setopt($ch, CURLOPT_HEADER, true);
    $fullResponse = curl_exec($ch);
    $headerContent = substr($fullResponse, 0, $headerSize);
    $response = substr($fullResponse, $headerSize);
    
    if (preg_match('/Link: (.*)/i', $headerContent, $matches)) {
        header("Link: {$matches[1]}");
    }
}

curl_close($ch);

header('Content-Type: application/json');
http_response_code((int)$httpCode);
echo $response;
