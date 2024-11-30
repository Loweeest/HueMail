<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection setup
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

// Pagination settings
$emails_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $emails_per_page;

// Folder selection
$valid_folders = ['inbox', 'unread', 'draft', 'sent', 'archive', 'trash', 'spam', 'starred'];
$folder = isset($_GET['folder']) ? $_GET['folder'] : 'inbox';
if (!in_array($folder, $valid_folders)) {
    $folder = 'inbox';
}

// Get the last fetched email ID (if provided)
$lastFetchedEmailId = isset($_GET['lastFetchedEmailId']) ? (int)$_GET['lastFetchedEmailId'] : 0;

// Prepare the SQL query for the emails in the selected folder
$query = '
    SELECT id, sender, subject, body, received_at, status, attachment
    FROM inbox_emails
    WHERE user_id = :user_id AND status IN ("inbox", "unread")
    ORDER BY received_at DESC
    LIMIT :offset, :emails_per_page
';

$stmt = $pdo->prepare($query);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':emails_per_page', $emails_per_page, PDO::PARAM_INT);
$stmt->execute();

$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there are any new emails (those with an ID greater than the provided lastFetchedEmailId)
$newEmails = [];
if ($emails) {
    foreach ($emails as $email) {
        if ($email['id'] > $lastFetchedEmailId) {
            $newEmails[] = $email;
        }
    }
}

// Return the response as JSON
echo json_encode([
    'status' => 'success',
    'emails' => $emails,  // Return all emails for pagination
    'newEmails' => $newEmails // Return only new emails
]);
?>
