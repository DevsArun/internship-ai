<?php
/**
 * AI Studio — test-models.php  [repo-local, self-contained]
 *
 * Served at {app}/api/ai/test-models.php; settings.php calls it via
 * "api/ai/test-models.php". Tests an API key against each of a provider's
 * models and reports which ones work. Supports ALL providers including the
 * Supports deepseek (primary) and groq (backup).
 *
 * Request  (POST JSON): { "api_key": "...", "provider": "deepseek|groq" }
 * Response (JSON):      { "success": true, "results": [ {model, working, status, message, latency_ms} ], "best_model": "..." }
 */
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in', 'results' => []]);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$apiKey   = trim($input['api_key'] ?? '');
$provider = trim($input['provider'] ?? '');

if ($apiKey === '' || $provider === '') {
    echo json_encode(['success' => false, 'message' => 'api_key and provider required', 'results' => []]);
    exit;
}

// Models to test per provider (kept in sync with building.php PROVIDER_MODELS)
$MODELS = [
    'deepseek' => ['deepseek-v4-flash', 'deepseek-chat', 'deepseek-v4-pro'],
    'groq'     => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'gemma2-9b-it'],
];

$ENDPOINTS = [
    'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
    'groq'     => 'https://api.groq.com/openai/v1/chat/completions',
];

if (!in_array($provider, ['deepseek','groq'])) {
    echo json_encode(['success' => false, 'message' => 'Unknown provider: ' . $provider, 'results' => []]);
    exit;
}


/** Test a single OpenAI-compatible model (deepseek/groq). */
function testOpenAIStyle($endpoint, $model, $apiKey) {
    $body = json_encode([
        'model'      => $model,
        'messages'   => [['role' => 'user', 'content' => 'Hi']],
        'max_tokens' => 5,
    ]);
    return httpPost($endpoint, $body, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        // OpenRouter likes these (optional, harmless for others)
        'HTTP-Referer: https://internshipadda.ai',
        'X-Title: AI Studio',
    ]);
}

/** Generic POST with cURL. Returns [working(bool), status(int), message(string)]. */
function httpPost($url, $body, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $err) {
        return [false, 0, 'Connection error: ' . $err];
    }
    if ($status === 200) {
        return [true, 200, 'Working ✓'];
    }

    // Try to extract a useful error message from the JSON body
    $msg = 'HTTP ' . $status;
    $j   = json_decode($resp, true);
    if (is_array($j)) {
        if (isset($j['error']['message'])) $msg = substr($j['error']['message'], 0, 90);
        elseif (isset($j['error']) && is_string($j['error'])) $msg = substr($j['error'], 0, 90);
        elseif (isset($j['message'])) $msg = substr($j['message'], 0, 90);
    }
    if ($status === 401) $msg = 'Invalid API key (401)';
    if ($status === 429) $msg = 'Rate limited (429) — key works, try later';
    return [false, $status, $msg];
}

$results   = [];
$bestModel = '';
$bestMs    = PHP_INT_MAX;

foreach ($MODELS[$provider] as $model) {
    $start = microtime(true);
    [$working, $status, $message] = testOpenAIStyle($ENDPOINTS[$provider], $model, $apiKey);
    $latency = (int) round((microtime(true) - $start) * 1000);

    // A 429 means the key is valid but the model is busy — still "usable"
    $usable = $working || $status === 429;

    $results[] = [
        'model'      => $model,
        'working'    => $usable,
        'status'     => $status,
        'message'    => $message,
        'latency_ms' => $latency,
    ];

    if ($working && $latency < $bestMs) {
        $bestMs    = $latency;
        $bestModel = $model;
    }
}

// If none returned a clean 200 but some are 429, pick the first usable one as best
if ($bestModel === '') {
    foreach ($results as $r) {
        if ($r['working']) { $bestModel = $r['model']; break; }
    }
}

echo json_encode([
    'success'    => true,
    'provider'   => $provider,
    'results'    => $results,
    'best_model' => $bestModel,
]);
