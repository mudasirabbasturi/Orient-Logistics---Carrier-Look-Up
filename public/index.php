<?php
/**
 * Orient Logistics Carrier Intelligence Engine
 * Pure PHP entry point
 */

declare(strict_types=1);

// Error reporting for robust execution
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

// Autoload Core and App
spl_autoload_register(function ($class) {
    $prefixApp = 'App\\';
    $prefixCore = 'Core\\';
    $baseDir = __DIR__ . '/../';

    if (strncmp($prefixApp, $class, strlen($prefixApp)) === 0) {
        $relativeClass = substr($class, strlen($prefixApp));
        $file = $baseDir . 'app/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (strncmp($prefixCore, $class, strlen($prefixCore)) === 0) {
        $relativeClass = substr($class, strlen($prefixCore));
        $file = $baseDir . 'core/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

require_once __DIR__ . '/../core/App.php';

try {
    Core\App::run();
} catch (\Throwable $e) {
    if (Core\Request::isAjax()) {
        Core\Response::json([
            'success' => false,
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine()
        ], 500);
    } else {
        http_response_code(500);
        echo "<div style='font-family:sans-serif; padding:40px; background:#fff1f2; color:#991b1b; border:1px solid #fecdd3; border-radius:8px; margin:40px auto; max-width:700px;'>";
        echo "<h2 style='margin-top:0;'>Application Exception</h2>";
        echo "<p style='font-size:16px;'><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p style='font-size:13px; color:#b91c1c;'>File: " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</p>";
        echo "</div>";
    }
}
