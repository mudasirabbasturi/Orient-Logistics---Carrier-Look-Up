<?php
namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Request;
use Core\FMCSAEngine;

class AdminController extends Controller {
    public function dashboard() {
        $admin = $this->requireAdmin();
        $pdo = Database::connect();

        // 1. Overall stats
        $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'carrier'")->fetchColumn();
        $activeSessionsToday = (int)$pdo->query("SELECT COUNT(DISTINCT `user_id`) FROM `sessions` WHERE DATE(`checkin_time`) = CURDATE()")->fetchColumn();
        $totalCallsToday = (int)$pdo->query("SELECT COUNT(*) FROM `carrier_calls` WHERE DATE(`created_at`) = CURDATE()")->fetchColumn();
        $totalSearchesToday = (int)$pdo->query("SELECT COUNT(*) FROM `activity_logs` WHERE `action_type` = 'search' AND DATE(`created_at`) = CURDATE()")->fetchColumn();
        $totalCopiesToday = (int)$pdo->query("SELECT COUNT(*) FROM `activity_logs` WHERE `action_type` = 'copy' AND DATE(`created_at`) = CURDATE()")->fetchColumn();

        // 2. Dispatchers / Carriers List with their assigned ranges & live activity
        $usersStmt = $pdo->query("
            SELECT u.*, 
                   r.id AS range_id, r.start_mc, r.end_mc, r.current_mc, r.range_label,
                   s.status AS live_status, s.last_active, s.checkin_time, s.calls_count, s.search_count
            FROM `users` u
            LEFT JOIN `carrier_ranges` r ON r.user_id = u.id AND r.status = 'active'
            LEFT JOIN `sessions` s ON s.user_id = u.id AND s.status != 'completed'
            WHERE u.role = 'carrier'
            ORDER BY u.id DESC
        ");
        $carriers = $usersStmt->fetchAll();

        // 3. System API & Proxy Settings
        $settingsStmt = $pdo->query("SELECT * FROM `settings`");
        $rawSettings = $settingsStmt->fetchAll();
        $settings = [];
        foreach ($rawSettings as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $this->view('admin.dashboard', [
            'admin'               => $admin,
            'totalUsers'          => $totalUsers,
            'activeSessionsToday' => $activeSessionsToday,
            'totalCallsToday'     => $totalCallsToday,
            'totalSearchesToday'  => $totalSearchesToday,
            'totalCopiesToday'    => $totalCopiesToday,
            'carriers'            => $carriers,
            'settings'            => $settings
        ]);
    }

    public function saveSettings() {
        $this->requireAdmin();
        $pdo = Database::connect();

        $allowedKeys = [
            'fmcsa_webkey', 'proxy_enabled', 'proxy_type', 'proxy_host',
            'proxy_port', 'proxy_user', 'proxy_pass', 'cache_hours',
            'default_voip', 'rc_client_id', 'show_safer_link'
        ];

        $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");

        foreach ($allowedKeys as $key) {
            $val = Request::input($key, '');
            $stmt->execute([$key, $val]);
        }

        if (Request::isAjax()) {
            $this->json(['success' => true, 'message' => 'Settings updated successfully!']);
        }
        $this->redirect('/admin?saved=1');
    }

    public function testFmcsa() {
        $this->requireAdmin();
        $webkey = Request::input('fmcsa_webkey', FMCSAEngine::getSetting('fmcsa_webkey', '134fafdfaa76ae82a7eb3c6a3386e8d0f7f5012a'));
        
        $testUrl = FMCSAEngine::API_BASE . "/carriers/3074558?webKey={$webkey}";
        $startTime = microtime(true);
        $res = FMCSAEngine::executeHttp($testUrl, 8);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if ($res && (strpos($res, 'carrier') !== false || strpos($res, 'legalName') !== false || strpos($res, 'dotNumber') !== false)) {
            $this->json([
                'success' => true,
                'latency' => $elapsed,
                'message' => "FMCSA API Connected Successfully! Response time: {$elapsed}ms."
            ]);
        } else {
            $this->json([
                'success' => false,
                'latency' => $elapsed,
                'message' => "Connection failed. Check your WebKey or Proxy settings. (Response: " . substr(strip_tags((string)$res), 0, 150) . ")"
            ], 500);
        }
    }

    public function createCarrier() {
        $this->requireAdmin();
        $pdo = Database::connect();

        $name = trim(Request::input('name', ''));
        $username = trim(Request::input('username', ''));
        $email = trim(Request::input('email', ''));
        $password = Request::input('password', '');
        $startMc = intval(Request::input('start_mc', 1739800));
        $endMc = intval(Request::input('end_mc', 1750799));
        $currentMc = intval(Request::input('current_mc', $startMc));
        $rangeLabel = trim(Request::input('range_label', 'Sequential Block'));

        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            $this->json(['success' => false, 'message' => 'All fields are required.'], 400);
        }

        // Check duplicates
        $check = $pdo->prepare("SELECT id FROM `users` WHERE `username` = ? OR `email` = ?");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $this->json(['success' => false, 'message' => 'Username or Email is already in use.'], 400);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `status`) VALUES (?, ?, ?, ?, 'carrier', 'active')");
        $stmt->execute([$name, $username, $email, $hash]);
        $carrierId = $pdo->lastInsertId();

        // Assign range
        $rStmt = $pdo->prepare("INSERT INTO `carrier_ranges` (`user_id`, `start_mc`, `end_mc`, `current_mc`, `range_label`, `status`) VALUES (?, ?, ?, ?, ?, 'active')");
        $rStmt->execute([$carrierId, $startMc, $endMc, $currentMc, $rangeLabel]);

        $this->json(['success' => true, 'message' => 'Carrier user created and MC range allocated successfully!']);
    }

