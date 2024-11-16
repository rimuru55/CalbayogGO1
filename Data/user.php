<?php
    session_start();
    include('../includes/db.php');

    // Role distribution (Fetch total non-admin users)
    $sql = "SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'";
    $result = $conn->query($sql);

    $total_users = 0;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_users = $row['total_users'];
    } else {
        echo json_encode(["error" => "Error fetching user count: " . $conn->error]);
        exit();
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // Edit user profile action
        if ($action === 'edit_profile') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(["error" => "User ID not found in session."]);
                exit();
            }

            $userId = $_SESSION['user_id'];
            $firstname = $_POST['firstname'] ?? '';
            $lastname = $_POST['lastname'] ?? '';
            $username = $_POST['username'] ?? '';

            $profileImagePath = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $profileImagePath = 'uploads/' . uniqid('profile_', true) . '.' . $fileExtension;
                if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], '../' . $profileImagePath)) {
                    echo json_encode(["error" => "Failed to move uploaded file."]);
                    exit();
                }
            }

            // SQL Query to Update the User Profile
            $sql = "UPDATE users SET firstname=?, lastname=?, username=?";
            if ($profileImagePath) {
                $sql .= ", profile_picture=?";
            }
            $sql .= " WHERE id=?";

            $stmt = $conn->prepare($sql);
            if ($profileImagePath) {
                $stmt->bind_param("ssssi", $firstname, $lastname, $username, $profileImagePath, $userId);
            } else {
                $stmt->bind_param("sssi", $firstname, $lastname, $username, $userId);
            }

            if ($stmt->execute()) {
                echo json_encode(["success" => "Profile updated successfully"]);
            } else {
                echo json_encode(["error" => "Error updating profile: " . $conn->error]);
            }
            $stmt->close();
            exit();
        }

        // Delete review action
        if ($action === 'delete_review') {
            $review_id = $_POST['review_id'];
            $stmt = $conn->prepare("DELETE FROM ratings WHERE id = ?");
            $stmt->bind_param("i", $review_id);
            if ($stmt->execute()) {
                echo json_encode(["success" => "Review deleted successfully."]);
            } else {
                echo json_encode(["error" => "Error deleting review: " . $conn->error]);
            }
            $stmt->close();
            exit();
        }

        // Edit review action
        if ($action === 'edit_review') {
            $review_id = $_POST['review_id'];
            $rating = intval($_POST['rating']);
            $review = $_POST['review'] ?? '';

            // Ensure rating is within valid range
            if ($rating < 1 || $rating > 5) {
                echo json_encode(["error" => "Rating must be between 1 and 5."]);
                exit();
            }

            $stmt = $conn->prepare("UPDATE ratings SET rating = ?, review = ? WHERE id = ?");
            $stmt->bind_param("isi", $rating, $review, $review_id);
            if ($stmt->execute()) {
                echo json_encode(["success" => "Review updated successfully."]);
            } else {
                echo json_encode(["error" => "Error updating review: " . $conn->error]);
            }
            $stmt->close();
            exit();
        }

        // Get user photos action
        if ($action === 'get_user_photos') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(["success" => false, "message" => "User not logged in."]);
                exit();
            }

            $userId = $_SESSION['user_id'];
            $sql = "SELECT photo FROM ratings WHERE user_id = ? AND photo IS NOT NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $photos = [];
            while ($row = $result->fetch_assoc()) {
                $photoFiles = unserialize($row['photo']);
                if ($photoFiles && is_array($photoFiles)) {
                    $photos = array_merge($photos, $photoFiles);
                }
            }
            $stmt->close();

            echo json_encode(["success" => true, "photos" => $photos]);
            exit();
        }

        // Get single user details action
        if ($action === 'get_user' && isset($_POST['id'])) {
            $userId = intval($_POST['id']);

            $stmt = $conn->prepare("SELECT id, firstname, lastname, username, email, role, status FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                echo json_encode($user);
            } else {
                echo json_encode(["error" => "User not found"]);
            }
            $stmt->close();
            exit();
        }

        // Get detailed user info, reviews, and visited places
        if ($action === 'get_user_details' && isset($_POST['id'])) {
            $userId = intval($_POST['id']);
            
            // Fetch user info
            $stmt = $conn->prepare("SELECT id, username, email, role, status, created_at AS registration_date, last_login FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $user = $userResult->fetch_assoc();
            $stmt->close();

            // Fetch user reviews and ratings along with content titles
            $stmt = $conn->prepare("
            SELECT r.id, r.rating, r.review, r.created_at, c.title AS content_title
            FROM ratings r
            INNER JOIN contents c ON r.content_id = c.id
            WHERE r.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $reviewsResult = $stmt->get_result();
            $reviews = $reviewsResult->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Fetch visited places
            $stmt = $conn->prepare("
                SELECT contents.title, visited_places.last_visited_date 
                FROM visited_places 
                JOIN contents ON visited_places.content_id = contents.id 
                WHERE visited_places.user_id = ?
                ORDER BY visited_places.last_visited_date DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $visitedPlacesResult = $stmt->get_result();
            $visitedPlaces = $visitedPlacesResult->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Combine all data into a response
            echo json_encode([
                'user' => $user,
                'reviews' => $reviews,
                'visited_places' => $visitedPlaces
            ]);
            exit();
        }

        // Save visited place action
        if ($action === 'save_visit') {
            $data = json_decode(file_get_contents("php://input"), true);
            $userId = intval($data['user_id']);
            $placeId = intval($data['place_id']);
            $visitedDate = $data['last_visited_date'];

            // Insert the visit into the visited_places table
            $stmt = $conn->prepare("INSERT INTO visited_places (user_id, content_id, last_visited_date) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $userId, $placeId, $visitedDate);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
                error_log("Error saving visit: " . $stmt->error); // Log the error for debugging
            }

            $stmt->close();
            exit();
        }


        
    }



    // Fetch all users for management
    $sql = "SELECT id, firstname, lastname, username, email, role, status, created_at AS registration_date, last_login FROM users";
    $result = $conn->query($sql);
    $users = [];
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    }

    $conn->close();
    ?>