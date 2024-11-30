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

// Fetch the email details from the 'emails' table where status is 'spam'
$stmt = $pdo->prepare('
    SELECT * FROM emails 
    WHERE id = :id AND user_id = :user_id AND status = "spam"
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// If the email doesn't exist in spam, show an error
if (!$email) {
    die('Email not found in spam.');
}

// Log the email restoration process (for debugging)
error_log("Restoring email with ID: $email_id for user ID: {$_SESSION['user_id']}");

// Restore the email by updating its status to 'sent'
$stmt = $pdo->prepare('
    UPDATE emails 
    SET status = "sent"
    WHERE id = :id AND user_id = :user_id AND status = "spam"
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);

// Check if the email was successfully restored
if ($stmt->rowCount() > 0) {
    // Redirect to sent.php to show the email in the "Sent" folder
    header('Location: sent.php');
    exit;
} else {
    // If no rows were updated, output an error
    echo 'Failed to restore the email. Please try again.';
}
?>
