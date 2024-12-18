<?php
session_start(); // Start the session at the beginning of the page

// ** Check if the user has verified their PIN **
if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
    // If PIN is not verified, redirect to inbox.php to enter PIN
    $_SESSION['show_modal'] = true;  // This will trigger the PIN modal to show on inbox.php
    header('Location: inbox.php');   // Redirect to inbox.php to enter PIN
    exit;
}

// ** Check if the user is logged in **
if (!isset($_SESSION['user_id'])) {
    // If user is not logged in, redirect to login page
    header('Location: login.php');
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

// ** Check if the user has a PIN set **
$stmt = $pdo->prepare('SELECT * FROM user_pins WHERE user_id = ?');
$stmt->execute([$user_id]);
$pin_row = $stmt->fetch(PDO::FETCH_ASSOC);

// If the user does not have a PIN, redirect them to create_pin.php
if (!$pin_row) {
    header('Location: create_pin.php');
    exit;
}

// Define folder as 'trash' (We're on the Trash folder)
$folder = 'trash'; 

// Get the search query from the URL
$search = isset($_GET['search']) ? $_GET['search'] : ''; 

// Pagination setup
$limit = 10; // Emails per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// SQL query to fetch deleted emails (from deleted_emails table) with pagination and search
$sql = '
    SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.created_at, e.deleted_at, u.email AS sender_email
    FROM deleted_emails e
    JOIN register u ON e.user_id = u.id
    WHERE e.user_id = ? 
';

if (!empty($search)) {
    $sql .= ' AND (e.subject LIKE ? OR e.body LIKE ?)';
}

$sql .= " ORDER BY e.deleted_at DESC LIMIT $limit OFFSET $offset";

// Execute the query
$stmt = $pdo->prepare($sql);
if (!empty($search)) {
    $stmt->execute([$user_id, '%' . $search . '%', '%' . $search . '%']);
} else {
    $stmt->execute([$user_id]);
}

$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of trash emails for pagination
$total_query = $pdo->prepare("SELECT COUNT(*) FROM deleted_emails e WHERE e.user_id = ?");
$total_query->execute([$user_id]);
$total_trash = $total_query->fetchColumn();
$total_pages = ceil($total_trash / $limit);

// Fetch user's profile picture
$stmt = $pdo->prepare("SELECT profile_pic FROM register WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Default profile picture path
$default_profile_pic = 'images/pp.png'; // Ensure this matches the default in add_profile.php
$profile_pic_path = $user['profile_pic'] ?: $default_profile_pic;

// Fetch user's background image from the 'users' table
$stmt = $pdo->prepare("SELECT background_image FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user_bg = $stmt->fetch(PDO::FETCH_ASSOC);

// Default background image path
$default_background = 'images/mainbg.jpg'; // Default image if none is set
$current_background = $user_bg['background_image'] ?: $default_background; // Use user image or default

// Cache busting: Add a timestamp to the image URL to avoid caching
$background_image_url = $current_background . '?v=' . time();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon"> <!-- Adjust path if necessary -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">

 <!-- Link to Bootstrap JS (locally) -->
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">

    <title><?php echo ucfirst($folder); ?> - HueMail</title>
    <style>

body {
          font-family: 'Roboto', sans-serif;
          background: url('images/mainbg.jpg') no-repeat center center fixed;
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
            transition: transform 0.3s ease;
        }

/* Add hidden class to slide the side-panel out of view */
.side-panel.hidden {
    transform: translateX(-100%);
}

/* Adjust the main content when the side panel is hidden */
.main-content.shifted {
    margin-left: 0;
    max-width: 100%;
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
            font-size: 2em;
            font-weight: bold;
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
  background: url('images/mainbg.jpg') no-repeat center center fixed;
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
        <h1>Trash</h1>
    </div>
    <div class="navigation">
        <a href="compose.php"><i class="fas fa-pencil-alt"></i> Compose</a>

        <a href="inbox.php" class="<?php echo $folder === 'inbox' ? 'active' : ''; ?>">
        <i class="fas fa-inbox"></i> Inbox
        </a>

        <a href="starred.php" class="<?php echo $folder === 'starred' ? 'active' : ''; ?>">
        <i class="fas fa-star"></i> Starred
        </a>

      <!--  <a href="unread.php" class="<?php echo $folder === 'unread' ? 'active' : ''; ?>">
             <i class="fas fa-envelope-open-text"></i> Unread 
            </a> -->

        <a href="sent.php" class="<?php echo $folder === 'sent' ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane"></i> Sent
        </a>

        <a href="draft.php" class="<?php echo $folder === 'draft' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Drafts
        </a>

        <a href="archive.php" class="<?php echo $folder === 'archive' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i> Archive
        </a>

        <a href="spam.php" class="<?php echo $folder === 'spam' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i> Spam
        </a>

        <a href="trash.php" class="active">
            <i class="fas fa-trash"></i> Trash
        </a>
    </div>

</div>

    <div class="main-content">
    <div class="navbar">
    <i class="fas fa-arrow-left" id="toggle-icon" style="font-size: 1.5em; cursor: pointer; margin-right: 1em; color: white"></i>
        <div class="search-container">
            <input type="text" placeholder="Search emails..." id="search-bar">
            <i class="fas fa-search"></i>
        </div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Profile Picture">
            <div class="dropdown-menu" id="dropdown-menu">
            <a href="add_profile.php">Profile Settings</a>
                    <a href="account_settings.php">Account Settings</a>
                    <a href="change_password.php">Change Password</a>
                    <a href="background_images.php">Change Background</a>
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="terms.php">Terms of Service</a>
                    <a href="team.php">Meet The Team</a>
                    <a href="logout.php">Logout</a>

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
            <tbody>
                <?php
                if (count($emails) == 0) {
                    echo '<tr><td colspan="4" class="no-emails-message">No trash emails found.</td></tr>';
                } else {
                    foreach ($emails as $email) {
                        echo '<tr>';
                        echo '<td><a href="view_deleted.php?id=' . $email['id'] . '">View</a></td>';
                        echo '<td>' . htmlspecialchars($email['sender']) . '</td>';
                        echo '<td class="email-subject">' . htmlspecialchars($email['subject']) . '</td>';
                        echo '<td>' . htmlspecialchars($email['deleted_at']) . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>

        <style> 
    /* Pagination Styles */
.pagination {
    display: flex;
    justify-content: center; /* Center the pagination links */
    align-items: center;
    margin: 20px 0; /* Space above and below the pagination */
    list-style: none; /* Remove default list styling */
    padding: 0; /* Remove default padding */
}

.pagination li {
    margin: 0 5px; /* Space between pagination links */
}

.pagination a {
    display: block;
    padding: 10px 15px; /* Padding inside the pagination links */
    border: 1px solid #007bff; /* Border color */
    border-radius: 5px; /* Rounded corners */
    text-decoration: none; /* Remove underline */
    color: #007bff; /* Text color */
    font-size: 16px; /* Font size */
    transition: background-color 0.3s, color 0.3s; /* Smooth transition for hover effects */
}

.pagination a:hover {
    background-color: #007bff; /* Background color on hover */
    color: #fff; /* Text color on hover */
}

.pagination .active a {
    background-color: #007bff; /* Background color for active page */
    color: #fff; /* Text color for active page */
    border-color: #0056b3; /* Border color for active page */
}
</style>

    <!-- Pagination controls -->
    <div class="pagination">
        <?php
        if ($page > 1) {
            echo '<a href="?page=' . ($page - 1) . '" class="prev">Previous</a>';
        }
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<a href="?page=' . $i . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
        }
        if ($page < $total_pages) {
            echo '<a href="?page=' . ($page + 1) . '" class="next">Next</a>';
        }
        ?>
    </div>
</div>

    <script>
        // Example JavaScript to handle dropdown
        document.querySelector('.profile img').addEventListener('click', function() {
            const dropdown = document.querySelector('.dropdown-menu');
            dropdown.classList.toggle('show');
        });
    </script>
    
    <script>
    const toggleIcon = document.getElementById('toggle-icon');
    const sidePanel = document.querySelector('.side-panel');
    const mainContent = document.querySelector('.main-content'); // Adjust selector if needed

    toggleIcon.addEventListener('click', function () {
        sidePanel.classList.toggle('hidden');
        mainContent.classList.toggle('shifted');

        // Update the icon
        if (sidePanel.classList.contains('hidden')) {
            toggleIcon.classList.remove('fa-arrow-left');
            toggleIcon.classList.add('fa-arrow-right');
        } else {
            toggleIcon.classList.remove('fa-arrow-right');
            toggleIcon.classList.add('fa-arrow-left');
        }
    });
</script>

</body>
</html>
