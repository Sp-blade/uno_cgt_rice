<?php
	$inventoryRows = $connectDB->query("
		SELECT
			p.Product_ID,
			p.ProductName,
			COALESCE(p.ProductType, '') AS ProductType,
			COALESCE(p.ProductBaseUnit, 'pc') AS ProductBaseUnit,
			COALESCE(p.CanConvertToKg, 0) AS CanConvertToKg,
			COALESCE(p.KgEquivalentQty, 0) AS KgEquivalentQty,
			COALESCE(p.AlternateSaleUnit, '') AS AlternateSaleUnit,
			COALESCE(p.StockLimit, 0) AS StockLimit,
			COALESCE(SUM(b.QuantityRemaining), 0) AS TotalStock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
		WHERE p.IsActive = 1
		GROUP BY p.Product_ID, p.ProductName, p.ProductType, p.ProductBaseUnit, p.CanConvertToKg, p.KgEquivalentQty, p.AlternateSaleUnit, p.StockLimit
		ORDER BY ProductType ASC, ProductName ASC
	");

	$categoryStockTotals = [];
	$categoryStockResult = $connectDB->query("
		SELECT
			CASE
				WHEN TRIM(COALESCE(p.ProductType, '')) = '' THEN 'Products'
				ELSE TRIM(p.ProductType)
			END AS category_name,
			COALESCE(SUM(b.QuantityRemaining), 0) AS total_stock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
		WHERE p.IsActive = 1
		GROUP BY category_name
		ORDER BY category_name ASC
	");
	if ($categoryStockResult && $categoryStockResult->num_rows > 0) {
		while ($categoryStockRow = $categoryStockResult->fetch_assoc()) {
			$categoryName = trim((string) ($categoryStockRow['category_name'] ?? 'Products'));
			if ($categoryName === '') {
				$categoryName = 'Products';
			}
			$categoryStockTotals[$categoryName] = round((float) ($categoryStockRow['total_stock'] ?? 0), 2);
		}
	}

	$totalStockValue = 0.0;
	$totalStockValueResult = $connectDB->query("
		SELECT COALESCE(SUM(b.QuantityRemaining * b.UnitCost), 0) AS total_value
		FROM inventory_batches b
		INNER JOIN products p ON p.Product_ID = b.Product_ID
		WHERE p.IsActive = 1 AND b.QuantityRemaining > 0
	");
	if ($totalStockValueResult && $totalStockValueResult->num_rows > 0) {
		$totalStockValue = round((float) ($totalStockValueResult->fetch_assoc()['total_value'] ?? 0), 2);
	}

	$batchHistoryByProduct = [];
	$batchHistoryResult = $connectDB->query("
		SELECT
			b.ID,
			b.Product_ID,
			b.ProductName,
			b.BatchDate,
			b.BatchLabel,
			b.QuantityIn,
			b.QuantityRemaining,
			b.UnitCost,
			b.SourceType,
			b.SourceReference,
			b.CreatedAt
		FROM inventory_batches b
		INNER JOIN products p ON p.Product_ID = b.Product_ID
		WHERE p.IsActive = 1 AND b.QuantityRemaining > 0
		ORDER BY b.Product_ID ASC, b.BatchDate ASC, b.ID ASC
	");
	if ($batchHistoryResult && $batchHistoryResult->num_rows > 0) {
		while ($batch = $batchHistoryResult->fetch_assoc()) {
			$productId = (int) ($batch['Product_ID'] ?? 0);
			if ($productId <= 0) {
				continue;
			}
			if (!isset($batchHistoryByProduct[$productId])) {
				$batchHistoryByProduct[$productId] = [];
			}
			$batchHistoryByProduct[$productId][] = $batch;
		}
	}
?>

<style>
	.inventory-batch-history-row > td {
		background: #f8fafc;
		border-top: none;
		padding-top: 0;
	}

	.inventory-batch-table {
		margin: 0;
	}

	.inventory-batch-table th,
	.inventory-batch-table td {
		font-size: 0.92rem;
	}

	.inventory-status-consuming {
		background: #fff8e1;
	}

	.inventory-status-queued {
		background: #ffffff;
	}
</style>

<div class="dashboard-card">
	<div class="section-head">
		<div>
			<p class="section-kicker">Stock control</p>
			<h3>Inventory Monitor</h3>
			<p class="text-muted mb-0">Stock is consumed in first-in-first-out order. The current consuming price is the purchase cost of the oldest remaining batch.</p>
		</div>
		<a class="btn btn-info" href="?mainmenu=purchase_product">Purchase Stocks</a>
		<a class="btn btn-outline-secondary" href="?mainmenu=lpg_inventory">LPG Monitor</a>
	</div>

	<div class="dashboard-overview inventory-summary-cards margin-top">
		<?php if (!empty($categoryStockTotals)): ?>
			<?php foreach ($categoryStockTotals as $categoryName => $categoryStockAmount): ?>
				<div class="summary-card">
					<p class="metric-label">Total <?php echo htmlspecialchars($categoryName); ?> Stock</p>
					<div class="summary-value"><?php echo number_format($categoryStockAmount, 2); ?></div>
					<p class="summary-note">Combined stock for this category.</p>
				</div>
			<?php endforeach; ?>
		<?php else: ?>
			<div class="summary-card">
				<p class="metric-label">Total Category Stock</p>
				<div class="summary-value">0.00</div>
				<p class="summary-note">No active product stock recorded yet.</p>
			</div>
		<?php endif; ?>
		<div class="summary-card">
			<p class="metric-label">Total Stock Value</p>
			<div class="summary-value">&#8369;<?php echo number_format($totalStockValue, 2); ?></div>
			<p class="summary-note">Total purchase cost of all available stock batches.</p>
		</div>
	</div>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th></th>
						<th>Product</th>
						<th>Category</th>
						<th>Unit</th>
						<th class="text-end">Total Stock</th>
						<th class="text-end">Current Price</th>
						<th class="text-end">Total Cost</th>
						<th class="text-end">Available Units</th>
						<th class="text-end">Limit</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($inventoryRows && $inventoryRows->num_rows > 0): ?>
						<?php while ($row = $inventoryRows->fetch_assoc()): ?>
							<?php
								$productId = (int) ($row['Product_ID'] ?? 0);
								$totalStock = (float) $row['TotalStock'];
								$stockLimit = (float) $row['StockLimit'];
								$isLow = $stockLimit > 0 && $totalStock <= $stockLimit;
								$baseUnit = junkshop_normalize_base_unit($row['ProductBaseUnit'] ?? 'pc');
								$canConvert = (int) ($row['CanConvertToKg'] ?? 0);
								$equivQty = (float) ($row['KgEquivalentQty'] ?? 0);
								$alternateSaleUnit = trim((string) ($row['AlternateSaleUnit'] ?? ''));
								$alternateStock = junkshop_base_qty_to_alternate($totalStock, $baseUnit, $equivQty, $alternateSaleUnit);
								$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit);
								$stockDisplay = number_format($totalStock, 2) . ' ' . junkshop_unit_label($baseUnit);
								if ($alternateStock !== null && $alternateUnit !== null && $canConvert === 1) {
									$stockDisplay .= ' / ' . number_format($alternateStock, 2) . ' ' . junkshop_unit_label($alternateUnit);
								}

								$batches = $batchHistoryByProduct[$productId] ?? [];
								$currentConsumingPrice = 0.0;
								$stockCostValue = 0.0;
								$activeBatchCount = count($batches);
								$currentConsumingBatchId = 0;
								if ($activeBatchCount > 0) {
									$currentConsumingBatchId = (int) ($batches[0]['ID'] ?? 0);
									$currentConsumingPrice = round((float) ($batches[0]['UnitCost'] ?? 0), 2);
								}
								foreach ($batches as $batch) {
									$remainingQty = round((float) ($batch['QuantityRemaining'] ?? 0), 2);
									$unitCost = round((float) ($batch['UnitCost'] ?? 0), 2);
									$stockCostValue += round($remainingQty * $unitCost, 2);
								}
								$stockCostValue = round($stockCostValue, 2);
							?>
							<tr class="<?php echo $isLow ? 'table-warning' : ''; ?>">
								<td class="text-center">
									<?php if (count($batches) > 0): ?>
										<button
											type="button"
											class="btn btn-sm btn-outline-secondary inventory-history-toggle"
											data-bs-toggle="collapse"
											data-bs-target="#inventory-history-<?php echo $productId; ?>"
											aria-expanded="false"
											aria-controls="inventory-history-<?php echo $productId; ?>"
										>
											History
										</button>
									<?php else: ?>
										<span class="text-muted">—</span>
									<?php endif; ?>
								</td>
								<td><?php echo htmlspecialchars($row['ProductName']); ?></td>
								<td><?php echo htmlspecialchars($row['ProductType'] !== '' ? $row['ProductType'] : 'Products'); ?></td>
								<td><?php echo htmlspecialchars(junkshop_unit_label($baseUnit)); ?></td>
								<td class="text-end fw-semibold"><?php echo number_format($totalStock, 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($baseUnit)); ?></td>
								<td class="text-end">
									<?php if ($totalStock > 0 && $currentConsumingPrice > 0): ?>
										<span class="fw-semibold">&#8369;<?php echo number_format($currentConsumingPrice, 2); ?></span>
									<?php else: ?>
										<span class="text-muted">—</span>
									<?php endif; ?>
								</td>
								<td class="text-end">
									<?php if ($stockCostValue > 0): ?>
										<span class="fw-semibold">&#8369;<?php echo number_format($stockCostValue, 2); ?></span>
									<?php else: ?>
										<span class="text-muted">—</span>
									<?php endif; ?>
								</td>
								<td class="text-end"><?php echo htmlspecialchars($stockDisplay); ?></td>
								<td class="text-end"><?php echo number_format($stockLimit, 2); ?></td>
								<td>
									<?php if ($isLow): ?>
										<span class="badge text-bg-warning">Stock limit reached</span>
									<?php elseif ($totalStock <= 0): ?>
										<span class="badge text-bg-secondary">Out of stock</span>
									<?php elseif ($activeBatchCount > 1): ?>
										<span class="badge text-bg-info"><?php echo $activeBatchCount; ?> FIFO batches</span>
									<?php else: ?>
										<span class="badge text-bg-success">In stock</span>
									<?php endif; ?>
								</td>
							</tr>
							<?php if (count($batches) > 0): ?>
								<tr class="inventory-batch-history-row collapse" id="inventory-history-<?php echo $productId; ?>">
									<td colspan="10">
										<div class="table-responsive">
											<table class="table table-sm inventory-batch-table mb-0">
												<thead>
													<tr>
														<th>#</th>
														<th>Batch Date</th>
														<th>Source</th>
														<th class="text-end">Qty In</th>
														<th class="text-end">Remaining</th>
														<th class="text-end">Purchase Price</th>
														<th>FIFO Status</th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ($batches as $batchIndex => $batch): ?>
														<?php
															$batchId = (int) ($batch['ID'] ?? 0);
															$remaining = round((float) ($batch['QuantityRemaining'] ?? 0), 2);
															$isCurrentConsuming = $batchId === $currentConsumingBatchId;
															$rowClass = $isCurrentConsuming ? 'inventory-status-consuming' : 'inventory-status-queued';
														?>
														<tr class="<?php echo $rowClass; ?>">
															<td><?php echo $batchIndex + 1; ?></td>
															<td><?php echo junkshop_format_datetime($batch['BatchDate']); ?></td>
															<td>
																<div><?php echo htmlspecialchars($batch['SourceType'] ?? 'PURCHASE'); ?></div>
																<small class="text-muted"><?php echo htmlspecialchars($batch['SourceReference'] ?? ''); ?></small>
															</td>
															<td class="text-end"><?php echo number_format((float) ($batch['QuantityIn'] ?? 0), 2); ?></td>
															<td class="text-end fw-semibold"><?php echo number_format($remaining, 2); ?></td>
															<td class="text-end">&#8369;<?php echo number_format((float) ($batch['UnitCost'] ?? 0), 2); ?></td>
															<td>
																<?php if ($isCurrentConsuming): ?>
																	<span class="badge text-bg-warning">Consuming now</span>
																<?php else: ?>
																	<span class="badge text-bg-light">Waiting</span>
																<?php endif; ?>
															</td>
														</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="10" class="empty-state">No inventory records yet.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
