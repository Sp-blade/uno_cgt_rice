<?php
	$searchInventory = isset($_GET['searchInventory']) ? trim((string) $_GET['searchInventory']) : '';

	$lpgRows = [];
	$lpgResult = $connectDB->query("
		SELECT
			p.Product_ID,
			p.ProductName,
			COALESCE(p.ProductType, '') AS ProductType,
			COALESCE(SUM(b.QuantityRemaining), 0) AS FilledStock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID AND b.QuantityRemaining > 0
		WHERE p.IsActive = 1
			AND UPPER(COALESCE(p.ProductType, '')) <> 'LPG TANK'
			AND COALESCE(p.IsSubProduct, 0) = 0
			AND (
				UPPER(COALESCE(p.ProductType, '')) = 'LPG'
				OR LOWER(p.ProductName) LIKE '%lpg%'
				OR LOWER(p.ProductName) LIKE '%gasul%'
			)
		GROUP BY p.Product_ID, p.ProductName, p.ProductType
		ORDER BY p.ProductName ASC
	");
	if ($lpgResult && $lpgResult->num_rows > 0) {
		while ($row = $lpgResult->fetch_assoc()) {
			$productId = (int) ($row['Product_ID'] ?? 0);
			$filledStock = round((float) ($row['FilledStock'] ?? 0), 2);
			$category = trim((string) ($row['ProductType'] ?? ''));
			if ($category === '') {
				$category = 'LPG';
			}
			$lpgRows[] = [
				'product_id' => $productId,
				'product_name' => trim((string) ($row['ProductName'] ?? '')),
				'category' => $category,
				'filled_stock' => $filledStock,
				'available_units' => $filledStock,
				'empty_new' => junkshop_lpg_get_empty_balance($connectDB, $productId, 'NEW'),
				'empty_old' => junkshop_lpg_get_empty_balance($connectDB, $productId, 'OLD'),
				'status_label' => junkshop_lpg_inventory_status_label($filledStock),
			];
		}
	}

	$lpgAllRows = $lpgRows;
	if ($searchInventory !== '') {
		$lpgRows = array_values(array_filter($lpgRows, function ($row) use ($searchInventory) {
			return junkshop_inventory_matches_search($row, $searchInventory);
		}));
	}

	$lentLoans = [];
	$lentResult = $connectDB->query("
		SELECT
			l.ID,
			l.CustomerName,
			l.Product_ID,
			l.ProductName,
			l.Quantity,
			l.LoanDate,
			l.DeliveryNo,
			l.LoanCondition
		FROM lpg_tank_loans l
		WHERE l.Status = 'LENT'
		ORDER BY l.LoanDate DESC, l.ID DESC
	");
	if ($lentResult && $lentResult->num_rows > 0) {
		while ($row = $lentResult->fetch_assoc()) {
			$lentLoans[] = $row;
		}
	}

	$totalFilled = 0.0;
	$totalEmptyNew = 0.0;
	$totalEmptyOld = 0.0;
	$totalLent = 0.0;
	foreach ($lpgAllRows as $row) {
		$totalFilled += $row['filled_stock'];
		$totalEmptyNew += $row['empty_new'];
		$totalEmptyOld += $row['empty_old'];
	}
	foreach ($lentLoans as $loan) {
		$totalLent += round((float) ($loan['Quantity'] ?? 0), 2);
	}
?>

<div class="dashboard-card">
	<div class="section-head inventory-section-head">
		<div>
			<p class="section-kicker">LPG retail</p>
			<h3>LPG Inventory Monitor</h3>
			<p class="text-muted mb-0">Track filled LPG stock, empty tanks by condition, and tanks currently lent to customers.</p>
		</div>
		<div class="inventory-header-actions d-flex gap-2 flex-wrap">
			<a class="btn btn-outline-secondary" href="?mainmenu=inventory">All Products</a>
			<a class="btn btn-info" href="?mainmenu=purchase_product">Stock In</a>
			<a class="btn btn-success" href="?mainmenu=sell_product_others">Sell LPG</a>
			<a class="btn btn-outline-secondary" href="?mainmenu=inventory_audit">Inventory Audit</a>
		</div>
	</div>

	<div class="dashboard-overview lpg-summary-cards margin-top">
		<div class="summary-card">
			<p class="metric-label">Filled with LPG</p>
			<div class="summary-value"><?php echo number_format($totalFilled, 2); ?></div>
			<p class="summary-note">Tanks with LPG ready to sell or lend.</p>
		</div>
		<div class="summary-card">
			<p class="metric-label">Empty Tanks (New)</p>
			<div class="summary-value"><?php echo number_format($totalEmptyNew, 2); ?></div>
			<p class="summary-note">New-condition empty tanks in stock.</p>
		</div>
		<div class="summary-card">
			<p class="metric-label">Empty Tanks (Old)</p>
			<div class="summary-value"><?php echo number_format($totalEmptyOld, 2); ?></div>
			<p class="summary-note">Old-condition empty tanks in stock.</p>
		</div>
		<div class="summary-card">
			<p class="metric-label">Lent to Customers</p>
			<div class="summary-value"><?php echo number_format($totalLent, 2); ?></div>
			<p class="summary-note">Tanks currently out with customers.</p>
		</div>
	</div>

	<div class="table-card margin-top">
		<h4 class="h5 mb-3">LPG Stock by Product</h4>

		<form method="GET" class="list-toolbar margin-bottom">
			<input type="hidden" name="mainmenu" value="lpg_inventory" />
			<input type="text" class="searchbox" name="searchInventory" placeholder="Search product / category / status" value="<?php echo htmlspecialchars($searchInventory); ?>" />
			<button type="submit" class="btn btn-primary">Search</button>
		</form>

		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Product</th>
						<th>Category</th>
						<th class="text-end">Filled with LPG</th>
						<th class="text-end">Empty (New)</th>
						<th class="text-end">Empty (Old)</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($lpgRows)): ?>
						<?php foreach ($lpgRows as $row): ?>
							<?php
								$filledStock = (float) ($row['filled_stock'] ?? 0);
								$statusLabel = (string) ($row['status_label'] ?? '');
							?>
							<tr>
								<td><?php echo htmlspecialchars($row['product_name']); ?></td>
								<td><?php echo htmlspecialchars($row['category']); ?></td>
								<td class="text-end"><?php echo number_format($filledStock, 2); ?></td>
								<td class="text-end"><?php echo number_format((float) ($row['empty_new'] ?? 0), 2); ?></td>
								<td class="text-end"><?php echo number_format((float) ($row['empty_old'] ?? 0), 2); ?></td>
								<td>
									<?php if ($filledStock <= 0): ?>
										<span class="badge text-bg-secondary"><?php echo htmlspecialchars($statusLabel); ?></span>
									<?php else: ?>
										<span class="badge text-bg-success"><?php echo htmlspecialchars($statusLabel); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr><td colspan="6" class="empty-state"><?php echo !empty($lpgAllRows) && $searchInventory !== '' ? 'No LPG products matched your search.' : 'No LPG products found. Set Product Type to LPG on the Product List.'; ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="table-card margin-top">
		<h4 class="h5 mb-3">Lent Tanks</h4>
		<p class="text-muted">Return lent tanks from the <a href="?mainmenu=customers">Customers</a> tab.</p>
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Customer</th>
						<th>Product</th>
						<th>Condition</th>
						<th class="text-end">Qty</th>
						<th>Loan Date</th>
						<th>Sale #</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($lentLoans)): ?>
						<?php foreach ($lentLoans as $loan): ?>
							<tr>
								<td>
									<a href="?mainmenu=customers&amp;customer=<?php echo urlencode(trim((string) ($loan['CustomerName'] ?? ''))); ?>">
										<?php echo htmlspecialchars(trim((string) ($loan['CustomerName'] ?? ''))); ?>
									</a>
								</td>
								<td><?php echo htmlspecialchars(trim((string) ($loan['ProductName'] ?? ''))); ?></td>
								<td><?php echo htmlspecialchars(trim((string) ($loan['LoanCondition'] ?? '')) !== '' ? trim((string) $loan['LoanCondition']) : '—'); ?></td>
								<td class="text-end"><?php echo number_format((float) ($loan['Quantity'] ?? 0), 2); ?></td>
								<td><?php echo date('M-d-Y h:i A', strtotime((string) ($loan['LoanDate'] ?? 'now'))); ?></td>
								<td><?php echo (int) ($loan['DeliveryNo'] ?? 0); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr><td colspan="6" class="empty-state">No tanks currently lent.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
