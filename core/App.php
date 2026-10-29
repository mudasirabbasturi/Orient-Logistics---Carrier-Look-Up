<?php
namespace Core;

class App {
    public static function run(): void {
        Session::start();
        Database::initSchema();

        // Autoload helper
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

        // Load routes
        require_once __DIR__ . '/../routes/web.php';

        // Dispatch
        $method = Request::method();
        $uri = Request::uri();
        Router::dispatch($method, $uri);
    }
}

