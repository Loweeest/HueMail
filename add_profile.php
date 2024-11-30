<?php
session_start(); // Start the session at the beginning of the page

// ** Check if the user has verified their PIN **
if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
    // If PIN is not verified, redirect to inbox.php to enter PIN
    $_SESSION['show_modal'] = true;  // This will trigger the PIN modal to show on inbox.php
    header('Location: inbox.php');   // Redirect to inbox.php to enter PIN
    exit;
}

// Check if the user is logged in
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

// Check if the user has a PIN set
$stmt = $pdo->prepare('SELECT * FROM user_pins WHERE user_id = ?');
$stmt->execute([$user_id]);
$pin_row = $stmt->fetch(PDO::FETCH_ASSOC);

// If the user does not have a PIN, redirect them to create_pin.php
if (!$pin_row) {
    header('Location: create_pin.php');
    exit;
}

// Fetch existing profile data
try {
    $stmt = $pdo->prepare("SELECT profile_pic FROM register WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found or query failed.");
    }

    // Default profile picture path
    $default_profile_pic = 'images/pp.png';
    $profile_pic_path = $user['profile_pic'] ?: $default_profile_pic;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];

    // Ensure the uploaded file is valid
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Generate a unique file name
        $file_name = uniqid('profile_', true) . '.png'; // Save as PNG
        $file_path = 'uploads/' . $file_name;

        // Move the uploaded file to the uploads folder
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            try {
                // Begin transaction
                $pdo->beginTransaction();

                // Update profile picture in the register table
                $stmt = $pdo->prepare("UPDATE register SET profile_pic = :profile_pic WHERE id = :id");
                $stmt->execute([':profile_pic' => $file_path, ':id' => $_SESSION['user_id']]);

                // Update profile picture in the users table
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = :profile_pic WHERE id = :id");
                $stmt->execute([':profile_pic' => $file_path, ':id' => $_SESSION['user_id']]);

                // Commit the transaction
                $pdo->commit();

                // Success message
                $_SESSION['success_message'] = 'Profile picture successfully updated!';
                echo json_encode(['success' => true, 'file_path' => $file_path]);
                exit;

            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error updating profile picture.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error uploading the file.']);
    }
    exit;
}


// Handle "Delete Profile" (reset to default profile picture)
if (isset($_POST['delete_profile'])) {
    $default_profile_pic = 'images/pp.png';  // Default profile picture

    // Begin transaction to update both tables
    try {
        $pdo->beginTransaction();

        // Update the register table
        $stmt = $pdo->prepare("UPDATE register SET profile_pic = :profile_pic WHERE id = :id");
        $stmt->execute([':profile_pic' => $default_profile_pic, ':id' => $_SESSION['user_id']]);

        // Update the users table
        $stmt = $pdo->prepare("UPDATE users SET profile_pic = :profile_pic WHERE id = :id");
        $stmt->execute([':profile_pic' => $default_profile_pic, ':id' => $_SESSION['user_id']]);

        // Commit the transaction
        $pdo->commit();

        $_SESSION['success_message'] = 'Profile picture reset to default!';
        header('Location: add_profile.php'); // Redirect to the profile page
        exit;

    } catch (Exception $e) {
        // Rollback if something goes wrong
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error resetting profile picture.']);
        exit;
    }
}

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

    <title>Add Profile Picture - HueMail</title>

