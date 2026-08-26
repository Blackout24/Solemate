<?php
// ═══════════════════════════════════════════════════════
//  solemate_customer_logout.php  —  Customer Sign Out
// ═══════════════════════════════════════════════════════
require 'includes/solemate_customer_auth.php';

logoutCustomer();

header('Location: solemate_customer_login.php?bye=1');
exit;
