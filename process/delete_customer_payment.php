<?php
	$paymentId = (int) ($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);
	$customerName = trim((string) ($_GET['customer'] ?? $_POST['customer'] ?? ''));
	$searchCustomer = trim((string) ($_GET['searchCustomer'] ?? $_POST['search_customer'] ?? ''));
	$redirectSuffix = $searchCustomer !== '' ? '&searchCustomer=' . urlencode($searchCustomer) : '';

	if ($paymentId <= 0) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_payment" . $redirectSuffix);
		die();
	}

	$payment = junkshop_get_customer_payment($connectDB, $paymentId);
	if (!$payment) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_payment" . $redirectSuffix);
		die();
	}

	if ($customerName === '') {
		$customerName = trim((string) ($payment['CustomerName'] ?? ''));
	}

	if (!junkshop_delete_customer_payment($connectDB, $paymentId)) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_payment&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	header("Location: " . $server . "?mainmenu=customers&payment_status=deleted&customer=" . urlencode($customerName) . $redirectSuffix);
	die();
?>
