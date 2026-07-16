<?php
	$expectedDatabaseName = 'unocgtricedb';
	$redirectBase = $server . '?mainmenu=download_database';

	if (!isset($_POST['confirm_restore']) || $_POST['confirm_restore'] !== 'yes') {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('Please confirm the database restore warning before continuing.'));
		die();
	}

	if (!isset($_FILES['database_backup']) || !is_array($_FILES['database_backup'])) {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('Please upload a backup file first.'));
		die();
	}

	$uploadedFile = $_FILES['database_backup'];
	if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('The backup upload failed. Please try again.'));
		die();
	}

	$originalFilename = (string) ($uploadedFile['name'] ?? '');
	$normalizedFilename = strtolower(pathinfo($originalFilename, PATHINFO_FILENAME));
	$normalizedFilename = preg_replace('/[^a-z0-9]+/', '_', $normalizedFilename);
	$expectedPrefix = strtolower($expectedDatabaseName);

	if (pathinfo($originalFilename, PATHINFO_EXTENSION) !== 'sql') {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('Only .sql backup files are allowed.'));
		die();
	}

	if (strpos($normalizedFilename, $expectedPrefix) !== 0) {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('Invalid backup name. Upload a backup file for ' . $expectedDatabaseName . ' only.'));
		die();
	}

	$tempPath = $uploadedFile['tmp_name'] ?? '';
	if ($tempPath === '' || !is_uploaded_file($tempPath)) {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('Unable to read the uploaded backup file.'));
		die();
	}

	$sqlDump = file_get_contents($tempPath);
	if ($sqlDump === false || trim($sqlDump) === '') {
		header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('The uploaded backup file is empty or unreadable.'));
		die();
	}

	set_time_limit(0);
	$connectDB->query('SET FOREIGN_KEY_CHECKS=0');

	$tablesResult = $connectDB->query('SHOW TABLES');
	if ($tablesResult) {
		while ($tableRow = $tablesResult->fetch_array()) {
			$tableName = $tableRow[0];
			$connectDB->query("DROP TABLE IF EXISTS `$tableName`");
		}
	}

	$statements = [];
	$currentStatement = '';
	$lines = preg_split("/\r\n|\n|\r/", $sqlDump);

	foreach ($lines as $line) {
		$trimmedLine = trim($line);
		if ($trimmedLine === '' || strpos($trimmedLine, '--') === 0) {
			continue;
		}

		if (strpos($trimmedLine, '/*') === 0 && substr($trimmedLine, -2) === '*/') {
			continue;
		}

		$currentStatement .= $line . "\n";
		if (substr(rtrim($line), -1) === ';') {
			$statements[] = trim($currentStatement);
			$currentStatement = '';
		}
	}

	if (trim($currentStatement) !== '') {
		$statements[] = trim($currentStatement);
	}

	foreach ($statements as $statement) {
		if ($statement === '') {
			continue;
		}

		if (!$connectDB->query($statement)) {
			$connectDB->query('SET FOREIGN_KEY_CHECKS=1');
			header('Location: ' . $redirectBase . '&restore_status=error&restore_message=' . urlencode('Database restore failed: ' . $connectDB->error));
			die();
		}
	}

	$connectDB->query('SET FOREIGN_KEY_CHECKS=1');
	header('Location: ' . $redirectBase . '&restore_status=success&restore_message=' . urlencode('Database backup restored successfully. The existing database has been updated.'));
	die();
?>
