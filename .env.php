

<?php
/**
 * Environment Configuration — Uttarakhand Ventures CRM
 * DO NOT commit this file to version control.
 * Copy .env.example to .env.php and fill in your values.
 */
return [
    // Database
    'DB_HOST'   => 'localhost',
    'DB_NAME'   => 'employee_management',
    'DB_USER'   => 'root',
    'DB_PASS'   => '',
    'DB_CHARSET'=> 'utf8mb4',

    // Rate Limiting (requests per window)
    'RATE_LIMIT_LOGIN'         => 5,      // max login attempts
    'RATE_LIMIT_LOGIN_WINDOW'  => 900,    // 15 minutes in seconds
    'RATE_LIMIT_API'           => 60,     // max API requests
    'RATE_LIMIT_API_WINDOW'    => 60,     // 1 minute
    'RATE_LIMIT_BACKOFF_BASE'  => 00,     // base backoff in seconds

    // Session
    'SESSION_LIFETIME'         => 7200,    // 2 hours in seconds
    'SESSION_REGENERATE_INTERVAL' => 300, // regenerate ID every 5 min

    // App
    'APP_ENV'                  => 'development',  // development | production
    'APP_DEBUG'                => true,
    'APP_URL'                  => 'http://localhost/abhi',
];

