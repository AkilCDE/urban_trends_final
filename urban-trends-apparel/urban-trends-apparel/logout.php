<?php
// Include configuration
require_once 'includes/config.php';

// Use Auth class to handle logout
$auth->logout();

// Redirect to login page
header("Location: login.php");
exit;
?>