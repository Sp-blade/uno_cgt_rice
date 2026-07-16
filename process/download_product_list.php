<?php
include "connect.php";

// SQL query to fetch purchase products only for export
$sql = "SELECT Product_ID, ProductName, ProductType, ProductBaseUnit, ProductPrice, SellingPrice, CanConvertToKg, KgEquivalentQty, IsActive
		FROM products
		WHERE COALESCE(IsSubProduct, 0) = 0
		ORDER BY ProductType ASC, ProductName ASC";
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