<style>

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            border: 5px solid white; /* Light gray border */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 35px;
            max-width: 650px;
            width: 100%;
            margin: 100px auto;
            text-align: center;
    margin: auto; /* Center the container */
    position: relative;
    margin-top: auto;
    overflow: hidden;
        }
        h1 {
            margin-bottom: 20px;
            color: #333;
            font-size: 24px;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        input[type="file"] {
            padding: 5px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;
            width: auto;
        }
        button {
            width: 20%;
            padding: 12px;
            background-color: #00a400;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: green;
        }
        .error {
            color: red;
            font-size: 16px;
        }
        .profile-pic {
            margin-bottom: 15px;
        }
        .profile-pic img {
            border-radius: 50%;
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 5px solid #00a400;
            display: block;
            margin: 0 auto;
        }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ff4d4d;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #fff;
            font-size: 18px;
            transition: background 0.3s;
        }
        .close-btn:hover {
            background: #ff2d2d;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        #crop-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            padding: 20px;
            border-radius: 10px;
            z-index: 1000;
            color: #fff;
            display: none;
        }
        .crop-container {
            width: 500px;
            height: 500px;
            overflow: hidden;
            border-radius: 1000%;
        }
        #crop-image {
            max-width: none;
        }
        #crop-save, #crop-cancel {
            margin: 10px;
            padding: 10px;
            border: none;
            color: #fff;
            cursor: pointer;
        }
        #crop-save {
            background-color: #00a400;
        }
        #crop-cancel {
            background-color: #ff4d4d;
        }
        #zoom-in, #zoom-out {
            margin: 10px;
            padding: 10px;
            border: none;
            color: #fff;
            background-color: #007bff; /* Blue color */
            cursor: pointer;
        }
        #zoom-in:hover, #zoom-out:hover {
            background-color: #0056b3; /* Darker blue */
        }
        .submit-button {
            display: block;
            margin: 0 auto; /* Centers the button */
            width: 35%; /* Adjust width as needed */
        }
        .remove-profile-btn {
    background-color: #ff4d4d;
    width: 40%;
    margin-top: 10px;
    padding: 12px;
    color: #fff;
    border: none;
    border-radius: 5px;
    font-size: 18px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.remove-profile-btn:hover {
    background-color: #e60000;  /* Darker red on hover */
}

    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='inbox.php';">&times;</button>
        <h1>Add Profile Picture</h1>
        <div class="profile-pic">
            <img id="profile-thumbnail" src="<?= htmlspecialchars($profile_pic_path) ?>" alt="Profile Picture">
        </div>
        <div id="notification" class="notification"><?= htmlspecialchars($error) ?></div>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <form id="profile-form" method="POST" enctype="multipart/form-data">
            <input type="file" name="profile_pic" accept="image/*" id="profilePicInput">
        </form><br>

        <form method="POST">
            <center>
            <button type="submit" class="submit-button">Save Profile Picture</button>
            <button type="submit" name="delete_profile" class="remove-profile-btn">Remove Profile Picture</button>
            </form>
        </center>
<br>
    <div class="form-row">
        <div class="form-column">
            <p>Need to change your password? <a href="change_password.php">Click here.</a></p>
            <p>Need to update your account? <a href="account_settings.php">Click here.</a></p>
        </div>
    </div>
</form>

    <!-- Modal for cropping -->
    <div id="crop-modal">
        <div class="crop-container">
            <img id="crop-image" src="" alt="Image to crop">
        </div>
        <button id="zoom-in">+</button>
        <button id="zoom-out">-</button>
        <button id="crop-save">Save</button>
        <button id="crop-cancel">Cancel</button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        const profilePicInput = document.getElementById('profilePicInput');
        const cropModal = document.getElementById('crop-modal');
        const cropImage = document.getElementById('crop-image');
        const cropSaveButton = document.getElementById('crop-save');
        const cropCancelButton = document.getElementById('crop-cancel');
        const zoomInButton = document.getElementById('zoom-in');
        const zoomOutButton = document.getElementById('zoom-out');
        const profileThumbnail = document.getElementById('profile-thumbnail');

        let cropper;

        profilePicInput.addEventListener('change', function (event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    cropImage.src = e.target.result;  // Set image source for cropping
                    cropModal.style.display = 'block';  // Show crop modal
                    cropper = new Cropper(cropImage, {
                        aspectRatio: 1,
                        viewMode: 2,
                        minContainerWidth: 500,
                        minContainerHeight: 500
                    });
                };
                reader.readAsDataURL(files[0]);  // Load selected file
            }
        });

        zoomInButton.addEventListener('click', function () {
            cropper.zoom(0.1);
        });

        zoomOutButton.addEventListener('click', function () {
            cropper.zoom(-0.1);
        });

        cropSaveButton.addEventListener('click', function () {
            if (!cropper) {
                console.error("Cropper not initialized");
                return;
            }

            // Get the cropped image canvas
            const canvas = cropper.getCroppedCanvas({
                width: 500,
                height: 500
            });

            // Convert canvas to Blob and send it to the server
            canvas.toBlob(function (blob) {
                // Prepare FormData to send the image to the server
                const formData = new FormData();
                formData.append('profile_pic', blob, 'profile_pic.png');

                // Send FormData to the server via AJAX
                fetch('add_profile.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update profile thumbnail with the new image
                        profileThumbnail.src = data.file_path;
                        cropModal.style.display = 'none'; // Hide crop modal after save
                    } else {
                        document.getElementById('notification').textContent = data.message; // Show error message
                    }
                }).catch(error => console.error('Error:', error));
            });
        });

        cropCancelButton.addEventListener('click', function () {
            cropModal.style.display = 'none'; // Close the crop modal without saving
        });
    </script>
</body>
</html>
