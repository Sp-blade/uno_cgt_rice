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

	$purchasesByProduct = $connectDB->query("
		SELECT 
			DATE(PurchaseDate) AS PurchaseDate,
			COUNT(DISTINCT InvoiceNo) AS invoice_count,
			SUM(Quantity) AS total_quantity,
			AVG(ProductPrice) AS average_price,
			SUM(TotalPurchasePrice) AS total_purchase_price
		FROM purchases
		WHERE ($productClause)
		AND DATE(PurchaseDate) BETWEEN '$fromDate' AND '$toDate'
		GROUP BY DATE(PurchaseDate)
		ORDER BY DATE(PurchaseDate) DESC
	");
	$productTotal = 0;
	$productQuantity = 0;
?>

<div class="container-fluid has-fixed-footer">
	<h2>Purchase History: <?php echo htmlspecialchars($productName !== '' ? $productName : 'Product'); ?></h2>
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
						<th class="text-end">PO Count</th>
						<th class="text-end">Pcs/Kg</th>
						<th class="text-end">Average Purchase</th>
						<th class="text-end">Total</th>
					</tr>
				</thead>
				<tbody>
			<?php if ($purchasesByProduct && $purchasesByProduct->num_rows > 0): ?>
				<?php while ($purchase = $purchasesByProduct->fetch_assoc()): ?>
					<?php
						$productTotal += (float) $purchase['total_purchase_price'];
						$productQuantity += (float) $purchase['total_quantity'];
					?>
					<tr>
						<td><?php echo date('M-d-Y', strtotime($purchase['PurchaseDate'])); ?></td>
						<td class="text-end"><?php echo $purchase['invoice_count']; ?></td>
						<td class="text-end"><?php echo number_format($purchase['total_quantity'], 2); ?></td>
						<td class="text-end">&#8369;<?php echo number_format($purchase['average_price'], 2); ?></td>
						<td class="text-end">&#8369;<?php echo number_format($purchase['total_purchase_price'], 2); ?></td>
					</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="5" class="empty-state">No purchase records found for this product.</td></tr>
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
		<div class="totalprice"><?php echo number_format($productQuantity, 2); ?></div>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p class="metric-label">Total Purchase</p>
		<div class="totalprice">&#8369;<?php echo number_format($productTotal, 2); ?></div>
	</div>
</div>
