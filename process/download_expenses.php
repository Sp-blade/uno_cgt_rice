<?php
include "connect.php";

$sql = "SELECT * FROM expenses ORDER BY ExpenseDate DESC, ExpenseNo DESC, ID DESC";
$result = $connectDB->query($sql);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="expenses_export.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

if ($result && $result->num_rows > 0) {
	$columns = $result->fetch_fields();
	$header = [];
	foreach ($columns as $column) {
		$header[] = $column->name;
	}
	fputcsv($output, $header);

	while ($row = $result->fetch_assoc()) {
		fputcsv($output, $row);
	}
} else {
	fputcsv($output, ['No expense records found']);
}

fclose($output);
exit;
?>
