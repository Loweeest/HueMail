<?php
// Start the session
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection details
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

// Get the email ID from the URL
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($email_id <= 0) {
    die('Invalid email ID.');
}

// Fetch the email details from the 'emails' table where status is 'archive'
$stmt = $pdo->prepare('
    SELECT * FROM emails 
    WHERE id = :id AND user_id = :user_id AND status = "archive"
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// If the email doesn't exist in the archive, show an error
if (!$email) {
    die('Email not found in archive.');
}

// Restore the email by updating its status to 'sent'
$stmt = $pdo->prepare('
    UPDATE emails 
    SET status = "sent"
    WHERE id = :id AND user_id = :user_id AND status = "archive"
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);

// Redirect to sent.php to show the email in the "Sent" folder
header('Location: sent.php');
exit;
?>
