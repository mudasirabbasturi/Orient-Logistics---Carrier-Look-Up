<?php
namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Session;
use Core\FMCSAEngine;

class CarrierController extends Controller {
    public function dashboard() {
        $user = $this->requireAuth();
        
        $pdo = Database::connect();
        
        // Ensure active attendance session
        $sessStmt = $pdo->prepare("SELECT * FROM `sessions` WHERE `user_id` = ? AND `status` != 'completed' ORDER BY `id` DESC LIMIT 1");
        $sessStmt->execute([$user['id']]);
        $activeSession = $sessStmt->fetch();

        if (!$activeSession) {
            $ins = $pdo->prepare("INSERT INTO `sessions` (`user_id`, `checkin_time`, `last_active`, `status`, `ip_address`) VALUES (?, NOW(), NOW(), 'active', ?)");
            $ins->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            $activeSession = [
                'id' => $pdo->lastInsertId(),
                'user_id' => $user['id'],
                'checkin_time' => date('Y-m-d H:i:s'),
                'status' => 'active',
                'total_break_seconds' => 0,
                'mcs_covered_count' => 0,
                'calls_count' => 0,
                'conversions_count' => 0
            ];
        }

        // Get user's allocated range (Admin customizable!)
        $rangeStmt = $pdo->prepare("SELECT * FROM `carrier_ranges` WHERE `user_id` = ? AND `status` = 'active' ORDER BY `id` DESC LIMIT 1");
        $rangeStmt->execute([$user['id']]);
        $range = $rangeStmt->fetch();

        if (!$range) {
            // Allocate default range if admin hasn't set one yet
            $pdo->prepare("INSERT INTO `carrier_ranges` (`user_id`, `start_mc`, `end_mc`, `current_mc`, `range_label`, `status`) VALUES (?, 1739800, 1750799, 1743052, 'Default Sequential', 'active')")
                ->execute([$user['id']]);
            $rangeStmt->execute([$user['id']]);
            $range = $rangeStmt->fetch();
        }

        // Get assigned VOIP from settings (controlled by admin)
        $voipSetting = FMCSAEngine::getSetting('default_voip', 'ringcentral');
        $showSafer   = FMCSAEngine::getSetting('show_safer_link', '1');

        $this->view('carrier.dashboard', [
            'user'          => $user,
            'session'       => $activeSession,
            'range'         => $range,
            'voip'          => $voipSetting,
            'showSafer'     => $showSafer
        ]);
    }