    public function updateCarrierRange() {
        $this->requireAdmin();
        $pdo = Database::connect();

        $userId = intval(Request::input('user_id', 0));
        $startMc = intval(Request::input('start_mc', 0));
        $endMc = intval(Request::input('end_mc', 0));
        $currentMc = intval(Request::input('current_mc', 0));
        $rangeLabel = trim(Request::input('range_label', 'Sequential Block'));

        if (!$userId || !$startMc || !$endMc) {
            $this->json(['success' => false, 'message' => 'Invalid parameters provided.'], 400);
        }

        // Check if range exists for user
        $stmt = $pdo->prepare("SELECT id FROM `carrier_ranges` WHERE `user_id` = ? AND `status` = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE `carrier_ranges` SET `start_mc` = ?, `end_mc` = ?, `current_mc` = ?, `range_label` = ?, `updated_at` = NOW() WHERE `id` = ?")
                ->execute([$startMc, $endMc, $currentMc ?: $startMc, $rangeLabel, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO `carrier_ranges` (`user_id`, `start_mc`, `end_mc`, `current_mc`, `range_label`, `status`) VALUES (?, ?, ?, ?, ?, 'active')")
                ->execute([$userId, $startMc, $endMc, $currentMc ?: $startMc, $rangeLabel]);
        }

        $this->json(['success' => true, 'message' => 'Carrier MC range updated successfully!']);
    }

    public function getUserLogs() {
        $this->requireAdmin();
        $pdo = Database::connect();

        $userId = intval(Request::input('user_id', 0));
        $filterAction = Request::input('action_type', '');

        $sql = "SELECT a.*, u.name AS user_name, u.username 
                FROM `activity_logs` a 
                JOIN `users` u ON u.id = a.user_id 
                WHERE 1=1";
        $params = [];

        if ($userId > 0) {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }

        if (!empty($filterAction)) {
            $sql .= " AND a.action_type = ?";
            $params[] = $filterAction;
        }

        $sql .= " ORDER BY a.id DESC LIMIT 150";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Also fetch user calls if filtered by single user
        $calls = [];
        if ($userId > 0) {
            $cStmt = $pdo->prepare("SELECT * FROM `carrier_calls` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 100");
            $cStmt->execute([$userId]);
            $calls = $cStmt->fetchAll();
        }

        $this->json(['success' => true, 'logs' => $logs, 'calls' => $calls]);
    }

    public function getUserSessions() {
        $this->requireAdmin();
        $pdo = Database::connect();

        $userId = intval(Request::input('user_id', 0));
        $sql = "SELECT s.*, u.name, u.username FROM `sessions` s JOIN `users` u ON u.id = s.user_id";
        $params = [];
        if ($userId > 0) {
            $sql .= " WHERE s.user_id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY s.id DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll();

        $this->json(['success' => true, 'sessions' => $sessions]);
    }
}

