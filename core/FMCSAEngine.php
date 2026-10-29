<?php
namespace Core;

class FMCSAEngine {
    const DEFAULT_WEBKEY = '134fafdfaa76ae82a7eb3c6a3386e8d0f7f5012a';
    const API_BASE = 'https://mobile.fmcsa.dot.gov/qc/services';
    const CENSUS_API_BASE = 'https://data.transportation.gov/resource/az4n-8mr2.json';

    public static function getSetting(string $key, $default = '') {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT setting_value FROM `settings` WHERE `setting_key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    }

    /**
     * HTTP Request with optional US-proxy routing & automatic fallback
     */
    public static function executeHttp(string $url, int $timeout = 10, bool $forceDirect = false): ?string {
        $proxyEnabled = !$forceDirect && (self::getSetting('proxy_enabled', '0') === '1');
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
            if ($proxyType === 'socks5') curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            elseif ($proxyType === 'https') curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTPS);
            else curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

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

    /**
     * Parallel HTTP request handler
     */
    public static function executeParallel(array $requests): array {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        $proxyEnabled = (self::getSetting('proxy_enabled', '0') === '1');
        $proxyType    = self::getSetting('proxy_type', 'http');
        $proxyHost    = self::getSetting('proxy_host', '');
        $proxyPort    = self::getSetting('proxy_port', '');
        $proxyUser    = self::getSetting('proxy_user', '');
        $proxyPass    = self::getSetting('proxy_pass', '');

        foreach ($requests as $key => $req) {
            $url = is_array($req) ? $req['url'] : $req;
            $forceDirect = is_array($req) ? !empty($req['force_direct']) : false;
            $timeout = is_array($req) ? ($req['timeout'] ?? 8) : 8;

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

            if (!$forceDirect && $proxyEnabled && !empty($proxyHost)) {
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
     * Query FMCSA Census open dataset (data.transportation.gov)
     * Directly queried for ultra-low latency contact resolution
     */
    public static function fetchCensusRecords(string $field, string $value, int $limit = 5): array {
        $cleanVal = trim($value);
        if (empty($cleanVal)) return [];

        $url = self::CENSUS_API_BASE . '?' . rawurlencode($field) . '=' . rawurlencode($cleanVal) . '&$limit=' . intval($limit);
        $res = self::executeHttp($url, 7, true); // force direct
        if (!$res) return [];

        $json = json_decode($res, true);
        return is_array($json) ? $json : [];
    }

    /**
     * Format Phone Number into (XXX) XXX-XXXX
     */
    public static function formatPhone(?string $phone): string {
        if (empty($phone)) return '';
        $digits = preg_replace('/\D/', '', (string)$phone);
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            return sprintf('(%s) %s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7));
        }
        return $phone;
    }

    /**
     * Complete Carrier Intelligence Search
     * Supports: dot, mc, phone, email, and name (with auto-detection)
     */
    public static function search(string $type, string $query): ?array {
        $webkey = self::getSetting('fmcsa_webkey', self::DEFAULT_WEBKEY);
        $query = trim($query);

        if (empty($query)) return null;

        // Auto-detect search type
        if ($type === 'auto') {
            $digitsOnly = preg_replace('/\D/', '', $query);
            if (strpos($query, '@') !== false) {
                $type = 'email';
            } elseif (strlen($digitsOnly) >= 7 && (strpos($query, '-') !== false || strpos($query, '(') !== false || strlen($digitsOnly) === 10 || (strlen($digitsOnly) === 11 && $digitsOnly[0] === '1'))) {
                $type = 'phone';
            } elseif (preg_match('/^[a-zA-Z\s.,&-]{4,}$/', $query)) {
                $type = 'name';
            } else {
                $type = 'mc';
            }
        }

        $targetDot = null;
        $targetMc = null;
        $prefetchedCensus = null;

        // 1. PHONE LOOKUP (Matching official FMCSA census dataset)
        if ($type === 'phone') {
            $digits = preg_replace('/\D/', '', $query);
            if (strlen($digits) === 11 && $digits[0] === '1') {
                $digits = substr($digits, 1);
            }

            // A: Query primary phone
            $censusList = self::fetchCensusRecords('phone', $digits, 5);

            // B: Query cell_phone fallback
            if (empty($censusList)) {
                $censusList = self::fetchCensusRecords('cell_phone', $digits, 5);
            }

            // C: Socrata SoQL contains search fallback
            if (empty($censusList) && strlen($digits) >= 7) {
                $url = self::CENSUS_API_BASE . '?$where=phone+like+%27%25' . $digits . '%25%27&$limit=5';
                $soqlRes = self::executeHttp($url, 7, true);
                if ($soqlRes) {
                    $json = json_decode($soqlRes, true);
                    if (is_array($json) && !empty($json)) {
                        $censusList = $json;
                    }
                }
            }

            if (!empty($censusList[0]['dot_number'])) {
                $targetDot = $censusList[0]['dot_number'];
                $prefetchedCensus = $censusList[0];
            } else {
                return null;
            }
        }

        // 2. EMAIL LOOKUP
        elseif ($type === 'email') {
            $cleanEmail = strtolower(trim($query));
            $censusList = self::fetchCensusRecords('email_address', $cleanEmail, 5);

            if (!empty($censusList[0]['dot_number'])) {
                $targetDot = $censusList[0]['dot_number'];
                $prefetchedCensus = $censusList[0];
            } else {
                // Try domain search
                $domain = substr(strrchr($cleanEmail, "@"), 1);
                $companyPart = preg_replace('/\.(com|net|org|us|biz|co|info|logistics|transport).*$/i', '', $domain);
                if (!empty($companyPart) && strlen($companyPart) >= 3) {
                    $nameUrl = self::CENSUS_API_BASE . '?$where=legal_name+like+%27%25' . urlencode(strtoupper($companyPart)) . '%25%27&$limit=1';
                    $nRes = self::executeHttp($nameUrl, 7, true);
                    if ($nRes) {
                        $nArr = json_decode($nRes, true);
                        if (!empty($nArr[0]['dot_number'])) {
                            $targetDot = $nArr[0]['dot_number'];
                            $prefetchedCensus = $nArr[0];
                        }
                    }
                }
                if (!$targetDot) return null;
            }
        }

        // 3. MC LOOKUP (Docket Translation)
        elseif ($type === 'mc') {
            $targetMc = preg_replace('/\D/', '', $query);

            // Docket endpoint query
            $docketUrl = self::API_BASE . "/carriers/docket-number/{$targetMc}?webKey={$webkey}";
            $docketRes = self::executeHttp($docketUrl, 8);

            if ($docketRes) {
                $docketData = json_decode($docketRes, true);
                if (!empty($docketData['content']['carrier']['dotNumber'])) {
                    $targetDot = $docketData['content']['carrier']['dotNumber'];
                } elseif (!empty($docketData['content'][0]['dotNumber'])) {
                    $targetDot = $docketData['content'][0]['dotNumber'];
                }
            }

            // Fallback: Check census dataset for MC / MX / FF
            if (!$targetDot) {
                $cList = self::fetchCensusRecords('mc_mx_ff_number', "MC-{$targetMc}", 1);
                if (empty($cList)) {
                    $cList = self::fetchCensusRecords('mc_mx_ff_number', $targetMc, 1);
                }
                if (!empty($cList[0]['dot_number'])) {
                    $targetDot = $cList[0]['dot_number'];
                    $prefetchedCensus = $cList[0];
                }
            }
        }

        // 4. USDOT DIRECT
        elseif ($type === 'dot') {
            $targetDot = preg_replace('/\D/', '', $query);
        }

        // 5. NAME LOOKUP
        elseif ($type === 'name') {
            $cleanName = urlencode(strtoupper($query));
            $nameUrl = self::CENSUS_API_BASE . "?\$where=legal_name+like+%27%25{$cleanName}%25%27&\$limit=1";
            $nameRes = self::executeHttp($nameUrl, 7, true);
            if ($nameRes) {
                $nameArr = json_decode($nameRes, true);
                if (!empty($nameArr[0]['dot_number'])) {
                    $targetDot = $nameArr[0]['dot_number'];
                    $prefetchedCensus = $nameArr[0];
                }
            }
        }

        if (!$targetDot && !$targetMc) {
            return null;
        }

        // Parallel fetch: Carrier profile, Authority details, Census contact info, Dockets
        $parallel = [];
        if ($targetDot) {
            $parallel['carrier']   = ['url' => self::API_BASE . "/carriers/{$targetDot}?webKey={$webkey}", 'force_direct' => false, 'timeout' => 8];
            $parallel['authority'] = ['url' => self::API_BASE . "/carriers/{$targetDot}/authority?webKey={$webkey}", 'force_direct' => false, 'timeout' => 8];
            $parallel['dockets']   = ['url' => self::API_BASE . "/carriers/{$targetDot}/docket-numbers?webKey={$webkey}", 'force_direct' => false, 'timeout' => 8];
            if (!$prefetchedCensus) {
                $parallel['census'] = ['url' => self::CENSUS_API_BASE . "?dot_number={$targetDot}&\$limit=1", 'force_direct' => true, 'timeout' => 7];
            }
        } else {
            $parallel['docket'] = ['url' => self::API_BASE . "/carriers/docket-number/{$targetMc}?webKey={$webkey}", 'force_direct' => false, 'timeout' => 8];
        }

        $responses = self::executeParallel($parallel);

        $carrierJson   = !empty($responses['carrier']) ? json_decode($responses['carrier'], true) : [];
        $authJson      = !empty($responses['authority']) ? json_decode($responses['authority'], true) : [];
        $docketsJson   = !empty($responses['dockets']) ? json_decode($responses['dockets'], true) : [];
        
        $censusItem = $prefetchedCensus;
        if (!$censusItem && !empty($responses['census'])) {
            $cArr = json_decode($responses['census'], true);
            $censusItem = $cArr[0] ?? [];
        }

        $profile = $carrierJson['content']['carrier'] ?? ($carrierJson['content'][0] ?? []);

        // Resolve MC Number from dockets or census
        $resolvedMc = $targetMc;
        if (!$resolvedMc && !empty($docketsJson['content'])) {
            $dList = is_array($docketsJson['content']) ? $docketsJson['content'] : [$docketsJson['content']];
            foreach ($dList as $d) {
                $num = $d['docketNumber'] ?? ($d['docket'] ?? '');
                if (!empty($num)) {
                    $resolvedMc = preg_replace('/\D/', '', $num);
                    break;
                }
            }
        }
        if (!$resolvedMc && !empty($censusItem['mc_mx_ff_number'])) {
            $resolvedMc = preg_replace('/\D/', '', $censusItem['mc_mx_ff_number']);
        }
        if (!$resolvedMc && !empty($censusItem['docket1'])) {
            $resolvedMc = preg_replace('/\D/', '', $censusItem['docket1']);
        }

        // Contact details
        $phoneRaw = $censusItem['phone'] ?? ($censusItem['cell_phone'] ?? ($profile['telephone'] ?? ''));
        $email = $censusItem['email_address'] ?? ($profile['emailAddress'] ?? '');
        $officer = $censusItem['company_officer_1'] ?? ($censusItem['company_officer_2'] ?? ($profile['primaryOfficer'] ?? ''));

        // Physical Address
        $street = $censusItem['phy_street'] ?? ($profile['phyStreet'] ?? '');
        $city = $censusItem['phy_city'] ?? ($profile['phyCity'] ?? '');
        $state = $censusItem['phy_state'] ?? ($profile['phyState'] ?? '');
        $zip = $censusItem['phy_zip'] ?? ($profile['phyZipcode'] ?? '');
        $address = trim("{$street}, {$city}, {$state} {$zip}", ", ");

        // Authority Breakdown
        $authCommon = 'None';
        $authContract = 'None';
        $authBroker = 'None';
        if (!empty($authJson['content'])) {
            $authorities = is_array($authJson['content']) ? $authJson['content'] : [$authJson['content']];
            foreach ($authorities as $a) {
                $desc = strtolower($a['authorityTypeDesc'] ?? '');
                $stat = $a['authorityStatusDesc'] ?? 'Active';
                if (strpos($desc, 'common') !== false) $authCommon = $stat;
                if (strpos($desc, 'contract') !== false) $authContract = $stat;
                if (strpos($desc, 'broker') !== false) $authBroker = $stat;
            }
        }

        // Allowed to operate
        $statusFlag = strtoupper($profile['allowedToOperate'] ?? 'Y');
        $isAuthorized = ($statusFlag === 'Y');

        return [
            'dot_number'         => $targetDot ?? ($profile['dotNumber'] ?? ($censusItem['dot_number'] ?? '')),
            'mc_number'          => $resolvedMc ?: '',
            'legal_name'         => $profile['legalName'] ?? ($censusItem['legal_name'] ?? 'Unknown Carrier'),
            'dba_name'           => $profile['dbaName'] ?? ($censusItem['dba_name'] ?? ''),
            'phone'              => self::formatPhone($phoneRaw),
            'phone_raw'          => $phoneRaw,
            'email'              => $email,
            'officer'            => $officer,
            'address'            => $address,
            'city'               => $city,
            'state'              => $state,
            'zip'                => $zip,
            'power_units'        => $censusItem['power_units'] ?? ($profile['totalPowerUnits'] ?? '0'),
            'drivers'            => $censusItem['total_drivers'] ?? ($profile['totalDrivers'] ?? '0'),
            'operation'          => $censusItem['carrier_operation'] ?? ($profile['carrierOperation'] ?? 'Interstate'),
            'operating_status'   => $isAuthorized ? 'Active / Authorized' : 'NOT AUTHORIZED',
            'allowed_to_operate' => $isAuthorized,
            'auth_common'        => $authCommon,
            'auth_contract'      => $authContract,
            'auth_broker'        => $authBroker
        ];
    }
}
