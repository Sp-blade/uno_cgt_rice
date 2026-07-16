<?php
	$saleDate = mysqli_real_escape_string($connectDB, $_POST['sale_date'] ?? date('Y-m-d'));
	$salesType = strtoupper(trim((string) ($_POST['sales_type'] ?? 'OTHERS')));
	if (!in_array($salesType, ['OTHERS', 'LPG'], true)) {
		$salesType = 'OTHERS';
	}
	$customerName = trim(mysqli_real_escape_string($connectDB, $_POST['customer_name'] ?? 'Walk-in Customer'));
	$customerAddress = trim(mysqli_real_escape_string($connectDB, $_POST['customer_address'] ?? ''));
	$customerGoogleMap = trim(mysqli_real_escape_string($connectDB, $_POST['customer_google_map'] ?? ''));
	$saleNotes = trim(mysqli_real_escape_string($connectDB, $_POST['sale_notes'] ?? ''));
	$paymentStatus = strtoupper(trim((string) ($_POST['payment_status'] ?? 'PAID')));
	if (!in_array($paymentStatus, ['PAID', 'PARTIAL', 'UNPAID'], true)) {
		$paymentStatus = 'PAID';
	}
	$amountPaid = (float) ($_POST['amount_paid'] ?? 0);
	$dueDateValue = trim((string) ($_POST['due_date'] ?? ''));
	$dueDateSql = $dueDateValue !== '' ? "'" . mysqli_real_escape_string($connectDB, $dueDateValue) . "'" : "NULL";
	$productNames = isset($_POST['product_name']) ? $_POST['product_name'] : [];
	$productIds = isset($_POST['product_id']) ? $_POST['product_id'] : [];
	$productQuantities = isset($_POST['product_quantity']) ? $_POST['product_quantity'] : [];
	$productPrices = isset($_POST['product_price']) ? $_POST['product_price'] : [];
	$totalPrices = isset($_POST['total_price']) ? $_POST['total_price'] : [];
	$saleUnits = isset($_POST['sale_unit']) ? $_POST['sale_unit'] : [];
	$kgConversionQuantities = isset($_POST['kg_conversion_qty']) ? $_POST['kg_conversion_qty'] : [];
	$lpgTransactionTypes = isset($_POST['lpg_transaction_type']) ? $_POST['lpg_transaction_type'] : [];
	$lpgTankConditions = isset($_POST['lpg_tank_condition']) ? $_POST['lpg_tank_condition'] : [];
	$lpgTankPayments = isset($_POST['lpg_tank_payment']) ? $_POST['lpg_tank_payment'] : [];

	if (!is_array($productNames)) {
		$productNames = unserialize($productNames);
		$productIds = unserialize($productIds);
		$productQuantities = unserialize($productQuantities);
		$productPrices = unserialize($productPrices);
		$totalPrices = unserialize($totalPrices);
		$saleUnits = unserialize($saleUnits);
		$kgConversionQuantities = unserialize($kgConversionQuantities);
		$lpgTransactionTypes = unserialize($lpgTransactionTypes);
		$lpgTankConditions = unserialize($lpgTankConditions);
		$lpgTankPayments = unserialize($lpgTankPayments);
	}

	if (empty($productNames) || empty($productQuantities) || empty($productPrices)) {
		header("Location: " . $server . "?mainmenu=" . ($salesType === 'LPG' ? 'sell_product_lpg' : 'sell_product_others'));
		die();
	}

	$totalSavedSaleAmount = 0;

	$getDeliveryNo = $connectDB->query("SELECT DeliveryNo FROM sales ORDER BY DeliveryNo DESC LIMIT 1");
	if ($getDeliveryNo && $getDeliveryNo->num_rows > 0) {
		$deliveryRow = $getDeliveryNo->fetch_assoc();
		$deliveryNo = (int) $deliveryRow['DeliveryNo'] + 1;
	} else {
		$deliveryNo = 1;
	}

	$expenseNo = 0;
	if (!empty($expenseCategories) && !empty($expenseAmounts)) {
		$getExpenseNo = $connectDB->query("SELECT ExpenseNo FROM expenses ORDER BY ExpenseNo DESC LIMIT 1");
		if ($getExpenseNo && $getExpenseNo->num_rows > 0) {
			$expenseRow = $getExpenseNo->fetch_assoc();
			$expenseNo = (int) $expenseRow['ExpenseNo'] + 1;
		} else {
			$expenseNo = 1;
		}
	}

	foreach ($productNames as $index => $productName) {
		$name = trim(mysqli_real_escape_string($connectDB, $productName));
		$productId = (int) ($productIds[$index] ?? 0);
		$quantity = (float) ($productQuantities[$index] ?? 0);
		$price = (float) ($productPrices[$index] ?? 0);
		$total = isset($totalPrices[$index]) ? (float) $totalPrices[$index] : ($quantity * $price);
		$lpgTransactionType = strtoupper(trim((string) ($lpgTransactionTypes[$index] ?? 'NONE')));
		if (!in_array($lpgTransactionType, ['NONE', 'SWAPPED', 'SOLD'], true)) {
			$lpgTransactionType = 'NONE';
		}
		$lpgTankCondition = trim(mysqli_real_escape_string($connectDB, $lpgTankConditions[$index] ?? ''));
		$lpgTankPayment = (float) ($lpgTankPayments[$index] ?? 0);
		if ($lpgTransactionType !== 'SOLD') {
			$lpgTankPayment = 0;
		}
		$total += $lpgTankPayment;
		$saleUnit = strtolower(trim((string) ($saleUnits[$index] ?? 'pc'))) === 'kg' ? 'kg' : 'pc';
		$kgConversionQty = (float) ($kgConversionQuantities[$index] ?? 0);
		if ($saleUnit !== 'kg') {
			$kgConversionQty = 0;
		}

		if ($name === '' || $quantity <= 0 || $price <= 0) {
			continue;
		}

		$sql = "INSERT INTO sales (SaleDate, DeliveryNo, CustomerName, Product_ID, ProductName, Quantity, SaleUnit, KgConversionQty, UnitPrice, Less, TotalSalePrice, Notes, SalesType, PaymentStatus, AmountPaid, DueDate, LpgTransactionType, LpgTankCondition, LpgTankPayment)
				VALUES ('$saleDate', '$deliveryNo', '$customerName', '$productId', '$name', '$quantity', '$saleUnit', '$kgConversionQty', '$price', '0', '$total', '$saleNotes', '$salesType', '$paymentStatus', '$amountPaid', $dueDateSql, '$lpgTransactionType', '$lpgTankCondition', '$lpgTankPayment')";
		if ($connectDB->query($sql)) {
			$saleId = $connectDB->insert_id;
			$totalSavedSaleAmount += $total;
			$remainingToDeduct = $quantity;
			$batchResult = $connectDB->query("
				SELECT ID, QuantityRemaining
				FROM inventory_batches
				WHERE (Product_ID = '$productId' OR ProductName = '$name') AND QuantityRemaining > 0
				ORDER BY CASE WHEN BatchLabel = 'OLD' THEN 0 ELSE 1 END, BatchDate ASC, ID ASC
			");
			while ($batchResult && $batch = $batchResult->fetch_assoc()) {
				if ($remainingToDeduct <= 0) {
					break;
				}
				$batchId = (int) $batch['ID'];
				$available = (float) $batch['QuantityRemaining'];
				$deductQty = min($remainingToDeduct, $available);
				$safeDeductQty = mysqli_real_escape_string($connectDB, number_format($deductQty, 2, '.', ''));
				$connectDB->query("UPDATE inventory_batches SET QuantityRemaining = QuantityRemaining - $safeDeductQty WHERE ID = '$batchId'");
				$connectDB->query("
					INSERT INTO inventory_movements (Product_ID, ProductName, Batch_ID, MovementDate, MovementType, Quantity, ReferenceType, ReferenceNo, Notes)
					VALUES ('$productId', '$name', '$batchId', '$saleDate', 'OUT', '$safeDeductQty', 'SALE', 'SaleID#$saleId', 'FIFO sale deduction for Sale#$deliveryNo')
				");
				$remainingToDeduct -= $deductQty;
			}
		}
	}
	$accountAmountPaid = $paymentStatus === 'UNPAID' ? 0 : min($amountPaid, $totalSavedSaleAmount);
	if ($paymentStatus === 'PAID') {
		$accountAmountPaid = $totalSavedSaleAmount;
	}
	$balance = max($totalSavedSaleAmount - $accountAmountPaid, 0);
	$safeAccountPaid = mysqli_real_escape_string($connectDB, number_format($accountAmountPaid, 2, '.', ''));
	$safeAccountTotal = mysqli_real_escape_string($connectDB, number_format($totalSavedSaleAmount, 2, '.', ''));
	$safeAccountBalance = mysqli_real_escape_string($connectDB, number_format($balance, 2, '.', ''));
	$connectDB->query("
		INSERT INTO customer_accounts (CustomerName, DeliveryNo, SaleDate, DueDate, TotalAmount, AmountPaid, Balance, PaymentStatus)
		VALUES ('$customerName', '$deliveryNo', '$saleDate', $dueDateSql, '$safeAccountTotal', '$safeAccountPaid', '$safeAccountBalance', '$paymentStatus')
		ON DUPLICATE KEY UPDATE CustomerName = VALUES(CustomerName), SaleDate = VALUES(SaleDate), DueDate = VALUES(DueDate), TotalAmount = VALUES(TotalAmount), AmountPaid = VALUES(AmountPaid), Balance = VALUES(Balance), PaymentStatus = VALUES(PaymentStatus)
	");
	$connectDB->query("
		INSERT INTO customers (CustomerName, Address, GoogleMap)
		VALUES ('$customerName', '$customerAddress', '$customerGoogleMap')
		ON DUPLICATE KEY UPDATE
			Address = CASE WHEN VALUES(Address) <> '' THEN VALUES(Address) ELSE Address END,
			GoogleMap = CASE WHEN VALUES(GoogleMap) <> '' THEN VALUES(GoogleMap) ELSE GoogleMap END
	");

	header("Location: " . $server . "?mainmenu=sales_list");
	die();
?>
