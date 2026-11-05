<?php
/* For licensing terms, see /license.txt */

/**
 * Direct Magic Login Handler
 * Authenticates user via secret token and redirects to their dashboard.
 */

require_once __DIR__.'/../inc/global.inc.php';
require_once api_get_path(SYS_CODE_PATH).'inc/lib/magic_login.lib.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    displayError('No login token provided. Please use the complete link provided by your administrator.');
}

$result = MagicLoginManager::validateAndLogin($token);

if ($result['success']) {
    // Successfully authenticated!
    // Redirect to user portal / dashboard
    $portalUrl = api_get_path(WEB_PATH).'user_portal.php';
    header('Location: '.$portalUrl);
    exit;
} else {
    $errCode = $result['error'] ?? 'unknown';
    $msg = 'This login link is invalid or has expired.';
    if ($errCode === 'user_inactive') {
        $msg = 'This user account is currently deactivated. Please contact support.';
    } elseif ($errCode === 'user_not_found') {
        $msg = 'The account associated with this link was not found.';
    }
    displayError($msg);
}

function displayError($message) {
    $homeUrl = api_get_path(WEB_PATH);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Link Expired - Chamilo</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .card {
                background: rgba(30, 41, 59, 0.9);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 40px;
                max-width: 480px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
                backdrop-filter: blur(12px);
            }
            .icon-wrap {
                width: 68px;
                height: 68px;
                background: rgba(239, 68, 68, 0.15);
                color: #ef4444;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
                font-size: 32px;
            }
            h1 {
                font-size: 22px;
                font-weight: 700;
                color: #ffffff;
                margin-bottom: 12px;
            }
            p {
                font-size: 15px;
                color: #94a3b8;
                line-height: 1.6;
                margin-bottom: 28px;
            }
            .btn {
                display: inline-block;
                padding: 12px 28px;
                font-size: 15px;
                font-weight: 600;
                color: #ffffff;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s ease;
                box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
            }
            .btn:hover {
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                transform: translateY(-1px);
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrap">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <h1>Link Invalid or Expired</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="btn">Go to Main Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
