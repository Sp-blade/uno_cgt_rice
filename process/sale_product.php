<?php
	$previousMenu = $_POST['previousmenu'] ?? ($_POST['sales_type'] ?? 'OTHERS');
	$salesType = strtoupper(trim((string) ($_POST['sales_type'] ?? 'OTHERS')));
	if (!in_array($salesType, ['OTHERS', 'LPG'], true)) {
		$salesType = 'OTHERS';
	}

	$saleDate = $_POST['sale_date'] ?? date('Y-m-d');
	$customerName = trim((string) ($_POST['customer_name'] ?? 'Walk-in Customer'));
	$customerAddress = trim((string) ($_POST['customer_address'] ?? ''));
	$customerGoogleMap = trim((string) ($_POST['customer_google_map'] ?? ''));
	$saleNotes = trim((string) ($_POST['sale_notes'] ?? ''));
	$paymentStatus = strtoupper(trim((string) ($_POST['payment_status'] ?? 'PAID')));
	if (!in_array($paymentStatus, ['PAID', 'PARTIAL', 'UNPAID'], true)) {
		$paymentStatus = 'PAID';
	}
	$amountPaid = (float) ($_POST['amount_paid'] ?? 0);
	$dueDate = trim((string) ($_POST['due_date'] ?? ''));

	$productNames = isset($_POST['product_name']) && is_array($_POST['product_name']) ? $_POST['product_name'] : [];
	$productIds = isset($_POST['product_id']) && is_array($_POST['product_id']) ? $_POST['product_id'] : [];
	$productQuantities = isset($_POST['product_quantity']) && is_array($_POST['product_quantity']) ? $_POST['product_quantity'] : [];
	$productPrices = isset($_POST['product_price']) && is_array($_POST['product_price']) ? $_POST['product_price'] : [];
	$totalPrices = isset($_POST['total_price']) && is_array($_POST['total_price']) ? $_POST['total_price'] : [];
	$saleUnits = isset($_POST['sale_unit']) && is_array($_POST['sale_unit']) ? $_POST['sale_unit'] : [];
	$kgConversionQuantities = isset($_POST['kg_conversion_qty']) && is_array($_POST['kg_conversion_qty']) ? $_POST['kg_conversion_qty'] : [];
	$lpgTransactionTypes = isset($_POST['lpg_transaction_type']) && is_array($_POST['lpg_transaction_type']) ? $_POST['lpg_transaction_type'] : [];
	$lpgTankConditions = isset($_POST['lpg_tank_condition']) && is_array($_POST['lpg_tank_condition']) ? $_POST['lpg_tank_condition'] : [];
	$lpgTankPayments = isset($_POST['lpg_tank_payment']) && is_array($_POST['lpg_tank_payment']) ? $_POST['lpg_tank_payment'] : [];

	$getSaleNo = $connectDB->query("SELECT DeliveryNo FROM sales ORDER BY DeliveryNo DESC LIMIT 1");
	if ($getSaleNo && $getSaleNo->num_rows > 0) {
		$saleNoRow = $getSaleNo->fetch_assoc();
		$saleNo = (int) $saleNoRow['DeliveryNo'] + 1;
	} else {
		$saleNo = 1;
	}

	$grandTotal = 0;
	foreach ($productNames as $index => $productName) {
		$name = trim((string) $productName);
		$quantity = (float) ($productQuantities[$index] ?? 0);
		$price = (float) ($productPrices[$index] ?? 0);
		if ($name === '' || $quantity <= 0 || $price <= 0) {
			continue;
		}
		$rowTotal = isset($totalPrices[$index]) ? (float) $totalPrices[$index] : ($quantity * $price);
		$tankType = strtoupper(trim((string) ($lpgTransactionTypes[$index] ?? 'NONE')));
		$grandTotal += $rowTotal;
	}

	$balance = max($grandTotal - ($paymentStatus === 'PAID' ? $grandTotal : $amountPaid), 0);
?>
