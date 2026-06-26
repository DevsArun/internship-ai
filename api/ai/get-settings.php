<?php
/**
 * AI Studio — get-settings.php  [repo-local, self-contained]
 *
 * Served at {app}/api/ai/get-settings.php; building.php / generate.php fetch it
 * via "api/ai/get-settings.php" to load all saved provider keys/models.
 */
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

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

try {
    $db   = aiStudioDb();
    $rows = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'settings' => ($rows ?: new stdClass())]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
