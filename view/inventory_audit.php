<?php
	$searchAudit = isset($_GET['searchAudit']) ? trim((string) $_GET['searchAudit']) : '';
	$auditId = (int) ($_GET['audit_id'] ?? 0);
	$auditStatus = trim((string) ($_GET['audit_status'] ?? ''));
	$auditError = trim((string) ($_GET['audit_error'] ?? ''));

	$auditProducts = [];
	$productResult = $connectDB->query("
		SELECT
			p.Product_ID,
			p.ProductName,
			COALESCE(p.ProductType, '') AS ProductType,
			COALESCE(p.ProductBaseUnit, 'pc') AS ProductBaseUnit,
			COALESCE(SUM(b.QuantityRemaining), 0) AS SystemStock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
		WHERE p.IsActive = 1
			AND UPPER(COALESCE(p.ProductType, '')) <> 'LPG TANK'
			AND COALESCE(p.IsSubProduct, 0) = 0
		GROUP BY p.Product_ID, p.ProductName, p.ProductType, p.ProductBaseUnit
		ORDER BY p.ProductName ASC
	");
	if ($productResult && $productResult->num_rows > 0) {
		while ($row = $productResult->fetch_assoc()) {
			$category = trim((string) ($row['ProductType'] ?? ''));
			if ($category === '') {
				$category = 'Products';
			}
			$auditProducts[] = [
				'product_id' => (int) ($row['Product_ID'] ?? 0),
				'product_name' => trim((string) ($row['ProductName'] ?? '')),
				'category' => $category,
				'base_unit' => junkshop_normalize_base_unit($row['ProductBaseUnit'] ?? 'pc'),
				'system_qty' => round((float) ($row['SystemStock'] ?? 0), 2),
			];
		}
	}

	if ($searchAudit !== '') {
		$needle = strtolower($searchAudit);
		$auditProducts = array_values(array_filter($auditProducts, function ($item) use ($needle) {
			$fields = [
				(string) ($item['product_name'] ?? ''),
				(string) ($item['category'] ?? ''),
			];
			foreach ($fields as $field) {
				if ($field !== '' && strpos(strtolower($field), $needle) !== false) {
					return true;
				}
			}
			return false;
		}));
	}

	$pastAudits = [];
	$pastAuditResult = $connectDB->query("
		SELECT ID, AuditDate, Notes, ProductCount, MatchCount, OverCount, UnderCount, CreatedAt
		FROM inventory_audits
		ORDER BY AuditDate DESC, ID DESC
		LIMIT 25
	");
	if ($pastAuditResult && $pastAuditResult->num_rows > 0) {
		while ($row = $pastAuditResult->fetch_assoc()) {
			$pastAudits[] = $row;
		}
	}

	$selectedAudit = null;
	$selectedAuditItems = [];
	if ($auditId > 0) {
		$auditHeaderResult = $connectDB->query("
			SELECT ID, AuditDate, Notes, ProductCount, MatchCount, OverCount, UnderCount, CreatedAt
			FROM inventory_audits
			WHERE ID = '$auditId'
			LIMIT 1
		");
		if ($auditHeaderResult && ($selectedAudit = $auditHeaderResult->fetch_assoc())) {
			$auditItemsResult = $connectDB->query("
				SELECT Product_ID, ProductName, Category, BaseUnit, SystemQty, ActualQty, VarianceQty, ItemNotes
				FROM inventory_audit_items
				WHERE Audit_ID = '$auditId'
				ORDER BY ProductName ASC
			");
			if ($auditItemsResult && $auditItemsResult->num_rows > 0) {
				while ($row = $auditItemsResult->fetch_assoc()) {
					$selectedAuditItems[] = $row;
				}
			}
		} else {
			$auditId = 0;
		}
	}
?>

<style>
	.inventory-audit-variance-match {
		color: #198754;
		font-weight: 600;
	}

	.inventory-audit-variance-over {
		color: #0d6efd;
		font-weight: 600;
	}

	.inventory-audit-variance-under {
		color: #dc3545;
		font-weight: 600;
	}

	.inventory-audit-actual-input {
		min-width: 110px;
	}

	.inventory-audit-summary-cards .summary-card {
		min-height: 100%;
	}

	.inventory-audit-detail-cards {
		grid-template-columns: repeat(4, minmax(0, 1fr));
	}

	@media (max-width: 991px) {
		.inventory-audit-detail-cards {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	@media (max-width: 575px) {
		.inventory-audit-detail-cards {
			grid-template-columns: minmax(0, 1fr);
		}
	}
</style>

<div class="dashboard-card">
	<div class="section-head">
		<div>
			<p class="section-kicker">Stock control</p>
			<h3>Inventory Audit</h3>
			<p class="text-muted mb-0">Compare system inventory with your actual store count to spot shortages and overages.</p>
		</div>
		<div class="d-flex gap-2 flex-wrap">
			<a class="btn btn-outline-secondary" href="?mainmenu=inventory">All Products</a>
			<a class="btn btn-outline-secondary" href="?mainmenu=lpg_inventory">LPG Monitor</a>
			<?php if ($auditId > 0): ?>
				<a class="btn btn-primary" href="?mainmenu=inventory_audit">New Audit</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($auditStatus === 'success'): ?>
		<div class="alert alert-success margin-top" role="alert">Inventory audit saved successfully.</div>
	<?php elseif ($auditStatus === 'deleted'): ?>
		<div class="alert alert-success margin-top" role="alert">Inventory audit deleted successfully.</div>
	<?php elseif ($auditStatus === 'error' && $auditError !== ''): ?>
		<div class="alert alert-danger margin-top" role="alert"><?php echo htmlspecialchars($auditError); ?></div>
	<?php endif; ?>

	<?php if ($auditId > 0 && $selectedAudit): ?>
		<div class="dashboard-overview inventory-audit-summary-cards inventory-audit-detail-cards margin-top">
			<div class="summary-card">
				<p class="metric-label">Audit Date</p>
				<div class="summary-value"><?php echo date('M-d-Y h:i A', strtotime((string) ($selectedAudit['AuditDate'] ?? 'now'))); ?></div>
				<p class="summary-note">Saved physical count session.</p>
			</div>
			<div class="summary-card">
				<p class="metric-label">Products Counted</p>
				<div class="summary-value"><?php echo (int) ($selectedAudit['ProductCount'] ?? 0); ?></div>
				<p class="summary-note">Items included in this audit.</p>
			</div>
			<div class="summary-card">
				<p class="metric-label">Matched</p>
				<div class="summary-value text-success"><?php echo (int) ($selectedAudit['MatchCount'] ?? 0); ?></div>
				<p class="summary-note">Actual equals system stock.</p>
			</div>
			<div class="summary-card">
				<p class="metric-label">Over / Under</p>
				<div class="summary-value">
					<span class="text-primary"><?php echo (int) ($selectedAudit['OverCount'] ?? 0); ?></span>
					/
					<span class="text-danger"><?php echo (int) ($selectedAudit['UnderCount'] ?? 0); ?></span>
				</div>
				<p class="summary-note">Overstock vs shortage items.</p>
			</div>
		</div>

		<?php if (trim((string) ($selectedAudit['Notes'] ?? '')) !== ''): ?>
			<div class="table-card margin-top">
				<h4 class="h6 mb-2">Audit Notes</h4>
				<p class="mb-0"><?php echo nl2br(htmlspecialchars(trim((string) $selectedAudit['Notes']))); ?></p>
			</div>
		<?php endif; ?>

		<div class="table-card margin-top">
			<h4 class="h5 mb-3">Audit Results</h4>
			<div class="table-responsive">
				<table class="table table-hover">
					<thead>
						<tr>
							<th>Product</th>
							<th>Category</th>
							<th>Unit</th>
							<th class="text-end">System Qty</th>
							<th class="text-end">Actual Qty</th>
							<th class="text-end">Variance</th>
							<th>Notes</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($selectedAuditItems)): ?>
							<?php foreach ($selectedAuditItems as $item): ?>
								<?php
									$variance = round((float) ($item['VarianceQty'] ?? 0), 2);
									$varianceClass = 'inventory-audit-variance-match';
									if ($variance > 0.009) {
										$varianceClass = 'inventory-audit-variance-over';
									} elseif ($variance < -0.009) {
										$varianceClass = 'inventory-audit-variance-under';
									}
									$varianceLabel = $variance > 0 ? '+' . number_format($variance, 2) : number_format($variance, 2);
								?>
								<tr>
									<td><?php echo htmlspecialchars(trim((string) ($item['ProductName'] ?? ''))); ?></td>
									<td><?php echo htmlspecialchars(trim((string) ($item['Category'] ?? ''))); ?></td>
									<td><?php echo htmlspecialchars(junkshop_unit_label($item['BaseUnit'] ?? 'pc')); ?></td>
									<td class="text-end"><?php echo number_format((float) ($item['SystemQty'] ?? 0), 2); ?></td>
									<td class="text-end"><?php echo number_format((float) ($item['ActualQty'] ?? 0), 2); ?></td>
									<td class="text-end <?php echo $varianceClass; ?>"><?php echo $varianceLabel; ?></td>
									<td><?php echo htmlspecialchars(trim((string) ($item['ItemNotes'] ?? '')) !== '' ? trim((string) $item['ItemNotes']) : '—'); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="7" class="empty-state">No audit line items found.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php else: ?>
		<div class="dashboard-overview inventory-audit-summary-cards inventory-audit-detail-cards margin-top">
			<div class="summary-card">
				<p class="metric-label">Active Products</p>
				<div class="summary-value" id="auditSummaryTotal"><?php echo count($auditProducts); ?></div>
				<p class="summary-note">Products available to count.</p>
			</div>
			<div class="summary-card">
				<p class="metric-label">Counted</p>
				<div class="summary-value" id="auditSummaryCounted">0</div>
				<p class="summary-note">Rows with an actual quantity entered.</p>
			</div>
			<div class="summary-card">
				<p class="metric-label">Matched</p>
				<div class="summary-value text-success" id="auditSummaryMatched">0</div>
				<p class="summary-note">Actual equals system stock.</p>
			</div>
			<div class="summary-card">
				<p class="metric-label">Over / Under</p>
				<div class="summary-value">
					<span class="text-primary" id="auditSummaryOver">0</span>
					/
					<span class="text-danger" id="auditSummaryUnder">0</span>
				</div>
				<p class="summary-note">Overstock vs shortage while counting.</p>
			</div>
		</div>

		<form method="GET" class="list-toolbar margin-top">
			<input type="hidden" name="mainmenu" value="inventory_audit" />
			<input type="text" class="searchbox" name="searchAudit" placeholder="Search product / category" value="<?php echo htmlspecialchars($searchAudit); ?>" />
			<button type="submit" class="btn btn-primary">Search</button>
		</form>

		<form method="POST" action="?mainmenu=save_inventory_audit" id="inventoryAuditForm" class="margin-top">
			<div class="table-card">
				<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
					<h4 class="h5 mb-0">Physical Count</h4>
					<div class="d-flex flex-wrap gap-2">
						<button type="button" class="btn btn-sm btn-outline-secondary" id="auditCopySystemBtn">Copy System to Actual</button>
						<button type="button" class="btn btn-sm btn-outline-secondary" id="auditClearActualBtn">Clear Actual Counts</button>
					</div>
				</div>

				<div class="row g-3 mb-3">
					<div class="col-md-4">
						<label for="audit_date" class="form-label">Audit Date</label>
						<input type="datetime-local" class="form-control" id="audit_date" name="audit_date" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>" required />
					</div>
					<div class="col-md-8">
						<label for="audit_notes" class="form-label">Audit Notes</label>
						<input type="text" class="form-control" id="audit_notes" name="audit_notes" placeholder="Optional notes (e.g. end-of-day count, shelf section A)" />
					</div>
				</div>

				<div class="table-responsive">
					<table class="table table-hover" id="inventoryAuditTable">
						<thead>
							<tr>
								<th>Product</th>
								<th>Category</th>
								<th>Unit</th>
								<th class="text-end">System Qty</th>
								<th class="text-end">Actual Qty</th>
								<th class="text-end">Variance</th>
								<th>Notes</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($auditProducts)): ?>
								<?php foreach ($auditProducts as $item): ?>
									<tr class="inventory-audit-row">
										<td>
											<?php echo htmlspecialchars($item['product_name']); ?>
											<input type="hidden" name="product_id[]" value="<?php echo (int) $item['product_id']; ?>" />
											<input type="hidden" name="system_qty[]" value="<?php echo htmlspecialchars(number_format((float) $item['system_qty'], 2, '.', ''), ENT_QUOTES); ?>" />
										</td>
										<td><?php echo htmlspecialchars($item['category']); ?></td>
										<td><?php echo htmlspecialchars(junkshop_unit_label($item['base_unit'])); ?></td>
										<td class="text-end fw-semibold audit-system-qty"><?php echo number_format((float) $item['system_qty'], 2); ?></td>
										<td class="text-end">
											<input
												type="number"
												step="0.01"
												class="form-control form-control-sm inventory-audit-actual-input audit-actual-input"
												name="actual_qty[]"
												placeholder="Count"
												data-system-qty="<?php echo htmlspecialchars(number_format((float) $item['system_qty'], 2, '.', ''), ENT_QUOTES); ?>"
											/>
										</td>
										<td class="text-end audit-variance-cell">—</td>
										<td>
											<input type="text" class="form-control form-control-sm" name="item_notes[]" placeholder="Optional" />
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr><td colspan="7" class="empty-state">No products matched your search.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php if (!empty($auditProducts)): ?>
					<div class="d-flex justify-content-end margin-top">
						<button type="submit" class="btn btn-success">Save Audit</button>
					</div>
				<?php endif; ?>
			</div>
		</form>

		<script>
			(function () {
				function parseQty(value) {
					const parsed = parseFloat(value);
					return Number.isFinite(parsed) ? parsed : null;
				}

				function formatQty(value) {
					return (Number(value) || 0).toFixed(2);
				}

				function varianceClass(variance) {
					if (Math.abs(variance) < 0.009) return 'inventory-audit-variance-match';
					if (variance > 0) return 'inventory-audit-variance-over';
					return 'inventory-audit-variance-under';
				}

				function formatVariance(variance) {
					if (Math.abs(variance) < 0.009) return '0.00';
					return (variance > 0 ? '+' : '') + formatQty(variance);
				}

				function updateAuditSummary() {
					let counted = 0;
					let matched = 0;
					let over = 0;
					let under = 0;

					document.querySelectorAll('.inventory-audit-row').forEach(function (row) {
						const actualInput = row.querySelector('.audit-actual-input');
						const varianceCell = row.querySelector('.audit-variance-cell');
						if (!actualInput || !varianceCell) return;

						const actualRaw = actualInput.value.trim();
						if (actualRaw === '') {
							varianceCell.textContent = '—';
							varianceCell.className = 'text-end audit-variance-cell';
							return;
						}

						const systemQty = parseQty(actualInput.dataset.systemQty || '0') || 0;
						const actualQty = parseQty(actualRaw) || 0;
						const variance = actualQty - systemQty;
						counted++;

						if (Math.abs(variance) < 0.009) matched++;
						else if (variance > 0) over++;
						else under++;

						varianceCell.textContent = formatVariance(variance);
						varianceCell.className = 'text-end audit-variance-cell ' + varianceClass(variance);
					});

					const countedEl = document.getElementById('auditSummaryCounted');
					const matchedEl = document.getElementById('auditSummaryMatched');
					const overEl = document.getElementById('auditSummaryOver');
					const underEl = document.getElementById('auditSummaryUnder');
					if (countedEl) countedEl.textContent = String(counted);
					if (matchedEl) matchedEl.textContent = String(matched);
					if (overEl) overEl.textContent = String(over);
					if (underEl) underEl.textContent = String(under);
				}

				document.querySelectorAll('.audit-actual-input').forEach(function (input) {
					input.addEventListener('input', updateAuditSummary);
				});

				const copyBtn = document.getElementById('auditCopySystemBtn');
				if (copyBtn) {
					copyBtn.addEventListener('click', function () {
						document.querySelectorAll('.audit-actual-input').forEach(function (input) {
							input.value = formatQty(input.dataset.systemQty || '0');
						});
						updateAuditSummary();
					});
				}

				const clearBtn = document.getElementById('auditClearActualBtn');
				if (clearBtn) {
					clearBtn.addEventListener('click', function () {
						document.querySelectorAll('.audit-actual-input').forEach(function (input) {
							input.value = '';
						});
						updateAuditSummary();
					});
				}

				const auditForm = document.getElementById('inventoryAuditForm');
				if (auditForm) {
					auditForm.addEventListener('submit', function (event) {
						let hasActual = false;
						document.querySelectorAll('.audit-actual-input').forEach(function (input) {
							if (input.value.trim() !== '') {
								hasActual = true;
							}
						});
						if (!hasActual) {
							event.preventDefault();
							alert('Enter at least one actual quantity before saving the audit.');
						}
					});
				}
			})();
		</script>
	<?php endif; ?>

	<?php if (!empty($pastAudits)): ?>
		<div class="table-card margin-top">
			<h4 class="h5 mb-3">Recent Audits</h4>
			<div class="table-responsive">
				<table class="table table-hover">
					<thead>
						<tr>
							<th>Date</th>
							<th class="text-end">Counted</th>
							<th class="text-end">Matched</th>
							<th class="text-end">Over</th>
							<th class="text-end">Under</th>
							<th>Notes</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($pastAudits as $audit): ?>
							<?php $pastAuditId = (int) ($audit['ID'] ?? 0); ?>
							<tr>
								<td><?php echo date('M-d-Y h:i A', strtotime((string) ($audit['AuditDate'] ?? 'now'))); ?></td>
								<td class="text-end"><?php echo (int) ($audit['ProductCount'] ?? 0); ?></td>
								<td class="text-end text-success"><?php echo (int) ($audit['MatchCount'] ?? 0); ?></td>
								<td class="text-end text-primary"><?php echo (int) ($audit['OverCount'] ?? 0); ?></td>
								<td class="text-end text-danger"><?php echo (int) ($audit['UnderCount'] ?? 0); ?></td>
								<td><?php echo htmlspecialchars(trim((string) ($audit['Notes'] ?? '')) !== '' ? trim((string) $audit['Notes']) : '—'); ?></td>
								<td class="text-end">
									<div class="d-inline-flex gap-2">
										<a class="btn btn-sm btn-outline-primary" href="?mainmenu=inventory_audit&amp;audit_id=<?php echo $pastAuditId; ?>">View</a>
										<a
											class="btn btn-sm btn-outline-danger"
											href="?mainmenu=delete_inventory_audit&amp;audit_id=<?php echo $pastAuditId; ?>"
											onclick="return confirm('Delete this inventory audit? This cannot be undone.');"
										>Delete</a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>
