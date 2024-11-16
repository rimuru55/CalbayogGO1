<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to rate.']);
    exit;
}

if (isset($_POST['content_id'], $_POST['rating'], $_POST['review'])) {
    $user_id = $_SESSION['user_id'];
    $content_id = $_POST['content_id'];
    $rating = (int)$_POST['rating'];
    $review = htmlspecialchars($_POST['review']);
    $photo_filenames = [];  // Array to store filenames of uploaded photos

    // Handle multiple file uploads
    if (isset($_FILES['rating_photos']) && $_FILES['rating_photos']['error'][0] == UPLOAD_ERR_OK) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        foreach ($_FILES['rating_photos']['tmp_name'] as $index => $tmp_name) {
            $file_name = $_FILES['rating_photos']['name'][$index];
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

            if (in_array(strtolower($file_ext), $allowed_exts)) {
                $photo_filename = uniqid('rating_', true) . '.' . $file_ext;
                $upload_path = '../uploads/' . $photo_filename;

                if (move_uploaded_file($tmp_name, $upload_path)) {
                    $photo_filenames[] = $photo_filename;
                }
            }
        }
    }

    // Serialize the array of photo filenames to store in the database
    $photo_filenames_serialized = serialize($photo_filenames);

    // Insert or update rating and review in the database
    $sql = "INSERT INTO ratings (user_id, content_id, rating, review, photo) VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review), photo = VALUES(photo)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $user_id, $content_id, $rating, $review, $photo_filenames_serialized);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Rating submitted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit rating.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
}
?>
