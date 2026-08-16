<?php
	date_default_timezone_set('Asia/Manila');

	$hosts = array("127.0.0.1", "localhost");
	$user = "root";
	$pass = "";
	$db = "unocgtricedb";
	
	$connectDB = null;
	$lastConnectionError = "";
	foreach ($hosts as $host) {
		$connectDB = @new mysqli($host, $user, $pass);
		if (!$connectDB->connect_error) {
			break;
		}
		$lastConnectionError = $connectDB->connect_error;
		$connectDB = null;
	}
	if (!$connectDB){
		die("Database connection failed. Please check XAMPP MySQL/MariaDB user access. Last error: " . $lastConnectionError);
	}
	$connectDB->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
	$connectDB->select_db($db);
	$connectDB->query("SET time_zone = '+08:00'");

	if (!function_exists('junkshop_normalize_datetime')) {
		function junkshop_normalize_datetime($value) {
			$value = trim((string) $value);
			if ($value === '') {
				return date('Y-m-d H:i:s');
			}

			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				return $value . ' ' . date('H:i:s');
			}

			$value = str_replace('T', ' ', $value);
			$timestamp = strtotime($value);
			if ($timestamp === false) {
				return date('Y-m-d H:i:s');
			}

			return date('Y-m-d H:i:s', $timestamp);
		}
	}

	if (!function_exists('junkshop_format_datetime')) {
		function junkshop_format_datetime($value) {
			$timestamp = strtotime((string) $value);
			if ($timestamp === false) {
				return '';
			}

			return date('M-d-Y h:i A', $timestamp);
		}
	}

	if (!function_exists('junkshop_datetime_input_value')) {
		function junkshop_datetime_input_value($value) {
			$timestamp = strtotime((string) $value);
			if ($timestamp === false) {
				$timestamp = time();
			}

			return date('Y-m-d\TH:i', $timestamp);
		}
	}

	if (!function_exists('junkshop_normalize_base_unit')) {
		function junkshop_normalize_base_unit($value) {
			$unit = strtolower(trim((string) $value));
			$allowed = ['pc', 'kg', 'sack', 'tray', 'pack', 'tank'];
			return in_array($unit, $allowed, true) ? $unit : 'pc';
		}
	}

	if (!function_exists('junkshop_unit_label')) {
		function junkshop_unit_label($unit) {
			$labels = [
				'pc' => 'Pcs',
				'kg' => 'KG',
				'sack' => 'Sack',
				'tray' => 'Tray',
				'pack' => 'Pack',
				'tank' => 'Tank',
			];
			$normalized = junkshop_normalize_base_unit($unit);
			return $labels[$normalized] ?? strtoupper($normalized);
		}
	}

	if (!function_exists('junkshop_base_unit_options')) {
		function junkshop_base_unit_options() {
			return ['pc', 'kg', 'sack', 'tray', 'pack', 'tank'];
		}
	}

	if (!function_exists('junkshop_get_alternate_sale_unit')) {
		function junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit = '') {
			$baseUnit = junkshop_normalize_base_unit($baseUnit);
			$alternateUnit = junkshop_normalize_base_unit($alternateSaleUnit);
			if ($alternateUnit === '' || $alternateUnit === $baseUnit) {
				return null;
			}

			return $alternateUnit;
		}
	}

	if (!function_exists('junkshop_uses_legacy_pc_kg_conversion')) {
		function junkshop_uses_legacy_pc_kg_conversion($baseUnit, $alternateUnit) {
			return junkshop_normalize_base_unit($baseUnit) === 'pc'
				&& junkshop_normalize_base_unit($alternateUnit) === 'kg';
		}
	}

	if (!function_exists('junkshop_can_convert_units')) {
		function junkshop_can_convert_units($baseUnit, $canConvert, $equivQty, $alternateSaleUnit = '') {
			return (int) $canConvert === 1
				&& (float) $equivQty > 0
				&& junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit) !== null;
		}
	}

	if (!function_exists('junkshop_sale_qty_to_base')) {
		function junkshop_sale_qty_to_base($quantity, $saleUnit, $baseUnit, $equivQty, $alternateSaleUnit = '') {
			$quantity = (float) $quantity;
			$baseUnit = junkshop_normalize_base_unit($baseUnit);
			$saleUnit = junkshop_normalize_base_unit($saleUnit);
			$equivQty = (float) $equivQty;
			$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit);

			if ($saleUnit === $baseUnit || $equivQty <= 0 || $alternateUnit === null) {
				return $quantity;
			}

			if ($saleUnit !== $alternateUnit) {
				return $quantity;
			}

			if (junkshop_uses_legacy_pc_kg_conversion($baseUnit, $alternateUnit)) {
				return $quantity * $equivQty;
			}

			return $quantity / $equivQty;
		}
	}

	if (!function_exists('junkshop_base_qty_to_alternate')) {
		function junkshop_base_qty_to_alternate($quantity, $baseUnit, $equivQty, $alternateSaleUnit = '') {
			$quantity = (float) $quantity;
			$baseUnit = junkshop_normalize_base_unit($baseUnit);
			$equivQty = (float) $equivQty;
			$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit);

			if ($equivQty <= 0 || $alternateUnit === null || !junkshop_can_convert_units($baseUnit, 1, $equivQty, $alternateSaleUnit)) {
				return null;
			}

			if (junkshop_uses_legacy_pc_kg_conversion($baseUnit, $alternateUnit)) {
				return $quantity / $equivQty;
			}

			return $quantity * $equivQty;
		}
	}

	if (!function_exists('junkshop_conversion_label')) {
		function junkshop_conversion_label($baseUnit, $equivQty, $alternateSaleUnit = '') {
			$baseUnit = junkshop_normalize_base_unit($baseUnit);
			$equivQty = (float) $equivQty;
			if ($equivQty <= 0) {
				return '';
			}

			$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit);
			if ($alternateUnit === null) {
				return '';
			}

			if (junkshop_uses_legacy_pc_kg_conversion($baseUnit, $alternateUnit)) {
				return '1 ' . junkshop_unit_label($alternateUnit) . ' = ' . number_format($equivQty, 2) . ' ' . junkshop_unit_label($baseUnit);
			}

			return '1 ' . junkshop_unit_label($baseUnit) . ' = ' . number_format($equivQty, 2) . ' ' . junkshop_unit_label($alternateUnit);
		}
	}

	if (!function_exists('junkshop_walk_in_customer_name')) {
		function junkshop_walk_in_customer_name() {
			return 'Walk-in Customer';
		}
	}

	if (!function_exists('junkshop_is_walk_in_customer')) {
		function junkshop_is_walk_in_customer($customerName) {
			return strcasecmp(trim((string) $customerName), junkshop_walk_in_customer_name()) === 0;
		}
	}

	if (!function_exists('junkshop_normalize_customers_sort')) {
		function junkshop_normalize_customers_sort($sort) {
			$sort = trim((string) $sort);
			return in_array($sort, ['due_date', 'name', 'balance'], true) ? $sort : 'due_date';
		}
	}

	if (!function_exists('junkshop_customers_redirect_suffix')) {
		function junkshop_customers_redirect_suffix($searchCustomer = '', $sortCustomer = 'due_date') {
			$parts = [];
			$searchCustomer = trim((string) $searchCustomer);
			$sortCustomer = junkshop_normalize_customers_sort($sortCustomer);
			if ($searchCustomer !== '') {
				$parts[] = 'searchCustomer=' . urlencode($searchCustomer);
			}
			if ($sortCustomer !== 'due_date') {
				$parts[] = 'sortCustomer=' . urlencode($sortCustomer);
			}
			return $parts ? '&' . implode('&', $parts) : '';
		}
	}

	if (!function_exists('junkshop_customers_order_by_sql')) {
		function junkshop_customers_order_by_sql($sortCustomer = 'due_date') {
			switch (junkshop_normalize_customers_sort($sortCustomer)) {
				case 'name':
					return 'c.CustomerName ASC';
				case 'balance':
					return 'Balance DESC, c.CustomerName ASC';
				case 'due_date':
				default:
					return "CASE WHEN MAX(a.DueDate) IS NULL OR MAX(a.DueDate) = '0000-00-00' THEN 1 ELSE 0 END ASC, MAX(a.DueDate) ASC, c.CustomerName ASC";
			}
		}
	}

	if (!function_exists('junkshop_normalize_inventory_sort')) {
		function junkshop_normalize_inventory_sort($sort) {
			$sort = trim((string) $sort);
			return in_array($sort, ['available_units', 'name', 'category'], true) ? $sort : 'available_units';
		}
	}

	if (!function_exists('junkshop_inventory_status_label')) {
		function junkshop_inventory_status_label($totalStock, $stockLimit, $activeBatchCount) {
			$totalStock = round((float) $totalStock, 2);
			$stockLimit = round((float) $stockLimit, 2);
			$activeBatchCount = (int) $activeBatchCount;
			if ($stockLimit > 0 && $totalStock <= $stockLimit) {
				return 'Stock limit reached';
			}
			if ($totalStock <= 0) {
				return 'Out of stock';
			}
			if ($activeBatchCount > 1) {
				return $activeBatchCount . ' FIFO batches';
			}
			return 'In stock';
		}
	}

	if (!function_exists('junkshop_lpg_inventory_status_label')) {
		function junkshop_lpg_inventory_status_label($filledStock) {
			return round((float) $filledStock, 2) <= 0 ? 'Out of stock' : 'In stock';
		}
	}

	if (!function_exists('junkshop_inventory_matches_search')) {
		function junkshop_inventory_matches_search(array $row, $search) {
			$search = trim((string) $search);
			if ($search === '') {
				return true;
			}
			$needle = strtolower($search);
			$fields = [
				(string) ($row['product_name'] ?? ''),
				(string) ($row['category'] ?? ''),
				(string) ($row['status_label'] ?? ''),
			];
			foreach ($fields as $field) {
				if ($field !== '' && strpos(strtolower($field), $needle) !== false) {
					return true;
				}
			}
			return false;
		}
	}

	if (!function_exists('junkshop_sort_inventory_rows')) {
		function junkshop_sort_inventory_rows(array $rows, $sort) {
			$sort = junkshop_normalize_inventory_sort($sort);
			usort($rows, function ($left, $right) use ($sort) {
				switch ($sort) {
					case 'name':
						$compare = strcasecmp((string) ($left['product_name'] ?? ''), (string) ($right['product_name'] ?? ''));
						break;
					case 'category':
						$compare = strcasecmp((string) ($left['category'] ?? ''), (string) ($right['category'] ?? ''));
						if ($compare === 0) {
							$compare = strcasecmp((string) ($left['product_name'] ?? ''), (string) ($right['product_name'] ?? ''));
						}
						break;
					case 'available_units':
					default:
						$compare = ((float) ($left['available_units'] ?? 0) <=> (float) ($right['available_units'] ?? 0));
						if ($compare === 0) {
							$compare = strcasecmp((string) ($left['product_name'] ?? ''), (string) ($right['product_name'] ?? ''));
						}
						break;
				}
				return $compare;
			});
			return $rows;
		}
	}

	if (!function_exists('junkshop_get_last_purchase_price')) {
		function junkshop_get_last_purchase_price($connectDB, $productId, $productName = '') {
			$productId = (int) $productId;
			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));

			if ($productId > 0) {
				$result = $connectDB->query("
					SELECT ProductPrice
					FROM purchases
					WHERE Product_ID = '$productId'
					ORDER BY PurchaseDate DESC, ID DESC
					LIMIT 1
				");
			} elseif ($safeProductName !== '') {
				$result = $connectDB->query("
					SELECT ProductPrice
					FROM purchases
					WHERE ProductName = '$safeProductName'
					ORDER BY PurchaseDate DESC, ID DESC
					LIMIT 1
				");
			} else {
				return 0.0;
			}

			if ($result && $result->num_rows > 0) {
				return round((float) ($result->fetch_assoc()['ProductPrice'] ?? 0), 2);
			}

			return 0.0;
		}
	}

	if (!function_exists('junkshop_prices_match')) {
		function junkshop_prices_match($left, $right) {
			return abs(round((float) $left, 2) - round((float) $right, 2)) < 0.01;
		}
	}

	if (!function_exists('junkshop_resolve_inventory_batch_label')) {
		function junkshop_resolve_inventory_batch_label($connectDB, $productId, $productName, $newUnitCost) {
			$productId = (int) $productId;
			$newUnitCost = round((float) $newUnitCost, 2);
			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			$safeUnitCost = mysqli_real_escape_string($connectDB, number_format($newUnitCost, 2, '.', ''));

			$whereClause = $productId > 0
				? "Product_ID = '$productId'"
				: "ProductName = '$safeProductName'";

			$result = $connectDB->query("
				SELECT ID, UnitCost
				FROM inventory_batches
				WHERE ($whereClause) AND QuantityRemaining > 0
			");

			if (!$result || $result->num_rows === 0) {
				return 'OLD';
			}

			$hasOtherPrices = false;
			while ($row = $result->fetch_assoc()) {
				if (!junkshop_prices_match($row['UnitCost'] ?? 0, $newUnitCost)) {
					$hasOtherPrices = true;
					break;
				}
			}

			if (!$hasOtherPrices) {
				return 'OLD';
			}

			$connectDB->query("
				UPDATE inventory_batches
				SET BatchLabel = 'OLD'
				WHERE ($whereClause)
					AND QuantityRemaining > 0
					AND ABS(UnitCost - $safeUnitCost) >= 0.01
			");
			$connectDB->query("
				UPDATE inventory_batches
				SET BatchLabel = 'NEW'
				WHERE ($whereClause)
					AND QuantityRemaining > 0
					AND ABS(UnitCost - $safeUnitCost) < 0.01
			");

			return 'NEW';
		}
	}

	if (!function_exists('junkshop_fifo_batch_order_sql')) {
		function junkshop_fifo_batch_order_sql($alias = '') {
			$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
			return "ORDER BY {$prefix}BatchDate ASC, {$prefix}ID ASC";
		}
	}

	if (!function_exists('junkshop_get_batch_fifo_status')) {
		function junkshop_get_batch_fifo_status($batch, $isCurrentConsumingBatch) {
			$remaining = round((float) ($batch['QuantityRemaining'] ?? 0), 2);
			if ($remaining <= 0) {
				return 'Depleted';
			}
			if ($isCurrentConsumingBatch) {
				return 'Consuming';
			}
			return 'Queued';
		}
	}

	if (!function_exists('junkshop_get_purchase_row')) {
		function junkshop_get_purchase_row($connectDB, $purchaseId) {
			$purchaseId = (int) $purchaseId;
			if ($purchaseId <= 0) {
				return null;
			}

			$result = $connectDB->query("SELECT * FROM purchases WHERE ID = '$purchaseId' LIMIT 1");
			return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
		}
	}

	if (!function_exists('junkshop_purchase_product_clause')) {
		function junkshop_purchase_product_clause($connectDB, $productId, $productName) {
			$productId = (int) $productId;
			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			if ($productId > 0) {
				return "Product_ID = '$productId'";
			}

			return $safeProductName !== '' ? "ProductName = '$safeProductName'" : '1 = 0';
		}
	}

	if (!function_exists('junkshop_inventory_product_clause')) {
		function junkshop_inventory_product_clause($connectDB, $productId, $productName) {
			return junkshop_purchase_product_clause($connectDB, $productId, $productName);
		}
	}

	if (!function_exists('junkshop_sync_product_current_name')) {
		function junkshop_sync_product_current_name($connectDB, $productId, $productName) {
			$productId = (int) $productId;
			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			if ($productId <= 0 || $safeProductName === '') {
				return;
			}

			$incomeHasProductId = false;
			$incomeProductIdCheck = $connectDB->query("SHOW COLUMNS FROM income LIKE 'Product_ID'");
			if ($incomeProductIdCheck && $incomeProductIdCheck->num_rows > 0) {
				$incomeHasProductId = true;
			}

			if ($incomeHasProductId) {
				$connectDB->query("UPDATE income SET ProductName = '$safeProductName' WHERE Product_ID = '$productId'");
			} else {
				$connectDB->query("UPDATE income i INNER JOIN products p ON p.Product_ID = '$productId' SET i.ProductName = '$safeProductName' WHERE i.ProductName = p.ProductName");
			}

			$connectDB->query("UPDATE lpg_tank_balances SET ProductName = '$safeProductName' WHERE Product_ID = '$productId'");
		}
	}

	if (!function_exists('junkshop_backfill_product_ids')) {
		function junkshop_backfill_product_ids($connectDB) {
			$tables = ['purchases', 'sales', 'inventory_batches', 'inventory_movements', 'product_returns', 'lpg_tank_balances', 'lpg_tank_loans'];
			foreach ($tables as $tableName) {
				$tableCheck = $connectDB->query("SHOW TABLES LIKE '$tableName'");
				if (!$tableCheck || $tableCheck->num_rows === 0) {
					continue;
				}
				$columnCheck = $connectDB->query("SHOW COLUMNS FROM `$tableName` LIKE 'Product_ID'");
				if (!$columnCheck || $columnCheck->num_rows === 0) {
					continue;
				}
				$connectDB->query("
					UPDATE `$tableName` t
					INNER JOIN products p ON p.ProductName = t.ProductName
					SET t.Product_ID = p.Product_ID
					WHERE (t.Product_ID IS NULL OR t.Product_ID = 0) AND TRIM(t.ProductName) <> ''
				");
			}

			$incomeProductIdCheck = $connectDB->query("SHOW COLUMNS FROM income LIKE 'Product_ID'");
			if ($incomeProductIdCheck && $incomeProductIdCheck->num_rows > 0) {
				$connectDB->query("
					UPDATE income i
					INNER JOIN products p ON p.ProductName = i.ProductName
					SET i.Product_ID = p.Product_ID
					WHERE (i.Product_ID IS NULL OR i.Product_ID = 0) AND TRIM(i.ProductName) <> ''
				");
			}
		}
	}

	if (!function_exists('junkshop_find_inventory_batches_for_purchase')) {
		function junkshop_find_inventory_batches_for_purchase($connectDB, array $purchaseRow) {
			$invoiceNo = (int) ($purchaseRow['InvoiceNo'] ?? 0);
			$productId = (int) ($purchaseRow['Product_ID'] ?? 0);
			$productName = trim((string) ($purchaseRow['ProductName'] ?? ''));
			$sourceReference = mysqli_real_escape_string($connectDB, 'Invoice#' . $invoiceNo);
			$productClause = junkshop_purchase_product_clause($connectDB, $productId, $productName);
			$batches = [];

			$result = $connectDB->query("
				SELECT *
				FROM inventory_batches
				WHERE SourceType = 'PURCHASE'
					AND SourceReference = '$sourceReference'
					AND ($productClause)
				ORDER BY ID ASC
			");
			if ($result && $result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$batches[] = $row;
				}
			}

			return $batches;
		}
	}

	if (!function_exists('junkshop_get_batch_sold_quantity')) {
		function junkshop_get_batch_sold_quantity(array $batch) {
			$quantityIn = round((float) ($batch['QuantityIn'] ?? 0), 2);
			$quantityRemaining = round((float) ($batch['QuantityRemaining'] ?? 0), 2);

			return round(max($quantityIn - $quantityRemaining, 0), 2);
		}
	}

	if (!function_exists('junkshop_match_purchase_inventory_batch')) {
		function junkshop_match_purchase_inventory_batch($connectDB, array $purchaseRow) {
			$purchaseId = (int) ($purchaseRow['ID'] ?? 0);
			$invoiceNo = (int) ($purchaseRow['InvoiceNo'] ?? 0);
			$quantity = round((float) ($purchaseRow['Quantity'] ?? 0), 2);
			$unitCost = round((float) ($purchaseRow['ProductPrice'] ?? 0), 2);
			$batches = junkshop_find_inventory_batches_for_purchase($connectDB, $purchaseRow);
			$matchingBatches = [];

			foreach ($batches as $batch) {
				$batchQuantityIn = round((float) ($batch['QuantityIn'] ?? 0), 2);
				$batchUnitCost = round((float) ($batch['UnitCost'] ?? 0), 2);
				if (abs($batchQuantityIn - $quantity) < 0.01 && junkshop_prices_match($batchUnitCost, $unitCost)) {
					$matchingBatches[] = $batch;
				}
			}

			if (count($matchingBatches) === 1) {
				return $matchingBatches[0];
			}

			if (count($matchingBatches) > 1 && $purchaseId > 0) {
				$productId = (int) ($purchaseRow['Product_ID'] ?? 0);
				$productName = trim((string) ($purchaseRow['ProductName'] ?? ''));
				$productClause = junkshop_purchase_product_clause($connectDB, $productId, $productName);
				$safeQuantity = mysqli_real_escape_string($connectDB, number_format($quantity, 2, '.', ''));
				$safeUnitCost = mysqli_real_escape_string($connectDB, number_format($unitCost, 2, '.', ''));
				$matchingPurchaseIds = [];
				$purchaseResult = $connectDB->query("
					SELECT ID
					FROM purchases
					WHERE InvoiceNo = '$invoiceNo'
						AND ($productClause)
						AND ABS(Quantity - $safeQuantity) < 0.01
						AND ABS(ProductPrice - $safeUnitCost) < 0.01
					ORDER BY ID ASC
				");
				if ($purchaseResult && $purchaseResult->num_rows > 0) {
					while ($row = $purchaseResult->fetch_assoc()) {
						$matchingPurchaseIds[] = (int) ($row['ID'] ?? 0);
					}
				}

				$matchIndex = array_search($purchaseId, $matchingPurchaseIds, true);
				if ($matchIndex !== false && isset($matchingBatches[$matchIndex])) {
					return $matchingBatches[$matchIndex];
				}
			}

			if (count($matchingBatches) > 0) {
				return $matchingBatches[0];
			}

			return count($batches) === 1 ? $batches[0] : null;
		}
	}

	if (!function_exists('junkshop_build_purchase_delete_block_message')) {
		function junkshop_build_purchase_delete_block_message($productName, $soldQty, $quantityRemaining) {
			$productLabel = htmlspecialchars(trim((string) $productName), ENT_QUOTES);
			if ($quantityRemaining <= 0.009) {
				return $productLabel . ' cannot be deleted because all stock from this purchase has already been sold or depleted.';
			}

			return $productLabel . ' cannot be deleted because ' . number_format((float) $soldQty, 2) . ' unit(s) from this purchase were already sold.';
		}
	}

	if (!function_exists('junkshop_assert_purchase_inventory_deletable')) {
		function junkshop_assert_purchase_inventory_deletable($connectDB, array $purchaseRow) {
			$batch = junkshop_match_purchase_inventory_batch($connectDB, $purchaseRow);
			if ($batch) {
				$soldQty = junkshop_get_batch_sold_quantity($batch);
				$quantityRemaining = round((float) ($batch['QuantityRemaining'] ?? 0), 2);
				if ($soldQty > 0.009) {
					return [
						'success' => false,
						'message' => junkshop_build_purchase_delete_block_message($purchaseRow['ProductName'] ?? 'Product', $soldQty, $quantityRemaining),
					];
				}

				return ['success' => true, 'batch' => $batch];
			}

			$batches = junkshop_find_inventory_batches_for_purchase($connectDB, $purchaseRow);
			if (empty($batches)) {
				return ['success' => true, 'batch' => null];
			}

			$quantity = round((float) ($purchaseRow['Quantity'] ?? 0), 2);
			$unitCost = round((float) ($purchaseRow['ProductPrice'] ?? 0), 2);
			foreach ($batches as $relatedBatch) {
				$batchQuantityIn = round((float) ($relatedBatch['QuantityIn'] ?? 0), 2);
				$batchUnitCost = round((float) ($relatedBatch['UnitCost'] ?? 0), 2);
				if (abs($batchQuantityIn - $quantity) >= 0.01 || !junkshop_prices_match($batchUnitCost, $unitCost)) {
					continue;
				}

				$soldQty = junkshop_get_batch_sold_quantity($relatedBatch);
				$quantityRemaining = round((float) ($relatedBatch['QuantityRemaining'] ?? 0), 2);
				if ($soldQty > 0.009) {
					return [
						'success' => false,
						'message' => junkshop_build_purchase_delete_block_message($purchaseRow['ProductName'] ?? 'Product', $soldQty, $quantityRemaining),
					];
				}

				return ['success' => true, 'batch' => $relatedBatch];
			}

			if (count($batches) === 1) {
				$relatedBatch = $batches[0];
				$soldQty = junkshop_get_batch_sold_quantity($relatedBatch);
				$quantityRemaining = round((float) ($relatedBatch['QuantityRemaining'] ?? 0), 2);
				if ($soldQty > 0.009) {
					return [
						'success' => false,
						'message' => junkshop_build_purchase_delete_block_message($purchaseRow['ProductName'] ?? 'Product', $soldQty, $quantityRemaining),
					];
				}

				return ['success' => true, 'batch' => $relatedBatch];
			}

			return [
				'success' => false,
				'message' => htmlspecialchars(trim((string) ($purchaseRow['ProductName'] ?? 'Product')), ENT_QUOTES) . ' cannot be deleted because its inventory batch could not be safely matched.',
			];
		}
	}

	if (!function_exists('junkshop_find_inventory_batch_for_purchase')) {
		function junkshop_find_inventory_batch_for_purchase($connectDB, array $purchaseRow) {
			return junkshop_match_purchase_inventory_batch($connectDB, $purchaseRow);
		}
	}

	if (!function_exists('junkshop_record_purchase_inventory')) {
		function junkshop_record_purchase_inventory($connectDB, $productId, $productName, $purchaseDate, $invoiceNo, $quantity, $unitCost, $tankUnitCost = 0) {
			$productId = (int) $productId;
			$quantity = round((float) $quantity, 2);
			$unitCost = round((float) $unitCost, 2);
			$tankUnitCost = round((float) $tankUnitCost, 2);
			if ($quantity <= 0) {
				return true;
			}

			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			$safePurchaseDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($purchaseDate));
			$safeQuantity = mysqli_real_escape_string($connectDB, number_format($quantity, 2, '.', ''));
			$safeProductPrice = mysqli_real_escape_string($connectDB, number_format($unitCost, 2, '.', ''));
			$safeTankUnitCost = mysqli_real_escape_string($connectDB, number_format($tankUnitCost, 2, '.', ''));
			$batchLabel = junkshop_resolve_inventory_batch_label($connectDB, $productId, $productName, $unitCost);
			$sourceReference = mysqli_real_escape_string($connectDB, 'Invoice#' . (int) $invoiceNo);

			if (!$connectDB->query("
				INSERT INTO inventory_batches (Product_ID, ProductName, BatchDate, BatchLabel, QuantityIn, QuantityRemaining, UnitCost, LpgTankUnitCost, SourceType, SourceReference)
				VALUES ('$productId', '$safeProductName', '$safePurchaseDate', '$batchLabel', '$safeQuantity', '$safeQuantity', '$safeProductPrice', '$safeTankUnitCost', 'PURCHASE', '$sourceReference')
			")) {
				return false;
			}

			$batchId = (int) $connectDB->insert_id;
			return (bool) $connectDB->query("
				INSERT INTO inventory_movements (Product_ID, ProductName, Batch_ID, MovementDate, MovementType, Quantity, ReferenceType, ReferenceNo, Notes)
				VALUES ('$productId', '$safeProductName', '$batchId', '$safePurchaseDate', 'IN', '$safeQuantity', 'PURCHASE', '$sourceReference', '$batchLabel stock received')
			");
		}
	}

	if (!function_exists('junkshop_lpg_tank_product_suffix')) {
		function junkshop_lpg_tank_product_suffix() {
			return ' - Tank';
		}
	}

	if (!function_exists('junkshop_lpg_tank_product_name')) {
		function junkshop_lpg_tank_product_name($parentProductName) {
			$parentProductName = trim((string) $parentProductName);
			$suffix = junkshop_lpg_tank_product_suffix();
			if ($parentProductName === '') {
				return 'Tank';
			}
			if (substr($parentProductName, -strlen($suffix)) === $suffix) {
				return $parentProductName;
			}
			return $parentProductName . $suffix;
		}
	}

	if (!function_exists('junkshop_product_is_lpg_tank')) {
		function junkshop_product_is_lpg_tank($connectDB, $productId, $productName = '') {
			$productId = (int) $productId;
			if ($productId > 0) {
				$result = $connectDB->query("SELECT ProductType, IsSubProduct, ParentProduct_ID FROM products WHERE Product_ID = '$productId' LIMIT 1");
				if ($result && ($row = $result->fetch_assoc())) {
					$type = strtoupper(trim((string) ($row['ProductType'] ?? '')));
					if ($type === 'LPG TANK') {
						return true;
					}
					if ((int) ($row['IsSubProduct'] ?? 0) === 1 && (int) ($row['ParentProduct_ID'] ?? 0) > 0) {
						return junkshop_product_is_lpg($connectDB, (int) $row['ParentProduct_ID'], '');
					}
				}
			}
			$name = trim((string) $productName);
			$suffix = junkshop_lpg_tank_product_suffix();
			return $name !== '' && substr($name, -strlen($suffix)) === $suffix;
		}
	}

	if (!function_exists('junkshop_product_is_lpg')) {
		function junkshop_product_is_lpg($connectDB, $productId, $productName = '') {
			$productId = (int) $productId;
			$name = trim((string) $productName);
			if ($productId > 0) {
				if (junkshop_product_is_lpg_tank($connectDB, $productId, $productName)) {
					return false;
				}
				$result = $connectDB->query("SELECT ProductType, ProductName, IsSubProduct FROM products WHERE Product_ID = '$productId' LIMIT 1");
				if ($result && ($row = $result->fetch_assoc())) {
					$type = strtoupper(trim((string) ($row['ProductType'] ?? '')));
					$name = trim((string) ($row['ProductName'] ?? $name));
					if ((int) ($row['IsSubProduct'] ?? 0) === 1) {
						return false;
					}
					if ($type === 'LPG') {
						return true;
					}
				}
			}
			if (junkshop_product_is_lpg_tank($connectDB, 0, $name)) {
				return false;
			}
			$lower = strtolower($name);
			return strpos($lower, 'lpg') !== false || strpos($lower, 'gasul') !== false;
		}
	}

	if (!function_exists('junkshop_get_lpg_tank_product_id')) {
		function junkshop_get_lpg_tank_product_id($connectDB, $parentProductId) {
			$parentProductId = (int) $parentProductId;
			if ($parentProductId <= 0) {
				return 0;
			}
			$result = $connectDB->query("
				SELECT Product_ID
				FROM products
				WHERE ParentProduct_ID = '$parentProductId'
					AND COALESCE(IsSubProduct, 0) = 1
				ORDER BY Product_ID ASC
				LIMIT 1
			");
			if ($result && ($row = $result->fetch_assoc())) {
				return (int) ($row['Product_ID'] ?? 0);
			}
			return 0;
		}
	}

	if (!function_exists('junkshop_ensure_lpg_tank_product')) {
		function junkshop_ensure_lpg_tank_product($connectDB, $parentProductId, $parentProductName, $parentBaseUnit = 'tank', $parentIsActive = 1) {
			$parentProductId = (int) $parentProductId;
			$parentProductName = trim((string) $parentProductName);
			if ($parentProductId <= 0 || $parentProductName === '' || !junkshop_product_is_lpg($connectDB, $parentProductId, $parentProductName)) {
				return 0;
			}

			$tankProductName = junkshop_lpg_tank_product_name($parentProductName);
			$safeTankProductName = mysqli_real_escape_string($connectDB, $tankProductName);
			$safeParentId = mysqli_real_escape_string($connectDB, (string) $parentProductId);
			$baseUnit = junkshop_normalize_base_unit($parentBaseUnit);
			if ($baseUnit !== 'tank') {
				$baseUnit = 'tank';
			}
			$safeBaseUnit = mysqli_real_escape_string($connectDB, $baseUnit);
			$isActive = (int) ((bool) $parentIsActive);
			$existingTankId = junkshop_get_lpg_tank_product_id($connectDB, $parentProductId);
			if ($existingTankId > 0) {
				$connectDB->query("
					UPDATE products
					SET ProductName = '$safeTankProductName',
						ProductType = 'LPG Tank',
						ProductBaseUnit = '$safeBaseUnit',
						ParentProduct_ID = '$safeParentId',
						IsSubProduct = 1,
						IsActive = '$isActive'
					WHERE Product_ID = '$existingTankId'
				");
				return $existingTankId;
			}

			$duplicateCheck = $connectDB->query("SELECT Product_ID FROM products WHERE ProductName = '$safeTankProductName' LIMIT 1");
			if ($duplicateCheck && ($duplicateRow = $duplicateCheck->fetch_assoc())) {
				$existingId = (int) ($duplicateRow['Product_ID'] ?? 0);
				if ($existingId > 0) {
					$connectDB->query("
						UPDATE products
						SET ProductType = 'LPG Tank',
							ProductBaseUnit = '$safeBaseUnit',
							ParentProduct_ID = '$safeParentId',
							IsSubProduct = 1,
							IsActive = '$isActive'
						WHERE Product_ID = '$existingId'
					");
					return $existingId;
				}
			}

			if (!$connectDB->query("
				INSERT INTO products (ProductName, ProductType, ProductBaseUnit, ProductPrice, SellingPrice, AlternateSellingPrice, LpgRefillPrice, LpgNewTankPrice, StockLimit, CanConvertToKg, KgEquivalentQty, AlternateSaleUnit, ParentProduct_ID, IsSubProduct, IsActive)
				VALUES ('$safeTankProductName', 'LPG Tank', '$safeBaseUnit', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '0.00', '', '$safeParentId', '1', '$isActive')
			")) {
				return 0;
			}

			return (int) $connectDB->insert_id;
		}
	}

	if (!function_exists('junkshop_get_inventory_stock')) {
		function junkshop_get_inventory_stock($connectDB, $productId) {
			$productId = (int) $productId;
			if ($productId <= 0) {
				return 0.0;
			}
			$result = $connectDB->query("SELECT COALESCE(SUM(QuantityRemaining), 0) AS total_stock FROM inventory_batches WHERE Product_ID = '$productId'");
			if ($result && ($row = $result->fetch_assoc())) {
				return round((float) ($row['total_stock'] ?? 0), 2);
			}
			return 0.0;
		}
	}

	if (!function_exists('junkshop_deduct_inventory_fifo')) {
		function junkshop_deduct_inventory_fifo($connectDB, $productId, $productName, $quantity, $saleDate, $saleId, $deliveryNo, $includeTankUnitCost = false) {
			$productId = (int) $productId;
			$quantity = round((float) $quantity, 2);
			if ($productId <= 0 || $quantity <= 0) {
				return ['remaining' => $quantity, 'cost' => 0.0];
			}

			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			$safeSaleDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($saleDate));
			$safeDeliveryNo = mysqli_real_escape_string($connectDB, trim((string) $deliveryNo));
			$safeSaleId = (int) $saleId;
			$productClause = junkshop_inventory_product_clause($connectDB, $productId, trim((string) $productName));
			$remainingToDeduct = $quantity;
			$linePurchaseCost = 0.0;
			$batchResult = $connectDB->query("
				SELECT ID, QuantityRemaining, UnitCost, COALESCE(LpgTankUnitCost, 0) AS LpgTankUnitCost
				FROM inventory_batches
				WHERE ($productClause) AND QuantityRemaining > 0
				ORDER BY BatchDate ASC, ID ASC
			");
			while ($batchResult && $batch = $batchResult->fetch_assoc()) {
				if ($remainingToDeduct <= 0) {
					break;
				}
				$batchId = (int) $batch['ID'];
				$available = (float) $batch['QuantityRemaining'];
				$unitCost = round((float) ($batch['UnitCost'] ?? 0), 2);
				$tankUnitCost = $includeTankUnitCost ? round((float) ($batch['LpgTankUnitCost'] ?? 0), 2) : 0.0;
				$effectiveUnitCost = round($unitCost + $tankUnitCost, 2);
				$deductQty = min($remainingToDeduct, $available);
				$deductCost = round($deductQty * $effectiveUnitCost, 2);
				$linePurchaseCost += $deductCost;
				$safeDeductQty = mysqli_real_escape_string($connectDB, number_format($deductQty, 2, '.', ''));
				$safeUnitCost = mysqli_real_escape_string($connectDB, number_format($effectiveUnitCost, 2, '.', ''));
				$safeDeductCost = mysqli_real_escape_string($connectDB, number_format($deductCost, 2, '.', ''));
				$connectDB->query("UPDATE inventory_batches SET QuantityRemaining = QuantityRemaining - $safeDeductQty WHERE ID = '$batchId'");
				$connectDB->query("
					INSERT INTO inventory_movements (Product_ID, ProductName, Batch_ID, MovementDate, MovementType, Quantity, UnitCost, TotalCost, ReferenceType, ReferenceNo, Notes)
					VALUES ('$productId', '$safeProductName', '$batchId', '$safeSaleDate', 'OUT', '$safeDeductQty', '$safeUnitCost', '$safeDeductCost', 'SALE', 'SaleID#$safeSaleId', 'FIFO sale deduction for Sale#$safeDeliveryNo')
				");
				$remainingToDeduct -= $deductQty;
			}

			return [
				'remaining' => round(max($remainingToDeduct, 0), 2),
				'cost' => round($linePurchaseCost, 2),
			];
		}
	}

	if (!function_exists('junkshop_lpg_normalize_tank_condition')) {
		function junkshop_lpg_normalize_tank_condition($condition) {
			$condition = strtoupper(trim((string) $condition));
			return in_array($condition, ['NEW', 'OLD'], true) ? $condition : '';
		}
	}

	if (!function_exists('junkshop_lpg_get_empty_balance')) {
		function junkshop_lpg_get_empty_balance($connectDB, $productId, $condition = '') {
			$productId = (int) $productId;
			if ($productId <= 0) {
				return 0.0;
			}
			$condition = junkshop_lpg_normalize_tank_condition($condition);
			if ($condition !== '') {
				$result = $connectDB->query("SELECT Quantity FROM lpg_tank_balances WHERE Product_ID = '$productId' AND TankCondition = '$condition' LIMIT 1");
				if ($result && ($row = $result->fetch_assoc())) {
					return round((float) ($row['Quantity'] ?? 0), 2);
				}
				return 0.0;
			}
			$result = $connectDB->query("SELECT COALESCE(SUM(Quantity), 0) AS total_qty FROM lpg_tank_balances WHERE Product_ID = '$productId'");
			if ($result && ($row = $result->fetch_assoc())) {
				return round((float) ($row['total_qty'] ?? 0), 2);
			}
			return 0.0;
		}
	}

	if (!function_exists('junkshop_lpg_adjust_empty_balance')) {
		function junkshop_lpg_adjust_empty_balance($connectDB, $productId, $productName, $condition, $qtyDelta) {
			$productId = (int) $productId;
			$condition = junkshop_lpg_normalize_tank_condition($condition);
			$qtyDelta = round((float) $qtyDelta, 2);
			if ($productId <= 0 || $condition === '' || abs($qtyDelta) < 0.001) {
				return ['success' => true, 'message' => ''];
			}

			$safeName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			$existing = $connectDB->query("SELECT ID, Quantity FROM lpg_tank_balances WHERE Product_ID = '$productId' AND TankCondition = '$condition' LIMIT 1");
			if ($existing && ($row = $existing->fetch_assoc())) {
				$newQty = round((float) ($row['Quantity'] ?? 0) + $qtyDelta, 2);
				if ($newQty < -0.009) {
					return ['success' => false, 'message' => 'Not enough empty ' . strtolower($condition) . ' LPG tanks in stock.'];
				}
				$safeNewQty = mysqli_real_escape_string($connectDB, number_format(max($newQty, 0), 2, '.', ''));
				if (!$connectDB->query("UPDATE lpg_tank_balances SET Quantity = '$safeNewQty', ProductName = '$safeName' WHERE ID = " . (int) ($row['ID'] ?? 0))) {
					return ['success' => false, 'message' => 'Failed to update empty LPG tank balance.'];
				}
				return ['success' => true, 'message' => ''];
			}

			if ($qtyDelta < 0) {
				return ['success' => false, 'message' => 'Not enough empty ' . strtolower($condition) . ' LPG tanks in stock.'];
			}

			$safeQty = mysqli_real_escape_string($connectDB, number_format($qtyDelta, 2, '.', ''));
			if (!$connectDB->query("INSERT INTO lpg_tank_balances (Product_ID, ProductName, TankCondition, Quantity) VALUES ('$productId', '$safeName', '$condition', '$safeQty')")) {
				return ['success' => false, 'message' => 'Failed to record empty LPG tank balance.'];
			}
			return ['success' => true, 'message' => ''];
		}
	}

	if (!function_exists('junkshop_lpg_record_loan')) {
		function junkshop_lpg_record_loan($connectDB, $customerName, $productId, $productName, $quantity, $deliveryNo, $saleId, $loanDate, $loanCondition = '') {
			$customerName = trim((string) $customerName);
			if ($customerName === '' || junkshop_is_walk_in_customer($customerName)) {
				return ['success' => false, 'message' => 'Lent LPG tanks require a registered customer.'];
			}
			$loanCondition = junkshop_lpg_normalize_tank_condition($loanCondition);
			if ($loanCondition === '') {
				return ['success' => false, 'message' => 'Select lent tank condition (New or Old).'];
			}
			$productId = (int) $productId;
			$quantity = round((float) $quantity, 2);
			if ($productId <= 0 || $quantity <= 0) {
				return ['success' => false, 'message' => 'Invalid LPG loan quantity.'];
			}

			$safeCustomer = mysqli_real_escape_string($connectDB, $customerName);
			$safeProductName = mysqli_real_escape_string($connectDB, trim((string) $productName));
			$safeLoanDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($loanDate));
			$safeQty = mysqli_real_escape_string($connectDB, number_format($quantity, 2, '.', ''));
			$safeLoanCondition = mysqli_real_escape_string($connectDB, $loanCondition);
			$deliveryNo = (int) $deliveryNo;
			$saleId = (int) $saleId;

			if (!$connectDB->query("
				INSERT INTO lpg_tank_loans (CustomerName, Product_ID, ProductName, Quantity, LoanDate, DeliveryNo, Sale_ID, Status, LoanCondition)
				VALUES ('$safeCustomer', '$productId', '$safeProductName', '$safeQty', '$safeLoanDate', '$deliveryNo', '$saleId', 'LENT', '$safeLoanCondition')
			")) {
				return ['success' => false, 'message' => 'Failed to record lent LPG tank.'];
			}
			return ['success' => true, 'message' => ''];
		}
	}

	if (!function_exists('junkshop_lpg_return_loan')) {
		function junkshop_lpg_return_loan($connectDB, $loanId, $returnCondition, $returnDate = null) {
			$loanId = (int) $loanId;
			$returnCondition = junkshop_lpg_normalize_tank_condition($returnCondition);
			if ($loanId <= 0 || $returnCondition === '') {
				return ['success' => false, 'message' => 'Select the returned tank condition (New or Old).'];
			}

			$result = $connectDB->query("SELECT * FROM lpg_tank_loans WHERE ID = '$loanId' AND Status = 'LENT' LIMIT 1");
			if (!$result || !($loan = $result->fetch_assoc())) {
				return ['success' => false, 'message' => 'Active lent tank record not found.'];
			}

			$productId = (int) ($loan['Product_ID'] ?? 0);
			$productName = trim((string) ($loan['ProductName'] ?? ''));
			$quantity = round((float) ($loan['Quantity'] ?? 0), 2);
			$balanceResult = junkshop_lpg_adjust_empty_balance($connectDB, $productId, $productName, $returnCondition, $quantity);
			if (!$balanceResult['success']) {
				return $balanceResult;
			}

			$safeReturnDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($returnDate ?: date('Y-m-d H:i:s')));
			$safeReturnCondition = mysqli_real_escape_string($connectDB, $returnCondition);
			if (!$connectDB->query("
				UPDATE lpg_tank_loans
				SET Status = 'RETURNED', ReturnedAt = '$safeReturnDate', ReturnCondition = '$safeReturnCondition'
				WHERE ID = '$loanId'
			")) {
				return ['success' => false, 'message' => 'Failed to mark lent tank as returned.'];
			}
			return ['success' => true, 'message' => ''];
		}
	}

	if (!function_exists('junkshop_lpg_apply_purchase')) {
		function junkshop_lpg_apply_purchase($connectDB, $productId, $productName, $quantity, $stockInType, $emptyTankCondition) {
			if (!junkshop_product_is_lpg($connectDB, $productId, $productName)) {
				return ['success' => true, 'message' => ''];
			}
			$stockInType = strtoupper(trim((string) $stockInType));
			$quantity = round((float) $quantity, 2);
			if ($stockInType !== 'REFILL') {
				return ['success' => true, 'message' => ''];
			}
			$condition = junkshop_lpg_normalize_tank_condition($emptyTankCondition);
			if ($condition === '') {
				return ['success' => false, 'message' => 'Select which empty tank condition was used for the refill.'];
			}
			if (junkshop_lpg_get_empty_balance($connectDB, $productId, $condition) + 0.009 < $quantity) {
				return ['success' => false, 'message' => 'Not enough empty ' . strtolower($condition) . ' tanks available for refill.'];
			}
			return junkshop_lpg_adjust_empty_balance($connectDB, $productId, $productName, $condition, -$quantity);
		}
	}

	if (!function_exists('junkshop_lpg_sold_receipt_prices')) {
		function junkshop_lpg_sold_receipt_prices($connectDB, $productId, $unitPrice) {
			$productId = (int) $productId;
			$unitPrice = round((float) $unitPrice, 2);
			$refillPrice = 0.0;
			if ($productId > 0) {
				$result = $connectDB->query("SELECT LpgRefillPrice, SellingPrice FROM products WHERE Product_ID = '$productId' LIMIT 1");
				if ($result && ($row = $result->fetch_assoc())) {
					$refillPrice = round((float) ($row['LpgRefillPrice'] ?? 0), 2);
					if ($refillPrice <= 0) {
						$refillPrice = round((float) ($row['SellingPrice'] ?? 0), 2);
					}
				}
			}
			if ($refillPrice <= 0) {
				$refillPrice = $unitPrice;
			}
			if ($refillPrice > $unitPrice) {
				$refillPrice = $unitPrice;
			}
			$tankPrice = round(max($unitPrice - $refillPrice, 0), 2);
			return [
				'refill_price' => $refillPrice,
				'tank_price' => $tankPrice,
			];
		}
	}

	if (!function_exists('junkshop_lpg_apply_sale')) {
		function junkshop_lpg_apply_sale($connectDB, $productId, $productName, $baseQuantity, $lpgTransactionType, $tankCondition, $customerName, $deliveryNo, $saleId, $saleDate) {
			if (!junkshop_product_is_lpg($connectDB, $productId, $productName)) {
				return ['success' => true, 'message' => ''];
			}
			$type = strtoupper(trim((string) $lpgTransactionType));
			$qty = round((float) $baseQuantity, 2);
			if ($qty <= 0 || $type === 'NONE') {
				return ['success' => true, 'message' => ''];
			}
			switch ($type) {
				case 'SWAPPED':
					$condition = junkshop_lpg_normalize_tank_condition($tankCondition);
					if ($condition === '') {
						return ['success' => false, 'message' => 'Select swapped empty tank condition (New or Old).'];
					}
					return junkshop_lpg_adjust_empty_balance($connectDB, $productId, $productName, $condition, $qty);
				case 'LENT':
					$condition = junkshop_lpg_normalize_tank_condition($tankCondition);
					if ($condition === '') {
						return ['success' => false, 'message' => 'Select lent tank condition (New or Old).'];
					}
					return junkshop_lpg_record_loan($connectDB, $customerName, $productId, $productName, $qty, $deliveryNo, $saleId, $saleDate, $condition);
				case 'SOLD':
					return ['success' => true, 'message' => ''];
				default:
					return ['success' => true, 'message' => ''];
			}
		}
	}

	if (!function_exists('junkshop_reverse_purchase_inventory')) {
		function junkshop_reverse_purchase_inventory($connectDB, $purchaseId) {
			$purchaseRow = junkshop_get_purchase_row($connectDB, $purchaseId);
			if (!$purchaseRow) {
				return ['success' => false, 'message' => 'Purchase record not found.'];
			}

			$purchaseQty = round((float) ($purchaseRow['Quantity'] ?? 0), 2);
			if ($purchaseQty <= 0) {
				return ['success' => true, 'message' => ''];
			}

			$deleteCheck = junkshop_assert_purchase_inventory_deletable($connectDB, $purchaseRow);
			if (!$deleteCheck['success']) {
				return $deleteCheck;
			}

			$batch = $deleteCheck['batch'] ?? null;
			if (!$batch) {
				return ['success' => true, 'message' => ''];
			}

			$batchId = (int) ($batch['ID'] ?? 0);
			$quantityIn = round((float) ($batch['QuantityIn'] ?? 0), 2);
			$quantityRemaining = round((float) ($batch['QuantityRemaining'] ?? 0), 2);
			$qtyToRemove = min($purchaseQty, $quantityIn);

			if ($quantityRemaining + 0.009 < $qtyToRemove) {
				$soldQty = junkshop_get_batch_sold_quantity($batch);
				return [
					'success' => false,
					'message' => junkshop_build_purchase_delete_block_message($purchaseRow['ProductName'] ?? 'Product', $soldQty, $quantityRemaining),
				];
			}

			$newRemaining = round($quantityRemaining - $qtyToRemove, 2);
			$newQuantityIn = round($quantityIn - $qtyToRemove, 2);

			if ($newRemaining <= 0 && $newQuantityIn <= 0) {
				if (!$connectDB->query("DELETE FROM inventory_movements WHERE Batch_ID = '$batchId'")) {
					return ['success' => false, 'message' => 'Failed to remove inventory movement history.'];
				}
				if (!$connectDB->query("DELETE FROM inventory_batches WHERE ID = '$batchId'")) {
					return ['success' => false, 'message' => 'Failed to remove inventory batch.'];
				}
			} else {
				$safeNewRemaining = mysqli_real_escape_string($connectDB, number_format(max($newRemaining, 0), 2, '.', ''));
				$safeNewQuantityIn = mysqli_real_escape_string($connectDB, number_format(max($newQuantityIn, 0), 2, '.', ''));
				if (!$connectDB->query("UPDATE inventory_batches SET QuantityIn = '$safeNewQuantityIn', QuantityRemaining = '$safeNewRemaining' WHERE ID = '$batchId'")) {
					return ['success' => false, 'message' => 'Failed to update inventory batch.'];
				}
				$connectDB->query("DELETE FROM inventory_movements WHERE Batch_ID = '$batchId' AND MovementType = 'IN' AND ReferenceType = 'PURCHASE' LIMIT 1");
			}

			return ['success' => true, 'message' => ''];
		}
	}

	if (!function_exists('junkshop_delete_purchase_records')) {
		function junkshop_delete_purchase_records($connectDB, $whereSql) {
			$purchasesResult = $connectDB->query("SELECT ID FROM purchases WHERE $whereSql ORDER BY ID ASC");
			if (!$purchasesResult) {
				return ['success' => false, 'message' => 'Unable to load purchase records.'];
			}

			$purchaseIds = [];
			while ($row = $purchasesResult->fetch_assoc()) {
				$purchaseIds[] = (int) ($row['ID'] ?? 0);
			}

			if (empty($purchaseIds)) {
				return ['success' => false, 'message' => 'Purchase record not found.'];
			}

			foreach ($purchaseIds as $purchaseId) {
				$reverseResult = junkshop_reverse_purchase_inventory($connectDB, $purchaseId);
				if (!$reverseResult['success']) {
					return $reverseResult;
				}
			}

			$idsList = implode(',', array_map('intval', $purchaseIds));
			if (!$connectDB->query("DELETE FROM purchases WHERE ID IN ($idsList)")) {
				return ['success' => false, 'message' => 'Failed to delete purchase record(s).'];
			}

			return ['success' => true, 'message' => ''];
		}
	}

	if (!function_exists('junkshop_sync_purchase_inventory')) {
		function junkshop_sync_purchase_inventory($connectDB, $purchaseId, $newQuantity, $newUnitCost, $newPurchaseDate) {
			$purchaseRow = junkshop_get_purchase_row($connectDB, $purchaseId);
			if (!$purchaseRow) {
				return ['success' => false, 'message' => 'Purchase record not found.'];
			}

			$newQuantity = round((float) $newQuantity, 2);
			$newUnitCost = round((float) $newUnitCost, 2);
			$newPurchaseDate = junkshop_normalize_datetime($newPurchaseDate);
			$productId = (int) ($purchaseRow['Product_ID'] ?? 0);
			$productName = trim((string) ($purchaseRow['ProductName'] ?? ''));
			$invoiceNo = (int) ($purchaseRow['InvoiceNo'] ?? 0);

			if ($newQuantity <= 0) {
				return ['success' => false, 'message' => 'Purchase quantity must be greater than zero.'];
			}

			$batch = junkshop_find_inventory_batch_for_purchase($connectDB, $purchaseRow);
			if (!$batch) {
				if (!junkshop_record_purchase_inventory($connectDB, $productId, $productName, $newPurchaseDate, $invoiceNo, $newQuantity, $newUnitCost)) {
					return ['success' => false, 'message' => 'Failed to create inventory batch for this purchase.'];
				}
				return ['success' => true, 'message' => ''];
			}

			$batchId = (int) ($batch['ID'] ?? 0);
			$quantityIn = round((float) ($batch['QuantityIn'] ?? 0), 2);
			$quantityRemaining = round((float) ($batch['QuantityRemaining'] ?? 0), 2);
			$soldQty = round(max($quantityIn - $quantityRemaining, 0), 2);

			if ($newQuantity + 0.009 < $soldQty) {
				return [
					'success' => false,
					'message' => 'Cannot reduce quantity below ' . number_format($soldQty, 2) . ' unit(s) already sold from this purchase.',
				];
			}

			$newRemaining = round($newQuantity - $soldQty, 2);
			$batchLabel = junkshop_resolve_inventory_batch_label($connectDB, $productId, $productName, $newUnitCost);
			$safeProductName = mysqli_real_escape_string($connectDB, $productName);
			$safePurchaseDate = mysqli_real_escape_string($connectDB, $newPurchaseDate);
			$safeQuantityIn = mysqli_real_escape_string($connectDB, number_format($newQuantity, 2, '.', ''));
			$safeQuantityRemaining = mysqli_real_escape_string($connectDB, number_format($newRemaining, 2, '.', ''));
			$safeUnitCost = mysqli_real_escape_string($connectDB, number_format($newUnitCost, 2, '.', ''));
			$safeBatchLabel = mysqli_real_escape_string($connectDB, $batchLabel);

			if (!$connectDB->query("
				UPDATE inventory_batches
				SET ProductName = '$safeProductName',
					BatchDate = '$safePurchaseDate',
					BatchLabel = '$safeBatchLabel',
					QuantityIn = '$safeQuantityIn',
					QuantityRemaining = '$safeQuantityRemaining',
					UnitCost = '$safeUnitCost'
				WHERE ID = '$batchId'
			")) {
				return ['success' => false, 'message' => 'Failed to update inventory batch.'];
			}

			$connectDB->query("
				UPDATE inventory_movements
				SET MovementDate = '$safePurchaseDate',
					Quantity = '$safeQuantityIn',
					Notes = '$safeBatchLabel stock received'
				WHERE Batch_ID = '$batchId' AND MovementType = 'IN' AND ReferenceType = 'PURCHASE'
				LIMIT 1
			");

			return ['success' => true, 'message' => ''];
		}
	}

	if (!function_exists('junkshop_delete_sale_loan_records')) {
		function junkshop_delete_sale_loan_records($connectDB, $deliveryNo, $deletePayments = true) {
			$deliveryNo = (int) $deliveryNo;
			if ($deliveryNo <= 0) {
				return;
			}

			if ($deletePayments) {
				$connectDB->query("DELETE FROM customer_payments WHERE DeliveryNo = '$deliveryNo'");
			}

			$connectDB->query("DELETE FROM customer_accounts WHERE DeliveryNo = '$deliveryNo'");
		}
	}

	if (!function_exists('junkshop_sync_customer_account_for_delivery')) {
		function junkshop_sync_customer_account_for_delivery($connectDB, $deliveryNo) {
			$deliveryNo = (int) $deliveryNo;
			if ($deliveryNo <= 0) {
				return;
			}

			$salesResult = $connectDB->query("
				SELECT CustomerName, SaleDate, TotalSalePrice, AmountPaid, DueDate
				FROM sales
				WHERE DeliveryNo = '$deliveryNo'
				ORDER BY ID ASC
			");

			if (!$salesResult || $salesResult->num_rows === 0) {
				junkshop_delete_sale_loan_records($connectDB, $deliveryNo, true);
				return;
			}

			$customerName = '';
			$saleDate = '';
			$totalAmount = 0.0;
			$amountPaid = 0.0;
			$dueDates = [];

			while ($row = $salesResult->fetch_assoc()) {
				$customerName = trim((string) ($row['CustomerName'] ?? ''));
				$saleDate = trim((string) ($row['SaleDate'] ?? ''));
				$lineTotal = round((float) ($row['TotalSalePrice'] ?? 0), 2);
				$linePaid = round((float) ($row['AmountPaid'] ?? 0), 2);
				$totalAmount += $lineTotal;
				$amountPaid += $linePaid;
				$dueDate = trim((string) ($row['DueDate'] ?? ''));
				if ($lineTotal - $linePaid > 0 && $dueDate !== '') {
					$dueDates[] = $dueDate;
				}
			}

			if ($customerName === '' || junkshop_is_walk_in_customer($customerName)) {
				junkshop_delete_sale_loan_records($connectDB, $deliveryNo, true);
				return;
			}

			$amountPaid = min($amountPaid, $totalAmount);
			$balance = max($totalAmount - $amountPaid, 0);

			if ($balance <= 0) {
				junkshop_delete_sale_loan_records($connectDB, $deliveryNo, false);
				return;
			}

			if ($amountPaid <= 0) {
				$paymentStatus = 'UNPAID';
			} else {
				$paymentStatus = 'PARTIAL';
			}

			$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
			$safeSaleDate = mysqli_real_escape_string($connectDB, $saleDate);
			$accountDueDateValue = !empty($dueDates) ? max($dueDates) : '';
			$accountDueDateSql = $accountDueDateValue !== '' ? "'" . mysqli_real_escape_string($connectDB, $accountDueDateValue) . "'" : "NULL";
			$safeAmountPaid = mysqli_real_escape_string($connectDB, number_format($amountPaid, 2, '.', ''));
			$safeTotalAmount = mysqli_real_escape_string($connectDB, number_format($totalAmount, 2, '.', ''));
			$safeBalance = mysqli_real_escape_string($connectDB, number_format($balance, 2, '.', ''));

			$connectDB->query("
				INSERT INTO customer_accounts (CustomerName, DeliveryNo, SaleDate, DueDate, TotalAmount, AmountPaid, Balance, PaymentStatus)
				VALUES ('$safeCustomerName', '$deliveryNo', '$safeSaleDate', $accountDueDateSql, '$safeTotalAmount', '$safeAmountPaid', '$safeBalance', '$paymentStatus')
				ON DUPLICATE KEY UPDATE
					CustomerName = VALUES(CustomerName),
					SaleDate = VALUES(SaleDate),
					DueDate = VALUES(DueDate),
					TotalAmount = VALUES(TotalAmount),
					AmountPaid = VALUES(AmountPaid),
					Balance = VALUES(Balance),
					PaymentStatus = VALUES(PaymentStatus)
			");
		}
	}

	if (!function_exists('junkshop_apply_customer_payment_to_delivery')) {
		function junkshop_apply_customer_payment_to_delivery($connectDB, $customerName, $deliveryNo, $appliedAmount, $paymentDate, $paymentNotes = '', $insertPaymentRecord = true) {
			$deliveryNo = (int) $deliveryNo;
			$appliedAmount = round((float) $appliedAmount, 2);
			if ($deliveryNo <= 0 || $appliedAmount <= 0 || junkshop_is_walk_in_customer($customerName)) {
				return false;
			}

			$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
			$safePaymentDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($paymentDate));
			$safePaymentNotes = mysqli_real_escape_string($connectDB, trim((string) $paymentNotes));
			$safeAppliedAmount = mysqli_real_escape_string($connectDB, number_format($appliedAmount, 2, '.', ''));
			$remainingLinePayment = $appliedAmount;

			$salesResult = $connectDB->query("
				SELECT ID, TotalSalePrice, AmountPaid, PaymentStatus
				FROM sales
				WHERE DeliveryNo = '$deliveryNo' AND CustomerName = '$safeCustomerName'
				ORDER BY ID ASC
			");

			if ($salesResult && $salesResult->num_rows > 0) {
				while ($remainingLinePayment > 0 && ($sale = $salesResult->fetch_assoc())) {
					$saleId = (int) ($sale['ID'] ?? 0);
					$lineTotal = round((float) ($sale['TotalSalePrice'] ?? 0), 2);
					$linePaid = round((float) ($sale['AmountPaid'] ?? 0), 2);
					$lineBalance = round($lineTotal - $linePaid, 2);
					if ($saleId <= 0 || $lineBalance <= 0) {
						continue;
					}

					$lineApply = min($remainingLinePayment, $lineBalance);
					$newLinePaid = round($linePaid + $lineApply, 2);
					$newLineBalance = round($lineTotal - $newLinePaid, 2);
					if ($newLineBalance <= 0) {
						$lineStatus = 'PAID';
					} elseif ($newLinePaid <= 0) {
						$lineStatus = 'UNPAID';
					} else {
						$lineStatus = 'PARTIAL';
					}

					$safeLinePaid = mysqli_real_escape_string($connectDB, number_format($newLinePaid, 2, '.', ''));
					$connectDB->query("
						UPDATE sales
						SET AmountPaid = '$safeLinePaid', PaymentStatus = '$lineStatus'
						WHERE ID = '$saleId'
					");
					$remainingLinePayment = round($remainingLinePayment - $lineApply, 2);
				}
			}

			junkshop_sync_customer_account_for_delivery($connectDB, $deliveryNo);

			if ($insertPaymentRecord) {
				$connectDB->query("
					INSERT INTO customer_payments (CustomerName, DeliveryNo, PaymentDate, Amount, Notes)
					VALUES ('$safeCustomerName', '$deliveryNo', '$safePaymentDate', '$safeAppliedAmount', '$safePaymentNotes')
				");
			}

			return true;
		}
	}

	if (!function_exists('junkshop_reverse_customer_payment_on_delivery')) {
		function junkshop_reverse_customer_payment_on_delivery($connectDB, $customerName, $deliveryNo, $reverseAmount) {
			$deliveryNo = (int) $deliveryNo;
			$reverseAmount = round((float) $reverseAmount, 2);
			if ($deliveryNo <= 0 || $reverseAmount <= 0 || junkshop_is_walk_in_customer($customerName)) {
				return false;
			}

			$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
			$remainingReverse = $reverseAmount;

			$salesResult = $connectDB->query("
				SELECT ID, TotalSalePrice, AmountPaid
				FROM sales
				WHERE DeliveryNo = '$deliveryNo' AND CustomerName = '$safeCustomerName'
				ORDER BY ID DESC
			");

			if ($salesResult && $salesResult->num_rows > 0) {
				while ($remainingReverse > 0 && ($sale = $salesResult->fetch_assoc())) {
					$saleId = (int) ($sale['ID'] ?? 0);
					$lineTotal = round((float) ($sale['TotalSalePrice'] ?? 0), 2);
					$linePaid = round((float) ($sale['AmountPaid'] ?? 0), 2);
					if ($saleId <= 0 || $linePaid <= 0) {
						continue;
					}

					$lineReverse = min($remainingReverse, $linePaid);
					$newLinePaid = round($linePaid - $lineReverse, 2);
					$newLineBalance = round($lineTotal - $newLinePaid, 2);
					if ($newLineBalance <= 0) {
						$lineStatus = 'PAID';
					} elseif ($newLinePaid <= 0) {
						$lineStatus = 'UNPAID';
					} else {
						$lineStatus = 'PARTIAL';
					}

					$safeLinePaid = mysqli_real_escape_string($connectDB, number_format($newLinePaid, 2, '.', ''));
					$connectDB->query("
						UPDATE sales
						SET AmountPaid = '$safeLinePaid', PaymentStatus = '$lineStatus'
						WHERE ID = '$saleId'
					");
					$remainingReverse = round($remainingReverse - $lineReverse, 2);
				}
			}

			junkshop_sync_customer_account_for_delivery($connectDB, $deliveryNo);

			return true;
		}
	}

	if (!function_exists('junkshop_get_customer_payment')) {
		function junkshop_get_customer_payment($connectDB, $paymentId) {
			$paymentId = (int) $paymentId;
			if ($paymentId <= 0) {
				return null;
			}

			$result = $connectDB->query("SELECT * FROM customer_payments WHERE ID = '$paymentId' LIMIT 1");
			if (!$result || $result->num_rows === 0) {
				return null;
			}

			return $result->fetch_assoc();
		}
	}

	if (!function_exists('junkshop_delete_customer_payment')) {
		function junkshop_delete_customer_payment($connectDB, $paymentId) {
			$payment = junkshop_get_customer_payment($connectDB, $paymentId);
			if (!$payment) {
				return false;
			}

			$customerName = trim((string) ($payment['CustomerName'] ?? ''));
			$deliveryNo = (int) ($payment['DeliveryNo'] ?? 0);
			$amount = round((float) ($payment['Amount'] ?? 0), 2);
			if ($customerName === '' || $amount <= 0) {
				return false;
			}

			junkshop_reverse_customer_payment_on_delivery($connectDB, $customerName, $deliveryNo, $amount);
			$connectDB->query("DELETE FROM customer_payments WHERE ID = '" . (int) $paymentId . "'");

			return true;
		}
	}

	if (!function_exists('junkshop_update_delivery_due_date')) {
		function junkshop_update_delivery_due_date($connectDB, $customerName, $deliveryNo, $dueDate) {
			$deliveryNo = (int) $deliveryNo;
			if ($deliveryNo <= 0 || junkshop_is_walk_in_customer($customerName)) {
				return false;
			}

			$safeCustomerName = mysqli_real_escape_string($connectDB, $customerName);
			$dueDate = trim((string) $dueDate);
			$dueDateSql = $dueDate !== '' ? "'" . mysqli_real_escape_string($connectDB, $dueDate) . "'" : 'NULL';

			$connectDB->query("
				UPDATE sales
				SET DueDate = $dueDateSql
				WHERE DeliveryNo = '$deliveryNo' AND CustomerName = '$safeCustomerName'
			");
			$connectDB->query("
				UPDATE customer_accounts
				SET DueDate = $dueDateSql
				WHERE DeliveryNo = '$deliveryNo' AND CustomerName = '$safeCustomerName'
			");

			return true;
		}
	}

	if (!function_exists('junkshop_count_stock_limit_alerts')) {
		function junkshop_count_stock_limit_alerts($connectDB) {
			$result = $connectDB->query("
				SELECT COUNT(*) AS alert_count
				FROM (
					SELECT p.Product_ID
					FROM products p
					LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
					WHERE p.IsActive = 1 AND COALESCE(p.StockLimit, 0) > 0
					GROUP BY p.Product_ID, p.StockLimit
					HAVING COALESCE(SUM(b.QuantityRemaining), 0) <= p.StockLimit
				) alerts
			");

			if (!$result) {
				return 0;
			}

			$row = $result->fetch_assoc();
			return (int) ($row['alert_count'] ?? 0);
		}
	}

	if (!function_exists('junkshop_count_due_date_alerts')) {
		function junkshop_count_due_date_alerts($connectDB) {
			$walkInCustomerName = mysqli_real_escape_string($connectDB, junkshop_walk_in_customer_name());
			$today = date('Y-m-d');
			$result = $connectDB->query("
				SELECT COUNT(*) AS alert_count
				FROM customer_accounts
				WHERE Balance > 0
					AND CustomerName <> '$walkInCustomerName'
					AND DueDate IS NOT NULL
					AND DueDate <= '$today'
			");

			if (!$result) {
				return 0;
			}

			$row = $result->fetch_assoc();
			return (int) ($row['alert_count'] ?? 0);
		}
	}

	$productsTableExists = false;
	$purchasesTableExists = false;
	$createProductsTableSql = "CREATE TABLE IF NOT EXISTS products (
		Product_ID int(11) NOT NULL AUTO_INCREMENT,
		ProductName varchar(255) NOT NULL,
		ProductType varchar(255) NOT NULL DEFAULT '',
		ProductBaseUnit varchar(20) NOT NULL DEFAULT 'pc',
		ProductPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		SellingPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		AlternateSellingPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		StockLimit decimal(12,2) NOT NULL DEFAULT 0.00,
		CanConvertToKg tinyint(1) NOT NULL DEFAULT 0,
		KgEquivalentQty decimal(12,2) NOT NULL DEFAULT 0.00,
		ParentProduct_ID int(11) NOT NULL DEFAULT 0,
		IsSubProduct tinyint(1) NOT NULL DEFAULT 0,
		IsActive tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (Product_ID),
		UNIQUE KEY ProductName (ProductName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createProductsTableSql);
	$productTypeColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'ProductType'");
	if (!$productTypeColumnCheck || $productTypeColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN ProductType varchar(255) NOT NULL DEFAULT '' AFTER ProductName");
	}
	$productBaseUnitColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'ProductBaseUnit'");
	if (!$productBaseUnitColumnCheck || $productBaseUnitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN ProductBaseUnit varchar(20) NOT NULL DEFAULT 'pc' AFTER ProductType");
	}
	$productSellingPriceColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'SellingPrice'");
	if (!$productSellingPriceColumnCheck || $productSellingPriceColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN SellingPrice decimal(12,2) NOT NULL DEFAULT 0.00 AFTER ProductPrice");
	}
	$productAlternateSellingPriceColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'AlternateSellingPrice'");
	if (!$productAlternateSellingPriceColumnCheck || $productAlternateSellingPriceColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN AlternateSellingPrice decimal(12,2) NOT NULL DEFAULT 0.00 AFTER SellingPrice");
	}
	$productStockLimitColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'StockLimit'");
	if (!$productStockLimitColumnCheck || $productStockLimitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN StockLimit decimal(12,2) NOT NULL DEFAULT 0.00 AFTER AlternateSellingPrice");
	}
	$productCanConvertColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'CanConvertToKg'");
	if (!$productCanConvertColumnCheck || $productCanConvertColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN CanConvertToKg tinyint(1) NOT NULL DEFAULT 0 AFTER SellingPrice");
	}
	$productKgEquivalentColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'KgEquivalentQty'");
	if (!$productKgEquivalentColumnCheck || $productKgEquivalentColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN KgEquivalentQty decimal(12,2) NOT NULL DEFAULT 0.00 AFTER CanConvertToKg");
	}
	$productAlternateSaleUnitColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'AlternateSaleUnit'");
	if (!$productAlternateSaleUnitColumnCheck || $productAlternateSaleUnitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN AlternateSaleUnit varchar(20) NOT NULL DEFAULT '' AFTER KgEquivalentQty");
	}
	$productLpgPriceColumns = [
		'LpgRefillPrice' => "ALTER TABLE products ADD COLUMN LpgRefillPrice decimal(12,2) NOT NULL DEFAULT 0.00 AFTER AlternateSellingPrice",
		'LpgNewTankPrice' => "ALTER TABLE products ADD COLUMN LpgNewTankPrice decimal(12,2) NOT NULL DEFAULT 0.00 AFTER LpgRefillPrice",
	];
	foreach ($productLpgPriceColumns as $columnName => $alterSql) {
		$columnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE '$columnName'");
		if (!$columnCheck || $columnCheck->num_rows === 0) {
			$connectDB->query($alterSql);
		}
	}
	$connectDB->query("
		UPDATE products
		SET LpgRefillPrice = SellingPrice
		WHERE (UPPER(ProductType) = 'LPG' OR LOWER(ProductName) LIKE '%lpg%' OR LOWER(ProductName) LIKE '%gasul%')
			AND LpgRefillPrice <= 0
			AND SellingPrice > 0
	");
	$connectDB->query("
		UPDATE products
		SET LpgNewTankPrice = CASE
			WHEN AlternateSellingPrice > 0 THEN AlternateSellingPrice
			ELSE SellingPrice
		END
		WHERE (UPPER(ProductType) = 'LPG' OR LOWER(ProductName) LIKE '%lpg%' OR LOWER(ProductName) LIKE '%gasul%')
			AND LpgNewTankPrice <= 0
			AND SellingPrice > 0
	");
	$connectDB->query("UPDATE products SET AlternateSaleUnit = 'kg' WHERE CanConvertToKg = 1 AND ProductBaseUnit = 'sack' AND AlternateSaleUnit = ''");
	$connectDB->query("UPDATE products SET AlternateSaleUnit = 'pc' WHERE CanConvertToKg = 1 AND ProductBaseUnit = 'tray' AND AlternateSaleUnit = ''");
	$connectDB->query("UPDATE products SET AlternateSaleUnit = 'kg' WHERE CanConvertToKg = 1 AND ProductBaseUnit = 'pc' AND AlternateSaleUnit = ''");
	$productParentColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'ParentProduct_ID'");
	if (!$productParentColumnCheck || $productParentColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN ParentProduct_ID int(11) NOT NULL DEFAULT 0 AFTER KgEquivalentQty");
	}
	$productIsSubColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'IsSubProduct'");
	if (!$productIsSubColumnCheck || $productIsSubColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN IsSubProduct tinyint(1) NOT NULL DEFAULT 0 AFTER ParentProduct_ID");
	}
	$productIsActiveColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'IsActive'");
	if (!$productIsActiveColumnCheck || $productIsActiveColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN IsActive tinyint(1) NOT NULL DEFAULT 1 AFTER IsSubProduct");
	}

	$createPurchasesTableSql = "CREATE TABLE IF NOT EXISTS purchases (
		ID int(11) NOT NULL AUTO_INCREMENT,
		PurchaseDate datetime NOT NULL,
		InvoiceNo int(11) NOT NULL,
		ProductName varchar(255) NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		ProductPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		TotalPurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Product_ID int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY (ID),
		KEY PurchaseDate (PurchaseDate),
		KEY InvoiceNo (InvoiceNo),
		KEY Product_ID (Product_ID)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createPurchasesTableSql);
	$purchaseDateColumnCheck = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'PurchaseDate'");
	if ($purchaseDateColumnCheck && $purchaseDateColumnCheck->num_rows > 0) {
		$purchaseDateColumn = $purchaseDateColumnCheck->fetch_assoc();
		if (strtolower((string) ($purchaseDateColumn['Type'] ?? '')) === 'date') {
			$connectDB->query("ALTER TABLE purchases MODIFY PurchaseDate datetime NOT NULL");
		}
	}
	$purchaseLpgColumns = [
		'LpgStockInType' => "ALTER TABLE purchases ADD COLUMN LpgStockInType enum('NONE','FULL_TANK','REFILL') NOT NULL DEFAULT 'NONE' AFTER TotalPurchasePrice",
		'LpgEmptyTankCondition' => "ALTER TABLE purchases ADD COLUMN LpgEmptyTankCondition enum('','NEW','OLD') NOT NULL DEFAULT '' AFTER LpgStockInType",
		'LpgTankPurchasePrice' => "ALTER TABLE purchases ADD COLUMN LpgTankPurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00 AFTER LpgEmptyTankCondition",
	];
	foreach ($purchaseLpgColumns as $columnName => $alterSql) {
		$purchaseColumnCheck = $connectDB->query("SHOW COLUMNS FROM purchases LIKE '$columnName'");
		if (!$purchaseColumnCheck || $purchaseColumnCheck->num_rows === 0) {
			$connectDB->query($alterSql);
		}
	}

	$productsTableCheck = $connectDB->query("SHOW TABLES LIKE 'products'");
	if ($productsTableCheck && $productsTableCheck->num_rows > 0) {
		$productsTableExists = true;
	}
	$purchasesTableCheck = $connectDB->query("SHOW TABLES LIKE 'purchases'");
	if ($purchasesTableCheck && $purchasesTableCheck->num_rows > 0) {
		$purchasesTableExists = true;
	}

	$createIncomeTableSql = "CREATE TABLE IF NOT EXISTS income (
		ID int(11) NOT NULL AUTO_INCREMENT,
		ProductName varchar(255) NOT NULL,
		PurchaseDate_From date NOT NULL,
		PurchaseDate_To date NOT NULL,
		SellingPrice float NOT NULL DEFAULT 0,
		Less float NOT NULL DEFAULT 0,
		PRIMARY KEY (ID),
		UNIQUE KEY ProductName (ProductName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createIncomeTableSql);
	$incomeProductIdColumnCheck = $connectDB->query("SHOW COLUMNS FROM income LIKE 'Product_ID'");
	if (!$incomeProductIdColumnCheck || $incomeProductIdColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE income ADD COLUMN Product_ID int(11) NOT NULL DEFAULT 0 AFTER ID");
		$connectDB->query("ALTER TABLE income ADD KEY Product_ID (Product_ID)");
	}

	$createExpensesTableSql = "CREATE TABLE IF NOT EXISTS expenses (
		ID int(11) NOT NULL AUTO_INCREMENT,
		ExpenseDate date NOT NULL,
		ExpenseNo int(11) NOT NULL,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		ExpenseCategory varchar(255) NOT NULL,
		Description varchar(255) NOT NULL,
		Amount decimal(12,2) NOT NULL DEFAULT 0.00,
		PRIMARY KEY (ID),
		KEY ExpenseDate (ExpenseDate),
		KEY ExpenseNo (ExpenseNo),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createExpensesTableSql);
	$expensesDeliveryColumnCheck = $connectDB->query("SHOW COLUMNS FROM expenses LIKE 'DeliveryNo'");
	if (!$expensesDeliveryColumnCheck || $expensesDeliveryColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE expenses ADD COLUMN DeliveryNo int(11) NOT NULL DEFAULT 0 AFTER ExpenseNo");
		$connectDB->query("ALTER TABLE expenses ADD KEY DeliveryNo (DeliveryNo)");
	}

	$createExpenseCategoriesTableSql = "CREATE TABLE IF NOT EXISTS expense_categories (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CategoryName varchar(255) NOT NULL,
		IsActive tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (ID),
		UNIQUE KEY CategoryName (CategoryName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createExpenseCategoriesTableSql);
	$expenseCategoryIsActiveColumnCheck = $connectDB->query("SHOW COLUMNS FROM expense_categories LIKE 'IsActive'");
	if (!$expenseCategoryIsActiveColumnCheck || $expenseCategoryIsActiveColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE expense_categories ADD COLUMN IsActive tinyint(1) NOT NULL DEFAULT 1 AFTER CategoryName");
	}

	$createSalesTableSql = "CREATE TABLE IF NOT EXISTS sales (
		ID int(11) NOT NULL AUTO_INCREMENT,
		SaleDate datetime NOT NULL,
		DeliveryNo int(11) NOT NULL,
		CustomerName varchar(255) NOT NULL,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		SaleUnit varchar(20) NOT NULL DEFAULT 'pc',
		KgConversionQty decimal(12,2) NOT NULL DEFAULT 0.00,
		UnitPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Less decimal(12,2) NOT NULL DEFAULT 0.00,
		TotalSalePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Notes varchar(255) NOT NULL,
		SalesType enum('OTHERS','LPG') NOT NULL DEFAULT 'OTHERS',
		PaymentStatus enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID',
		AmountPaid decimal(12,2) NOT NULL DEFAULT 0.00,
		DueDate date DEFAULT NULL,
		LpgTransactionType enum('NONE','SWAPPED','SOLD','LENT') NOT NULL DEFAULT 'NONE',
		LpgTankCondition varchar(255) NOT NULL DEFAULT '',
		LpgTankPayment decimal(12,2) NOT NULL DEFAULT 0.00,
		IsReturned tinyint(1) NOT NULL DEFAULT 0,
		ReturnedAt datetime DEFAULT NULL,
		PRIMARY KEY (ID),
		KEY SaleDate (SaleDate),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createSalesTableSql);
	$salesLessColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'Less'");
	if (!$salesLessColumnCheck || $salesLessColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD COLUMN Less decimal(12,2) NOT NULL DEFAULT 0.00 AFTER UnitPrice");
	}
	$salesUnitColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'SaleUnit'");
	if (!$salesUnitColumnCheck || $salesUnitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD COLUMN SaleUnit varchar(20) NOT NULL DEFAULT 'pc' AFTER Quantity");
	}
	$salesKgConversionColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'KgConversionQty'");
	if (!$salesKgConversionColumnCheck || $salesKgConversionColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD COLUMN KgConversionQty decimal(12,2) NOT NULL DEFAULT 0.00 AFTER SaleUnit");
	}
	$salesExtraColumns = [
		'SalesType' => "ALTER TABLE sales ADD COLUMN SalesType enum('OTHERS','LPG') NOT NULL DEFAULT 'OTHERS' AFTER Notes",
		'PaymentStatus' => "ALTER TABLE sales ADD COLUMN PaymentStatus enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID' AFTER Notes",
		'AmountPaid' => "ALTER TABLE sales ADD COLUMN AmountPaid decimal(12,2) NOT NULL DEFAULT 0.00 AFTER PaymentStatus",
		'DueDate' => "ALTER TABLE sales ADD COLUMN DueDate date DEFAULT NULL AFTER AmountPaid",
		'LpgTransactionType' => "ALTER TABLE sales ADD COLUMN LpgTransactionType enum('NONE','SWAPPED','SOLD','LENT') NOT NULL DEFAULT 'NONE' AFTER DueDate",
		'LpgTankCondition' => "ALTER TABLE sales ADD COLUMN LpgTankCondition varchar(255) NOT NULL DEFAULT '' AFTER LpgTransactionType",
		'LpgTankPayment' => "ALTER TABLE sales ADD COLUMN LpgTankPayment decimal(12,2) NOT NULL DEFAULT 0.00 AFTER LpgTankCondition",
		'IsReturned' => "ALTER TABLE sales ADD COLUMN IsReturned tinyint(1) NOT NULL DEFAULT 0 AFTER LpgTankPayment",
		'ReturnedAt' => "ALTER TABLE sales ADD COLUMN ReturnedAt datetime DEFAULT NULL AFTER IsReturned",
		'TotalPurchaseCost' => "ALTER TABLE sales ADD COLUMN TotalPurchaseCost decimal(12,2) NOT NULL DEFAULT 0.00 AFTER TotalSalePrice",
		'GrossProfit' => "ALTER TABLE sales ADD COLUMN GrossProfit decimal(12,2) NOT NULL DEFAULT 0.00 AFTER TotalPurchaseCost",
	];
	foreach ($salesExtraColumns as $columnName => $alterSql) {
		$salesColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE '$columnName'");
		if (!$salesColumnCheck || $salesColumnCheck->num_rows === 0) {
			$connectDB->query($alterSql);
		}
	}
	$salesDateColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'SaleDate'");
	if ($salesDateColumnCheck && ($salesDateColumn = $salesDateColumnCheck->fetch_assoc()) && stripos((string) ($salesDateColumn['Type'] ?? ''), 'datetime') === false) {
		$connectDB->query("ALTER TABLE sales MODIFY SaleDate datetime NOT NULL");
	}

	$createInventoryBatchesTableSql = "CREATE TABLE IF NOT EXISTS inventory_batches (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		BatchDate datetime NOT NULL,
		BatchLabel enum('OLD','NEW') NOT NULL DEFAULT 'NEW',
		QuantityIn decimal(12,2) NOT NULL DEFAULT 0.00,
		QuantityRemaining decimal(12,2) NOT NULL DEFAULT 0.00,
		UnitCost decimal(12,2) NOT NULL DEFAULT 0.00,
		SourceType varchar(30) NOT NULL DEFAULT 'PURCHASE',
		SourceReference varchar(100) NOT NULL DEFAULT '',
		CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		KEY Product_ID (Product_ID),
		KEY ProductName (ProductName),
		KEY BatchDate (BatchDate),
		KEY BatchLabel (BatchLabel)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createInventoryBatchesTableSql);
	$inventoryBatchTankCostColumnCheck = $connectDB->query("SHOW COLUMNS FROM inventory_batches LIKE 'LpgTankUnitCost'");
	if (!$inventoryBatchTankCostColumnCheck || $inventoryBatchTankCostColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE inventory_batches ADD COLUMN LpgTankUnitCost decimal(12,2) NOT NULL DEFAULT 0.00 AFTER UnitCost");
	}

	$createInventoryMovementsTableSql = "CREATE TABLE IF NOT EXISTS inventory_movements (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		Batch_ID int(11) NOT NULL DEFAULT 0,
		MovementDate datetime NOT NULL,
		MovementType enum('IN','OUT','RETURN') NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		ReferenceType varchar(30) NOT NULL DEFAULT '',
		ReferenceNo varchar(100) NOT NULL DEFAULT '',
		Notes varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (ID),
		KEY Product_ID (Product_ID),
		KEY Batch_ID (Batch_ID),
		KEY MovementDate (MovementDate),
		KEY MovementType (MovementType)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createInventoryMovementsTableSql);
	$inventoryMovementUnitCostColumnCheck = $connectDB->query("SHOW COLUMNS FROM inventory_movements LIKE 'UnitCost'");
	if (!$inventoryMovementUnitCostColumnCheck || $inventoryMovementUnitCostColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE inventory_movements ADD COLUMN UnitCost decimal(12,2) NOT NULL DEFAULT 0.00 AFTER Quantity");
	}
	$inventoryMovementTotalCostColumnCheck = $connectDB->query("SHOW COLUMNS FROM inventory_movements LIKE 'TotalCost'");
	if (!$inventoryMovementTotalCostColumnCheck || $inventoryMovementTotalCostColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE inventory_movements ADD COLUMN TotalCost decimal(12,2) NOT NULL DEFAULT 0.00 AFTER UnitCost");
	}

	$createReturnsTableSql = "CREATE TABLE IF NOT EXISTS product_returns (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Sale_ID int(11) NOT NULL DEFAULT 0,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		ReturnDate datetime NOT NULL,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		Reason varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (ID),
		KEY Sale_ID (Sale_ID),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createReturnsTableSql);

	$createCustomerAccountsTableSql = "CREATE TABLE IF NOT EXISTS customer_accounts (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CustomerName varchar(255) NOT NULL,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		SaleDate datetime NOT NULL,
		DueDate date DEFAULT NULL,
		TotalAmount decimal(12,2) NOT NULL DEFAULT 0.00,
		AmountPaid decimal(12,2) NOT NULL DEFAULT 0.00,
		Balance decimal(12,2) NOT NULL DEFAULT 0.00,
		PaymentStatus enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID',
		UpdatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		UNIQUE KEY DeliveryNo (DeliveryNo),
		KEY CustomerName (CustomerName),
		KEY DueDate (DueDate),
		KEY PaymentStatus (PaymentStatus)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCustomerAccountsTableSql);
	$customerAccountSaleDateColumnCheck = $connectDB->query("SHOW COLUMNS FROM customer_accounts LIKE 'SaleDate'");
	if ($customerAccountSaleDateColumnCheck && ($customerAccountSaleDateColumn = $customerAccountSaleDateColumnCheck->fetch_assoc()) && stripos((string) ($customerAccountSaleDateColumn['Type'] ?? ''), 'datetime') === false) {
		$connectDB->query("ALTER TABLE customer_accounts MODIFY SaleDate datetime NOT NULL");
	}

	$createCustomersTableSql = "CREATE TABLE IF NOT EXISTS customers (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CustomerName varchar(255) NOT NULL,
		Address varchar(255) NOT NULL DEFAULT '',
		GoogleMap varchar(500) NOT NULL DEFAULT '',
		CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UpdatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		UNIQUE KEY CustomerName (CustomerName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCustomersTableSql);
	$connectDB->query("
		INSERT IGNORE INTO customers (CustomerName)
		SELECT DISTINCT CustomerName
		FROM customer_accounts
		WHERE CustomerName <> ''
			AND CustomerName <> '" . mysqli_real_escape_string($connectDB, junkshop_walk_in_customer_name()) . "'
	");

	$createCustomerPaymentsTableSql = "CREATE TABLE IF NOT EXISTS customer_payments (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CustomerName varchar(255) NOT NULL,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		PaymentDate datetime NOT NULL,
		Amount decimal(12,2) NOT NULL DEFAULT 0.00,
		Notes varchar(255) NOT NULL DEFAULT '',
		CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		KEY CustomerName (CustomerName),
		KEY DeliveryNo (DeliveryNo),
		KEY PaymentDate (PaymentDate)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCustomerPaymentsTableSql);

	$createLpgTankBalancesTableSql = "CREATE TABLE IF NOT EXISTS lpg_tank_balances (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL DEFAULT '',
		TankCondition enum('NEW','OLD') NOT NULL DEFAULT 'NEW',
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		UpdatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		UNIQUE KEY ProductCondition (Product_ID, TankCondition),
		KEY Product_ID (Product_ID)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createLpgTankBalancesTableSql);

	$createLpgTankLoansTableSql = "CREATE TABLE IF NOT EXISTS lpg_tank_loans (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CustomerName varchar(255) NOT NULL,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL DEFAULT '',
		Quantity decimal(12,2) NOT NULL DEFAULT 1.00,
		LoanDate datetime NOT NULL,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		Sale_ID int(11) NOT NULL DEFAULT 0,
		Status enum('LENT','RETURNED') NOT NULL DEFAULT 'LENT',
		ReturnCondition enum('','NEW','OLD') NOT NULL DEFAULT '',
		ReturnedAt datetime DEFAULT NULL,
		Notes varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (ID),
		KEY CustomerName (CustomerName),
		KEY Product_ID (Product_ID),
		KEY Status (Status),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createLpgTankLoansTableSql);
	$lpgLoanConditionColumnCheck = $connectDB->query("SHOW COLUMNS FROM lpg_tank_loans LIKE 'LoanCondition'");
	if (!$lpgLoanConditionColumnCheck || $lpgLoanConditionColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE lpg_tank_loans ADD COLUMN LoanCondition enum('','NEW','OLD') NOT NULL DEFAULT '' AFTER Status");
	}

	$salesLpgTransactionTypeColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'LpgTransactionType'");
	if ($salesLpgTransactionTypeColumnCheck && ($salesLpgTransactionTypeColumn = $salesLpgTransactionTypeColumnCheck->fetch_assoc())) {
		if (stripos((string) ($salesLpgTransactionTypeColumn['Type'] ?? ''), 'LENT') === false) {
			$connectDB->query("ALTER TABLE sales MODIFY LpgTransactionType enum('NONE','SWAPPED','SOLD','LENT') NOT NULL DEFAULT 'NONE'");
		}
	}
	$salesProductIdIndexCheck = $connectDB->query("SHOW INDEX FROM sales WHERE Key_name = 'Product_ID'");
	if (!$salesProductIdIndexCheck || $salesProductIdIndexCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD KEY Product_ID (Product_ID)");
	}

	junkshop_backfill_product_ids($connectDB);

	$createSaleAppLinksTableSql = "CREATE TABLE IF NOT EXISTS sale_app_links (
		ID int(11) NOT NULL AUTO_INCREMENT,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		SaleRowKey varchar(50) NOT NULL,
		SaleProductName varchar(255) NOT NULL,
		SourceProducts text NOT NULL,
		AveragePurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		TotalPurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		PRIMARY KEY (ID),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createSaleAppLinksTableSql);

	$createCompanyProfileTableSql = "CREATE TABLE IF NOT EXISTS company_profile (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CompanyName varchar(255) NOT NULL,
		AddressLine1 varchar(255) NOT NULL,
		AddressLine2 varchar(255) NOT NULL,
		ContactNumber varchar(255) NOT NULL,
		TinNumber varchar(255) NOT NULL,
		PRIMARY KEY (ID)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCompanyProfileTableSql);

	$companyProfileCount = $connectDB->query("SELECT COUNT(*) AS total FROM company_profile");
	$companyProfileRowCount = $companyProfileCount ? (int) (($companyProfileCount->fetch_assoc()['total'] ?? 0)) : 0;
	if ($companyProfileRowCount === 0) {
		$connectDB->query("INSERT INTO company_profile (CompanyName, AddressLine1, AddressLine2, ContactNumber, TinNumber) VALUES ('UNO CGT Rice Trading', '170 R. Martinez Street, Brgy 9', 'Nasugbu, Batangas, 4231', '0921-500-6487', '220-138-725-000')");
	}

	if ($productsTableExists) {
		if ($purchasesTableExists) {
			$seedIncomeTableSql = "INSERT INTO income (Product_ID, ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less)
				SELECT
					p.Product_ID,
					p.ProductName,
					COALESCE(
						(SELECT MIN(PurchaseDate) FROM purchases pu WHERE pu.Product_ID = p.Product_ID OR (pu.Product_ID = 0 AND pu.ProductName = p.ProductName)),
						CURDATE()
					) AS PurchaseDate_From,
					COALESCE(
						(SELECT MAX(PurchaseDate) FROM purchases pu WHERE pu.Product_ID = p.Product_ID OR (pu.Product_ID = 0 AND pu.ProductName = p.ProductName)),
						CURDATE()
					) AS PurchaseDate_To,
					ROUND(p.ProductPrice * 1.20, 2) AS SellingPrice,
					5.00 AS Less
				FROM products p
				LEFT JOIN income i ON i.Product_ID = p.Product_ID
				WHERE i.ID IS NULL AND p.Product_ID > 0";
		} else {
			$seedIncomeTableSql = "INSERT INTO income (Product_ID, ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less)
				SELECT
					p.Product_ID,
					p.ProductName,
					CURDATE() AS PurchaseDate_From,
					CURDATE() AS PurchaseDate_To,
					ROUND(p.ProductPrice * 1.20, 2) AS SellingPrice,
					5.00 AS Less
				FROM products p
				LEFT JOIN income i ON i.Product_ID = p.Product_ID
				WHERE i.ID IS NULL AND p.Product_ID > 0";
		}

		$connectDB->query($seedIncomeTableSql);
	}

	$connectDB->query("
		UPDATE products p
		LEFT JOIN income i ON i.Product_ID = p.Product_ID
		SET p.SellingPrice = CASE
			WHEN p.SellingPrice > 0 THEN p.SellingPrice
			WHEN i.SellingPrice IS NOT NULL AND i.SellingPrice > 0 THEN ROUND(i.SellingPrice, 2)
			ELSE ROUND(p.ProductPrice * 1.20, 2)
		END
		WHERE COALESCE(p.IsSubProduct, 0) = 0
	");

	$legacySoldProductsCheck = $connectDB->query("SHOW TABLES LIKE 'sold_products'");
	if ($legacySoldProductsCheck && $legacySoldProductsCheck->num_rows > 0) {
		$connectDB->query("
			INSERT INTO products (ProductName, ProductType, ProductBaseUnit, ProductPrice, SellingPrice, CanConvertToKg, KgEquivalentQty, ParentProduct_ID, IsSubProduct, IsActive)
			SELECT
				sp.ProductName,
				sp.ProductType,
				sp.ProductBaseUnit,
				0.00,
				sp.ProductPrice,
				sp.CanConvertToKg,
				sp.KgEquivalentQty,
				0,
				1,
				sp.IsActive
			FROM sold_products sp
			LEFT JOIN products p ON p.ProductName = sp.ProductName
			WHERE p.Product_ID IS NULL
		");
	}

	$seedExpenseCategoriesSql = "INSERT IGNORE INTO expense_categories (CategoryName) VALUES
		('Employee Salary'),
		('Gas'),
		('Delivery Expenses'),
		('LPG Handling'),
		('Cold Storage'),
		('Office Supplies'),
		('Electric Bill'),
		('Water Bill'),
		('Internet Bill'),
		('Others')";
	$connectDB->query($seedExpenseCategoriesSql);
	$connectDB->query("UPDATE products SET ProductType = 'Rice' WHERE ProductType = '' AND (ProductName LIKE '%rice%' OR ProductName LIKE '%bigas%' OR ProductName LIKE '%dinorado%' OR ProductName LIKE '%sinandomeng%')");
	$connectDB->query("UPDATE products SET ProductType = 'LPG' WHERE ProductType = '' AND COALESCE(IsSubProduct, 0) = 0 AND (ProductName LIKE '%lpg%' OR ProductName LIKE '%gasul%')");
	$connectDB->query("UPDATE products SET ProductType = 'Frozen Foods' WHERE ProductType = '' AND (ProductName LIKE '%frozen%' OR ProductName LIKE '%hotdog%' OR ProductName LIKE '%tocino%' OR ProductName LIKE '%longganisa%')");
	$connectDB->query("UPDATE products SET ProductType = 'Eggs' WHERE ProductType = '' AND ProductName LIKE '%egg%'");
	$connectDB->query("UPDATE products SET ProductType = 'LPG Tank' WHERE COALESCE(IsSubProduct, 0) = 1 AND ParentProduct_ID > 0 AND UPPER(ProductType) = 'LPG'");
	$lpgParentProductsResult = $connectDB->query("
		SELECT Product_ID, ProductName, ProductBaseUnit, IsActive
		FROM products
		WHERE COALESCE(IsSubProduct, 0) = 0
			AND UPPER(COALESCE(ProductType, '')) <> 'LPG TANK'
			AND (
				UPPER(COALESCE(ProductType, '')) = 'LPG'
				OR LOWER(ProductName) LIKE '%lpg%'
				OR LOWER(ProductName) LIKE '%gasul%'
			)
		ORDER BY Product_ID ASC
	");
	if ($lpgParentProductsResult && $lpgParentProductsResult->num_rows > 0) {
		while ($lpgParentProduct = $lpgParentProductsResult->fetch_assoc()) {
			junkshop_ensure_lpg_tank_product(
				$connectDB,
				(int) ($lpgParentProduct['Product_ID'] ?? 0),
				trim((string) ($lpgParentProduct['ProductName'] ?? '')),
				trim((string) ($lpgParentProduct['ProductBaseUnit'] ?? 'tank')),
				(int) ($lpgParentProduct['IsActive'] ?? 1)
			);
		}
	}

	$companyProfileResult = $connectDB->query("SELECT * FROM company_profile ORDER BY ID ASC LIMIT 1");
	$companyProfile = $companyProfileResult && $companyProfileResult->num_rows > 0
		? $companyProfileResult->fetch_assoc()
		: [
			'ID' => 1,
			'CompanyName' => "UNO CGT Rice Trading",
			'AddressLine1' => '170 R. Martinez Street, Brgy 9',
			'AddressLine2' => 'Nasugbu, Batangas, 4231',
			'ContactNumber' => '0921-500-6487',
			'TinNumber' => '220-138-725-000',
		];

	$companyProfileId = (int) ($companyProfile['ID'] ?? 1);
	$companyName = trim((string) ($companyProfile['CompanyName'] ?? 'UNO CGT Rice Trading'));
	$companyAddressLine1 = trim((string) ($companyProfile['AddressLine1'] ?? ''));
	$companyAddressLine2 = trim((string) ($companyProfile['AddressLine2'] ?? ''));
	$companyContactNumber = trim((string) ($companyProfile['ContactNumber'] ?? ''));
	$companyTinNumber = trim((string) ($companyProfile['TinNumber'] ?? ''));

	if (!function_exists('junkshop_company_initials')) {
		function junkshop_company_initials($name) {
			$cleanName = preg_replace('/[^A-Za-z0-9 ]+/', '', (string) $name);
			$parts = preg_split('/\s+/', trim($cleanName));
			$initials = '';
			foreach ($parts as $part) {
				if ($part === '') {
					continue;
				}
				$initials .= strtoupper(substr($part, 0, 1));
				if (strlen($initials) >= 2) {
					break;
				}
			}
			return $initials !== '' ? $initials : 'RT';
		}
	}

	$companyInitials = junkshop_company_initials($companyName);

	$server = "/uno_cgt_rice/";
?>
