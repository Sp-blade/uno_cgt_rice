<?php
include "connect.php";

// SQL query to fetch data
$sql = "SELECT * FROM purchases WHERE DATE(PurchaseDate) >= CURDATE() - INTERVAL 1 YEAR;";
$result = $connectDB->query($sql);

// Check if the query returned results
if ($result->num_rows > 0) {
    // Set headers to force download as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="data_export.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open the output stream
    $output = fopen('php://output', 'w');

    // Output column headers
    $columns = $result->fetch_fields();
    $header = [];
    foreach ($columns as $column) {
        $header[] = $column->name;  // Column names
    }
    fputcsv($output, $header);

    // Fetch data and write to CSV
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);  // Write data to CSV
    }

    // Close the output stream
    fclose($output);
} else {
    echo "No results found";
}


?>
