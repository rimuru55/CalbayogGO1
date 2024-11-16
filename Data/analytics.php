<?php
include $_SERVER['DOCUMENT_ROOT'] . '/CalbayogGO/includes/db.php';
global $conn;

// Fetch most added places to favorite lists
function getMostAddedPlaces($conn) {
    $sql = "
        SELECT c.title, COUNT(flp.content_id) as favorite_count
        FROM favorite_list_places flp
        JOIN contents c ON flp.content_id = c.id
        GROUP BY flp.content_id
        ORDER BY favorite_count DESC
        LIMIT 10";
    
    $result = $conn->query($sql);
    $places = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $places[] = $row;
        }
    }
    return $places;
}

function getMostVisitedPlaces($conn) {
    $sql = "
        SELECT c.title, COUNT(vp.content_id) as visit_count
        FROM visited_places vp
        JOIN contents c ON vp.content_id = c.id
        GROUP BY vp.content_id
        ORDER BY visit_count DESC
        LIMIT 5";
    
    $result = $conn->query($sql);
    $places = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $places[] = $row;
        }
    }
    return $places;
}

// Fetch new users per month
function getNewUsersPerMonth($conn) {
    $sql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as user_count
        FROM users
        WHERE role != 'admin'
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12";
    
    $result = $conn->query($sql);
    $newUsers = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $newUsers[] = $row;
        }
    }
    return $newUsers;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_most_added_places') {
        echo json_encode(getMostAddedPlaces($conn));
    } elseif ($_GET['action'] === 'get_new_users_per_month') {
        echo json_encode(getNewUsersPerMonth($conn));
    } elseif ($_GET['action'] === 'get_most_visited_places') {  // New action for most visited places
        echo json_encode(getMostVisitedPlaces($conn));
    }
}
$conn->close();
?>
