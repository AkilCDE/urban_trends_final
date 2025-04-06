<?php
require_once '../config.php';

if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$backupDir = '../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Backup filename
$backupFile = $backupDir . 'urban_trends_' . date("Y-m-d_H-i-s") . '.sql';

// Command to dump database
$command = "mysqldump --user=" . DB_USER . " --password=" . DB_PASS . " --host=" . DB_HOST . " " . DB_NAME . " > " . $backupFile;

// Execute command
system($command, $output);

if ($output === 0) {
    // Get file size
    $fileSize = filesize($backupFile);
    
    // Record in database
    $stmt = $db->prepare("INSERT INTO backup_logs (filename, backup_size, backup_type, created_by) 
                         VALUES (?, ?, 'full', ?)");
    $stmt->execute([basename($backupFile), $fileSize, $_SESSION['user_id']]);
    
    $_SESSION['success'] = "Database backup created successfully!";
} else {
    $_SESSION['error'] = "Failed to create database backup";
}

header("Location: dashboard.php");
exit;
?>