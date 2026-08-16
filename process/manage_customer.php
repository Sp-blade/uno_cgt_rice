<?php
	$originalCustomerName = trim((string) ($_POST['original_customer_name'] ?? ''));
	$customerName = trim((string) ($_POST['customer_name'] ?? ''));
	$customerAddress = trim((string) ($_POST['customer_address'] ?? ''));
	$customerGoogleMap = trim((string) ($_POST['customer_google_map'] ?? ''));
	$searchCustomer = trim((string) ($_POST['search_customer'] ?? ''));
	$redirectSuffix = $searchCustomer !== '' ? '&searchCustomer=' . urlencode($searchCustomer) : '';

	if ($originalCustomerName !== '') {
		if ($originalCustomerName === '' || junkshop_is_walk_in_customer($originalCustomerName)) {
			header("Location: " . $server . "?mainmenu=customers&customer_status=missing" . $redirectSuffix);
			die();
		}

		$safeOriginalName = mysqli_real_escape_string($connectDB, $originalCustomerName);
		$safeCustomerAddress = mysqli_real_escape_string($connectDB, $customerAddress);
		$safeCustomerGoogleMap = mysqli_real_escape_string($connectDB, $customerGoogleMap);

		$connectDB->query("
			UPDATE customers
			SET Address = '$safeCustomerAddress', GoogleMap = '$safeCustomerGoogleMap'
			WHERE CustomerName = '$safeOriginalName'
		");

		header("Location: " . $server . "?mainmenu=customers&customer_status=updated" . $redirectSuffix);
		die();
	}

	if ($customerName === '' || junkshop_is_walk_in_customer($customerName)) {
		header("Location: " . $server . "?mainmenu=customers&customer_status=missing" . $redirectSuffix);
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

	header("Location: " . $server . "?mainmenu=customers&customer_status=saved" . $redirectSuffix);
	die();
?>
