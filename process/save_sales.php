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

	$requestedSaleLines = [];
	foreach ($productNames as $index => $productName) {
		$name = trim((string) $productName);
		$productId = (int) ($productIds[$index] ?? 0);
		$quantity = (float) ($productQuantities[$index] ?? 0);
		$price = (float) ($productPrices[$index] ?? 0);
		$saleUnit = junkshop_normalize_base_unit($saleUnits[$index] ?? 'pc');
		if ($name === '' || $quantity <= 0 || $price <= 0) {
			continue;
		}
		$baseUnit = 'pc';
		$canConvert = 0;
		$equivQty = 0;
		$alternateSaleUnit = '';
		if ($productId > 0) {
			$productMetaResult = $connectDB->query("SELECT ProductBaseUnit, CanConvertToKg, KgEquivalentQty, AlternateSaleUnit FROM products WHERE Product_ID = '$productId' LIMIT 1");
			if ($productMetaResult && ($productMetaRow = $productMetaResult->fetch_assoc())) {
				$baseUnit = junkshop_normalize_base_unit($productMetaRow['ProductBaseUnit'] ?? 'pc');
				$canConvert = (int) ($productMetaRow['CanConvertToKg'] ?? 0);
				$equivQty = (float) ($productMetaRow['KgEquivalentQty'] ?? 0);
				$alternateSaleUnit = trim((string) ($productMetaRow['AlternateSaleUnit'] ?? ''));
			}
		}
		$requestedSaleLines[] = [
			'product_id' => $productId,
			'product_name' => $name,
			'quantity' => $quantity,
			'sale_unit' => $saleUnit,
			'base_unit' => $baseUnit,
			'can_convert' => $canConvert,
			'equiv_qty' => $equivQty,
			'alternate_sale_unit' => $alternateSaleUnit,
		];
	}

	if (empty($requestedSaleLines)) {
		header("Location: " . $server . "?mainmenu=" . 'sell_product_others' . "&sale_error=empty");
		die();
	}

	$stockAvailability = junkshop_simulate_sale_stock_availability($connectDB, $requestedSaleLines);
	if (!$stockAvailability['success']) {
		header("Location: " . $server . "?mainmenu=" . 'sell_product_others' . "&sale_error=stock");
		die();
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
		$lpgTransactionType = strtoupper(trim((string) ($lpgTransactionTypes[$index] ?? 'SWAPPED')));
		if ($rowSalesType !== 'LPG') {
			$lpgTransactionType = 'NONE';
			$lpgTankCondition = '';
		} else {
			if (!in_array($lpgTransactionType, ['SOLD', 'LENT', 'SWAPPED'], true)) {
				$lpgTransactionType = 'SWAPPED';
			}
			if ($lpgTransactionType === 'LENT' && junkshop_is_walk_in_customer($customerName)) {
				header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode('Lent LPG tanks require a registered customer.'));
				die();
			}
			if ($lpgTransactionType === 'SWAPPED' || $lpgTransactionType === 'LENT') {
				$lpgTankCondition = junkshop_lpg_normalize_tank_condition($lpgTankConditions[$index] ?? '');
				if ($lpgTankCondition === '') {
					$conditionMessage = $lpgTransactionType === 'LENT'
						? 'Select lent tank condition (New or Old).'
						: 'Select empty tank condition (New or Old).';
					header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode($conditionMessage));
					die();
				}
				$lpgTankCondition = mysqli_real_escape_string($connectDB, $lpgTankCondition);
			} else {
				$lpgTankCondition = '';
			}
		}
		$lpgTankPayment = 0;
		$baseUnit = 'pc';
		$canConvert = 0;
		$equivQty = 0;
		$alternateSaleUnit = '';
		if ($productId > 0) {
			$productMetaResult = $connectDB->query("SELECT ProductBaseUnit, CanConvertToKg, KgEquivalentQty, AlternateSaleUnit FROM products WHERE Product_ID = '$productId' LIMIT 1");
			if ($productMetaResult && ($productMetaRow = $productMetaResult->fetch_assoc())) {
				$baseUnit = junkshop_normalize_base_unit($productMetaRow['ProductBaseUnit'] ?? 'pc');
				$canConvert = (int) ($productMetaRow['CanConvertToKg'] ?? 0);
				$equivQty = (float) ($productMetaRow['KgEquivalentQty'] ?? 0);
				$alternateSaleUnit = trim((string) ($productMetaRow['AlternateSaleUnit'] ?? ''));
			}
		}
		$saleUnit = junkshop_normalize_base_unit($saleUnits[$index] ?? $baseUnit);
		$kgConversionQty = (float) ($kgConversionQuantities[$index] ?? 0);
		if (!junkshop_can_convert_units($baseUnit, $canConvert, $equivQty, $alternateSaleUnit)) {
			$saleUnit = $baseUnit;
			$kgConversionQty = 0;
		} elseif ($saleUnit !== $baseUnit) {
			$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit);
			if ($saleUnit !== $alternateUnit) {
				$saleUnit = $baseUnit;
				$kgConversionQty = 0;
			} else {
				$kgConversionQty = $equivQty;
			}
		} else {
			$kgConversionQty = 0;
		}
		$baseQuantity = junkshop_sale_qty_to_base($quantity, $saleUnit, $baseUnit, $kgConversionQty > 0 ? $kgConversionQty : $equivQty, $alternateSaleUnit);

		if ($name === '' || $quantity <= 0 || $price <= 0) {
			continue;
		}

		$stockCheckLines = [[
			'product_id' => $productId,
			'product_name' => trim((string) $productName),
			'quantity' => $quantity,
			'sale_unit' => $saleUnit,
			'base_unit' => $baseUnit,
			'can_convert' => $canConvert,
			'equiv_qty' => $kgConversionQty > 0 ? $kgConversionQty : $equivQty,
			'alternate_sale_unit' => $alternateSaleUnit,
		]];
		$lineStockCheck = junkshop_simulate_sale_stock_availability($connectDB, $stockCheckLines);
		if (!$lineStockCheck['success']) {
			header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode('Not enough inventory stock for ' . trim((string) $productName) . '.'));
			die();
		}
		if ($rowSalesType === 'LPG' && $lpgTransactionType === 'SOLD') {
			$tankProductId = junkshop_get_lpg_tank_product_id($connectDB, $productId);
			if ($tankProductId <= 0) {
				$tankProductId = junkshop_ensure_lpg_tank_product($connectDB, $productId, trim((string) $productName), 'tank', 1);
			}
			if ($tankProductId <= 0) {
				header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode('Tank product is missing for this LPG item. Check All Products.'));
				die();
			}
			$tankStock = junkshop_get_inventory_stock($connectDB, $tankProductId);
			if ($tankStock + 0.009 < $baseQuantity) {
				header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode('Not enough tank stock for New Tank with LPG sale. Available tanks: ' . number_format($tankStock, 2)));
				die();
			}
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
			$linePurchaseCost = 0.0;
			$fifoResult = junkshop_deduct_inventory_for_sale(
				$connectDB,
				$productId,
				$name,
				$quantity,
				$saleUnit,
				$baseUnit,
				$canConvert,
				$kgConversionQty > 0 ? $kgConversionQty : $equivQty,
				$alternateSaleUnit,
				$saleDate,
				$saleId,
				$deliveryNo,
				false
			);
			$linePurchaseCost = $fifoResult['cost'];
			if ($fifoResult['remaining'] > 0.009) {
				header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode('Not enough inventory stock for ' . trim((string) $productName) . '.'));
				die();
			}
			if ($rowSalesType === 'LPG' && $lpgTransactionType === 'SOLD') {
				$tankProductId = junkshop_get_lpg_tank_product_id($connectDB, $productId);
				$tankProductName = junkshop_lpg_tank_product_name(trim((string) $productName));
				$tankFifoResult = junkshop_deduct_inventory_fifo(
					$connectDB,
					$tankProductId,
					$tankProductName,
					$baseQuantity,
					$saleDate,
					$saleId,
					$deliveryNo,
					false
				);
				$linePurchaseCost += $tankFifoResult['cost'];
				if ($tankFifoResult['remaining'] > 0.009) {
					header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode('Not enough tank stock for New Tank with LPG sale.'));
					die();
				}
			}
			$lineGrossProfit = round($total - $linePurchaseCost, 2);
			$safeLinePurchaseCost = mysqli_real_escape_string($connectDB, number_format($linePurchaseCost, 2, '.', ''));
			$safeLineGrossProfit = mysqli_real_escape_string($connectDB, number_format($lineGrossProfit, 2, '.', ''));
			$connectDB->query("UPDATE sales SET TotalPurchaseCost = '$safeLinePurchaseCost', GrossProfit = '$safeLineGrossProfit' WHERE ID = '$saleId'");
			if ($rowSalesType === 'LPG' && $lpgTransactionType !== 'NONE') {
				$lpgResult = junkshop_lpg_apply_sale(
					$connectDB,
					$productId,
					$name,
					$baseQuantity,
					$lpgTransactionType,
					$lpgTankCondition,
					$customerName,
					$deliveryNo,
					$saleId,
					$saleDate
				);
				if (!$lpgResult['success']) {
					header('Location: ' . $server . '?mainmenu=sell_product_others&sale_error=' . urlencode($lpgResult['message']));
					die();
				}
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
	if (!junkshop_is_walk_in_customer($customerName)) {
		$connectDB->query("
			INSERT INTO customers (CustomerName, Address, GoogleMap)
			VALUES ('$customerName', '$customerAddress', '$customerGoogleMap')
			ON DUPLICATE KEY UPDATE
				Address = CASE WHEN VALUES(Address) <> '' THEN VALUES(Address) ELSE Address END,
				GoogleMap = CASE WHEN VALUES(GoogleMap) <> '' THEN VALUES(GoogleMap) ELSE GoogleMap END
		");
	}

	header("Location: " . $server . "?mainmenu=sales_list");
	die();
?>
