<?php
// ═══════════════════════════════════════════════════════
//  db.php  —  PDO Database Connection (SQL Server / SSMS)
// ═══════════════════════════════════════════════════════
//
//  REQUIREMENTS
//  ─────────────────────────────────────────────────────
//  • PHP extension: pdo_sqlsrv   (Microsoft SQL Server Driver for PHP)
//    Install on Windows (XAMPP / WAMP):
//      1. Download the correct .dll from:
//         https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
//      2. Copy php_pdo_sqlsrv_XX_ts.dll to your PHP ext\ folder
//      3. Add to php.ini:
//           extension=php_pdo_sqlsrv_XX_ts.dll
//      4. Restart Apache / IIS
//
//    Install on Linux (Ubuntu/Debian):
//      sudo pecl install pdo_sqlsrv
//
//  • Microsoft ODBC Driver 17 or 18 for SQL Server must be installed.
//    Download: https://aka.ms/downloadmsodbcsql
//
//  CONNECTION SETTINGS — edit these to match your environment
//  ─────────────────────────────────────────────────────

$host    = 'DESKTOP-4F355RV\\SQLEXPRESS';          // e.g. 'localhost\SQLEXPRESS' for Express edition
$db      = 'shoe_repair_db';     // Database created by sql/SQLQuery1.sql
$user    = 'DESKTOP-4F355RV\\User';                 // SQL Server login (ignored if Windows Auth below)
$pass    = '';                   // SQL Server password

// Set to true to use Windows Authentication instead of SQL login:
$use_windows_auth = true;

// ─────────────────────────────────────────────────────
//  Build DSN & connect
// ─────────────────────────────────────────────────────
$dsn = "sqlsrv:Server={$host};Database={$db};TrustServerCertificate=1";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if ($use_windows_auth) {
        $pdo = new PDO($dsn, null, null, $options);
    } else {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (\PDOException $e) {
    die('
    <!DOCTYPE html><html><head>
    <link rel="shortcut icon" href="includes/Solemate_logo.ico" type="image/x-icon">
    <link rel="icon" href="includes/Solemate_logo.ico" type="image/x-icon">
    <style>
      body{font-family:sans-serif;background:#FAF7F2;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
      .box{background:#fff;border:1px solid #f0c0a0;border-radius:12px;padding:2rem;max-width:520px;text-align:center}
      h2{color:#D4561A;margin-bottom:0.5rem}
      p{color:#8B7355;font-size:14px;line-height:1.6}
      code{background:#FAF7F2;padding:2px 8px;border-radius:4px;font-size:13px;color:#6B4226}
      ul{text-align:left;color:#8B7355;font-size:13px;line-height:1.8;margin-top:0.75rem}
    </style>
    </head><body><div class="box">
    <h2>⚠️ SQL Server Connection Failed</h2>
    <p>Could not connect to <code>' . htmlspecialchars($db) . '</code> on <code>' . htmlspecialchars($host) . '</code>.</p>
    <ul>
      <li>SQL Server / SQL Server Express is running</li>
      <li>You have run <code>sql/SQLQuery1.sql</code> in SSMS</li>
      <li>The <code>pdo_sqlsrv</code> PHP extension is installed &amp; enabled</li>
      <li>Microsoft ODBC Driver 17 or 18 for SQL Server is installed</li>
      <li><code>$host</code>, <code>$user</code>, and <code>$pass</code> in <code>solemate_database_connection.php</code> are correct</li>
      <li>SQL Server Browser service is running (required for named instances like <code>localhost\SQLEXPRESS</code>)</li>
    </ul>
    <p><small>Error detail: ' . htmlspecialchars($e->getMessage()) . '</small></p>
    </div></body></html>');
}