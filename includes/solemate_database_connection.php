<?php
// ═══════════════════════════════════════════════════════
//  solemate_database_connection.php  —  PDO MySQL (Railway)
// ═══════════════════════════════════════════════════════

$host = getenv('MYSQLHOST')     ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT')     ?: '3306';
$db   = getenv('MYSQL_DATABASE') ?: 'railway';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
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
    <h2>⚠️ Database Connection Failed</h2>
    <p>Could not connect to <code>' . htmlspecialchars($db) . '</code> on <code>' . htmlspecialchars($host) . '</code>.</p>
    <ul>
      <li>MySQL service is running on Railway</li>
      <li>Environment variables are set correctly in Railway dashboard</li>
      <li>Your SQL tables have been imported into the Railway MySQL database</li>
    </ul>
    <p><small>Error detail: ' . htmlspecialchars($e->getMessage()) . '</small></p>
    </div></body></html>');
}
