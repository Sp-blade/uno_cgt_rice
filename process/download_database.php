<?php
include "connect.php";

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="unocgtricedb_backup.sql"');
header('Pragma: no-cache');
header('Expires: 0');

$output = "-- Rice Trading Database Backup\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$output .= "SET time_zone = \"+00:00\";\n\n";

$tablesResult = $connectDB->query("SHOW TABLES");

if ($tablesResult) {
	while ($tableRow = $tablesResult->fetch_array()) {
		$tableName = $tableRow[0];
		$createResult = $connectDB->query("SHOW CREATE TABLE `$tableName`");
		$createRow = $createResult ? $createResult->fetch_assoc() : null;

		$output .= "DROP TABLE IF EXISTS `$tableName`;\n";
		if ($createRow && isset($createRow['Create Table'])) {
			$output .= $createRow['Create Table'] . ";\n\n";
		}

		$dataResult = $connectDB->query("SELECT * FROM `$tableName`");
		if ($dataResult && $dataResult->num_rows > 0) {
			while ($row = $dataResult->fetch_assoc()) {
				$values = [];
				foreach ($row as $value) {
					if ($value === null) {
						$values[] = "NULL";
					} else {
						$values[] = "'" . $connectDB->real_escape_string($value) . "'";
					}
				}
				$output .= "INSERT INTO `$tableName` VALUES (" . implode(', ', $values) . ");\n";
			}
			$output .= "\n";
		}
	}
}

echo $output;
exit;
?>
