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

// Fetch the email details from the deleted_emails table
$stmt = $pdo->prepare('
    SELECT * FROM deleted_emails 
    WHERE id = :id AND user_id = :user_id
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// Debugging: Check if the email was fetched
if (!$email) {
    echo "Email not found in trash.<br>";
    die();
} else {
    echo "Found email to restore: " . htmlspecialchars($email['subject']) . "<br>";
}

// Restore the email by inserting it back into the emails table
$stmt = $pdo->prepare('
    INSERT INTO emails (sender, recipient, subject, body, created_at, user_id, status)
    VALUES (:sender, :recipient, :subject, :body, :created_at, :user_id, :status)
');
$stmt->execute([
    ':sender' => $email['sender'],
    ':recipient' => $email['recipient'],
    ':subject' => $email['subject'],
    ':body' => $email['body'],
    ':created_at' => $email['created_at'],
    ':user_id' => $_SESSION['user_id'],
    ':status' => 'sent'  // Ensure the email has 'sent' status when restored
]);

// Check if the email was originally sent or archived
if ($email['status'] == 'sent') {
    // Restore the email by inserting it back into the emails table with 'sent' status
    $stmt = $pdo->prepare('
        INSERT INTO emails (sender, recipient, subject, body, created_at, user_id, status)
        VALUES (:sender, :recipient, :subject, :body, :created_at, :user_id, :status)
    ');
    $stmt->execute([
        ':sender' => $email['sender'],
        ':recipient' => $email['recipient'],
        ':subject' => $email['subject'],
        ':body' => $email['body'],
        ':created_at' => $email['created_at'],
        ':user_id' => $_SESSION['user_id'],
        ':status' => 'sent'  // Restore as 'sent'
    ]);
    echo "Email restored as 'sent'.<br>";

} else if ($email['status'] == 'archive') {
    // Restore the email by inserting it back into the emails table with 'archive' status
    $stmt = $pdo->prepare('
        INSERT INTO emails (sender, recipient, subject, body, created_at, user_id, status)
        VALUES (:sender, :recipient, :subject, :body, :created_at, :user_id, :status)
    ');
    $stmt->execute([
        ':sender' => $email['sender'],
        ':recipient' => $email['recipient'],
        ':subject' => $email['subject'],
        ':body' => $email['body'],
        ':created_at' => $email['created_at'],
        ':user_id' => $_SESSION['user_id'],
        ':status' => 'archive'  // Restore as 'archive'
    ]);
    echo "Email restored as 'archive'.<br>";

} else {
    echo "Email status not recognized.<br>";
}


// Now, delete it from the deleted_emails table (permanently removing it from trash)
$stmt = $pdo->prepare('DELETE FROM deleted_emails WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);



echo "Email deleted from trash.<br>";

// Redirect to sent.php to show the email in the "Sent" folder
header('Location: sent.php');
exit;
?>
