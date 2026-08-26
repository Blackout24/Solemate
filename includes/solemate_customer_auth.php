<?php
// ═══════════════════════════════════════════════════════
//  solemate_customer_auth.php  —  Customer Auth Helpers
//
//  Include in any page that needs customer authentication.
//  Usage:
//    require 'includes/solemate_customer_auth.php';
//    requireCustomerLogin();          // redirect if not logged in
//    $id   = getCustomerId();
//    $name = getCustomerName();
// ═══════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect to login if the customer is not authenticated.
 * Passes a 'redirect' param so the login page can return them after.
 */
function requireCustomerLogin(string $redirect = 'solemate_customer_login.php'): void
{
    if (empty($_SESSION['customer_logged_in'])) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$redirect}" . ($back ? "?redirect={$back}" : ''));
        exit;
    }
}

/** Is a customer currently logged in? */
function isCustomerLoggedIn(): bool
{
    return !empty($_SESSION['customer_logged_in']);
}

/** Return the logged-in customer's DB id (0 if not logged in). */
function getCustomerId(): int
{
    return (int)($_SESSION['customer_id'] ?? 0);
}

/** Return the logged-in customer's display name. */
function getCustomerName(): string
{
    return htmlspecialchars($_SESSION['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
}

/** Return the logged-in customer's email. */
function getCustomerEmail(): string
{
    return htmlspecialchars($_SESSION['customer_email'] ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Log a customer in — sets all session keys and regenerates the ID.
 * Call this after a successful password_verify() check.
 *
 * @param array $row   A row from dbo.customers (must have id, first_name, last_name, email).
 */
function loginCustomer(array $row): void
{
    session_regenerate_id(true);
    $_SESSION['customer_logged_in'] = true;
    $_SESSION['customer_id']        = (int)$row['id'];
    $_SESSION['customer_email']     = $row['email'];
    $_SESSION['customer_name']      = trim($row['first_name'] . ' ' . $row['last_name']);
}

/** Destroy the customer session. */
function logoutCustomer(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}
