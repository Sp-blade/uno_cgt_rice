<?php
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode('Invalid audit request.'));
		exit;
	}

	$auditDate = junkshop_normalize_datetime($_POST['audit_date'] ?? date('Y-m-d H:i:s'));
	$auditNotes = trim((string) ($_POST['audit_notes'] ?? ''));
	$productIds = isset($_POST['product_id']) && is_array($_POST['product_id']) ? $_POST['product_id'] : [];
	$systemQtys = isset($_POST['system_qty']) && is_array($_POST['system_qty']) ? $_POST['system_qty'] : [];
	$actualQtys = isset($_POST['actual_qty']) && is_array($_POST['actual_qty']) ? $_POST['actual_qty'] : [];
	$itemNotes = isset($_POST['item_notes']) && is_array($_POST['item_notes']) ? $_POST['item_notes'] : [];

	$auditItems = [];
	$itemCount = min(count($productIds), count($systemQtys), count($actualQtys));
	for ($index = 0; $index < $itemCount; $index++) {
		if (!array_key_exists($index, $actualQtys)) {
			continue;
		}
		$actualRaw = trim((string) $actualQtys[$index]);
		if ($actualRaw === '') {
			continue;
		}

		$productId = (int) ($productIds[$index] ?? 0);
		if ($productId <= 0) {
			continue;
		}

		$systemQty = round((float) ($systemQtys[$index] ?? 0), 2);
		$actualQty = round((float) $actualRaw, 2);
		$varianceQty = round($actualQty - $systemQty, 2);
		$note = trim((string) ($itemNotes[$index] ?? ''));

		$productResult = $connectDB->query("
			SELECT ProductName, ProductType, ProductBaseUnit, IsSubProduct
			FROM products
			WHERE Product_ID = '$productId' AND IsActive = 1
			LIMIT 1
		");
		if (!$productResult || !($productRow = $productResult->fetch_assoc())) {
			continue;
		}
		if ((int) ($productRow['IsSubProduct'] ?? 0) === 1 || strtoupper(trim((string) ($productRow['ProductType'] ?? ''))) === 'LPG TANK') {
			continue;
		}

		$auditItems[] = [
			'product_id' => $productId,
			'product_name' => trim((string) ($productRow['ProductName'] ?? '')),
			'category' => trim((string) ($productRow['ProductType'] ?? '')) !== '' ? trim((string) $productRow['ProductType']) : 'Products',
			'base_unit' => junkshop_normalize_base_unit($productRow['ProductBaseUnit'] ?? 'pc'),
			'system_qty' => $systemQty,
			'actual_qty' => $actualQty,
			'variance_qty' => $varianceQty,
			'item_notes' => $note,
		];
	}

	if (empty($auditItems)) {
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode('Enter at least one actual quantity before saving the audit.'));
		exit;
	}

	$matchCount = 0;
	$overCount = 0;
	$underCount = 0;
	foreach ($auditItems as $item) {
		$variance = (float) ($item['variance_qty'] ?? 0);
		if (abs($variance) < 0.009) {
			$matchCount++;
		} elseif ($variance > 0) {
			$overCount++;
		} else {
			$underCount++;
		}
	}

	$safeAuditDate = mysqli_real_escape_string($connectDB, $auditDate);
	$safeAuditNotes = mysqli_real_escape_string($connectDB, $auditNotes);
	$productCount = count($auditItems);

	$connectDB->begin_transaction();
	try {
		if (!$connectDB->query("
			INSERT INTO inventory_audits (AuditDate, Notes, ProductCount, MatchCount, OverCount, UnderCount)
			VALUES ('$safeAuditDate', '$safeAuditNotes', '$productCount', '$matchCount', '$overCount', '$underCount')
		")) {
			throw new Exception('Unable to save inventory audit.');
		}

		$auditId = (int) $connectDB->insert_id;
		if ($auditId <= 0) {
			throw new Exception('Unable to create inventory audit record.');
		}

		foreach ($auditItems as $item) {
			$safeProductId = (int) ($item['product_id'] ?? 0);
			$safeProductName = mysqli_real_escape_string($connectDB, (string) ($item['product_name'] ?? ''));
			$safeCategory = mysqli_real_escape_string($connectDB, (string) ($item['category'] ?? ''));
			$safeBaseUnit = mysqli_real_escape_string($connectDB, (string) ($item['base_unit'] ?? 'pc'));
			$safeSystemQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['system_qty'] ?? 0), 2, '.', ''));
			$safeActualQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['actual_qty'] ?? 0), 2, '.', ''));
			$safeVarianceQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['variance_qty'] ?? 0), 2, '.', ''));
			$safeItemNotes = mysqli_real_escape_string($connectDB, (string) ($item['item_notes'] ?? ''));

			if (!$connectDB->query("
				INSERT INTO inventory_audit_items (Audit_ID, Product_ID, ProductName, Category, BaseUnit, SystemQty, ActualQty, VarianceQty, ItemNotes)
				VALUES ('$auditId', '$safeProductId', '$safeProductName', '$safeCategory', '$safeBaseUnit', '$safeSystemQty', '$safeActualQty', '$safeVarianceQty', '$safeItemNotes')
			")) {
				throw new Exception('Unable to save one or more audit line items.');
			}
		}

		$connectDB->commit();
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_id=' . $auditId . '&audit_status=success');
		exit;
	} catch (Exception $exception) {
		$connectDB->rollback();
		$errorMessage = trim($exception->getMessage());
		if ($errorMessage === '') {
			$errorMessage = 'Unable to save inventory audit.';
		}
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode($errorMessage));
		exit;
	}
