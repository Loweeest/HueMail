<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'vendor/autoload.php';
use WebSocket\Client;

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

// Define valid folders
$valid_folders = ['inbox', 'unread', 'draft', 'sent', 'archive', 'trash', 'spam', 'starred'];

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Pagination setup
$emails_per_page = 5;  // Number of emails per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $emails_per_page;

// Handle folder selection (default to 'inbox' if invalid folder is provided)
$folder = isset($_GET['folder']) && in_array($_GET['folder'], $valid_folders) ? $_GET['folder'] : 'inbox';

// Handle search functionality
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$where_sql = '';

// If there is a search query, modify WHERE clause
if ($search_query) {
    $where_sql = "AND (e.subject LIKE :search_query OR e.sender LIKE :search_query)";
}

// Prepare the query based on the selected folder and search query
$sql = "
    SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.status, e.created_at, e.attachment, u.email AS sender_email
    FROM emails e
    JOIN register u ON e.user_id = u.id
    " . ($folder === 'starred' ? "JOIN starred_emails s ON e.id = s.email_id" : "") . "
    WHERE e.user_id = :user_id
    $where_sql
    ORDER BY e.created_at ASC
    LIMIT :limit OFFSET :offset
";

// Prepare and execute the statement
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':limit', $emails_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

// Bind the search query parameter if a search is provided
if ($search_query) {
    $stmt->bindValue(':search_query', "%$search_query%", PDO::PARAM_STR);
}

$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count the total number of emails for pagination
$total_stmt = $pdo->prepare('
    SELECT COUNT(*) AS total
    FROM emails e
    JOIN register u ON e.user_id = u.id
    WHERE e.user_id = :user_id
    ' . ($folder === 'starred' ? "JOIN starred_emails s ON e.id = s.email_id" : "") . '
    ' . ($search_query ? "AND (e.subject LIKE :search_query OR e.sender LIKE :search_query)" : "")
);

$total_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
if ($search_query) {
    $total_stmt->bindValue(':search_query', "%$search_query%", PDO::PARAM_STR);
}

$total_stmt->execute();
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $emails_per_page);

