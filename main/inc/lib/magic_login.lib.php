<?php

/* For licensing terms, see /license.txt */

use ChamiloSession as Session;

/**
 * Class MagicLoginManager
 * Manages token-based magic login links for users.
 */
class MagicLoginManager
{
    /**
     * Ensure the user_magic_token and rate-limit tables exist
     */
    public static function initTable()
    {
        $table = Database::get_main_table('user_magic_token');
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_by INT NOT NULL,
            INDEX (token),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        Database::query($sql);

        $attemptTable = Database::get_main_table('user_magic_attempt');
        $sqlAttempts = "CREATE TABLE IF NOT EXISTS $attemptTable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempt_time DATETIME NOT NULL,
            INDEX (ip, attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        Database::query($sqlAttempts);
    }

    /**
     * Get an existing active token or generate a new one
     *
     * @param int  $userId
     * @param int  $createdBy
     * @param bool $forceNew
     * @param int  $validDays
     * @return string
     */
    public static function getOrCreateToken($userId, $createdBy = 0, $forceNew = false, $validDays = 30)
    {
        self::initTable();
        $table = Database::get_main_table('user_magic_token');
        $userId = (int) $userId;
        $createdBy = (int) $createdBy;
        $now = api_get_utc_datetime();

        if ($forceNew) {
            // Expire all prior active tokens for this user
            $expireSql = "UPDATE $table SET expires_at = '$now' WHERE user_id = $userId AND expires_at > '$now'";
            Database::query($expireSql);
        } else {
            $sql = "SELECT token FROM $table 
                    WHERE user_id = $userId AND expires_at > '$now' 
                    ORDER BY id DESC LIMIT 1";
            $res = Database::query($sql);
            if ($row = Database::fetch_assoc($res)) {
                return $row['token'];
            }
        }

        // Generate cryptographic secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = api_get_utc_datetime(strtotime("+$validDays days"));

        $sql = "INSERT INTO $table (user_id, token, created_at, expires_at, created_by)
                VALUES ($userId, '$token', '$now', '$expiresAt', $createdBy)";
        Database::query($sql);

        return $token;
    }

    /**
     * Return the complete Magic Login URL for a user
     *
     * @param int  $userId
     * @param bool $forceNew
     * @param int  $validDays
     * @return string
     */
    public static function getMagicLoginUrl($userId, $forceNew = false, $validDays = 30)
    {
        $createdBy = api_get_user_id();
        $token = self::getOrCreateToken($userId, $createdBy, $forceNew, $validDays);

        if (!empty($_SERVER['HTTP_HOST'])) {
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $scheme = $isHttps ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $relCodePath = api_get_path(REL_CODE_PATH);
            return $scheme . $host . '/' . ltrim($relCodePath, '/') . 'auth/magic_login.php?token=' . $token;
        }

        return api_get_path(WEB_CODE_PATH) . 'auth/magic_login.php?token=' . $token;
    }

    /**
     * Check if client IP is currently rate-limited (max 10 failed attempts per 15 minutes)
     *
     * @param string $ip
     * @return bool
     */
    public static function isRateLimited($ip = '')
    {
        self::initTable();
        $table = Database::get_main_table('user_magic_attempt');
        if (empty($ip)) {
            $ip = function_exists('api_get_real_ip') ? api_get_real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
        }
        $ip = Database::escape_string(substr(trim($ip), 0, 45));
        $windowStart = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        // Clean up old attempts (>24h)
        $cleanupTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        Database::query("DELETE FROM $table WHERE attempt_time < '$cleanupTime'");

        $sql = "SELECT COUNT(*) as cnt FROM $table WHERE ip = '$ip' AND attempt_time > '$windowStart'";
        $res = Database::query($sql);
        if ($res && ($row = Database::fetch_assoc($res))) {
            return (int) $row['cnt'] >= 10;
        }
        return false;
    }

    /**
     * Record a failed magic login attempt for rate-limiting
     *
     * @param string $ip
     */
    public static function recordFailedAttempt($ip = '')
    {
        self::initTable();
        $table = Database::get_main_table('user_magic_attempt');
        if (empty($ip)) {
            $ip = function_exists('api_get_real_ip') ? api_get_real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
        }
        $ip = Database::escape_string(substr(trim($ip), 0, 45));
        $now = api_get_utc_datetime();
        Database::query("INSERT INTO $table (ip, attempt_time) VALUES ('$ip', '$now')");
    }

    /**
     * Revoke a specific magic token immediately
     *
     * @param string $token
     * @return bool
     */
    public static function revokeToken($token)
    {
        self::initTable();
        $table = Database::get_main_table('user_magic_token');
        $token = Database::escape_string(trim($token));
        $now = api_get_utc_datetime();
        Database::query("UPDATE $table SET expires_at = '$now' WHERE token = '$token'");
        return Database::affected_rows() > 0;
    }

    /**
     * Revoke all active magic tokens for a given user
     *
     * @param int $userId
     * @return int Number of revoked tokens
     */
    public static function revokeTokensForUser($userId)
    {
        self::initTable();
        $table = Database::get_main_table('user_magic_token');
        $userId = (int) $userId;
        $now = api_get_utc_datetime();
        Database::query("UPDATE $table SET expires_at = '$now' WHERE user_id = $userId AND expires_at > '$now'");
        return Database::affected_rows();
    }

    /**
     * Validate a magic token and log the user into the platform.
     * Enforces single-use, rate-limiting, expiration, and session regeneration.
     *
     * @param string $token
     * @return array
     */
    public static function validateAndLogin($token)
    {
        self::initTable();
        $ip = function_exists('api_get_real_ip') ? api_get_real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');

        if (self::isRateLimited($ip)) {
            return ['success' => false, 'error' => 'rate_limited'];
        }

        $table = Database::get_main_table('user_magic_token');
        $token = Database::escape_string(trim($token));

        if (empty($token)) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'error' => 'empty_token'];
        }

