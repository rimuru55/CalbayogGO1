<?php
include $_SERVER['DOCUMENT_ROOT'] . '/CalbayogGO/includes/db.php';
global $conn;

// Fetch contents along with their average ratings, sorted by rating in descending order
$sql = "SELECT c.*, 
               COALESCE(AVG(r.rating), 0) AS average_rating,
               COUNT(r.rating) AS review_count 
        FROM contents c
        LEFT JOIN ratings r ON c.id = r.content_id
        GROUP BY c.id
        ORDER BY average_rating DESC, review_count DESC";
$result = $conn->query($sql);
$contents = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $contents[] = $row;
    }
}

// JSON response for fetching all contents (if used elsewhere)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_all') {
    header('Content-Type: application/json');
    foreach ($contents as &$content) {
        $content['latitude'] = (double)$content['latitude'];
        $content['longitude'] = (double)$content['longitude'];
        
        // Use JSON decode instead of unserialize for transportation
        $content['transportation'] = isset($content['transportation']) ? json_decode($content['transportation'], true) : [];
        $content['things_to_do'] = isset($content['things_to_do']) ? $content['things_to_do'] : '';
    }
    echo json_encode($contents);
    exit();
}

// Fetch specific content by ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_by_id' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM contents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $content = $result->fetch_assoc();
    
    // Use JSON decode instead of unserialize for transportation
    $content['transportation'] = isset($content['transportation']) ? json_decode($content['transportation'], true) : [];
    $content['things_to_do'] = isset($content['things_to_do']) ? $content['things_to_do'] : '';

    header('Content-Type: application/json');
    echo json_encode($content);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            $sql = "DELETE FROM contents WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            echo $stmt->execute() ? "Content deleted successfully!" : "Error: " . $stmt->error;
            $stmt->close();
        } else {
            // Variables from the form submission
            $title = $_POST['title'];
            $description = $_POST['description'];
            $address = $_POST['address'];
            $price = isset($_POST['price']) && $_POST['price'] !== '' ? $_POST['price'] : null;
            $category = $_POST['category'];
            $rating = isset($_POST['rating']) ? $_POST['rating'] : null;
            $amenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];
            $serialized_amenities = serialize($amenities);
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            // Use JSON encoding for transportation
            $transportation = isset($_POST['transportation']) ? $_POST['transportation'] : [];
            $json_transportation = json_encode($transportation); // JSON encode the array
            
            // Set default empty string for things_to_do if not provided
            $things_to_do = isset($_POST['things_to_do']) ? $_POST['things_to_do'] : '';

            // Handle cover photo upload
            $cover_photo_name = null;
            if ($_FILES['cover_photo']['name']) {
                $cover_photo_name = uniqid() . '.' . strtolower(pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION));
                move_uploaded_file($_FILES['cover_photo']['tmp_name'], "../uploads/" . $cover_photo_name);
            }

            // Handle additional photos upload
            $uploaded_files = [];
            foreach ($_FILES['pictures']['name'] as $key => $picture) {
                if (!empty($picture)) {
                    $uniqueFileName = uniqid() . '.' . strtolower(pathinfo($picture, PATHINFO_EXTENSION));
                    if (move_uploaded_file($_FILES['pictures']['tmp_name'][$key], "../uploads/" . $uniqueFileName)) {
                        $uploaded_files[] = $uniqueFileName;
                    }
                }
            }
            $pictures_serialized = serialize($uploaded_files);

            // Insert or Update content
            if ($_POST['action'] === 'create') {
                // Insert statement
                $sql = "INSERT INTO contents (title, description, address, price, cover_photo, pictures, category, rating, amenities, latitude, longitude, transportation, things_to_do) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssdss", $title, $description, $address, $price, $cover_photo_name, $pictures_serialized, $category, $rating, $serialized_amenities, $latitude, $longitude, $json_transportation, $things_to_do);
                echo $stmt->execute() ? "Content added successfully!" : "Error: " . $stmt->error;
                $stmt->close();
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                
                // Fetch current content to retain existing values if empty fields
                $sql = "SELECT * FROM contents WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $currentContent = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // Check for new values or retain existing ones
                $latitude = !empty($latitude) ? $latitude : $currentContent['latitude'];
                $longitude = !empty($longitude) ? $longitude : $currentContent['longitude'];
                $price = isset($price) ? $price : $currentContent['price'];
                $serialized_amenities = !empty($amenities) ? serialize($amenities) : $currentContent['amenities'];
                $pictures_serialized = !empty($uploaded_files) ? $pictures_serialized : $currentContent['pictures'];
                $cover_photo_name = $cover_photo_name ? $cover_photo_name : $currentContent['cover_photo'];
                $json_transportation = !empty($transportation) ? json_encode($transportation) : $currentContent['transportation'];
                $things_to_do = !empty($things_to_do) ? $things_to_do : $currentContent['things_to_do'];

                // Update statement
                $sql = "UPDATE contents SET title=?, description=?, address=?, price=?, cover_photo=?, pictures=?, category=?, rating=?, amenities=?, latitude=?, longitude=?, transportation=?, things_to_do=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssdssi", $title, $description, $address, $price, $cover_photo_name, $pictures_serialized, $category, $rating, $serialized_amenities, $latitude, $longitude, $json_transportation, $things_to_do, $id);
                echo $stmt->execute() ? "Content updated successfully!" : "Error: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>
