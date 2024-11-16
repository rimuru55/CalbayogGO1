<?php
include $_SERVER['DOCUMENT_ROOT'] . '/CalbayogGO/includes/db.php';
global $conn;

// Define the path to your uploads directory
$uploads_dir = "uploads/";

// Check if the search query 'q' is set in the URL
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $search_query = "%" . $_GET['q'] . "%";  // Wrap the query in '%' for SQL LIKE
    $sql = "SELECT id, title, address, cover_photo, latitude, longitude 
            FROM contents 
            WHERE title LIKE ? OR description LIKE ? OR address LIKE ?";
    
    // Prepare the statement
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_query, $search_query, $search_query);  // Bind the query to title, description, and address
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contents = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['cover_photo'] = htmlspecialchars($uploads_dir . $row['cover_photo']);  // Sanitize file path
            $row['title'] = htmlspecialchars($row['title']); // Sanitize title
            $row['address'] = htmlspecialchars($row['address']); // Sanitize address
            $contents[] = $row;
        }
    }

    // Return JSON response for JavaScript to process
    header('Content-Type: application/json');
    echo json_encode($contents);
    
    // Close the statement and connection
    $stmt->close();
    $conn->close();
} else {
    // Return an empty JSON array if 'q' is not set or empty
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>
