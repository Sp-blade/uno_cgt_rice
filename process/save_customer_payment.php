<?php
	$paymentId = (int) ($_POST['payment_id'] ?? 0);
	$customerName = trim((string) ($_POST['customer_name'] ?? ''));
	$deliveryNo = (int) ($_POST['delivery_no'] ?? 0);
	$paymentAmount = round((float) ($_POST['payment_amount'] ?? 0), 2);
	$paymentDate = junkshop_normalize_datetime($_POST['payment_date'] ?? '');
	$paymentNotes = trim((string) ($_POST['payment_notes'] ?? ''));
	$searchCustomer = trim((string) ($_POST['search_customer'] ?? ''));
	$sortCustomer = junkshop_normalize_customers_sort($_POST['sort_customer'] ?? 'due_date');
	$redirectSuffix = junkshop_customers_redirect_suffix($searchCustomer, $sortCustomer);

	if ($customerName === '' || junkshop_is_walk_in_customer($customerName)) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_customer" . $redirectSuffix);
		die();
	}

	if ($paymentAmount <= 0) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_amount&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	if ($paymentId > 0) {
		$existingPayment = junkshop_get_customer_payment($connectDB, $paymentId);
		if (!$existingPayment) {
			header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_payment&customer=" . urlencode($customerName) . $redirectSuffix);
			die();
		}

		$existingCustomerName = trim((string) ($existingPayment['CustomerName'] ?? ''));
		$existingDeliveryNo = (int) ($existingPayment['DeliveryNo'] ?? 0);
		$existingAmount = round((float) ($existingPayment['Amount'] ?? 0), 2);

		if ($existingCustomerName !== $customerName) {
			header("Location: " . $server . "?mainmenu=customers&payment_status=invalid_payment&customer=" . urlencode($customerName) . $redirectSuffix);
			die();
		}

		junkshop_reverse_customer_payment_on_delivery($connectDB, $existingCustomerName, $existingDeliveryNo, $existingAmount);
		$connectDB->query("DELETE FROM customer_payments WHERE ID = '$paymentId'");

		if ($deliveryNo > 0) {
			junkshop_apply_customer_payment_to_delivery($connectDB, $customerName, $deliveryNo, $paymentAmount, $paymentDate, $paymentNotes, true);
		} else {
			$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
			$remainingPayment = $paymentAmount;
			$accountQuery = "
				SELECT DeliveryNo, Balance
				FROM customer_accounts
				WHERE CustomerName = '$safeCustomerName' AND Balance > 0
				ORDER BY CASE WHEN DueDate IS NULL THEN 1 ELSE 0 END, DueDate ASC, SaleDate ASC, DeliveryNo ASC
			";
			$accountsResult = $connectDB->query($accountQuery);
			while ($remainingPayment > 0 && $accountsResult && ($account = $accountsResult->fetch_assoc())) {
				$accountDeliveryNo = (int) ($account['DeliveryNo'] ?? 0);
				$accountBalance = round((float) ($account['Balance'] ?? 0), 2);
				if ($accountDeliveryNo <= 0 || $accountBalance <= 0) {
					continue;
				}
				$appliedAmount = min($remainingPayment, $accountBalance);
				junkshop_apply_customer_payment_to_delivery($connectDB, $customerName, $accountDeliveryNo, $appliedAmount, $paymentDate, $paymentNotes, true);
				$remainingPayment = round($remainingPayment - $appliedAmount, 2);
			}
		}

		header("Location: " . $server . "?mainmenu=customers&payment_status=updated&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
	$remainingPayment = $paymentAmount;

	$accountQuery = "
		SELECT DeliveryNo, Balance
		FROM customer_accounts
		WHERE CustomerName = '$safeCustomerName' AND Balance > 0
	";
	if ($deliveryNo > 0) {
		$accountQuery .= " AND DeliveryNo = '$deliveryNo'";
	}
	$accountQuery .= " ORDER BY CASE WHEN DueDate IS NULL THEN 1 ELSE 0 END, DueDate ASC, SaleDate ASC, DeliveryNo ASC";

	$accountsResult = $connectDB->query($accountQuery);
	if (!$accountsResult || $accountsResult->num_rows === 0) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=no_balance&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	while ($remainingPayment > 0 && ($account = $accountsResult->fetch_assoc())) {
		$accountDeliveryNo = (int) ($account['DeliveryNo'] ?? 0);
		$accountBalance = round((float) ($account['Balance'] ?? 0), 2);
		if ($accountDeliveryNo <= 0 || $accountBalance <= 0) {
			continue;
		}

		$appliedAmount = min($remainingPayment, $accountBalance);
		junkshop_apply_customer_payment_to_delivery($connectDB, $customerName, $accountDeliveryNo, $appliedAmount, $paymentDate, $paymentNotes, true);
		$remainingPayment = round($remainingPayment - $appliedAmount, 2);
	}

	if ($remainingPayment >= $paymentAmount) {
		header("Location: " . $server . "?mainmenu=customers&payment_status=no_balance&customer=" . urlencode($customerName) . $redirectSuffix);
		die();
	}

	header("Location: " . $server . "?mainmenu=customers&payment_status=saved&customer=" . urlencode($customerName) . $redirectSuffix);
	die();
?>
