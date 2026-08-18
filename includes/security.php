<?php
/**
 * Security Helpers — Rate Limiting, CSRF, Input Validation
 * Uttarakhand Ventures CRM
 */
declare(strict_types=1);

/* ── Rate Limiting ─────────────────────────────────────────────────────── */
/**
 * Simple file-based rate limiter.
 * @param string $key       Unique identifier (e.g. 'login:192.168.1.1')
 * @param int    $max       Max attempts allowed in window
 * @param int    $window    Window duration in seconds
 * @return array{allowed: bool, remaining: int, retry_after: int}
 */
function rate_limit(string $key, int $max, int $window): array {
    $dir = sys_get_temp_dir() . '/uv_rate_limits';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';

    $now = time();
    $data = ['attempts' => [], 'blocked_until' => 0];

    if (file_exists($file)) {
        $raw = file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
    }

    // Check if blocked (exponential backoff)
    if (($data['blocked_until'] ?? 0) > $now) {
        $retryAfter = $data['blocked_until'] - $now;
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
    }

    // Purge expired attempts
    $data['attempts'] = array_filter($data['attempts'], fn($t) => ($now - $t) < $window);
    $data['attempts'] = array_values($data['attempts']);

    $remaining = max(0, $max - count($data['attempts']));

    if (count($data['attempts']) >= $max) {
        // Exponential backoff: 30s, 60s, 120s, ... capped at 1 hour
        $attempts = count($data['attempts']) - $max;
        $cfg = config();
        $base = $cfg['RATE_LIMIT_BACKOFF_BASE'] ?? 30;
        $backoff = min($base * pow(2, $attempts), 3600);
        $data['blocked_until'] = $now + (int)$backoff;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => (int)$backoff];
    }

    $data['attempts'][] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);

    return ['allowed' => true, 'remaining' => $remaining, 'retry_after' => 0];
}

/**
 * Check rate limit for login attempts (per-IP + per-account).
 */
function check_login_rate_limit(string $username): array {
    $cfg = config();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $max = $cfg['RATE_LIMIT_LOGIN'] ?? 5;
    $window = $cfg['RATE_LIMIT_LOGIN_WINDOW'] ?? 900;

    // Check IP limit
    $ipResult = rate_limit("login:ip:{$ip}", $max, $window);
    if (!$ipResult['allowed']) {
        return ['allowed' => false, 'message' => "Too many login attempts. Try again in {$ipResult['retry_after']}s.", 'retry_after' => $ipResult['retry_after']];
    }

    // Check per-account limit
    $acctResult = rate_limit("login:user:{$username}", $max, $window);
    if (!$acctResult['allowed']) {
        return ['allowed' => false, 'message' => "Account temporarily locked. Try again in {$acctResult['retry_after']}s.", 'retry_after' => $acctResult['retry_after']];
    }

    return ['allowed' => true, 'message' => '', 'retry_after' => 0];
}

/**
 * Record a failed login attempt.
 */
function record_failed_login(string $username): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cfg = config();
    $max = $cfg['RATE_LIMIT_LOGIN'] ?? 5;
    $window = $cfg['RATE_LIMIT_LOGIN_WINDOW'] ?? 900;
    rate_limit("login:ip:{$ip}", $max, $window);
    rate_limit("login:user:{$username}", $max, $window);
}

/* ── CSRF Protection ───────────────────────────────────────────────────── */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh and try again.']);
        exit();
    }
}

/* ── Input Validation ──────────────────────────────────────────────────── */
function validate_required(array $input, array $rules): array {
    $errors = [];
    foreach ($rules as $field => $rule) {
        $value = $input[$field] ?? null;
        $label = $rule['label'] ?? $field;

        // Required check
        if (($rule['required'] ?? false) && ($value === null || $value === '' || $value === false)) {
            $errors[$field] = "{$label} is required.";
            continue;
        }

        if ($value === null || $value === '') continue;

        // Type check
        $type = $rule['type'] ?? 'string';
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "{$label} must be a valid email address.";
                }
                break;
            case 'int':
                if (!ctype_digit((string)$value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $errors[$field] = "{$label} must be a valid number.";
                }
                break;
            case 'float':
                if (!is_numeric($value)) {
                    $errors[$field] = "{$label} must be a valid number.";
                }
                break;
            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
                    $errors[$field] = "{$label} must be a valid date (YYYY-MM-DD).";
                }
                break;
            case 'phone':
                if (!preg_match('/^[\+]?[\d\s\-\(\)]{7,20}$/', (string)$value)) {
                    $errors[$field] = "{$label} must be a valid phone number.";
                }
                break;
        }

        // Length checks
        $len = mb_strlen((string)$value);
        if (isset($rule['min_length']) && $len < $rule['min_length']) {
            $errors[$field] = "{$label} must be at least {$rule['min_length']} characters.";
        }
        if (isset($rule['max_length']) && $len > $rule['max_length']) {
            $errors[$field] = "{$label} must not exceed {$rule['max_length']} characters.";
        }

        // Pattern match
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], (string)$value)) {
            $errors[$field] = "{$label} has an invalid format.";
        }
    }
    return $errors;
}

/* ── Secure Input Sanitization ─────────────────────────────────────────── */
function sanitize_string(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitize_email(string $input): string {
    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
}

function sanitize_int($input): int {
    return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitize_float($input): float {
    return (float)filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/* ── Security Headers ──────────────────────────────────────────────────── */
function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (($_SERVER['HTTPS'] ?? '') === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ── Safe Error Response (no info leakage) ─────────────────────────────── */
function safe_error_response(int $code, string $userMessage, ?Throwable $exception = null): never {
    $cfg = config();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    // Log detailed error server-side
    if ($exception) {
        error_log(sprintf(
            "[%s] %s in %s:%d — %s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));
    }

    // Never expose internal details to users
    $response = ['status' => 'error', 'message' => $userMessage];
    if (($cfg['APP_DEBUG'] ?? false) && $exception) {
        $response['debug'] = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ];
    }
    echo json_encode($response);
    exit();
}
