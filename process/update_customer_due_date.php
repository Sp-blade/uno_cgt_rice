<?php
	$customerName = trim((string) ($_POST['customer_name'] ?? ''));
	$deliveryNo = (int) ($_POST['delivery_no'] ?? 0);
	$dueDate = trim((string) ($_POST['due_date'] ?? ''));
	$searchCustomer = trim((string) ($_POST['search_customer'] ?? ''));
	$sortCustomer = junkshop_normalize_customers_sort($_POST['sort_customer'] ?? 'due_date');
	$redirectSuffix = junkshop_customers_redirect_suffix($searchCustomer, $sortCustomer);

	if ($customerName === '' || junkshop_is_walk_in_customer($customerName) || $deliveryNo <= 0) {
		header("Location: " . $server . "?mainmenu=customers&due_date_status=invalid" . $redirectSuffix);
		die();
	}

	if ($dueDate !== '' && strtotime($dueDate) === false) {
		header("Location: " . $server . "?mainmenu=customers&due_date_status=invalid&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	if (!junkshop_update_delivery_due_date($connectDB, $customerName, $deliveryNo, $dueDate)) {
		header("Location: " . $server . "?mainmenu=customers&due_date_status=invalid&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	header("Location: " . $server . "?mainmenu=customers&due_date_status=updated&customer=" . urlencode($customerName) . $redirectSuffix);
	die();
?>
