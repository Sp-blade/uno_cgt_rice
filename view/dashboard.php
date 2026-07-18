<?php
	function get_summary_total($connectDB, $table, $dateColumn, $amountColumn, $fromDate, $toDate) {
		$query = "SELECT COALESCE(SUM($amountColumn), 0) AS total FROM $table WHERE DATE($dateColumn) BETWEEN '$fromDate' AND '$toDate'";
		$result = $connectDB->query($query);
		$row = $result ? $result->fetch_assoc() : ['total' => 0];
		return (float) ($row['total'] ?? 0);
	}

	$today = date('Y-m-d');
	$currentMonthStart = date('Y-m-01');
	$currentMonthEnd = date('Y-m-t');
	$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? $_GET['from_date'] : $currentMonthStart;
	$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? $_GET['to_date'] : $currentMonthEnd;
	$expenseTotal = get_summary_total($connectDB, 'expenses', 'ExpenseDate', 'Amount', $fromDate, $toDate);
	$salesTotal = get_summary_total($connectDB, 'sales', 'SaleDate', 'TotalSalePrice', $fromDate, $toDate);
	$purchaseTotal = get_summary_total($connectDB, 'purchases', 'PurchaseDate', 'TotalPurchasePrice', $fromDate, $toDate);
	$netTotal = $salesTotal - $purchaseTotal - $expenseTotal;

	$recentPurchases = $connectDB->query("
		SELECT
			ProductName,
			SUM(Quantity) AS total_quantity,
			AVG(ProductPrice) AS average_purchase_price,
			SUM(TotalPurchasePrice) AS total_purchase_price
		FROM purchases
		WHERE DATE(PurchaseDate) BETWEEN '$fromDate' AND '$toDate'
		GROUP BY ProductName
		ORDER BY total_purchase_price DESC, ProductName ASC
	");
	$recentExpenses = $connectDB->query("
		SELECT
			ExpenseDate,
			ExpenseNo,
			GROUP_CONCAT(DISTINCT CASE WHEN Description IS NOT NULL AND Description <> '' THEN Description ELSE ExpenseCategory END ORDER BY ID SEPARATOR ', ') AS expense_summary,
			COUNT(ID) AS item_count,
			SUM(Amount) AS total_amount
		FROM expenses
		WHERE ExpenseDate BETWEEN '$fromDate' AND '$toDate'
		GROUP BY ExpenseDate, ExpenseNo
		ORDER BY ExpenseDate DESC, ExpenseNo DESC
	");
	$recentSales = $connectDB->query("
		SELECT
			SaleDate,
			DeliveryNo,
			CustomerName,
			COUNT(ID) AS item_count,
			SUM(TotalSalePrice) AS total_amount,
			GROUP_CONCAT(DISTINCT ProductName ORDER BY ID SEPARATOR ', ') AS sold_summary,
			GROUP_CONCAT(DISTINCT CASE WHEN Notes IS NOT NULL AND TRIM(Notes) <> '' THEN TRIM(Notes) END ORDER BY ID SEPARATOR ', ') AS notes_summary
		FROM sales
		WHERE SaleDate BETWEEN '$fromDate' AND '$toDate'
		GROUP BY SaleDate, DeliveryNo, CustomerName
		ORDER BY SaleDate DESC, DeliveryNo DESC
	");
	$returnTo = urlencode($_SERVER['REQUEST_URI']);
?>

<div class="dashboard-overview">
	<div class="summary-card">
		<p class="metric-label">Total Sales</p>
		<div class="summary-value">&#8369;<?php echo number_format($salesTotal, 2); ?></div>
		<p class="summary-note">Product sales recorded this period.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Total Expenses</p>
		<div class="summary-value">&#8369;<?php echo number_format($expenseTotal, 2); ?></div>
		<p class="summary-note">Operating expenses and related cash outflows.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Stock Purchases</p>
		<div class="summary-value">&#8369;<?php echo number_format($purchaseTotal, 2); ?></div>
		<p class="summary-note">Stock-in costs in the selected date range.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Net Position</p>
		<div class="summary-value <?php echo $netTotal >= 0 ? 'text-success' : 'text-danger'; ?>">&#8369;<?php echo number_format($netTotal, 2); ?></div>
		<p class="summary-note">Sales minus purchases and expenses.</p>
	</div>
</div>

<div class="filter-card mb-4">
	<div class="section-head">
		<div>
			<p class="section-kicker">Filter overview</p>
			<h3>Business activity by date</h3>
		</div>
	</div>
	<form method="GET" class="row align-items-end g-3">
		<div class="col-md-4">
			<label for="from_date" class="form-label">From</label>
			<input type="date" id="from_date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
		</div>
		<div class="col-md-4">
			<label for="to_date" class="form-label">To</label>
			<input type="date" id="to_date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
		</div>
		<div class="col-md-4 d-flex gap-2">
			<button type="submit" class="btn btn-primary w-100">Apply Filter</button>
			<a href="?from_date=<?php echo urlencode($today); ?>&to_date=<?php echo urlencode($today); ?>" class="btn btn-outline-secondary w-100">Today</a>
		</div>
	</form>
</div>

<ul class="nav nav-pills dashboard-history-tabs" id="dashboardTabs" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#dashboard-purchases" type="button" role="tab">Stock In</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#dashboard-expenses" type="button" role="tab">Expenses</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#dashboard-sold" type="button" role="tab">Sales</button>
	</li>
</ul>

<div class="tab-content">
	<div class="tab-pane fade" id="dashboard-purchases" role="tabpanel">
		<div class="dashboard-card">
			<div class="dashboard-list-head">
				<div>
					<p class="section-kicker">Purchase analytics</p>
					<h3>Highest-value purchase items</h3>
				</div>
				<a class="btn btn-info" href="?mainmenu=purchase_product">Add Purchase</a>
			</div>
			<div class="table-card">
				<div class="table-responsive">
					<table class="table table-hover dashboard-summary-table">
						<thead>
							<tr>
								<th>Product</th>
								<th class="text-end">Pcs/Kg</th>
								<th class="text-end">Average Purchase</th>
								<th class="text-end">Total</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($recentPurchases && $recentPurchases->num_rows > 0): ?>
								<?php while ($purchase = $recentPurchases->fetch_assoc()): ?>
									<tr>
										<td><?php echo htmlspecialchars($purchase['ProductName']); ?></td>
										<td class="text-end"><?php echo number_format($purchase['total_quantity'], 2); ?></td>
										<td class="text-end">&#8369;<?php echo number_format($purchase['average_purchase_price'], 2); ?></td>
										<td class="text-end">&#8369;<?php echo number_format($purchase['total_purchase_price'], 2); ?></td>
										<td><a class="btn btn-sm btn-info" href="?mainmenu=view_purchase_product&product_name=<?php echo urlencode($purchase['ProductName']); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>&return_to=<?php echo $returnTo; ?>">View</a></td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr><td colspan="5" class="empty-state">No purchase records found for this filter.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="tab-pane fade" id="dashboard-expenses" role="tabpanel">
		<div class="dashboard-card">
			<div class="dashboard-list-head">
				<div>
					<p class="section-kicker">Expense history</p>
					<h3>Recent expense records</h3>
				</div>
				<div class="d-flex gap-2 flex-wrap">
					<a class="btn btn-info" href="?mainmenu=expenses">Add Expense</a>
					<a class="btn btn-outline-secondary" href="?mainmenu=expenses_list">View Full History</a>
				</div>
			</div>
			<div class="table-card">
				<div class="table-responsive">
					<table class="table table-hover dashboard-summary-table">
						<thead>
							<tr>
								<th>Date</th>
								<th>Expense No</th>
								<th>Summary</th>
								<th class="text-end">Items</th>
								<th class="text-end">Total Amount</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($recentExpenses && $recentExpenses->num_rows > 0): ?>
								<?php while ($expense = $recentExpenses->fetch_assoc()): ?>
									<tr>
										<td><?php echo date('M-d-Y', strtotime($expense['ExpenseDate'])); ?></td>
										<td><?php echo $expense['ExpenseNo']; ?></td>
										<td>
											<?php
												$summaryText = trim((string) ($expense['expense_summary'] ?? ''));
												if ($summaryText === '') {
													$summaryText = 'No summary available';
												}
												if (strlen($summaryText) > 70) {
													$summaryText = substr($summaryText, 0, 67) . '...';
												}
												echo htmlspecialchars($summaryText);
											?>
										</td>
										<td class="text-end"><?php echo $expense['item_count']; ?></td>
										<td class="text-end">&#8369;<?php echo number_format($expense['total_amount'], 2); ?></td>
										<td><a class="btn btn-sm btn-info" href="?mainmenu=view_expense&expenseNo=<?php echo $expense['ExpenseNo']; ?>&return_to=<?php echo $returnTo; ?>">View</a></td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr><td colspan="6" class="empty-state">No expense history found for this filter.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="tab-pane fade show active" id="dashboard-sold" role="tabpanel">
		<div class="dashboard-card">
			<div class="dashboard-list-head">
				<div>
					<p class="section-kicker">Sales history</p>
					<h3>Recent sales records</h3>
				</div>
				<div class="d-flex gap-2 flex-wrap">
					<a class="btn btn-info" href="?mainmenu=sell_product_others">Sell Product</a>
					<a class="btn btn-outline-secondary" href="?mainmenu=sales_list">View Full History</a>
				</div>
			</div>
			<div class="table-card">
				<div class="table-responsive">
					<table class="table table-hover dashboard-summary-table">
						<thead>
							<tr>
								<th>Date</th>
								<th>Sale No</th>
								<th>Customer</th>
								<th>Summary</th>
								<th>Note</th>
								<th class="text-end">Items</th>
								<th class="text-end">Total Sold</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($recentSales && $recentSales->num_rows > 0): ?>
								<?php while ($sale = $recentSales->fetch_assoc()): ?>
									<tr>
										<td><?php echo junkshop_format_datetime($sale['SaleDate']); ?></td>
										<td><?php echo $sale['DeliveryNo']; ?></td>
										<td><?php echo htmlspecialchars($sale['CustomerName']); ?></td>
										<td>
											<?php
												$summaryText = trim((string) ($sale['sold_summary'] ?? ''));
												if ($summaryText === '') {
													$summaryText = 'No summary available';
												}
												if (strlen($summaryText) > 70) {
													$summaryText = substr($summaryText, 0, 67) . '...';
												}
												echo htmlspecialchars($summaryText);
											?>
										</td>
										<td>
											<?php
												$notesText = trim((string) ($sale['notes_summary'] ?? ''));
												if ($notesText === '') {
													$notesText = 'No note';
												}
												if (strlen($notesText) > 70) {
													$notesText = substr($notesText, 0, 67) . '...';
												}
												echo htmlspecialchars($notesText);
											?>
										</td>
										<td class="text-end"><?php echo $sale['item_count']; ?></td>
										<td class="text-end">&#8369;<?php echo number_format($sale['total_amount'], 2); ?></td>
										<td><a class="btn btn-sm btn-info" href="?mainmenu=view_sale&deliveryNo=<?php echo $sale['DeliveryNo']; ?>&return_to=<?php echo $returnTo; ?>">View</a></td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr><td colspan="8" class="empty-state">No sold/delivery history found for this filter.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row fixed-footer">
	<div class="col-md-3 col-6 footer-metric">
		<p class="metric-label">Purchases</p>
		<div class="totalprice">&#8369;<?php echo number_format($purchaseTotal, 2); ?></div>
	</div>
	<div class="col-md-3 col-6 footer-metric">
		<p class="metric-label">Expenses</p>
		<div class="totalprice">&#8369;<?php echo number_format($expenseTotal, 2); ?></div>
	</div>
	<div class="col-md-3 col-6 footer-metric">
		<p class="metric-label">Sales</p>
		<div class="totalprice">&#8369;<?php echo number_format($salesTotal, 2); ?></div>
	</div>
	<div class="col-md-3 col-6 footer-metric">
		<p class="metric-label">Net Position</p>
		<div class="totalprice <?php echo $netTotal >= 0 ? 'text-success' : 'text-danger'; ?>">&#8369;<?php echo number_format($netTotal, 2); ?></div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		const hash = window.location.hash;
		if (hash) {
			const trigger = document.querySelector('[data-bs-target="' + hash + '"]');
			if (trigger) {
				bootstrap.Tab.getOrCreateInstance(trigger).show();
			}
		}

		document.querySelectorAll('#dashboardTabs [data-bs-toggle="tab"]').forEach(function (tab) {
			tab.addEventListener('shown.bs.tab', function (event) {
				if (event.target.dataset.bsTarget) {
					window.location.hash = event.target.dataset.bsTarget;
				}
			});
		});
	});
</script>
