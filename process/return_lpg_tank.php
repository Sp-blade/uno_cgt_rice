<?php
	$loanId = (int) ($_POST['loan_id'] ?? 0);
	$returnCondition = junkshop_lpg_normalize_tank_condition($_POST['return_condition'] ?? '');
	$customerName = trim((string) ($_POST['customer_name'] ?? ''));
	$searchCustomer = trim((string) ($_POST['search_customer'] ?? ''));

	$result = junkshop_lpg_return_loan($connectDB, $loanId, $returnCondition);
	$redirect = $server . '?mainmenu=customers';
	if ($searchCustomer !== '') {
		$redirect .= '&searchCustomer=' . urlencode($searchCustomer);
	}
	if ($customerName !== '') {
		$redirect .= '&customer=' . urlencode($customerName);
	}
	if ($result['success']) {
		$redirect .= '&lpg_return_status=returned';
	} else {
		$redirect .= '&lpg_return_status=error&lpg_return_error=' . urlencode($result['message']);
	}
	header('Location: ' . $redirect);
	die();
