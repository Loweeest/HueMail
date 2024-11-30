<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error_message' => 'User is not logged in.']);
    exit;
}

// Ensure CSRF token is valid
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error_message' => 'Invalid CSRF token.']);
    exit;
}

// Get the posted data
$recipient = filter_var($_POST['recipient'], FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars($_POST['subject']);
$body = htmlspecialchars($_POST['body']);
$save_draft = isset($_POST['save_draft']) ? (int)$_POST['save_draft'] : 0;
$user_id = $_SESSION['user_id'];

// Validate the recipient email address
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error_message' => 'Invalid recipient email address.']);
    exit;
}

try {
    // Database connection setup
    $pdo = new PDO("mysql:host=localhost;dbname=HueMail", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start a transaction to ensure both updates succeed or fail together
    $pdo->beginTransaction();

    // Check if a draft already exists, otherwise create a new one
    $stmt = $pdo->prepare('
        INSERT INTO emails (sender, recipient, subject, body, user_id, status)
        VALUES (?, ?, ?, ?, ?, "draft")
        ON DUPLICATE KEY UPDATE recipient = ?, subject = ?, body = ?
    ');

    // If the email doesn't already exist as a draft, it will be inserted; if it does, it will be updated
    $stmt->execute([$_SESSION['email'], $recipient, $subject, $body, $user_id, $recipient, $subject, $body]);

    // Update or insert the email in the inbox_emails table as well
    $stmtInbox = $pdo->prepare('
        INSERT INTO inbox_emails (email_id, user_id, recipient, subject, body, status)
        VALUES (?, ?, ?, ?, ?, "draft")
        ON DUPLICATE KEY UPDATE recipient = ?, subject = ?, body = ?
    ');

    // Get the last inserted email ID from the emails table
    $email_id = $pdo->lastInsertId(); // Get the ID of the email that was just inserted or updated

    // Insert or update the record in inbox_emails
    $stmtInbox->execute([$email_id, $user_id, $recipient, $subject, $body, $recipient, $subject, $body]);

    // Commit the transaction
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Rollback the transaction in case of error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error_message' => 'Database error: ' . $e->getMessage()]);
}
?>