<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($email_id <= 0) {
    die('Invalid email ID.');
}

// Database connection
$host = 'localhost';
$db   = 'HueMail';
$user = 'root';  // Change to your MySQL username
$pass = '';      // Change to your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if we are permanently deleting or just moving to trash
if (isset($_GET['permanent']) && $_GET['permanent'] == 'true') {
    // Permanently delete the email from the deleted_emails table
    $stmt = $pdo->prepare('DELETE FROM deleted_emails WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $email_id, ':user_id' => $user_id]);

    // Redirect to Trash folder or show success message
    header('Location: trash.php');
    exit;
} else {
    // Move the email to deleted_emails table (soft delete)
    $stmt = $pdo->prepare('
        INSERT INTO deleted_emails (sender, recipient, subject, body, created_at, deleted_at, user_id)
        SELECT sender, recipient, subject, body, created_at, NOW(), user_id
        FROM emails
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([':id' => $email_id, ':user_id' => $user_id]);

    // Delete the email from the emails table
    $stmt = $pdo->prepare('DELETE FROM emails WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $email_id, ':user_id' => $user_id]);

    // Redirect to Sent folder or show success message
    header('Location: sent.php');
    exit;
}
?>
