<?php
	$saleDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($_POST['sale_date'] ?? date('Y-m-d H:i:s')));
	$salesType = strtoupper(trim((string) ($_POST['sales_type'] ?? 'OTHERS')));
	if (!in_array($salesType, ['OTHERS', 'LPG'], true)) {
		$salesType = 'OTHERS';
	}
	$customerName = trim(mysqli_real_escape_string($connectDB, $_POST['customer_name'] ?? 'Walk-in Customer'));
	$customerAddress = trim(mysqli_real_escape_string($connectDB, $_POST['customer_address'] ?? ''));
	$customerGoogleMap = trim(mysqli_real_escape_string($connectDB, $_POST['customer_google_map'] ?? ''));
	$saleNotes = trim(mysqli_real_escape_string($connectDB, $_POST['sale_notes'] ?? ''));
	$customerType = strtolower(trim((string) ($_POST['customer_type'] ?? 'walk_in')));
	$paymentStatuses = isset($_POST['payment_status']) ? $_POST['payment_status'] : [];
	$amountPaids = isset($_POST['amount_paid']) ? $_POST['amount_paid'] : [];
	$dueDates = isset($_POST['due_date']) ? $_POST['due_date'] : [];
	$productNames = isset($_POST['product_name']) ? $_POST['product_name'] : [];
	$productIds = isset($_POST['product_id']) ? $_POST['product_id'] : [];
	$productTypes = isset($_POST['product_type']) ? $_POST['product_type'] : [];
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
		$productTypes = is_array($productTypes) ? $productTypes : [];
		$productQuantities = unserialize($productQuantities);
		$productPrices = unserialize($productPrices);
		$totalPrices = unserialize($totalPrices);
		$saleUnits = unserialize($saleUnits);
		$kgConversionQuantities = unserialize($kgConversionQuantities);
		$lpgTransactionTypes = unserialize($lpgTransactionTypes);
		$lpgTankConditions = unserialize($lpgTankConditions);
		$lpgTankPayments = unserialize($lpgTankPayments);
		$paymentStatuses = is_array($paymentStatuses) ? $paymentStatuses : unserialize($paymentStatuses);
		$amountPaids = is_array($amountPaids) ? $amountPaids : unserialize($amountPaids);
		$dueDates = is_array($dueDates) ? $dueDates : unserialize($dueDates);
	}

	if (empty($productNames) || empty($productQuantities) || empty($productPrices)) {
		header("Location: " . $server . "?mainmenu=" . 'sell_product_others' . "&sale_error=empty");
		die();
	}

	$requestedQuantities = [];
	$productMetaByKey = [];
	foreach ($productNames as $index => $productName) {
		$name = trim((string) $productName);
		$productId = (int) ($productIds[$index] ?? 0);
		$quantity = (float) ($productQuantities[$index] ?? 0);
		$price = (float) ($productPrices[$index] ?? 0);
		$saleUnit = junkshop_normalize_base_unit($saleUnits[$index] ?? 'pc');
		if ($name === '' || $quantity <= 0 || $price <= 0) {
			continue;
		}
		$key = $productId . '|' . $name;
		$baseUnit = 'pc';
		$canConvert = 0;
		$equivQty = 0;
		if ($productId > 0) {
			$productMetaResult = $connectDB->query("SELECT ProductBaseUnit, CanConvertToKg, KgEquivalentQty FROM products WHERE Product_ID = '$productId' LIMIT 1");
			if ($productMetaResult && ($productMetaRow = $productMetaResult->fetch_assoc())) {
				$baseUnit = junkshop_normalize_base_unit($productMetaRow['ProductBaseUnit'] ?? 'pc');
				$canConvert = (int) ($productMetaRow['CanConvertToKg'] ?? 0);
				$equivQty = (float) ($productMetaRow['KgEquivalentQty'] ?? 0);
			}
		}
		$baseQuantity = junkshop_sale_qty_to_base($quantity, $saleUnit, $baseUnit, $equivQty);
		$requestedQuantities[$key] = ($requestedQuantities[$key] ?? 0) + $baseQuantity;
		$productMetaByKey[$key] = [
			'base_unit' => $baseUnit,
			'can_convert' => $canConvert,
			'equiv_qty' => $equivQty,
		];
	}

	if (empty($requestedQuantities)) {
		header("Location: " . $server . "?mainmenu=" . 'sell_product_others' . "&sale_error=empty");
		die();
	}

	foreach ($requestedQuantities as $key => $requestedQuantity) {
		$keyParts = explode('|', $key, 2);
		$productId = (int) ($keyParts[0] ?? 0);
		$name = mysqli_real_escape_string($connectDB, $keyParts[1] ?? '');
		$stockResult = $connectDB->query("
			SELECT COALESCE(SUM(QuantityRemaining), 0) AS AvailableStock
			FROM inventory_batches
			WHERE (Product_ID = '$productId' OR ProductName = '$name') AND QuantityRemaining > 0
		");
		$availableStock = 0;
		if ($stockResult && $stockRow = $stockResult->fetch_assoc()) {
			$availableStock = (float) ($stockRow['AvailableStock'] ?? 0);
		}
		if ($requestedQuantity > $availableStock) {
			header("Location: " . $server . "?mainmenu=" . 'sell_product_others' . "&sale_error=stock");
			die();
		}
	}

	$totalSavedSaleAmount = 0;
	$totalAccountPaid = 0;
	$accountDueDates = [];

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
		$rowSalesType = 'OTHERS';
		$typeResult = $connectDB->query("SELECT ProductType FROM products WHERE Product_ID = '$productId' LIMIT 1");
		if ($typeResult && ($typeRow = $typeResult->fetch_assoc())) {
			$productType = strtoupper(trim((string) ($typeRow['ProductType'] ?? '')));
			if ($productType === 'LPG' || stripos($name, 'LPG') !== false || stripos($name, 'GASUL') !== false) {
				$rowSalesType = 'LPG';
			}
		}
		$lpgTransactionType = strtoupper(trim((string) ($lpgTransactionTypes[$index] ?? 'NONE')));
		if ($rowSalesType !== 'LPG' || $lpgTransactionType !== 'SWAPPED') {
			$lpgTransactionType = 'NONE';
		}
		$lpgTankCondition = $lpgTransactionType === 'SWAPPED'
			? trim(mysqli_real_escape_string($connectDB, $lpgTankConditions[$index] ?? ''))
			: '';
		$lpgTankPayment = 0;
		$baseUnit = 'pc';
		$canConvert = 0;
		$equivQty = 0;
		if ($productId > 0) {
			$productMetaResult = $connectDB->query("SELECT ProductBaseUnit, CanConvertToKg, KgEquivalentQty FROM products WHERE Product_ID = '$productId' LIMIT 1");
			if ($productMetaResult && ($productMetaRow = $productMetaResult->fetch_assoc())) {
				$baseUnit = junkshop_normalize_base_unit($productMetaRow['ProductBaseUnit'] ?? 'pc');
				$canConvert = (int) ($productMetaRow['CanConvertToKg'] ?? 0);
				$equivQty = (float) ($productMetaRow['KgEquivalentQty'] ?? 0);
			}
		}
		$saleUnit = junkshop_normalize_base_unit($saleUnits[$index] ?? $baseUnit);
		$kgConversionQty = (float) ($kgConversionQuantities[$index] ?? 0);
		if (!junkshop_can_convert_units($baseUnit, $canConvert, $equivQty)) {
			$saleUnit = $baseUnit;
			$kgConversionQty = 0;
		} elseif ($saleUnit !== $baseUnit) {
			$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit);
			if ($saleUnit !== $alternateUnit) {
				$saleUnit = $baseUnit;
				$kgConversionQty = 0;
			} else {
				$kgConversionQty = $equivQty;
			}
		} else {
			$kgConversionQty = 0;
		}
		$baseQuantity = junkshop_sale_qty_to_base($quantity, $saleUnit, $baseUnit, $kgConversionQty > 0 ? $kgConversionQty : $equivQty);

		if ($name === '' || $quantity <= 0 || $price <= 0) {
			continue;
		}

		$rowPaymentStatus = strtoupper(trim((string) ($paymentStatuses[$index] ?? 'PAID')));
		if (!in_array($rowPaymentStatus, ['PAID', 'PARTIAL', 'UNPAID'], true)) {
			$rowPaymentStatus = 'PAID';
		}
		if ($customerType === 'walk_in') {
			$rowPaymentStatus = 'PAID';
		}
		$rowAmountPaid = (float) ($amountPaids[$index] ?? 0);
		if ($rowPaymentStatus === 'PAID') {
			$rowAmountPaid = $total;
		} elseif ($rowPaymentStatus === 'UNPAID') {
			$rowAmountPaid = 0;
		} else {
			$rowAmountPaid = min(max($rowAmountPaid, 0), $total);
		}
		$rowDueDateValue = trim((string) ($dueDates[$index] ?? ''));
		$rowDueDateSql = $rowDueDateValue !== '' ? "'" . mysqli_real_escape_string($connectDB, $rowDueDateValue) . "'" : "NULL";
		$rowBalance = max($total - $rowAmountPaid, 0);
		if ($rowBalance > 0 && $rowDueDateValue !== '') {
			$accountDueDates[] = $rowDueDateValue;
		}

		$sql = "INSERT INTO sales (SaleDate, DeliveryNo, CustomerName, Product_ID, ProductName, Quantity, SaleUnit, KgConversionQty, UnitPrice, Less, TotalSalePrice, Notes, SalesType, PaymentStatus, AmountPaid, DueDate, LpgTransactionType, LpgTankCondition, LpgTankPayment)
				VALUES ('$saleDate', '$deliveryNo', '$customerName', '$productId', '$name', '$quantity', '$saleUnit', '$kgConversionQty', '$price', '0', '$total', '$saleNotes', '$rowSalesType', '$rowPaymentStatus', '$rowAmountPaid', $rowDueDateSql, '$lpgTransactionType', '$lpgTankCondition', '$lpgTankPayment')";
		if ($connectDB->query($sql)) {
			$saleId = $connectDB->insert_id;
			$totalSavedSaleAmount += $total;
			$totalAccountPaid += $rowAmountPaid;
			$remainingToDeduct = $baseQuantity;
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
	$accountAmountPaid = min($totalAccountPaid, $totalSavedSaleAmount);
	$balance = max($totalSavedSaleAmount - $accountAmountPaid, 0);
	if ($balance <= 0) {
		$accountPaymentStatus = 'PAID';
	} elseif ($accountAmountPaid <= 0) {
		$accountPaymentStatus = 'UNPAID';
	} else {
		$accountPaymentStatus = 'PARTIAL';
	}
	$accountDueDateValue = !empty($accountDueDates) ? max($accountDueDates) : '';
	$accountDueDateSql = $accountDueDateValue !== '' ? "'" . mysqli_real_escape_string($connectDB, $accountDueDateValue) . "'" : "NULL";
	$safeAccountPaid = mysqli_real_escape_string($connectDB, number_format($accountAmountPaid, 2, '.', ''));
	$safeAccountTotal = mysqli_real_escape_string($connectDB, number_format($totalSavedSaleAmount, 2, '.', ''));
	$safeAccountBalance = mysqli_real_escape_string($connectDB, number_format($balance, 2, '.', ''));
	$connectDB->query("
		INSERT INTO customer_accounts (CustomerName, DeliveryNo, SaleDate, DueDate, TotalAmount, AmountPaid, Balance, PaymentStatus)
		VALUES ('$customerName', '$deliveryNo', '$saleDate', $accountDueDateSql, '$safeAccountTotal', '$safeAccountPaid', '$safeAccountBalance', '$accountPaymentStatus')
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
