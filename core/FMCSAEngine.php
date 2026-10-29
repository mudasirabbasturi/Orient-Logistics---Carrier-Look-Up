<?php
namespace Core;

class FMCSAEngine {
    const API_BASE = 'https://mobile.fmcsa.dot.gov/qc/services';
    const CENSUS_API_BASE = 'https://data.transportation.gov/resource/az4n-8mr2.json';

    public static function getSetting(string $key, $default = '') {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT setting_value FROM `settings` WHERE `setting_key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    public static function executeHttp(string $url, int $timeout = 10): ?string {
        $proxyEnabled = (self::getSetting('proxy_enabled', '0') === '1');
        $proxyType    = self::getSetting('proxy_type', 'http');
        $proxyHost    = self::getSetting('proxy_host', '');
        $proxyPort    = self::getSetting('proxy_port', '');
        $proxyUser    = self::getSetting('proxy_user', '');
        $proxyPass    = self::getSetting('proxy_pass', '');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache'
        ]);

        if ($proxyEnabled && !empty($proxyHost)) {
            $proxyAddr = $proxyHost . (!empty($proxyPort) ? ':' . intval($proxyPort) : '');
            curl_setopt($ch, CURLOPT_PROXY, $proxyAddr);
            if ($proxyType === 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            } elseif ($proxyType === 'https') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTPS);
            } else {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }

            if (!empty($proxyUser)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
            }
        }

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res && $httpCode >= 200 && $httpCode < 400) {
            return $res;
        }
        return $res ?: null;
    }

    public static function executeParallel(array $urls, int $timeout = 8): array {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        $proxyEnabled = (self::getSetting('proxy_enabled', '0') === '1');
        $proxyType    = self::getSetting('proxy_type', 'http');
        $proxyHost    = self::getSetting('proxy_host', '');
        $proxyPort    = self::getSetting('proxy_port', '');
        $proxyUser    = self::getSetting('proxy_user', '');
        $proxyPass    = self::getSetting('proxy_pass', '');

        foreach ($urls as $key => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json, text/plain, */*',
                'Cache-Control: no-cache'
            ]);

            if ($proxyEnabled && !empty($proxyHost)) {
                $proxyAddr = $proxyHost . (!empty($proxyPort) ? ':' . intval($proxyPort) : '');
                curl_setopt($ch, CURLOPT_PROXY, $proxyAddr);
                if ($proxyType === 'socks5') curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                elseif ($proxyType === 'https') curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTPS);
                else curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

                if (!empty($proxyUser)) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
                }
            }

            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($handles as $key => $ch) {
            $results[$key] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $results;
    }

    /**
     * Unified search handler
     */
    public static function search(string $type, string $query): ?array {
        $webkey = self::getSetting('fmcsa_webkey', '134fafdfaa76ae82a7eb3c6a3386e8d0f7f5012a');
        $query = trim($query);

        if ($type === 'auto') {
            $digitsOnly = preg_replace('/\D/', '', $query);
            if (strpos($query, '@') !== false) {
                $type = 'email';
            } elseif (strlen($digitsOnly) === 10 && (strpos($query, '-') !== false || strpos($query, '(') !== false)) {
                $type = 'phone';
            } elseif (preg_match('/^[a-zA-Z\s]{4,}$/', $query)) {
                $type = 'name';
            } else {
                // If 7 digits, typically MC or DOT
                $type = 'mc';
            }
        }

        $dotNumber = null;
        $mcNumber = null;

        if ($type === 'dot') {
            $dotNumber = preg_replace('/\D/', '', $query);
        } elseif ($type === 'mc') {
            $mcNumber = preg_replace('/\D/', '', $query);
            // Translate MC to DOT using FMCSA Docket Lookup
            $docketUrl = self::API_BASE . "/carriers/docket-number/{$mcNumber}?webKey={$webkey}";
            $docketRes = self::executeHttp($docketUrl);
            if ($docketRes) {
                $docketData = json_decode($docketRes, true);
                if (!empty($docketData['content']['carrier']['dotNumber'])) {
                    $dotNumber = $docketData['content']['carrier']['dotNumber'];
                } elseif (!empty($docketData['content'][0]['dotNumber'])) {
                    $dotNumber = $docketData['content'][0]['dotNumber'];
                }
            }
        } elseif ($type === 'phone' || $type === 'email' || $type === 'name') {
            // Query Census Socrata API for DOT
            $whereClause = '';
            if ($type === 'phone') {
                $cleanPhone = preg_replace('/\D/', '', $query);
                $whereClause = "telephone like '%25{$cleanPhone}%25'";
            } elseif ($type === 'email') {
                $cleanEmail = urlencode($query);
                $whereClause = "email_address='{$cleanEmail}'";
            } else {
                $cleanName = urlencode(strtoupper($query));
                $whereClause = "legal_name like '%25{$cleanName}%25'";
            }

            $censusUrl = self::CENSUS_API_BASE . "?\$where=" . $whereClause . "&\$limit=1";
            $censusRes = self::executeHttp($censusUrl);
            if ($censusRes) {
                $censusArr = json_decode($censusRes, true);
                if (!empty($censusArr[0]['dot_number'])) {
                    $dotNumber = $censusArr[0]['dot_number'];
                    if (!empty($censusArr[0]['mc_mx_ff_number'])) {
                        $mcNumber = preg_replace('/\D/', '', $censusArr[0]['mc_mx_ff_number']);
                    }
                }
            }
        }

        if (!$dotNumber && !$mcNumber) {
            return null;
        }

        // Parallel fetch carrier profile & authority details
        $reqs = [];
        if ($dotNumber) {
            $reqs['carrier']   = self::API_BASE . "/carriers/{$dotNumber}?webKey={$webkey}";
            $reqs['authority'] = self::API_BASE . "/carriers/{$dotNumber}/authority?webKey={$webkey}";
            $reqs['census']    = self::CENSUS_API_BASE . "?dot_number={$dotNumber}";
        } elseif ($mcNumber) {
            $reqs['docket'] = self::API_BASE . "/carriers/docket-number/{$mcNumber}?webKey={$webkey}";
        }

        $responses = self::executeParallel($reqs);

        $carrierData = !empty($responses['carrier']) ? json_decode($responses['carrier'], true) : [];
        $authData    = !empty($responses['authority']) ? json_decode($responses['authority'], true) : [];
        $censusData  = !empty($responses['census']) ? json_decode($responses['census'], true) : [];

        $profile = $carrierData['content']['carrier'] ?? ($carrierData['content'][0] ?? []);
        $censusItem = $censusData[0] ?? [];

        // Synthesize high-fidelity carrier record
        $legalName = $profile['legalName'] ?? ($censusItem['legal_name'] ?? 'Unknown Carrier');
        $dbaName = $profile['dbaName'] ?? ($censusItem['dba_name'] ?? '');
        $finalDot = $dotNumber ?? ($profile['dotNumber'] ?? ($censusItem['dot_number'] ?? ''));
        $finalMc = $mcNumber ?? ($profile['mcNumber'] ?? ($censusItem['mc_mx_ff_number'] ?? ''));
        $phone = $censusItem['telephone'] ?? ($profile['telephone'] ?? '');
        $email = $censusItem['email_address'] ?? ($profile['emailAddress'] ?? '');
        
        $street = $censusItem['phy_street'] ?? ($profile['phyStreet'] ?? '');
        $city = $censusItem['phy_city'] ?? ($profile['phyCity'] ?? '');
        $state = $censusItem['phy_state'] ?? ($profile['phyState'] ?? '');
        $zip = $censusItem['phy_zip'] ?? ($profile['phyZipcode'] ?? '');
        $address = trim("{$street}, {$city}, {$state} {$zip}", ", ");

        $powerUnits = $censusItem['total_power_units'] ?? ($profile['totalPowerUnits'] ?? '0');
        $drivers = $censusItem['total_drivers'] ?? ($profile['totalDrivers'] ?? '0');
        $operation = $censusItem['carrier_operation'] ?? ($profile['carrierOperation'] ?? 'Interstate');
        $status = $profile['allowedToOperate'] ?? 'Y';
        $operatingStatus = ($status === 'Y') ? 'Active / Authorized' : 'NOT AUTHORIZED';

        // Authority details
        $authCommon = 'None';
        $authContract = 'None';
        $authBroker = 'None';
        if (!empty($authData['content'])) {
            $authorities = is_array($authData['content']) ? $authData['content'] : [$authData['content']];
            foreach ($authorities as $a) {
                $desc = strtolower($a['authorityTypeDesc'] ?? '');
                $stat = $a['authorityStatusDesc'] ?? 'Active';
                if (strpos($desc, 'common') !== false) $authCommon = $stat;
                if (strpos($desc, 'contract') !== false) $authContract = $stat;
                if (strpos($desc, 'broker') !== false) $authBroker = $stat;
            }
        }

        return [
            'dot_number'       => $finalDot,
            'mc_number'        => $finalMc,
            'legal_name'       => $legalName,
            'dba_name'         => $dbaName,
            'phone'            => $phone,
            'email'            => $email,
            'address'          => $address,
            'city'             => $city,
            'state'            => $state,
            'zip'              => $zip,
            'power_units'      => $powerUnits,
            'drivers'          => $drivers,
            'operation'        => $operation,
            'operating_status' => $operatingStatus,
            'allowed_to_operate' => $status === 'Y',
            'auth_common'      => $authCommon,
            'auth_contract'    => $authContract,
            'auth_broker'      => $authBroker,
            'raw_profile'      => $profile,
            'raw_census'       => $censusItem
        ];
    }
}

