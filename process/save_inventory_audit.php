<?php
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		header('Location: ' . $server . '?mainmenu=inventory_audit&audit_status=error&audit_error=' . urlencode('Invalid audit request.'));
		exit;
	}

	$auditDate = junkshop_normalize_datetime($_POST['audit_date'] ?? date('Y-m-d H:i:s'));
	$auditNotes = trim((string) ($_POST['audit_notes'] ?? ''));
	$productIds = isset($_POST['product_id']) && is_array($_POST['product_id']) ? $_POST['product_id'] : [];
	$systemQtys = isset($_POST['system_qty']) && is_array($_POST['system_qty']) ? $_POST['system_qty'] : [];
	$systemWholeQtys = isset($_POST['system_whole_qty']) && is_array($_POST['system_whole_qty']) ? $_POST['system_whole_qty'] : [];
	$systemOpenedQtys = isset($_POST['system_opened_qty']) && is_array($_POST['system_opened_qty']) ? $_POST['system_opened_qty'] : [];
	$actualWholeQtys = isset($_POST['actual_whole_qty']) && is_array($_POST['actual_whole_qty']) ? $_POST['actual_whole_qty'] : [];
	$actualOpenedQtys = isset($_POST['actual_opened_qty']) && is_array($_POST['actual_opened_qty']) ? $_POST['actual_opened_qty'] : [];
	$supportsSplitFlags = isset($_POST['supports_split']) && is_array($_POST['supports_split']) ? $_POST['supports_split'] : [];
	$baseUnits = isset($_POST['base_unit']) && is_array($_POST['base_unit']) ? $_POST['base_unit'] : [];
	$alternateUnits = isset($_POST['alternate_unit']) && is_array($_POST['alternate_unit']) ? $_POST['alternate_unit'] : [];
	$equivQtys = isset($_POST['equiv_qty']) && is_array($_POST['equiv_qty']) ? $_POST['equiv_qty'] : [];
	$itemNotes = isset($_POST['item_notes']) && is_array($_POST['item_notes']) ? $_POST['item_notes'] : [];

	$auditItems = [];
	$itemCount = min(count($productIds), count($systemQtys), count($actualWholeQtys));
	for ($index = 0; $index < $itemCount; $index++) {
		$actualWholeRaw = trim((string) ($actualWholeQtys[$index] ?? ''));
		$actualOpenedRaw = trim((string) ($actualOpenedQtys[$index] ?? ''));
		if ($actualWholeRaw === '' && $actualOpenedRaw === '') {
			continue;
		}

		$productId = (int) ($productIds[$index] ?? 0);
		if ($productId <= 0) {
			continue;
		}

		$supportsSplit = (int) ($supportsSplitFlags[$index] ?? 0) === 1;
		$baseUnit = junkshop_normalize_base_unit($baseUnits[$index] ?? 'pc');
		$alternateUnit = junkshop_normalize_base_unit($alternateUnits[$index] ?? '');
		$equivQty = (float) ($equivQtys[$index] ?? 0);
		$systemWholeQty = round((float) ($systemWholeQtys[$index] ?? 0), 2);
		$systemOpenedQty = round((float) ($systemOpenedQtys[$index] ?? 0), 2);
		$actualWholeQty = $actualWholeRaw === '' ? 0.0 : round((float) $actualWholeRaw, 2);
		$actualOpenedQty = $actualOpenedRaw === '' ? 0.0 : round((float) $actualOpenedRaw, 2);
		$systemQty = round((float) ($systemQtys[$index] ?? 0), 2);
		if ($systemQty <= 0.009) {
			$systemQty = junkshop_effective_base_stock(
				$systemWholeQty,
				$systemOpenedQty,
				$baseUnit,
				$supportsSplit ? 1 : 0,
				$equivQty,
				$alternateUnit
			);
		}
		$actualQty = junkshop_effective_base_stock(
			$actualWholeQty,
			$actualOpenedQty,
			$baseUnit,
			$supportsSplit ? 1 : 0,
			$equivQty,
			$alternateUnit
		);
		$varianceQty = round($actualQty - $systemQty, 2);
		$comparisonDifference = $supportsSplit && $equivQty > 0
			? round((($actualWholeQty - $systemWholeQty) * $equivQty) + ($actualOpenedQty - $systemOpenedQty), 2)
			: round($actualWholeQty - $systemWholeQty, 2);
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
			'base_unit' => $baseUnit,
			'alternate_unit' => $supportsSplit ? $alternateUnit : '',
			'equivalent_qty' => $supportsSplit ? $equivQty : 0,
			'system_qty' => $systemQty,
			'system_whole_qty' => $systemWholeQty,
			'system_opened_qty' => $systemOpenedQty,
			'actual_qty' => $actualQty,
			'actual_whole_qty' => $actualWholeQty,
			'actual_opened_qty' => $actualOpenedQty,
			'variance_qty' => $varianceQty,
			'comparison_difference' => $comparisonDifference,
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
		$variance = (float) ($item['comparison_difference'] ?? 0);
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
			$safeAlternateUnit = mysqli_real_escape_string($connectDB, (string) ($item['alternate_unit'] ?? ''));
			$safeEquivalentQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['equivalent_qty'] ?? 0), 2, '.', ''));
			$safeSystemQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['system_qty'] ?? 0), 2, '.', ''));
			$safeSystemWholeQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['system_whole_qty'] ?? 0), 2, '.', ''));
			$safeSystemOpenedQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['system_opened_qty'] ?? 0), 2, '.', ''));
			$safeActualQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['actual_qty'] ?? 0), 2, '.', ''));
			$safeActualWholeQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['actual_whole_qty'] ?? 0), 2, '.', ''));
			$safeActualOpenedQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['actual_opened_qty'] ?? 0), 2, '.', ''));
			$safeVarianceQty = mysqli_real_escape_string($connectDB, number_format((float) ($item['variance_qty'] ?? 0), 2, '.', ''));
			$safeItemNotes = mysqli_real_escape_string($connectDB, (string) ($item['item_notes'] ?? ''));

			if (!$connectDB->query("
				INSERT INTO inventory_audit_items (
					Audit_ID, Product_ID, ProductName, Category, BaseUnit, AlternateUnit, EquivalentQty,
					SystemQty, SystemWholeQty, SystemOpenedQty,
					ActualQty, ActualWholeQty, ActualOpenedQty,
					VarianceQty, ItemNotes
				)
				VALUES (
					'$auditId', '$safeProductId', '$safeProductName', '$safeCategory', '$safeBaseUnit', '$safeAlternateUnit', '$safeEquivalentQty',
					'$safeSystemQty', '$safeSystemWholeQty', '$safeSystemOpenedQty',
					'$safeActualQty', '$safeActualWholeQty', '$safeActualOpenedQty',
					'$safeVarianceQty', '$safeItemNotes'
				)
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
