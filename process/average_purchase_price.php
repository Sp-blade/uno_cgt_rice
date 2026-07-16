<?php
header('Content-Type: application/json');

$dateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$productNames = isset($_GET['products']) ? $_GET['products'] : [];

if (!is_array($productNames)) {
	$productNames = [$productNames];
}

$productNames = array_values(array_unique(array_filter(array_map('trim', $productNames), function ($productName) {
	return $productName !== '';
})));

if ($dateFrom === '' || $dateTo === '') {
	http_response_code(422);
	echo json_encode([
		'success' => false,
		'message' => 'Please select both Date From and Date To.',
	]);
	exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
	http_response_code(422);
	echo json_encode([
		'success' => false,
		'message' => 'Invalid date format.',
	]);
	exit;
}

if ($dateFrom > $dateTo) {
	http_response_code(422);
	echo json_encode([
		'success' => false,
		'message' => 'Date From must not be later than Date To.',
	]);
	exit;
}

if (count($productNames) === 0) {
	http_response_code(422);
	echo json_encode([
		'success' => false,
		'message' => 'Please select at least one product.',
	]);
	exit;
}

$escapedProductNames = array_map(function ($productName) use ($connectDB) {
	return "'" . mysqli_real_escape_string($connectDB, $productName) . "'";
}, $productNames);

$safeDateFrom = mysqli_real_escape_string($connectDB, $dateFrom);
$safeDateTo = mysqli_real_escape_string($connectDB, $dateTo);
$productListSql = implode(',', $escapedProductNames);

$averageResult = $connectDB->query("
	SELECT
		pu.ProductName,
		COUNT(*) AS purchase_count,
			SUM(
				CASE
					WHEN COALESCE(p.ProductBaseUnit, 'pc') = 'pc' AND COALESCE(p.CanConvertToKg, 0) = 1 AND COALESCE(p.KgEquivalentQty, 0) > 0
						THEN pu.Quantity / p.KgEquivalentQty
					ELSE pu.Quantity
				END
			) AS normalized_quantity,
		SUM(pu.Quantity) AS raw_quantity,
		SUM(pu.TotalPurchasePrice) AS total_purchase_price,
		MIN(pu.PurchaseDate) AS first_purchase_date,
		MAX(pu.PurchaseDate) AS last_purchase_date,
		COALESCE(p.CanConvertToKg, 0) AS can_convert_to_kg,
		COALESCE(p.KgEquivalentQty, 0) AS kg_equivalent_qty,
		COALESCE(p.ProductBaseUnit, 'pc') AS base_unit
	FROM purchases pu
	LEFT JOIN products p ON p.ProductName = pu.ProductName
	WHERE DATE(pu.PurchaseDate) BETWEEN '$safeDateFrom' AND '$safeDateTo'
	AND pu.ProductName IN ($productListSql)
	GROUP BY pu.ProductName, p.ProductBaseUnit, p.CanConvertToKg, p.KgEquivalentQty
	ORDER BY pu.ProductName ASC
");

$items = [];
$combinedQuantity = 0.0;
$combinedTotal = 0.0;
$matchedProducts = [];

if ($averageResult) {
	while ($row = $averageResult->fetch_assoc()) {
		$totalQuantity = (float) ($row['normalized_quantity'] ?? 0);
		$rawQuantity = (float) ($row['raw_quantity'] ?? 0);
		$totalPurchasePrice = (float) ($row['total_purchase_price'] ?? 0);
		$weightedAverage = $totalQuantity > 0 ? $totalPurchasePrice / $totalQuantity : 0;
		$productName = (string) ($row['ProductName'] ?? '');
		$canConvertToKg = (int) ($row['can_convert_to_kg'] ?? 0) === 1;
		$kgEquivalentQty = (float) ($row['kg_equivalent_qty'] ?? 0);
		$baseUnit = strtolower((string) ($row['base_unit'] ?? 'pc')) === 'kg' ? 'kg' : 'pc';

		$items[] = [
			'product_name' => $productName,
			'purchase_count' => (int) ($row['purchase_count'] ?? 0),
			'total_quantity' => round($totalQuantity, 2),
			'raw_quantity' => round($rawQuantity, 2),
			'total_purchase_price' => round($totalPurchasePrice, 2),
			'average_price' => round($weightedAverage, 2),
			'first_purchase_date' => $row['first_purchase_date'],
			'last_purchase_date' => $row['last_purchase_date'],
			'quantity_unit' => 'kg',
			'used_kg_conversion' => $baseUnit === 'pc' && $canConvertToKg && $kgEquivalentQty > 0,
			'kg_equivalent_qty' => round($kgEquivalentQty, 2),
			'base_unit' => $baseUnit,
		];

		$combinedQuantity += $totalQuantity;
		$combinedTotal += $totalPurchasePrice;
		$matchedProducts[] = $productName;
	}
}

$missingProducts = array_values(array_diff($productNames, $matchedProducts));
$hasData = count($items) > 0;
$combinedAverage = $combinedQuantity > 0 ? $combinedTotal / $combinedQuantity : 0;

echo json_encode([
	'success' => true,
	'has_data' => $hasData,
	'message' => $hasData ? 'Average purchase price calculated.' : 'No purchase records found for the selected filters.',
	'filters' => [
		'date_from' => $dateFrom,
		'date_to' => $dateTo,
		'products' => $productNames,
	],
	'summary' => [
		'selected_count' => count($productNames),
		'matched_count' => count($items),
		'total_quantity' => round($combinedQuantity, 2),
		'total_purchase_price' => round($combinedTotal, 2),
		'average_price' => round($combinedAverage, 2),
	],
	'items' => $items,
	'missing_products' => $missingProducts,
]);
