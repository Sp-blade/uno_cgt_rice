<?php
	$productId = (int) ($_GET['product_id'] ?? 0);
	$productName = isset($_GET['product_name']) ? trim((string) $_GET['product_name']) : '';
	$returnTo = isset($_GET['return_to']) ? $_GET['return_to'] : '?';
	$todayDate = date('Y-m-d');
	$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? $_GET['from_date'] : $todayDate;
	$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? $_GET['to_date'] : $todayDate;

	if ($productId > 0) {
		$productResult = $connectDB->query("SELECT ProductName FROM products WHERE Product_ID = '$productId' LIMIT 1");
		if ($productResult && ($productRow = $productResult->fetch_assoc())) {
			$productName = trim((string) ($productRow['ProductName'] ?? $productName));
		}
		$productClause = "Product_ID = '$productId'";
	} elseif ($productName !== '') {
		$safeProductName = mysqli_real_escape_string($connectDB, $productName);
		$productClause = "ProductName = '$safeProductName'";
	} else {
		$productClause = '1 = 0';
	}

	$salesByProduct = $connectDB->query("
		SELECT 
			SaleDate,
			COUNT(DISTINCT DeliveryNo) AS delivery_count,
			SUM(Quantity) AS total_quantity,
			AVG(UnitPrice) AS average_price,
			AVG(Less) AS average_less,
			SUM(TotalSalePrice) AS total_sale_price
		FROM sales
		WHERE ($productClause)
		AND SaleDate BETWEEN '$fromDate' AND '$toDate'
		GROUP BY SaleDate
		ORDER BY SaleDate DESC
	");
	$saleTotal = 0;
	$saleQuantity = 0;
?>

<div class="container-fluid has-fixed-footer">
	<h2>Sold/Delivery: <?php echo htmlspecialchars($productName !== '' ? $productName : 'Product'); ?></h2>
	<?php if ($productId > 0): ?>
		<p class="text-muted mb-1">Product ID: <?php echo (int) $productId; ?></p>
	<?php endif; ?>
	<p><strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($fromDate)); ?> to <?php echo date('M d, Y', strtotime($toDate)); ?></p>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Date</th>
						<th class="text-end">Deliveries</th>
						<th class="text-end">Pcs/Kg</th>
						<th class="text-end">Average Price</th>
						<th class="text-end">Less</th>
						<th class="text-end">Total</th>
					</tr>
				</thead>
				<tbody>
			<?php if ($salesByProduct && $salesByProduct->num_rows > 0): ?>
				<?php while ($sale = $salesByProduct->fetch_assoc()): ?>
					<?php
						$saleTotal += (float) $sale['total_sale_price'];
						$saleQuantity += (float) $sale['total_quantity'];
					?>
					<tr>
						<td><?php echo date('M-d-Y', strtotime($sale['SaleDate'])); ?></td>
						<td class="text-end"><?php echo $sale['delivery_count']; ?></td>
						<td class="text-end"><?php echo number_format($sale['total_quantity'], 2); ?></td>
						<td class="text-end">&#8369;<?php echo number_format($sale['average_price'], 2); ?></td>
						<td class="text-end"><?php echo number_format($sale['average_less'], 2); ?>%</td>
						<td class="text-end">&#8369;<?php echo number_format($sale['total_sale_price'], 2); ?></td>
					</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="6" class="empty-state">No sold/delivery records found for this product.</td></tr>
			<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="row custom-row fixed-footer footer-layout-dual">
	<div class="col-md-2 col-12 footer-action">
		<a class="btn btn-info w-100" href="<?php echo htmlspecialchars($returnTo, ENT_QUOTES); ?>">Back</a>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p class="metric-label">Total Pcs/Kg</p>
		<div class="totalprice"><?php echo number_format($saleQuantity, 2); ?></div>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p class="metric-label">Total Sold</p>
		<div class="totalprice">&#8369;<?php echo number_format($saleTotal, 2); ?></div>
	</div>
</div>