    public function search() {
        $user = $this->requireAuth();
        $type = Request::input('type', 'auto');
        $query = Request::input('query', '');

        if (empty($query)) {
            $this->json(['success' => false, 'message' => 'Search term cannot be empty'], 400);
        }

        $result = FMCSAEngine::search($type, $query);

        // Record activity in database
        $pdo = Database::connect();
        $sessId = Request::input('session_id', null);

        $pdo->prepare("INSERT INTO `activity_logs` (`session_id`, `user_id`, `action_type`, `target_type`, `search_query`, `carrier_dot`, `carrier_name`, `ip_address`) VALUES (?, ?, 'search', ?, ?, ?, ?, ?)")
            ->execute([
                $sessId,
                $user['id'],
                $type,
                $query,
                $result['dot_number'] ?? '',
                $result['legal_name'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

        // Increment session search count
        if ($sessId) {
            $pdo->prepare("UPDATE `sessions` SET `search_count` = `search_count` + 1, `last_active` = NOW() WHERE `id` = ?")->execute([$sessId]);
        }

        if (!$result) {
            $this->json(['success' => false, 'message' => 'No carrier records found matching this query.'], 404);
        }

        $this->json(['success' => true, 'data' => $result]);
    }

    public function updateRange() {
        $user = $this->requireAuth();
        $currentMc = intval(Request::input('current_mc', 0));
        $rangeId   = intval(Request::input('range_id', 0));

        if ($currentMc > 0 && $rangeId > 0) {
            $pdo = Database::connect();
            $pdo->prepare("UPDATE `carrier_ranges` SET `current_mc` = ?, `updated_at` = NOW() WHERE `id` = ? AND `user_id` = ?")
                ->execute([$currentMc, $rangeId, $user['id']]);
        }

        $this->json(['success' => true]);
    }

    public function clockAction() {
        $user = $this->requireAuth();
        $action = Request::input('clock_type', ''); // break_out, break_in, check_out
        $sessionId = intval(Request::input('session_id', 0));

        $pdo = Database::connect();

        if ($action === 'break_out') {
            $pdo->prepare("UPDATE `sessions` SET `status` = 'break', `break_start` = NOW(), `last_active` = NOW() WHERE `id` = ? AND `user_id` = ?")
                ->execute([$sessionId, $user['id']]);
        } elseif ($action === 'break_in') {
            $stmt = $pdo->prepare("SELECT `break_start`, `total_break_seconds` FROM `sessions` WHERE `id` = ? AND `user_id` = ?");
            $stmt->execute([$sessionId, $user['id']]);
            $sess = $stmt->fetch();
            $breakSecs = 0;
            if ($sess && !empty($sess['break_start'])) {
                $breakSecs = max(0, time() - strtotime($sess['break_start']));
            }
            $pdo->prepare("UPDATE `sessions` SET `status` = 'active', `break_start` = NULL, `total_break_seconds` = `total_break_seconds` + ?, `last_active` = NOW() WHERE `id` = ? AND `user_id` = ?")
                ->execute([$breakSecs, $sessionId, $user['id']]);
        } elseif ($action === 'check_out') {
            $pdo->prepare("UPDATE `sessions` SET `status` = 'completed', `checkout_time` = NOW(), `last_active` = NOW() WHERE `id` = ? AND `user_id` = ?")
                ->execute([$sessionId, $user['id']]);
        }

        $this->json(['success' => true, 'action' => $action]);
    }

    public function recordCall() {
        $user = $this->requireAuth();
        $data = Request::all();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("INSERT INTO `carrier_calls` 
            (`user_id`, `session_id`, `mc_number`, `dot_number`, `carrier_name`, `phone`, `email`, `outcome`, `duration_seconds`, `provider`, `callback_reminder`, `notes`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $user['id'],
            $data['session_id'] ?? null,
            $data['mc_number'] ?? '',
            $data['dot_number'] ?? '',
            $data['carrier_name'] ?? '',
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['outcome'] ?? 'New',
            intval($data['duration_seconds'] ?? 0),
            $data['provider'] ?? 'ringcentral',
            !empty($data['callback_reminder']) ? $data['callback_reminder'] : null,
            $data['notes'] ?? ''
        ]);

        $callId = $pdo->lastInsertId();

        // Increment stats on session
        if (!empty($data['session_id'])) {
            $isConversion = in_array(strtolower($data['outcome'] ?? ''), ['carrier signed', 'interested', 'converted']) ? 1 : 0;
            $pdo->prepare("UPDATE `sessions` SET `calls_count` = `calls_count` + 1, `mcs_covered_count` = `mcs_covered_count` + 1, `conversions_count` = `conversions_count` + ?, `last_active` = NOW() WHERE `id` = ?")
                ->execute([$isConversion, $data['session_id']]);
        }

        $this->json(['success' => true, 'call_id' => $callId]);
    }

    public function saveLead() {
        $user = $this->requireAuth();
        $data = Request::all();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("INSERT INTO `saved_carriers` (`user_id`, `mc_number`, `dot_number`, `carrier_name`, `phone`, `email`, `address`, `raw_data`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE `phone` = VALUES(`phone`), `email` = VALUES(`email`), `saved_at` = NOW()");

        $stmt->execute([
            $user['id'],
            $data['mc_number'] ?? '',
            $data['dot_number'] ?? '',
            $data['carrier_name'] ?? '',
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['address'] ?? '',
            json_encode($data['raw_data'] ?? [])
        ]);

        $this->json(['success' => true]);
    }

    public function logCopy() {
        $user = $this->requireAuth();
        $pdo = Database::connect();

        $pdo->prepare("INSERT INTO `activity_logs` (`session_id`, `user_id`, `action_type`, `target_type`, `carrier_dot`, `carrier_name`, `copied_value`, `ip_address`) VALUES (?, ?, 'copy', ?, ?, ?, ?, ?)")
            ->execute([
                Request::input('session_id'),
                $user['id'],
                Request::input('target_type', 'field'),
                Request::input('carrier_dot', ''),
                Request::input('carrier_name', ''),
                Request::input('copied_value', ''),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

        if (Request::input('session_id')) {
            $pdo->prepare("UPDATE `sessions` SET `copy_count` = `copy_count` + 1 WHERE `id` = ?")
                ->execute([Request::input('session_id')]);
        }

        $this->json(['success' => true]);
    }

    public function getCalls() {
        $user = $this->requireAuth();
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM `carrier_calls` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 100");
        $stmt->execute([$user['id']]);
        $calls = $stmt->fetchAll();
        $this->json(['success' => true, 'calls' => $calls]);
    }

    public function getSaved() {
        $user = $this->requireAuth();
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM `saved_carriers` WHERE `user_id` = ? ORDER BY `saved_at` DESC LIMIT 100");
        $stmt->execute([$user['id']]);
        $saved = $stmt->fetchAll();
        $this->json(['success' => true, 'saved' => $saved]);
    }

    public function getPerformance() {
        $user = $this->requireAuth();
        $pdo = Database::connect();

        // Total calls, total conversions, today's covered
        $totalCalls = (int)$pdo->query("SELECT COUNT(*) FROM `carrier_calls` WHERE `user_id` = {$user['id']}")->fetchColumn();
        $conversions = (int)$pdo->query("SELECT COUNT(*) FROM `carrier_calls` WHERE `user_id` = {$user['id']} AND LOWER(`outcome`) IN ('carrier signed', 'interested', 'converted')")->fetchColumn();
        $savedLeads = (int)$pdo->query("SELECT COUNT(*) FROM `saved_carriers` WHERE `user_id` = {$user['id']}")->fetchColumn();
        $totalCopies = (int)$pdo->query("SELECT COUNT(*) FROM `activity_logs` WHERE `user_id` = {$user['id']} AND `action_type` = 'copy'")->fetchColumn();

        $this->json([
            'success' => true,
            'stats' => [
                'total_calls' => $totalCalls,
                'conversions' => $conversions,
                'saved_leads' => $savedLeads,
                'total_copies' => $totalCopies,
                'conversion_rate' => $totalCalls > 0 ? round(($conversions / $totalCalls) * 100, 1) : 0
            ]
        ]);
    }
}

