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
     * Ensure the user_magic_token table exists
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
     * Validate a magic token and log the user into the platform
     *
     * @param string $token
     * @return array
     */
    public static function validateAndLogin($token)
    {
        self::initTable();
        $table = Database::get_main_table('user_magic_token');
        $token = Database::escape_string(trim($token));

        if (empty($token)) {
            return ['success' => false, 'error' => 'empty_token'];
        }

        $now = api_get_utc_datetime();
        $sql = "SELECT * FROM $table WHERE token = '$token' AND expires_at > '$now' LIMIT 1";
        $res = Database::query($sql);

        if (!$row = Database::fetch_assoc($res)) {
            return ['success' => false, 'error' => 'invalid_or_expired'];
        }

        $userId = (int) $row['user_id'];
        $userInfo = api_get_user_info($userId, true);

        if (empty($userInfo)) {
            return ['success' => false, 'error' => 'user_not_found'];
        }

        if (empty($userInfo['active'])) {
            return ['success' => false, 'error' => 'user_inactive'];
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

        // Update token used_at timestamp
        $updateSql = "UPDATE $table SET used_at = '$now' WHERE token = '$token'";
        Database::query($updateSql);

        return ['success' => true, 'user' => $userInfo];
    }
}
