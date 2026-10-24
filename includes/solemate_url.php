<?php
// ═══════════════════════════════════════════════════════
//  solemate_url.php  —  where is this site served from?
//
//  appBaseUrl() returns the public address of the SoleMate folder with no
//  trailing slash, e.g.
//     http://localhost:8092            (php -S started inside the folder)
//     http://localhost/SoleMate        (XAMPP / WAMP / IIS)
//     https://solemate.co.za           (live site — uses APP_URL from .env)
//
//  On localhost it always follows the address the visitor is really using,
//  so the project works on any laptop/port with no .env edits. Use it for
//  every absolute link that goes in an email or to PayFast — never hard-code
//  "/SoleMate/".
// ═══════════════════════════════════════════════════════
if (!function_exists('appBaseUrl')) {
    function appBaseUrl(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $env   = rtrim((string)getenv('APP_URL'), '/');
        $host  = $_SERVER['HTTP_HOST'] ?? '';
        $local = $host !== '' && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host);

        // Live site: trust APP_URL
        if (!$local && $env !== '') return $cached = $env;
        // CLI / no request: fall back to .env or the usual XAMPP address
        if ($host === '')           return $cached = ($env !== '' ? $env : 'http://localhost/SoleMate');

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // Web path of the project folder = project folder minus the web server's document root
        $root = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
        $docR = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $doc  = rtrim(str_replace('\\', '/', ($docR !== '' ? (realpath($docR) ?: $docR) : '')), '/');
        if ($doc !== '' && stripos($root, $doc) === 0) {
            $path = substr($root, strlen($doc));
        } else {
            $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        }
        $path = rtrim($path, '/');
        if ($path === '.') $path = '';

        return $cached = $scheme . '://' . $host . $path;
    }
}
