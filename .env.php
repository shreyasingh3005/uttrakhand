


<?php
/**
 * Environment Configuration — Uttarakhand Ventures CRM
 * DO NOT commit this file to version control.
 * Copy .env.example to .env.php and fill in your values.
 */
return [
    // Database
    'DB_HOST'   => 'localhost',
    'DB_NAME'   => 'u424679052_hotel',
    'DB_USER'   => 'u424679052_venture',
    'DB_PASS'   => 'VTF?D;PsA!2d',
    'DB_CHARSET'=> 'utf8mb4',

    // Rate Limiting (requests per window)
    'RATE_LIMIT_LOGIN'         => 40,      // max login attempts
    'RATE_LIMIT_LOGIN_WINDOW'  => 900,    // 15 minutes in seconds
    'RATE_LIMIT_API'           => 60,     // max API requests
    'RATE_LIMIT_API_WINDOW'    => 60,     // 1 minute
    'RATE_LIMIT_BACKOFF_BASE'  => 30,     // base backoff in seconds

    // Session
    'SESSION_LIFETIME'         => 7200,    // 2 hours in seconds
    'SESSION_REGENERATE_INTERVAL' => 300, // regenerate ID every 5 min

    // App
    'APP_ENV'                  => 'production',  // development | production
    'APP_DEBUG'                => false,
    'APP_URL'                  => 'http://paleturquoise-tarsier-877492.hostingersite.com',

    // SMTP (set these for admin password reset emails)
    'MAIL_HOST'                => '',
    'MAIL_PORT'                => 587,
    'MAIL_USERNAME'            => '',
    'MAIL_PASSWORD'            => '',
    'MAIL_ENCRYPTION'          => 'tls', // tls | ssl | none
    'MAIL_FROM_ADDRESS'        => '',
    'MAIL_FROM_NAME'           => 'Uttarakhand Ventures CRM',
];
