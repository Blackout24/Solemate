<?php
// ═══════════════════════════════════════════════════════
//  solemate_backup.php  —  Database Backup Exporter
//  Protected: admin session required.
//  Exports all tables as a portable SQL INSERT dump.
// ═══════════════════════════════════════════════════════
session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: solemate_admin_login.php');
    exit;
}

require 'includes/solemate_database_connection.php';

// ── Tables to export (in dependency order) ──────────────
$tables = ['services', 'orders', 'reviews', 'admin_users'];

// ── Helpers ─────────────────────────────────────────────

/**
 * Quote a value for safe SQL INSERT output.
 */
function quoteVal($val): string {
    if ($val === null)      return 'NULL';
    if (is_bool($val))      return $val ? '1' : '0';
    if (is_int($val) || is_float($val)) return (string)$val;
    // Escape single quotes and wrap
    return "'" . str_replace("'", "''", (string)$val) . "'";
}

/**
 * Fetch all rows from a table via PDO (SQL Server).
 */
function dumpTable(PDO $pdo, string $table): string {
    $out = '';

    // Get columns
    $colStmt = $pdo->query(
        "SELECT COLUMN_NAME, DATA_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_NAME = " . $pdo->quote($table) . "
         ORDER BY ORDINAL_POSITION"
    );
    $columns = $colStmt->fetchAll();

    if (empty($columns)) return '';

    $colNames = array_column($columns, 'COLUMN_NAME');

    // Get rows
    $rows = $pdo->query("SELECT * FROM dbo.{$table}")->fetchAll();

    if (empty($rows)) {
        $out .= "-- (no rows in {$table})\n";
        return $out;
    }

    $colList = implode(', ', array_map(fn($c) => "[{$c}]", $colNames));

    foreach ($rows as $row) {
        $vals = array_map(fn($col) => quoteVal($row[$col] ?? null), $colNames);
        $out .= "INSERT INTO dbo.[{$table}] ({$colList}) VALUES (" . implode(', ', $vals) . ");\n";
    }

    return $out;
}

// ── Build the SQL dump ───────────────────────────────────
$timestamp  = date('Y-m-d_His');
$filename   = "solemate_backup_{$timestamp}.sql";

$sql  = "-- ═══════════════════════════════════════════════════════\n";
$sql .= "--  SoleMate Database Backup\n";
$sql .= "--  Generated : " . date('Y-m-d H:i:s') . "\n";
$sql .= "--  Tables    : " . implode(', ', $tables) . "\n";
$sql .= "--\n";
$sql .= "--  Restore: run this file in SSMS against shoe_repair_db\n";
$sql .= "--           after the schema (SQLQuery1.sql) is in place.\n";
$sql .= "-- ═══════════════════════════════════════════════════════\n\n";

$sql .= "USE shoe_repair_db;\n";
$sql .= "SET NOCOUNT ON;\n\n";

foreach ($tables as $table) {
    $sql .= "-- ── {$table} ──────────────────────────────────────────\n";

    // Disable identity insert if the table has an identity column
    $idCheck = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_NAME = " . $pdo->quote($table) . "
           AND COLUMNPROPERTY(OBJECT_ID('dbo.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') = 1"
    )->fetch();

    $hasIdentity = ($idCheck['cnt'] ?? 0) > 0;

    if ($hasIdentity) {
        $sql .= "SET IDENTITY_INSERT dbo.[{$table}] ON;\n";
    }

    $sql .= dumpTable($pdo, $table);

    if ($hasIdentity) {
        $sql .= "SET IDENTITY_INSERT dbo.[{$table}] OFF;\n";
    }

    $sql .= "\n";
}

$sql .= "-- ── End of backup ────────────────────────────────────\n";

// ── Stream as download ───────────────────────────────────
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $sql;
exit;
