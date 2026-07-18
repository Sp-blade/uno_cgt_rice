<?php
	$customerName = trim((string) ($_POST['customer_name'] ?? ''));
	$customerAddress = trim((string) ($_POST['customer_address'] ?? ''));
	$customerGoogleMap = trim((string) ($_POST['customer_google_map'] ?? ''));

	if ($customerName === '') {
		header("Location: " . $server . "?mainmenu=customers&customer_status=missing");
		die();
	}

	$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
	$safeCustomerAddress = mysqli_real_escape_string($connectDB, $customerAddress);
	$safeCustomerGoogleMap = mysqli_real_escape_string($connectDB, $customerGoogleMap);

	$connectDB->query("
		INSERT INTO customers (CustomerName, Address, GoogleMap)
		VALUES ('$safeCustomerName', '$safeCustomerAddress', '$safeCustomerGoogleMap')
		ON DUPLICATE KEY UPDATE
			Address = VALUES(Address),
			GoogleMap = VALUES(GoogleMap)
	");

	header("Location: " . $server . "?mainmenu=customers&customer_status=saved");
	die();
?>
