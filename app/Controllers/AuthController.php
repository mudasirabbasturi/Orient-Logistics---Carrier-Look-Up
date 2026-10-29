<?php
namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Session;

class AuthController extends Controller {
    public function showLogin() {
        if (Session::check()) {
            $user = Session::user();
            if ($user['role'] === 'admin') {
                $this->redirect('/admin');
            } else {
                $this->redirect('/carrier');
            }
        }
        $this->view('auth.login');
    }

    public function login() {
        $username = trim(Request::input('username', ''));
        $password = Request::input('password', '');

        if (empty($username) || empty($password)) {
            if (Request::isAjax()) {
                $this->json(['success' => false, 'message' => 'Please provide username and password.'], 400);
            }
            $this->redirect('/login?error=empty_fields');
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? OR `email` = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            if (Request::isAjax()) {
                $this->json(['success' => false, 'message' => 'Invalid username or password.'], 401);
            }
            $this->redirect('/login?error=invalid_credentials');
        }

        if ($user['status'] !== 'active') {
            if (Request::isAjax()) {
                $this->json(['success' => false, 'message' => 'Account is suspended. Please contact administrator.'], 403);
            }
            $this->redirect('/login?error=account_suspended');
        }

        // Set session
        Session::set('user', [
            'id'       => $user['id'],
            'name'     => $user['name'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
        ]);

        $redirect = ($user['role'] === 'admin') ? '/admin' : '/carrier';

        if (Request::isAjax()) {
            $this->json([
                'success' => true,
                'role' => $user['role'],
                'redirect' => $redirect
            ]);
        }

        $this->redirect($redirect);
    }

    public function logout() {
        $user = Session::user();
        if ($user) {
            // If active attendance session, close it
            $pdo = Database::connect();
            $pdo->prepare("UPDATE `sessions` SET `checkout_time` = NOW(), `status` = 'completed' WHERE `user_id` = ? AND `status` != 'completed'")
                ->execute([$user['id']]);
        }
        Session::destroy();
        $this->redirect('/login');
    }
}

