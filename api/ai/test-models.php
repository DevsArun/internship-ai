<?php
/**
 * AI Studio — test-models.php  [repo-local, self-contained]
 *
 * Served at {app}/api/ai/test-models.php; settings.php calls it via
 * "api/ai/test-models.php". Tests an API key against each of a provider's
 * models and reports which ones work. Supports ALL providers including the
 * new free ones (openrouter, cerebras).
 *
 * Request  (POST JSON): { "api_key": "...", "provider": "gemini|groq|openrouter|cerebras|openai|grok" }
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
    'gemini'     => ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-pro', 'gemini-2.0-flash-lite'],
    'groq'       => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
    'deepseek'   => ['deepseek-chat', 'deepseek-reasoner'],
    'openrouter' => ['deepseek/deepseek-chat-v3-0324:free', 'meta-llama/llama-3.3-70b-instruct:free', 'qwen/qwen-2.5-72b-instruct:free', 'google/gemini-2.0-flash-exp:free', 'mistralai/mistral-small-3.1-24b-instruct:free'],
    'cerebras'   => ['llama-3.3-70b', 'qwen-3-32b', 'llama3.1-8b'],
    'openai'     => ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
    'grok'       => ['grok-3-fast', 'grok-3', 'grok-2'],
];

$ENDPOINTS = [
    'groq'       => 'https://api.groq.com/openai/v1/chat/completions',
    'deepseek'   => 'https://api.deepseek.com/v1/chat/completions',
    'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
    'cerebras'   => 'https://api.cerebras.ai/v1/chat/completions',
    'openai'     => 'https://api.openai.com/v1/chat/completions',
    'grok'       => 'https://api.x.ai/v1/chat/completions',
];

if (!isset($MODELS[$provider])) {
    echo json_encode(['success' => false, 'message' => 'Unknown provider: ' . $provider, 'results' => []]);
    exit;
}

/** Test a single Gemini model. Returns [working, status, message]. */
function testGemini($model, $apiKey) {
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);
    $body = json_encode(['contents' => [['parts' => [['text' => 'Hi']]]], 'generationConfig' => ['maxOutputTokens' => 5]]);
    return httpPost($url, $body, ['Content-Type: application/json']);
}

/** Test a single OpenAI-compatible model (groq/openrouter/cerebras/openai/grok). */
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
    if ($status === 402) $msg = 'Key OK but NO BALANCE (402) — add funds';
    if ($status === 429) $msg = 'Rate limited (429) — key works, try later';
    return [false, $status, $msg];
}

$results   = [];
$bestModel = '';
$bestMs    = PHP_INT_MAX;

foreach ($MODELS[$provider] as $model) {
    $start = microtime(true);
    if ($provider === 'gemini') {
        [$working, $status, $message] = testGemini($model, $apiKey);
    } else {
        [$working, $status, $message] = testOpenAIStyle($ENDPOINTS[$provider], $model, $apiKey);
    }
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

// Determine if the KEY itself is valid (authenticated) even if no model "worked".
// 200/402/429 all prove the key authenticated successfully.
$keyValid = false;
$hasBalanceIssue = false;
foreach ($results as $r) {
    if (in_array($r['status'], [200, 402, 429], true)) $keyValid = true;
    if ($r['status'] === 402) $hasBalanceIssue = true;
}

// Build a clear human hint for the frontend
$hint = '';
if ($bestModel !== '') {
    $hint = 'ok';
} elseif ($hasBalanceIssue) {
    $hint = 'no_balance';   // key works, account needs funds
} else {
    $allStatuses = array_column($results, 'status');
    if (in_array(401, $allStatuses, true))      $hint = 'bad_key';
    elseif (in_array(0, $allStatuses, true))     $hint = 'connection';
    else                                         $hint = 'unknown';
}

echo json_encode([
    'success'    => true,
    'provider'   => $provider,
    'results'    => $results,
    'best_model' => $bestModel,
    'key_valid'  => $keyValid,
    'hint'       => $hint,
]);
