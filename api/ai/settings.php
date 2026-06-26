<?php
/**
 * AI Studio — Settings API (get + save)  [repo-local, self-contained]
 *
 * Served in-place at {app}/api/ai/settings.php and called by settings.php
 * via the relative path "api/ai/settings.php". No external folder needed.
 *
 * Includes the new FREE providers (openrouter, cerebras) in the save
 * whitelist so their API keys + chosen model actually persist.
 */
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Robustly locate config/database.php no matter what the deployed app folder
 * is called. Tries common relative locations, then scans upward.
 */
function aiStudioDb() {
    $candidates = [
        __DIR__ . '/../../config/database.php',
        __DIR__ . '/../../ai-studio/config/database.php',
        __DIR__ . '/../../internship-ai/config/database.php',
        __DIR__ . '/../config/database.php',
        __DIR__ . '/config/database.php',
    ];
    foreach ($candidates as $p) {
        if (file_exists($p)) {
            require_once $p;
            if (function_exists('getAIDb')) return getAIDb();
        }
    }
    // Fallback: walk up the tree looking for config/database.php
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $guess = $dir . '/config/database.php';
        if (file_exists($guess)) {
            require_once $guess;
            if (function_exists('getAIDb')) return getAIDb();
        }
        $dir = dirname($dir);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'database.php not found']);
    exit;
}

$db     = aiStudioDb();
$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $rows = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'settings' => ($rows ?: new stdClass())]);
    exit;
}

if ($action === 'save') {
    if (($_SESSION['studio_role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        exit;
    }

    // Whitelist of settings we accept. Each provider has 3 key slots + 1 model.
    $allowed = ['active_ai_provider'];
    foreach (['gemini', 'groq', 'deepseek', 'openrouter', 'cerebras', 'openai', 'grok'] as $p) {
        $allowed[] = $p . '_api_key';
        $allowed[] = $p . '_api_key_2';
        $allowed[] = $p . '_api_key_3';
        $allowed[] = $p . '_model';
    }
    $allowed = array_merge($allowed, [
        'default_language', 'batch_size', 'image_search_engine', 'include_quiz'
    ]);

    $stmt = $db->prepare(
        "INSERT INTO ai_generator_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($allowed as $key) {
        if (array_key_exists($key, $input)) {
            $stmt->execute([$key, $input[$key]]);
        }
    }

    $verified = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'message' => 'Settings saved!', 'verified' => $verified]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
