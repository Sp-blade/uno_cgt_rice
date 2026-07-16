<?php
	$inventoryRows = $connectDB->query("
		SELECT
			p.Product_ID,
			p.ProductName,
			COALESCE(p.ProductType, '') AS ProductType,
			COALESCE(p.ProductBaseUnit, 'pc') AS ProductBaseUnit,
			COALESCE(p.StockLimit, 0) AS StockLimit,
			COALESCE(SUM(CASE WHEN b.BatchLabel = 'OLD' THEN b.QuantityRemaining ELSE 0 END), 0) AS OldStock,
			COALESCE(SUM(CASE WHEN b.BatchLabel = 'NEW' THEN b.QuantityRemaining ELSE 0 END), 0) AS NewStock,
			COALESCE(SUM(b.QuantityRemaining), 0) AS TotalStock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
		WHERE p.IsActive = 1
		GROUP BY p.Product_ID, p.ProductName, p.ProductType, p.ProductBaseUnit, p.StockLimit
		ORDER BY ProductType ASC, ProductName ASC
	");

	$dueAccounts = $connectDB->query("
		SELECT *
		FROM customer_accounts
		WHERE Balance > 0
		ORDER BY CASE WHEN DueDate IS NULL THEN 1 ELSE 0 END, DueDate ASC, CustomerName ASC
	");

	$returns = $connectDB->query("
		SELECT *
		FROM product_returns
		ORDER BY ReturnDate DESC, ID DESC
		LIMIT 30
	");
?>

<div class="dashboard-card">
	<div class="section-head">
		<div>
			<p class="section-kicker">Stock control</p>
			<h3>Inventory Monitor</h3>
		</div>
		<a class="btn btn-info" href="?mainmenu=purchase_product">Add Stock</a>
	</div>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Product</th>
						<th>Category</th>
						<th>Unit</th>
						<th class="text-end">Old Stock</th>
						<th class="text-end">New Stock</th>
						<th class="text-end">Total</th>
						<th class="text-end">Limit</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($inventoryRows && $inventoryRows->num_rows > 0): ?>
						<?php while ($row = $inventoryRows->fetch_assoc()): ?>
							<?php
								$totalStock = (float) $row['TotalStock'];
								$stockLimit = (float) $row['StockLimit'];
								$isLow = $stockLimit > 0 && $totalStock <= $stockLimit;
							?>
							<tr class="<?php echo $isLow ? 'table-warning' : ''; ?>">
								<td><?php echo htmlspecialchars($row['ProductName']); ?></td>
								<td><?php echo htmlspecialchars($row['ProductType'] !== '' ? $row['ProductType'] : 'Products'); ?></td>
								<td><?php echo strtoupper(htmlspecialchars($row['ProductBaseUnit'])); ?></td>
								<td class="text-end"><?php echo number_format($row['OldStock'], 2); ?></td>
								<td class="text-end"><?php echo number_format($row['NewStock'], 2); ?></td>
								<td class="text-end fw-semibold"><?php echo number_format($totalStock, 2); ?></td>
								<td class="text-end"><?php echo number_format($stockLimit, 2); ?></td>
								<td>
									<?php if ($isLow): ?>
										<span class="badge text-bg-warning">Stock limit reached</span>
									<?php else: ?>
										<span class="badge text-bg-success">In stock</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="8" class="empty-state">No inventory records yet.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="dashboard-card margin-top">
	<div class="section-head">
		<div>
			<p class="section-kicker">Customer loans</p>
			<h3>Payment and due-date alerts</h3>
		</div>
	</div>
	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Customer</th>
						<th>Sale No</th>
						<th>Due Date</th>
						<th>Status</th>
						<th class="text-end">Total</th>
						<th class="text-end">Paid</th>
						<th class="text-end">Balance</th>
						<th>Alert</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($dueAccounts && $dueAccounts->num_rows > 0): ?>
						<?php while ($account = $dueAccounts->fetch_assoc()): ?>
							<?php
								$dueDate = trim((string) ($account['DueDate'] ?? ''));
								$isDue = $dueDate !== '' && $dueDate <= date('Y-m-d');
							?>
							<tr class="<?php echo $isDue ? 'table-danger' : ''; ?>">
								<td><?php echo htmlspecialchars($account['CustomerName']); ?></td>
								<td><?php echo (int) $account['DeliveryNo']; ?></td>
								<td><?php echo $dueDate !== '' ? date('M-d-Y', strtotime($dueDate)) : 'No due date'; ?></td>
								<td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($account['PaymentStatus']); ?></span></td>
								<td class="text-end">&#8369;<?php echo number_format($account['TotalAmount'], 2); ?></td>
								<td class="text-end">&#8369;<?php echo number_format($account['AmountPaid'], 2); ?></td>
								<td class="text-end fw-semibold">&#8369;<?php echo number_format($account['Balance'], 2); ?></td>
								<td><?php echo $isDue ? '<span class="badge text-bg-danger">Due now</span>' : '<span class="badge text-bg-light">Monitoring</span>'; ?></td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="8" class="empty-state">No unpaid or partial customer balances.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="dashboard-card margin-top">
	<div class="section-head">
		<div>
			<p class="section-kicker">Returns</p>
			<h3>Recent returned items</h3>
		</div>
	</div>
	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Date</th>
						<th>Sale No</th>
						<th>Product</th>
						<th class="text-end">Qty</th>
						<th>Reason</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($returns && $returns->num_rows > 0): ?>
						<?php while ($return = $returns->fetch_assoc()): ?>
							<tr>
								<td><?php echo date('M-d-Y h:i A', strtotime($return['ReturnDate'])); ?></td>
								<td><?php echo (int) $return['DeliveryNo']; ?></td>
								<td><?php echo htmlspecialchars($return['ProductName']); ?></td>
								<td class="text-end"><?php echo number_format($return['Quantity'], 2); ?></td>
								<td><?php echo htmlspecialchars($return['Reason']); ?></td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="5" class="empty-state">No returned items recorded.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
