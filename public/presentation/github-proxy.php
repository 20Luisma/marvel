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

// ─── Acción especial: Disparar deploy via workflow_dispatch ───
if ($action === 'deploy') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['error' => 'Solo POST permitido']);
        exit;
    }

    $dispatchUrl = "https://api.github.com/repos/$repoOwner/$repoName/actions/workflows/deploy-ftp.yml/dispatches";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $dispatchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Clean-Marvel-TFM-Proxy');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $githubToken",
        "Accept: application/vnd.github.v3+json",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'ref' => 'main',
        'inputs' => ['motivo' => 'Deploy en vivo desde presentación TFM 🚀']
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    header('Content-Type: application/json');
    // GitHub devuelve 204 si el dispatch fue exitoso
    if ($httpCode === 204) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Deploy lanzado correctamente',
            'actions_url' => "https://github.com/$repoOwner/$repoName/actions/workflows/deploy-ftp.yml"
        ]);
    } else {
        http_response_code($httpCode ?: 500);
        echo json_encode(['status' => 'error', 'detail' => $response]);
    }
    exit;
}

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
