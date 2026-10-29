<?php
namespace Core;

class Controller {
    protected function view(string $viewPath, array $data = []): void {
        extract($data);
        $file = __DIR__ . '/../views/' . str_replace('.', '/', $viewPath) . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            die("View [{$viewPath}] not found at {$file}");
        }
    }

    protected function json($data, int $code = 200): void {
        Response::json($data, $code);
    }

    protected function redirect(string $url): void {
        Response::redirect($url);
    }

    protected function requireAuth(): array {
        if (!Session::check()) {
            if (Request::isAjax()) {
                $this->json(['error' => 'Unauthenticated', 'redirect' => '/login'], 401);
            }
            $this->redirect('/login');
        }
        return Session::user();
    }

    protected function requireAdmin(): array {
        $user = $this->requireAuth();
        if ($user['role'] !== 'admin') {
            if (Request::isAjax()) {
                $this->json(['error' => 'Unauthorized. Admin role required.'], 403);
            }
            $this->redirect('/carrier');
        }
        return $user;
    }
}

