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
        if (!is_array($cfg)) {
            http_response_code(500);
            exit('Invalid configuration file.');
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
