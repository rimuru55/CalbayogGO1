<?php
// Start output buffering to prevent unintended output
ob_start();

// Start session if none is active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include $_SERVER['DOCUMENT_ROOT'] . '/CalbayogGO/includes/db.php';
global $conn;

// Check if user is logged in by verifying if user_id is set in session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Set error reporting to log errors but not display them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR);

function getFavoriteLists($conn, $user_id, $content_id = null) {
    if (!$user_id) return [];

    // Optional content_id usage (only if it's provided)
    if ($content_id) {
        // Check if user has visited the place
        $sql_check_visit = "SELECT 1 FROM visited_places WHERE user_id = ? AND content_id = ?";
        $stmt_visit = $conn->prepare($sql_check_visit);
        $stmt_visit->bind_param("ii", $user_id, $content_id);
        $stmt_visit->execute();
        $visited_result = $stmt_visit->get_result();
        $has_visited = $visited_result->num_rows > 0;
        $stmt_visit->close();

        if (!$has_visited) {
            return ['success' => false, 'message' => 'User has not visited this content.'];
        }
    }

    // Fetch favorite lists
    $sql = "SELECT * FROM favorite_lists WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $favorite_lists = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($favorite_lists as &$list) {
        $sql = "SELECT contents.* FROM favorite_list_places 
                JOIN contents ON favorite_list_places.content_id = contents.id 
                WHERE favorite_list_places.favorite_list_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $list['id']);
        $stmt->execute();
        $places_result = $stmt->get_result();
        $places = $places_result->fetch_all(MYSQLI_ASSOC);
        $list['places'] = $places;
        $list['place_count'] = count($places);
        $stmt->close();
    }

    return $favorite_lists;
}



function addPlaceToList($conn, $list_id, $content_id, $user_id) {
    // Check if the place is already in the list
    $sql_check = "SELECT * FROM favorite_list_places WHERE favorite_list_id = ? AND content_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $list_id, $content_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        return ['success' => false, 'message' => 'Place already in the list.'];
    }
    $stmt_check->close();

    // Insert the place into the list
    $sql = "INSERT INTO favorite_list_places (favorite_list_id, content_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $list_id, $content_id);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to add place to list.'];
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean(); // Clear buffer before JSON response

    if (!$user_id) {
        echo json_encode(['success' => false, 'requires_login' => true]);
        exit();
    }

    // Fetch favorite lists
    if ($_POST['action'] === 'get_favorite_lists' && isset($_POST['content_id'])) {
        $content_id = $_POST['content_id'];
        $response = getFavoriteLists($conn, $user_id, $content_id);
    
        if (isset($response['success']) && !$response['success']) {
            ob_clean();
            echo json_encode($response); // Returns the error if not visited
            exit();
        }
    
        ob_clean();
        echo json_encode(['success' => true, 'favorite_lists' => $response]);
        exit();
    }
    

    // Add place to a favorite list
    if (isset($_POST['action']) && $_POST['action'] === 'add_to_list' && isset($_POST['content_id']) && isset($_POST['list_id'])) {
        $content_id = $_POST['content_id'];
        $list_id = $_POST['list_id'];
        $response = addPlaceToList($conn, $list_id, $content_id, $user_id);
        ob_clean();
        echo json_encode($response);
        exit();
    }

    // Create a new favorite list
    if (isset($_POST['create_favorite_list']) && !empty($_POST['name'])) {
        $list_name = $_POST['name'];
        $sql = "INSERT INTO favorite_lists (name, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $list_name, $user_id);

        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'list_name' => $list_name, 'list_id' => $stmt->insert_id]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to create list.']);
        }
        $stmt->close();
        exit();
    }

    // Rename a favorite list
    if (isset($_POST['action']) && $_POST['action'] === 'rename' && isset($_POST['list_id']) && isset($_POST['name'])) {
        $list_id = $_POST['list_id'];
        $new_name = $_POST['name'];
        $sql = "UPDATE favorite_lists SET name = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $new_name, $list_id, $user_id);

        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'list_id' => $list_id, 'new_name' => $new_name]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to rename the list.']);
        }
        $stmt->close();
        exit();
    }

    // Delete a favorite list
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $list_id = $_POST['id'];
        $sql = "DELETE FROM favorite_lists WHERE id=? AND user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $list_id, $user_id);

        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to delete the list.']);
        }
        $stmt->close();
        exit();
    }
}

// Final clean-up for unexpected output
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    error_log("Unexpected output detected in favorite.php: " . $unexpected_output);
}