        $now = api_get_utc_datetime();
        $sql = "SELECT * FROM $table WHERE token = '$token' LIMIT 1";
        $res = Database::query($sql);

        if (!$res || !($row = Database::fetch_assoc($res))) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'error' => 'invalid_or_expired'];
        }

        // Single-use enforcement: check if token was already used
        if (!empty($row['used_at'])) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'error' => 'already_used'];
        }

        // Expiration check
        if (strtotime($row['expires_at']) <= strtotime($now)) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'error' => 'expired'];
        }

        $userId = (int) $row['user_id'];
        $userInfo = api_get_user_info($userId, true);

        if (empty($userInfo)) {
            self::recordFailedAttempt($ip);
            return ['success' => false, 'error' => 'user_not_found'];
        }

        if (empty($userInfo['active'])) {
            return ['success' => false, 'error' => 'user_inactive'];
        }

        // Anti-session fixation: regenerate session id on privilege change
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Clean any existing session
        Session::erase('_user');
        Session::erase('_uid');
        Session::erase('is_platformAdmin');
        Session::erase('is_allowedCreateCourse');
        Session::erase('login_as');

        // Populate genuine user session
        $_user = _api_format_user($userInfo, false);
        $is_platformAdmin = UserManager::is_admin($userId);
        $is_allowedCreateCourse = $userInfo['status'] == COURSEMANAGER || (api_get_setting('drhCourseManagerRights') && $userInfo['status'] == DRH);

        Session::write('_uid', $userId);
        Session::write('_user', $userInfo);
        Session::write('is_platformAdmin', (bool) $is_platformAdmin);
        Session::write('is_allowedCreateCourse', (bool) $is_allowedCreateCourse);

        // Record tracking events
        Event::eventLogin($userId);
        Event::eventLoginAttempt($userInfo['username'], true);
        UserManager::update_extra_field_value($userId, 'already_logged_in', 'true');

        $logInfo = [
            'tool' => 'login',
            'tool_id' => 0,
            'tool_id_detail' => 0,
            'info' => $userId,
        ];
        Event::registerLog($logInfo);

        // Atomically mark token as used and expired to prevent replay race conditions
        $updateSql = "UPDATE $table SET used_at = '$now', expires_at = '$now' WHERE token = '$token'";
        Database::query($updateSql);

        return ['success' => true, 'user' => $userInfo];
    }
}
