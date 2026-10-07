<?php
/**
 * Config Loader — reads .env.php and provides global access
 * Usage: $cfg = config(); $dbHost = $cfg['DB_HOST'];
 */
if (!function_exists('config')) {
    function config(): array {
        static $cfg = null;
        if ($cfg !== null) return $cfg;
        $file = __DIR__ . '/../.env.php';
        if (!file_exists($file)) {
            http_response_code(500);
            exit('Configuration file missing. Copy .env.example to .env.php.');
        }
        $cfg = require $file;
        date_default_timezone_set('Asia/Kolkata');
        if (!is_array($cfg)) {
            http_response_code(500);
            exit('Invalid configuration file.');
        }
        foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','APP_URL'] as $key) {
            $value = getenv('CRM_' . $key);
            if ($value !== false) $cfg[$key] = $value;
        }
        return $cfg;
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string {
        $cfg = config();
        $base = rtrim($cfg['APP_URL'] ?? '', '/');
        if ($path === '') return $base ?: '/';
        return ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void {
        $url = site_url($path);
        header('Location: ' . $url);
        exit();
    }
}

if (!function_exists('abhi_url_rewrite_buffer')) {
    function abhi_url_rewrite_buffer(string $buffer): string {
        if (stripos($buffer, '<head') !== false && session_status() === PHP_SESSION_ACTIVE) {
            require_once __DIR__ . '/security.php';
            $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
            $script = htmlspecialchars(site_url('assets/js/request-security.js'), ENT_QUOTES, 'UTF-8');
            $buffer = preg_replace('/(<head\b[^>]*>)/i', '$1<meta name="csrf-token" content="' . $token . '"><script src="' . $script . '"></script>', $buffer, 1);
            $buffer = preg_replace('/(<form\b[^>]*\bmethod\s*=\s*["\x27]post["\x27][^>]*>)/i', '$1<input type="hidden" name="_csrf_token" value="' . $token . '">', $buffer);
        }
        $base = rtrim((string) (config()['APP_URL'] ?? ''), '/');
        if ($base === '') return $buffer;

        $patterns = [
            '~(href\s*=\s*["\'])\/(?!\/)~i',
            '~(src\s*=\s*["\'])\/(?!\/)~i',
            '~(action\s*=\s*["\'])\/(?!\/)~i',
            '~((?:location\.href|window\.location)\s*=\s*["\'])\/(?!\/)~i',
            '~(location\.replace\(\s*["\'])\/(?!\/)~i',
            '~(fetch\(\s*["\'])\/(?!\/)~i',
            '~(url\s*:\s*["\'])\/(?!\/)~i',
            '~(\$\.(?:get|post|getJSON|load)\(\s*["\'])\/(?!\/)~i',
            '~(axios\.[a-zA-Z]+\(\s*["\'])\/(?!\/)~i',
            '~(open\(\s*["\'][A-Z]+["\']\s*,\s*["\'])\/(?!\/)~i',
        ];

        $replacement = '$1' . $base . '/';
        return (string) preg_replace($patterns, $replacement, $buffer);
    }
}

if (!defined('ABHI_URL_REWRITE_STARTED')) {
    define('ABHI_URL_REWRITE_STARTED', true);
    if (PHP_SAPI !== 'cli') {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptBase = basename($scriptName);
        $skipRewrite = (
            strpos($scriptName, '/ajax/') !== false ||
            strpos($scriptName, '/scripts/') !== false ||
            strpos($scriptBase, 'export-') === 0
        );
        if (!$skipRewrite) {
            ob_start('abhi_url_rewrite_buffer');
        }
    }
}
