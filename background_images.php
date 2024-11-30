<?php
session_start();

// Database connection setup
$host = 'localhost';
$db   = 'HueMail';
$user = 'root';  // Change to your MySQL username
$pass = '';      // Change to your MySQL password

// Establish database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database $db :" . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// User ID from session
$user_id = $_SESSION['user_id'];

$error_message = '';
$success_message = '';

// Handle background image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['background_image'])) {
    $target_dir = "images/backgrounds/"; // Folder to store background images
    $file_name = basename($_FILES["background_image"]["name"]);
    $target_file = $target_dir . uniqid() . "-" . $file_name; // Append unique ID to prevent overwriting
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if the file is a valid image (MIME type validation)
    $check = getimagesize($_FILES["background_image"]["tmp_name"]);
    if ($check === false) {
        $error_message = "File is not an image.";
    } elseif ($_FILES["background_image"]["size"] > 5000000) { // Limit to 5MB
        $error_message = "File is too large.";
    } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
        $error_message = "Only JPG, JPEG, & PNG files are allowed.";
    } else {
        // Check if file already exists (we already ensure unique file name)
        if (file_exists($target_file)) {
            $error_message = "Sorry, file already exists.";
        } else {
            // Move the uploaded file to the server
            if (move_uploaded_file($_FILES["background_image"]["tmp_name"], $target_file)) {
                // Update the background image path in the database
                $stmt = $pdo->prepare("UPDATE users SET background_image = ? WHERE id = ?");
                if ($stmt->execute([$target_file, $user_id])) {
                    $_SESSION['background_image'] = $target_file; // Store path in session
                    $success_message = "Background image updated successfully!";
                } else {
                    $error_message = "There was an error updating the background image.";
                }
            } else {
                $error_message = "There was an error uploading the file.";
            }
        }
    }
}

// Handle reset background
if (isset($_POST['reset_background'])) {
    // Reset the background image to default (images/mainbg.jpg)
    $default_background = 'images/mainbg.jpg';
    
    // Update the background image path in the database
    $stmt = $pdo->prepare("UPDATE users SET background_image = ? WHERE id = ?");
    if ($stmt->execute([$default_background, $user_id])) {
        $_SESSION['background_image'] = $default_background; // Reset session background image
        $success_message = "Background image reset to default!";
    } else {
        $error_message = "There was an error resetting the background image.";
    }
}

// Fetch the current background image from the database
$stmt = $pdo->prepare("SELECT background_image FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$current_background = $row['background_image'] ?: 'images/mainbg.jpg'; // Default if no background set
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Background Image</title>
    <link rel="stylesheet" href="bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f4f4;
        }

        .container {
            max-width: 40rem;
            margin: 50px auto;
            border: 5px solid white;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .success-message {
            color: green;
            font-weight: bold;
        }

        .error-message {
            color: red;
            font-weight: bold;
        }

        .preview {
            margin-top: 20px;
        }

        /* Applying current background to the page for preview */
        body {
            background-image: url('<?php echo $current_background; ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .close-btn {
            position: absolute;
            top: auto;
            right: 300px;
            width: 30px;
            height: 30px;
            border: none;
            background: #ff4d4d;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .close-btn:hover {
            background: #e03e3e;
        }

        .form-column {
            text-align: center;
        }

        /* Style for the upload button */
.upload-button {
    display: inline-block;
    background-color: #007bff; /* Bootstrap primary blue */
    color: white;
    font-size: 16px;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.3s ease;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.upload-button:hover {
    background-color: #0056b3; /* Darker blue on hover */
}

.upload-button:active {
    background-color: #004085; /* Even darker blue on click */
}

.upload-button:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(38, 143, 255, 0.6); /* Focus state for accessibility */
}

/* Style for the reset button */
.reset-button {
    display: inline-block;
    background-color: #f0ad4e; /* Bootstrap warning yellow */
    color: white;
    font-size: 16px;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.3s ease;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.reset-button:hover {
    background-color: #ec971f; /* Darker yellow on hover */
}

.reset-button:active {
    background-color: #c67614; /* Even darker yellow on click */
}

.reset-button:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(254, 189, 44, 0.6); /* Focus state for accessibility */
}

/* Center buttons on the page */
.center-buttons {
    text-align: center;
    margin-top: 20px;
}

/* Style for the file input container */
.form-group {
    margin-bottom: 20px;
}

/* Style for the file input */
.form-group input[type="file"] {
    display: inline-block;
    width: 90px%;
    padding: 10px 15px;
    font-size: 16px;
    color: #333;
    background-color: #f8f9fa;
    border: 1px solid #ccc;
    border-radius: 5px;
    cursor: pointer;
    transition: border-color 0.3s, background-color 0.3s;
}

/* Style for the file input focus */
.form-group input[type="file"]:focus {
    border-color: #007bff;
    background-color: #e9f4ff; /* Light blue background on focus */
    outline: none; /* Removes the default outline */
}

/* Style for the file input hover state */
.form-group input[type="file"]:hover {
    border-color: #007bff; /* Blue border on hover */
    background-color: #e9f4ff; /* Light blue background on hover */
}

/* File input label styling */
.form-group input[type="file"]::-webkit-file-upload-button {
    background-color: #007bff;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s ease;
}

/* Hover effect for the file upload button */
.form-group input[type="file"]::-webkit-file-upload-button:hover {
    background-color: #0056b3;
}

/* Style for the file input button on Firefox */
.form-group input[type="file"]:-moz-file-upload {
    background-color: #007bff;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s ease;
}

/* Hover effect for the file upload button on Firefox */
.form-group input[type="file"]:-moz-file-upload:hover {
    background-color: #0056b3;
}


    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='inbox.php'">&times;</button>
        <h2>Change Background Image</h2>

        <!-- Success Message -->
        <?php if ($success_message): ?>
            <center><div class="success-message"><?php echo $success_message; ?></div></center>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <center><div class="error-message"><?php echo $error_message; ?></div></center>
        <?php endif; ?>

        <br>

     <!-- Form for Uploading Image -->
<form action="background_images.php" method="POST" enctype="multipart/form-data">
    <div class="form-group">
        <label for="background_image">Upload New Background Image</label>
        <br>        <br>

        <input type="file" name="background_image" id="background_image" class="form-control" accept="image/*" required>
    </div>
    <!-- Upload Image Button -->
    <center><button type="submit" class="upload-button">Upload Image</button></center>
</form>

<!-- Reset Background Button -->
<form action="background_images.php" method="POST">
    <div class="center-buttons">
        <button type="submit" name="reset_background" class="reset-button">Reset to Default Background</button>
    </div>
</form>


        <!-- Display Preview of the Uploaded Image -->
        <div class="preview">
            <h4>Current Background Preview:</h4>
            <img src="<?php echo $current_background; ?>" alt="Background Preview" style="max-width: 100%; height: auto;">
        </div>

        <div class="form-row">
            <div class="form-column">
                <p>Need to change your password? <a href="change_password.php">Click here.</a></p>
                <p>Need to update your account? <a href="account_settings.php">Click here.</a></p>
            </div>
        </div>
    </div>
</body>
</html>
