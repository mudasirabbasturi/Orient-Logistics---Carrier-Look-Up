<?php
namespace Core;

class Request {
    public static function method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Remove script subfolder if accessed via subfolder path
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && $scriptName !== '\\' && strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
        }
        
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    public static function isPost(): bool {
        return self::method() === 'POST';
    }

    public static function isAjax(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }

    public static function input(string $key, $default = null) {
        if (self::isPost()) {
            if (isset($_POST[$key])) return $_POST[$key];
            
            // Check json raw payload
            $raw = file_get_contents('php://input');
            if ($raw) {
                $json = json_decode($raw, true);
                if (isset($json[$key])) return $json[$key];
            }
        }
        return $_GET[$key] ?? $default;
    }

    public static function all(): array {
        $data = array_merge($_GET, $_POST);
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }
        return $data;
    }
}

