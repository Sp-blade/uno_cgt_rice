<?php
	$previousMenu = $_POST['previousmenu'];
	$productNames = isset($_POST['product_name']) ? $_POST['product_name'] : [];
	$productIds = isset($_POST['product_id']) ? $_POST['product_id'] : [];
	$productQuantities = isset($_POST['product_quantity']) ? $_POST['product_quantity'] : [];
	$productPrices = isset($_POST['product_price']) ? $_POST['product_price'] : [];
	$totalPrices = isset($_POST['total_price']) ? $_POST['total_price'] : [];
	$grandTotal = $_POST['grandTotal'];
	$purchaseDate = junkshop_normalize_datetime($_POST['purchase_date'] ?? '');
	$transactionType = isset($_POST['saveTransaction']) ? $_POST['saveTransaction'] : '';
	$lpgStockInTypes = isset($_POST['lpg_stock_in_type']) ? $_POST['lpg_stock_in_type'] : [];
	$lpgEmptyTankConditions = isset($_POST['lpg_empty_tank_condition']) ? $_POST['lpg_empty_tank_condition'] : [];
	$lpgTankPurchasePrices = isset($_POST['lpg_tank_purchase_price']) ? $_POST['lpg_tank_purchase_price'] : [];

	// Ensure that product-related data is arrays
	if (!is_array($productNames) || !is_array($productQuantities) || !is_array($productPrices) || !is_array($totalPrices)) {
		$productNames = unserialize($_POST['product_name']);
		$productIds = isset($_POST['product_id']) ? unserialize($_POST['product_id']) : [];
		$productQuantities = unserialize($_POST['product_quantity']);
		$productPrices = unserialize($_POST['product_price']);
		$totalPrices = unserialize($_POST['total_price']);
		$lpgStockInTypes = isset($_POST['lpg_stock_in_type']) ? unserialize($_POST['lpg_stock_in_type']) : [];
		$lpgEmptyTankConditions = isset($_POST['lpg_empty_tank_condition']) ? unserialize($_POST['lpg_empty_tank_condition']) : [];
		$lpgTankPurchasePrices = isset($_POST['lpg_tank_purchase_price']) ? unserialize($_POST['lpg_tank_purchase_price']) : [];
	}

	$purchasesHasProductId = false;
	$checkProductIdColumn = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'Product_ID'");
	if ($checkProductIdColumn && $checkProductIdColumn->num_rows > 0) {
		$purchasesHasProductId = true;
	}

	
	switch($previousMenu){
			
		case "purchase_product":
			$sql_getInvoice = "SELECT InvoiceNo FROM purchases ORDER BY InvoiceNo DESC LIMIT 1";
			$getInvoice = $connectDB->query($sql_getInvoice);
			
			if ($getInvoice->num_rows > 0) {
				$invoices = $getInvoice->fetch_assoc();
				$invoice = $invoices['InvoiceNo'];  // Get the last invoice number
				$invoice++;
			} else {
				$invoice = 1;
			}
		break;
		case "view_invoice":
			$invoice = $_POST['invoiceNo'];
		break;
	}
	
	if(array_key_exists('saveTransaction', $_POST)) {
            saveTransaction($connectDB,$server,$invoice,$previousMenu,$productNames,$productIds,$productQuantities,$productPrices,$purchaseDate,$totalPrices,$transactionType,$purchasesHasProductId,$lpgStockInTypes,$lpgEmptyTankConditions,$lpgTankPurchasePrices);
        }
	
	function saveTransaction($connectDB,$server,$invoice,$previousMenu,$productNames,$productIds,$productQuantities,$productPrices,$purchaseDate,$totalPrices,$transactionType,$purchasesHasProductId,$lpgStockInTypes,$lpgEmptyTankConditions,$lpgTankPurchasePrices){
		$totalInsertedPurchaseAmount = 0;
		foreach ($productNames as $index => $productName) {
			$productId = isset($productIds[$index]) ? (int) $productIds[$index] : 0;
			$productQuantity = isset($productQuantities[$index]) ? $productQuantities[$index] : 0;
			$productPrice = isset($productPrices[$index]) ? $productPrices[$index] : 0;
			$totalPrice = isset($totalPrices[$index]) ? $totalPrices[$index] : 0;
			$lpgStockInType = 'NONE';
			$lpgEmptyTankCondition = '';
			$lpgTankPurchasePrice = 0.0;
			$inventoryUnitCost = (float) $productPrice;
			$inventoryTankUnitCost = 0.0;
			if (junkshop_product_is_lpg($connectDB, $productId, $productName)) {
				$lpgStockInType = strtoupper(trim((string) ($lpgStockInTypes[$index] ?? 'FULL_TANK')));
				if (!in_array($lpgStockInType, ['FULL_TANK', 'REFILL'], true)) {
					$lpgStockInType = 'FULL_TANK';
				}
				$lpgEmptyTankCondition = junkshop_lpg_normalize_tank_condition($lpgEmptyTankConditions[$index] ?? '');
				if ($lpgStockInType === 'REFILL') {
					if ($lpgEmptyTankCondition === '') {
						header('Location: ' . $server . '?mainmenu=purchase_product&purchase_status=lpg_error&purchase_error=' . urlencode('Select which empty tank condition was used for the refill.'));
						die();
					}
					if (junkshop_lpg_get_empty_balance($connectDB, $productId, $lpgEmptyTankCondition) + 0.009 < (float) $productQuantity) {
						header('Location: ' . $server . '?mainmenu=purchase_product&purchase_status=lpg_error&purchase_error=' . urlencode('Not enough empty ' . strtolower($lpgEmptyTankCondition) . ' tanks available for refill.'));
						die();
					}
				} elseif ($lpgStockInType === 'FULL_TANK') {
					$lpgTankPurchasePrice = round((float) ($lpgTankPurchasePrices[$index] ?? 0), 2);
					if ($lpgTankPurchasePrice <= 0) {
						header('Location: ' . $server . '?mainmenu=purchase_product&purchase_status=lpg_error&purchase_error=' . urlencode('Enter the tank purchase price for new tank stock in.'));
						die();
					}
					$inventoryUnitCost = round((float) $productPrice, 2);
					$inventoryTankUnitCost = $lpgTankPurchasePrice;
					$productPrice = $inventoryUnitCost;
					$totalPrice = round((float) $productQuantity * ($inventoryUnitCost + $inventoryTankUnitCost), 2);
				}
			}

			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			$safeLpgStockInType = mysqli_real_escape_string($connectDB, $lpgStockInType);
			$safeLpgEmptyTankCondition = mysqli_real_escape_string($connectDB, $lpgEmptyTankCondition);
			$safeLpgTankPurchasePrice = mysqli_real_escape_string($connectDB, number_format($lpgTankPurchasePrice, 2, '.', ''));

			// Prepare the SQL query to insert the product into the 'purchases' table
			if ($purchasesHasProductId) {
				$sql_purchaseProduct = "INSERT INTO purchases (Product_ID, PurchaseDate, InvoiceNo, ProductName, Quantity, ProductPrice, TotalPurchasePrice, LpgStockInType, LpgEmptyTankCondition, LpgTankPurchasePrice) VALUES ('$productId', '$purchaseDate', '$invoice', '$safeProductName', '$productQuantity', '$productPrice', '$totalPrice', '$safeLpgStockInType', '$safeLpgEmptyTankCondition', '$safeLpgTankPurchasePrice')";
			} else {
				$sql_purchaseProduct = "INSERT INTO purchases VALUES (null,'$purchaseDate', '$invoice', '$safeProductName', '$productQuantity', '$productPrice', '$totalPrice')";
			}

			// Execute the query
			if ($connectDB->query($sql_purchaseProduct)) {
				// Save succeeded; redirect happens after all rows are inserted.
				$totalInsertedPurchaseAmount += (float) $totalPrice;
				junkshop_record_purchase_inventory(
					$connectDB,
					$productId,
					$productName,
					$purchaseDate,
					$invoice,
					(float) $productQuantity,
					$inventoryUnitCost,
					0
				);
				if ($lpgStockInType === 'FULL_TANK' && $inventoryTankUnitCost > 0) {
					$tankProductId = junkshop_ensure_lpg_tank_product($connectDB, $productId, $productName, 'tank', 1);
					if ($tankProductId <= 0) {
						echo 'Error: Unable to create tank product for LPG stock in.';
						die();
					}
					$tankProductName = junkshop_lpg_tank_product_name($productName);
					if (!junkshop_record_purchase_inventory(
						$connectDB,
						$tankProductId,
						$tankProductName,
						$purchaseDate,
						$invoice,
						(float) $productQuantity,
						$inventoryTankUnitCost,
						0
					)) {
						echo 'Error: Tank inventory was not updated.';
						die();
					}
				}
				if (junkshop_product_is_lpg($connectDB, $productId, $productName)) {
					junkshop_lpg_apply_purchase($connectDB, $productId, $productName, (float) $productQuantity, $lpgStockInType, $lpgEmptyTankCondition);
				}
			} else {
				// If there is an error, output the error
				echo 'Error in query: ' . $connectDB->error;
			}
		}

		if($transactionType == "Edit"){
		
			header("Location: " . $server . "?mainmenu=view_invoice&invoiceNo=" . $invoice );
			die();
			
		}
		else
		{
			$redirectUrl = $server . "?mainmenu=" . $previousMenu;
			if ($previousMenu === 'purchase_product') {
				$redirectUrl .= '&purchase_status=saved';
			}
			header("Location: " . $redirectUrl);
			die();
		}
		
	}
?>
