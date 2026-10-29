<?php
namespace Core;

class Database {
    private static ?\PDO $instance = null;

    public static function connect(): \PDO {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/app.php';
            $db = $config['db'];
            $dsn = "mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}";
            
            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            try {
                self::$instance = new \PDO($dsn, $db['username'], $db['password'], $options);
            } catch (\PDOException $e) {
                // If database does not exist, try creating it
                try {
                    $tmpDsn = "mysql:host={$db['host']};charset={$db['charset']}";
                    $tmpPdo = new \PDO($tmpDsn, $db['username'], $db['password'], $options);
                    $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    self::$instance = new \PDO($dsn, $db['username'], $db['password'], $options);
                } catch (\Exception $ex) {
                    die("Database connection error: " . $ex->getMessage());
                }
            }
        }
        return self::$instance;
    }

    public static function initSchema(): void {
        $pdo = self::connect();

        // 1. Users table (Admin & Carrier roles)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `username` VARCHAR(60) NOT NULL UNIQUE,
            `email` VARCHAR(120) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'carrier') NOT NULL DEFAULT 'carrier',
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `phone` VARCHAR(40) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Settings table (Key-value store only editable by Admin)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Carrier MC Ranges (Admin sets per-user ranges!)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `carrier_ranges` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `start_mc` INT UNSIGNED NOT NULL,
            `end_mc` INT UNSIGNED NOT NULL,
            `current_mc` INT UNSIGNED NOT NULL,
            `range_label` VARCHAR(100) DEFAULT 'Sequential Block',
            `status` ENUM('active', 'paused', 'completed') DEFAULT 'active',
            `allocated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `user_id_idx` (`user_id`),
            KEY `status_idx` (`status`),
            CONSTRAINT `fk_ranges_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4. Dispatcher Sessions (Attendance & Shift Clock)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `sessions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `checkin_time` DATETIME NOT NULL,
            `checkout_time` DATETIME DEFAULT NULL,
            `last_active` DATETIME NOT NULL,
            `break_start` DATETIME DEFAULT NULL,
            `total_break_seconds` INT DEFAULT 0,
            `search_count` INT DEFAULT 0,
            `copy_count` INT DEFAULT 0,
            `calls_count` INT DEFAULT 0,
            `mcs_covered_count` INT DEFAULT 0,
            `conversions_count` INT DEFAULT 0,
            `status` ENUM('active', 'break', 'completed') DEFAULT 'active',
            `ip_address` VARCHAR(45) DEFAULT '',
            KEY `user_id_idx` (`user_id`),
            KEY `status_idx` (`status`),
            KEY `checkin_idx` (`checkin_time`),
            CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. Activity Logs (Per-user detailed actions: searches, copy events, navigation)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `session_id` BIGINT UNSIGNED DEFAULT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `action_type` VARCHAR(40) NOT NULL,
            `target_type` VARCHAR(40) DEFAULT '',
            `search_query` VARCHAR(255) DEFAULT '',
            `carrier_dot` VARCHAR(50) DEFAULT '',
            `carrier_name` VARCHAR(255) DEFAULT '',
            `copied_value` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT '',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `session_id_idx` (`session_id`),
            KEY `user_id_idx` (`user_id`),
            KEY `action_type_idx` (`action_type`),
            KEY `created_at_idx` (`created_at`),
            CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 6. Calls & Dispositions Ledger
        $pdo->exec("CREATE TABLE IF NOT EXISTS `carrier_calls` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `session_id` BIGINT UNSIGNED DEFAULT NULL,
            `mc_number` VARCHAR(30) NOT NULL,
            `dot_number` VARCHAR(30) DEFAULT '',
            `carrier_name` VARCHAR(255) DEFAULT '',
            `phone` VARCHAR(50) DEFAULT '',
            `email` VARCHAR(120) DEFAULT '',
            `outcome` VARCHAR(60) DEFAULT 'New',
            `duration_seconds` INT DEFAULT 0,
            `provider` VARCHAR(50) DEFAULT 'ringcentral',
            `callback_reminder` DATETIME DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `user_id_idx` (`user_id`),
            KEY `mc_idx` (`mc_number`),
            KEY `outcome_idx` (`outcome`),
            KEY `created_at_idx` (`created_at`),
            CONSTRAINT `fk_calls_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 7. Saved Leads Pipeline
        $pdo->exec("CREATE TABLE IF NOT EXISTS `saved_carriers` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `mc_number` VARCHAR(30) NOT NULL,
            `dot_number` VARCHAR(30) DEFAULT '',
            `carrier_name` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(50) DEFAULT '',
            `email` VARCHAR(120) DEFAULT '',
            `address` TEXT DEFAULT NULL,
            `raw_data` LONGTEXT DEFAULT NULL,
            `saved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `user_carrier_uniq` (`user_id`, `mc_number`),
            KEY `user_id_idx` (`user_id`),
            CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed default Admin & Default Carrier if no users exist
        $userCount = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        if ($userCount === 0) {
            $defaultPassword = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
            
            // 1. Admin
            $stmt->execute([
                'Orient Administrator',
                'admin',
                'admin@orientlogisticssolution.com',
                $defaultPassword,
                'admin',
                'active'
            ]);
            $adminId = $pdo->lastInsertId();

            // 2. Demo Carrier / Dispatcher
            $carrierPassword = password_hash('carrier123', PASSWORD_BCRYPT);
            $stmt->execute([
                'Mudasir Dispatcher',
                'carrier1',
                'dispatcher1@orientlogisticssolution.com',
                $carrierPassword,
                'carrier',
                'active'
            ]);
            $carrierId = $pdo->lastInsertId();

            // Allocate initial MC Range for this carrier
            $rangeStmt = $pdo->prepare("INSERT INTO `carrier_ranges` (`user_id`, `start_mc`, `end_mc`, `current_mc`, `range_label`, `status`) VALUES (?, ?, ?, ?, ?, 'active')");
            $rangeStmt->execute([$carrierId, 1739800, 1750799, 1743052, 'West Coast Sequential']);
        }

        // Seed default settings if empty
        $settingsCount = (int)$pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
        if ($settingsCount === 0) {
            $defaultSettings = [
                'fmcsa_webkey'         => '134fafdfaa76ae82a7eb3c6a3386e8d0f7f5012a',
                'proxy_enabled'        => '0',
                'proxy_type'           => 'http',
                'proxy_host'           => '',
                'proxy_port'           => '',
                'proxy_user'           => '',
                'proxy_pass'           => '',
                'cache_hours'          => '24',
                'default_voip'         => 'ringcentral',
                'rc_client_id'         => '',
                'show_safer_link'      => '1',
            ];
            $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
            foreach ($defaultSettings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
        }
    }
}
