<?php
	$loanId = (int) ($_POST['loan_id'] ?? 0);
	$returnCondition = junkshop_lpg_normalize_tank_condition($_POST['return_condition'] ?? '');
	$customerName = trim((string) ($_POST['customer_name'] ?? ''));
	$searchCustomer = trim((string) ($_POST['search_customer'] ?? ''));
	$sortCustomer = junkshop_normalize_customers_sort($_POST['sort_customer'] ?? 'due_date');

	$result = junkshop_lpg_return_loan($connectDB, $loanId, $returnCondition);
	$redirect = $server . '?mainmenu=customers' . junkshop_customers_redirect_suffix($searchCustomer, $sortCustomer);
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