// Fetch the user's profile picture
$stmt = $pdo->prepare("SELECT profile_pic FROM register WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Default profile picture path
$default_profile_pic = 'images/pp.png'; // Ensure this matches the default in add_profile.php
$profile_pic_path = $user['profile_pic'] ?: $default_profile_pic;

// Fetch email counts for all folders
$email_counts = [];
foreach ($valid_folders as $folder_name) {
    if ($folder_name === 'starred') {
        $count_stmt = $pdo->prepare('
            SELECT COUNT(*) AS email_count
            FROM starred_emails s
            JOIN emails e ON s.email_id = e.id
            WHERE s.user_id = :user_id
        ');
        $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $count_stmt->execute();
    } elseif ($folder_name === 'unread') {
        // Fetch unread email count
        $count_stmt = $pdo->prepare('
            SELECT COUNT(*) AS email_count
            FROM emails e
            WHERE e.user_id = :user_id AND e.status = "unread"
        ');
        $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $count_stmt->execute();
    } else {
        $count_stmt = $pdo->prepare('
            SELECT COUNT(*) AS email_count
            FROM emails e
            WHERE e.user_id = :user_id AND e.status = :folder_name
        ');
        $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $count_stmt->bindParam(':folder_name', $folder_name, PDO::PARAM_STR);
        $count_stmt->execute();
    }
    $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $email_counts[$folder_name] = $result['email_count'];
}

// Search functionality
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
if ($search_query) {
    $stmt = $pdo->prepare('
        SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.status, e.created_at, e.attachment, u.email AS sender_email
        FROM emails e
        JOIN register u ON e.user_id = u.id
        WHERE e.user_id = :user_id AND (e.subject LIKE :search_query OR e.sender LIKE :search_query)
        ORDER BY e.created_at DESC
        LIMIT :limit OFFSET :offset
    ');
    $stmt->execute([':user_id' => $user_id, ':search_query' => "%$search_query%", ':limit' => $emails_per_page, ':offset' => $offset]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title><?php echo ucfirst($folder); ?> - HueMail</title>
    <style>

body {
          font-family: 'Roboto', sans-serif;
          background: url('images/huemail.jpg') no-repeat center center fixed;
          background-size: cover;
          margin: 0;
          padding: 0;
          display: flex;
      }

      .side-panel {
          background: rgba(255, 255, 255, 0.9);
          border-radius: 5px;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
          padding: 20px;
          width: 215px;
          max-width: 100%;
          height: 100vh;
          position: fixed;
          top: 0;
          left: 0;
          overflow-y: auto;
      }

      .side-panel .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          border-bottom: 1px solid #e0e0e0;
          padding-bottom: 10px;
          margin-bottom: 20px;
      }

      .side-panel .header h1 {
          margin: 0;
          font-size: 1.5em;
      }

      .side-panel .navigation a {
          display: flex;
          align-items: center;
          margin-bottom: 10px;
          text-decoration: none;
          color: #1a73e8; /* Default link color */
          font-weight: 500;
          font-size: 25px; /* Increased font size */
      }

      .side-panel .navigation a i {
          margin-right: 8px; /* Space between icon and text */
          font-size: 1.2em;   /* Adjust icon size */
      }

      .side-panel .navigation a.active {
          font-weight: bold;
          border-bottom: 2px solid black;
      }

      .side-panel .logout {
          background-color: #e53935; /* Red background color */
          color: #fff !important;    /* White text color with !important */
          border: none;
          border-radius: 10px;
          padding: 2px 2px;
          cursor: pointer;
          text-decoration: none;
          font-size: 1.5em;
          margin-top: 20px;
          display: flex;
          align-items: center;
          justify-content: center;
      }
      
      .side-panel .logout:hover {
          background-color: #b71c1c; /* Darker red background color on hover */
      }

      .side-panel .logout i {
          margin-right: 1px; /* Space between icon and text */
      }

      .main-content {
          margin-left: 250px; /* Adjust based on the width of the side panel */
          padding: 5px;
          flex: 1;
          max-width: calc(100% - 270px);
          display: flex;
          flex-direction: column;
      }

      .navbar {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
          padding: 10px 0;
          position: relative; /* Added for dropdown positioning */
      }

      .navbar .search-bar {
          flex: 1;
          margin: 0 20px;
      }

      .navbar .search-bar input {
          width: 98%;
          padding: 10px;
          border: 1px solid #ddd;
          border-radius: 4px;
          font-size: 14px;
      }

      .navbar .profile {
          display: flex;
          align-items: center;
          position: relative; /* Added for dropdown positioning */
      }

      .navbar .profile img { /* Profile picture styling */
          border-radius: 50%;
          width: 70px;
          height: 70px;
          margin-right: 20px;
          cursor: pointer;
          border: 4px solid #1a73e8; /* Border color and width */
      }

      .dropdown-menu {
          display: none;
          position: absolute;
          top: 100%;
          right: 0;
          background-color: #fff;
          border: 1px solid #ddd;
          border-radius: 4px;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
          z-index: 1000;
          width: 200px;
      }

      .dropdown-menu a {
          display: block;
          padding: 10px;
          text-decoration: none;
          color: #333;
          font-size: 14px;
      }

      .dropdown-menu a:hover {
          background-color: #f5f5f5;
      }

      .show {
          display: block;
      }

/* Style the email table */
table {
  width: 100%;
  border-collapse: collapse;
  background: rgba(255, 255, 255, 0.9); /* Matches the modal background */
  border-radius: 10px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  overflow: hidden; /* Ensures rounded corners are visible */
}

th, td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

thead {
  background-color: #007bff;
  color: white;
}

th {
  font-weight: bold;
}

tbody tr:hover {
  background-color: #f5f5f5;
}

.email-subject {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

a {
  color: #007bff;
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
}

.fas {
  margin-right: 5px;
}


      .email-body {
          color: #5f6368;
          font-size: 14px;
      }

      .no-emails-message {
          text-align: center;
          font-size: 1.2em;
          color: #555;
          margin-top: 20px;
      }

      .search-container {
          position: relative;
          width: 100%; /* Adjusted width to make it slightly larger */
          max-width: 500px; /* Optional: Set a max-width for better control on larger screens */
          margin: 0 auto; /* Center the search bar */
      }

      .search-container i {
          position: absolute;
          top: 50%;
          left: 15px; /* Adjusted for better spacing from the left edge */
          transform: translateY(-50%);
          font-size: 18px; /* Slightly larger icon */
          color: black; /* Icon color */
      }

      .search-container input {
          width: 100%;
          padding: 10px 20px 10px 40px; /* Adjusted padding for better balance */
          border: 2px solid black; /* Thinner border for a more modern look */
          border-radius: 8px; /* Increased border-radius for rounded corners */
          font-size: 16px; /* Larger font size for better readability */
          box-sizing: border-box; /* Ensure padding and border are included in the total width */
      }

      
/* Basic Reset */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Arial', sans-serif;
  background: url('images/huemail.jpg') no-repeat center center fixed;
  background-size: cover;
  color: #333;
  line-height: 1.6;
  margin: 0;
}

/* Style for folder count next to the folder name */
.folder-count {
  margin-left: 5px;  /* Space between folder name and count */
  font-size: 1em;    /* Adjust size as needed */
  color: #e53935;     /* Color for the count */
  font-weight: bold;  /* Make the count bold */
}

a:hover .folder-count {
  color: #007bff;  /* Color on hover */
}


  </style>
</head>
<body>
<div class="side-panel">
    <div class="header">
        <h1><?php echo ucfirst($folder); ?></h1>
    </div>
    <div class="navigation">
        <a href="compose.php"><i class="fas fa-pencil-alt"></i> Compose</a>
       
        <a href="inbox.php" class="active">
            <i class="fas fa-inbox"></i> Inbox
            <span class="folder-count"> <?php echo $email_counts['inbox']; ?></span>
        </a>

        <a href="starred.php" class="<?php echo $folder === 'unread' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> Starred
            <span class="folder-count"> <?php echo $email_counts['starred']; ?></span>
        </a>

        <a href="unread.php" class="<?php echo $folder === 'unread' ? 'active' : ''; ?>">
            <i class="fas fa-envelope-open-text"></i> Unread
            <span class="folder-count"> <?php echo $email_counts['unread']; ?></span>
        </a>

        <a href="sent.php" class="<?php echo $folder === 'sent' ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane"></i> Sent
            <span class="folder-count"> <?php echo $email_counts['sent']; ?></span>
        </a>

        <a href="draft.php" class="<?php echo $folder === 'draft' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Drafts
            <span class="folder-count"> <?php echo $email_counts['draft']; ?></span>
        </a>

        <a href="archive.php" class="<?php echo $folder === 'archive' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i> Archive
            <span class="folder-count"> <?php echo $email_counts['archive']; ?></span>
        </a>

        <a href="spam.php" class="<?php echo $folder === 'spam' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i> Spam
            <span class="folder-count"> <?php echo $email_counts['spam']; ?></span>
        </a>

        <a href="trash.php" class="<?php echo $folder === 'trash' ? 'active' : ''; ?>">
            <i class="fas fa-trash"></i> Trash
            <span class="folder-count"> <?php echo $email_counts['trash']; ?></span>
        </a>
    </div>

    <a href="logout.php" class="logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<div class="main-content">
    <div class="navbar">
        <div class="search-container">
            <form method="get" action="">
                <input type="text" name="search" placeholder="Search emails..." id="search-bar" value="<?php echo htmlspecialchars($search_query); ?>">
                <i class="fas fa-search"></i>
            </form>
        </div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Profile Picture">
            <div class="dropdown-menu" id="dropdown-menu">
                <a href="add_profile.php">Profile Settings</a>
                <a href="account_settings.php">Account Settings</a>
                <a href="change_password.php">Change Password</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="team.php">Meet The Team!</a>
            </div>
        </div>
    </div>
   
    <table>
        <thead>
            <tr>
                <th>Actions</th>
                <th>Sender</th>
                <th>Subject</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="email-list">
            <?php
            if (count($emails) == 0) {
                echo '<tr><td colspan="4" class="no-emails-message">No emails found.</td></tr>';
            } else {
                foreach ($emails as $email) {
                    echo '<tr>';
                    echo '<td><a href="view.php?id=' . $email['id'] . '">View</a></td>';
                    echo '<td>' . htmlspecialchars($email['sender_email']) . '</td>';
                    echo '<td class="email-subject">' . htmlspecialchars($email['subject']) . '</td>';
                    echo '<td>' . htmlspecialchars($email['created_at']) . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>

    <style>

        /* General Pagination Container */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 20px 0;
    padding: 10px;
    font-family: Arial, sans-serif;
}

/* Pagination Links */
.pagination a {
    text-decoration: none;
    color: #007bff; /* Link color */
    padding: 8px 12px;
    margin: 0 5px;
    border: 1px solid #007bff; /* Border around each link */
    border-radius: 5px;
    font-size: 14px;
    transition: background-color 0.3s ease, color 0.3s ease;
}

/* Hover and Active States */
.pagination a:hover,
.pagination a:focus {
    background-color: #007bff;
    color: white;
}

/* Disabled Links (when on the first or last page) */
.pagination a[disabled] {
    color: #ddd;
    border-color: #ddd;
    cursor: not-allowed;
    pointer-events: none;
}

/* Centered Page Info */
.pagination span {
    font-size: 14px;
    color: white;
    margin: 0 10px;
}

/* Make it look good on mobile devices */
@media (max-width: 600px) {
    .pagination {
        flex-direction: column;
    }

    .pagination a {
        margin: 5px 0;
    }
}

    </style>
    <!-- Pagination Controls -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?folder=<?php echo $folder; ?>&page=1&search=<?php echo urlencode($search_query); ?>">First</a>
            <a href="?folder=<?php echo $folder; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
        <?php endif; ?>

        <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

        <?php if ($page < $total_pages): ?>
            <a href="?folder=<?php echo $folder; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
            <a href="?folder=<?php echo $folder; ?>&page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search_query); ?>">Last</a>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Establish WebSocket connection (Make sure the WebSocket server is running)
        const ws = new WebSocket('ws://localhost:8081/email');  // Change this if your server uses a different URL

        // Handle successful connection
        ws.onopen = function() {
            console.log("Connected to WebSocket server.");
        };

        // Handle errors in WebSocket connection
        ws.onerror = function(error) {
            console.log("WebSocket Error: ", error);
        };

        // Handle incoming WebSocket messages (new email notifications)
        ws.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                console.log('Received data:', data);

                if (data.type === 'new_email') {
                    const newEmail = data.message;
                    console.log('New email:', newEmail);

                    // Check that necessary data is available
                    if (!newEmail || !newEmail.id || !newEmail.subject || !newEmail.sender_email || !newEmail.created_at) {
                        console.error('Email data is missing expected fields:', newEmail);
                        return;
                    }

                    // Format date for displaying
                    const createdAt = new Date(newEmail.created_at).toLocaleString();

                    // Create a new row for the email
                    const emailList = document.getElementById('email-list');
                    const newRow = document.createElement('tr');

                    // Insert email data into the row
                    newRow.innerHTML = `
                        <td><a href="view.php?id=${newEmail.id}">View</a></td>
                        <td>${newEmail.sender_email}</td>
                        <td class="email-subject">${newEmail.subject}</td>
                        <td>${createdAt}</td>
                    `;

                    // Insert the new row at the top of the email list (newest first)
                    emailList.insertBefore(newRow, emailList.firstChild);
                }
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        };
    });
</script>


<script>
    // Example JavaScript to handle dropdown
    document.querySelector('.profile img').addEventListener('click', function() {
        const dropdown = document.querySelector('.dropdown-menu');
        dropdown.classList.toggle('show');
    });
</script>

</body>
</html>
