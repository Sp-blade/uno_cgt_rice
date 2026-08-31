<?php
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		header('Location: ' . $server . '?mainmenu=repack_product&repack_status=error&repack_error=' . urlencode('Invalid repack request.'));
		exit;
	}

	$repackDate = junkshop_normalize_datetime($_POST['repack_date'] ?? date('Y-m-d H:i:s'));
	$sourceProductId = (int) ($_POST['source_product_id'] ?? 0);
	$targetProductId = (int) ($_POST['target_product_id'] ?? 0);
	$sourceQty = round((float) ($_POST['source_qty'] ?? 0), 2);
	$sourceUnit = junkshop_normalize_base_unit($_POST['source_unit'] ?? 'pc');
	$repackNotes = trim((string) ($_POST['repack_notes'] ?? ''));

	$result = junkshop_repack_product(
		$connectDB,
		$sourceProductId,
		$targetProductId,
		$sourceQty,
		$repackDate,
		$repackNotes,
		$sourceUnit
	);

	if (!empty($result['success'])) {
		$query = http_build_query([
			'mainmenu' => 'repack_product',
			'repack_status' => 'success',
			'repack_id' => (int) ($result['repack_id'] ?? 0),
			'source_qty' => (float) ($result['source_qty'] ?? 0),
			'source_unit' => (string) ($result['source_unit'] ?? 'pc'),
			'target_qty' => (float) ($result['target_qty'] ?? 0),
			'target_unit' => (string) ($result['target_unit'] ?? 'pc'),
		]);
		header('Location: ' . $server . '?' . $query);
		exit;
	}

	$errorMessage = trim((string) ($result['message'] ?? 'Unable to complete repack.'));
	header('Location: ' . $server . '?mainmenu=repack_product&repack_status=error&repack_error=' . urlencode($errorMessage));
	exit;
