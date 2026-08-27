<?php
	function get_summary_total($connectDB, $table, $dateColumn, $amountColumn, $fromDate, $toDate) {
		$query = "SELECT COALESCE(SUM($amountColumn), 0) AS total FROM $table WHERE DATE($dateColumn) BETWEEN '$fromDate' AND '$toDate'";
		$result = $connectDB->query($query);
		$row = $result ? $result->fetch_assoc() : ['total' => 0];
		return (float) ($row['total'] ?? 0);
	}

	function get_sales_loan_total($connectDB, $fromDate, $toDate) {
		$query = "
			SELECT COALESCE(SUM(GREATEST(TotalSalePrice - AmountPaid, 0)), 0) AS total
			FROM sales
			WHERE DATE(SaleDate) BETWEEN '$fromDate' AND '$toDate'
		";
		$result = $connectDB->query($query);
		$row = $result ? $result->fetch_assoc() : ['total' => 0];
		return (float) ($row['total'] ?? 0);
	}

	function get_realized_gross_profit_total($connectDB, $fromDate, $toDate) {
		$safeFromDate = mysqli_real_escape_string($connectDB, $fromDate);
		$safeToDate = mysqli_real_escape_string($connectDB, $toDate);

		$paymentMargin = 0.0;
		$paymentResult = $connectDB->query("
			SELECT COALESCE(SUM(
				CASE
					WHEN delivery_totals.total_sale > 0 THEN
						cp.Amount - (delivery_totals.total_cost * cp.Amount / delivery_totals.total_sale)
					ELSE 0
				END
			), 0) AS total
			FROM customer_payments cp
			INNER JOIN (
				SELECT
					DeliveryNo,
					SUM(TotalSalePrice) AS total_sale,
					SUM(TotalPurchaseCost) AS total_cost
				FROM sales
				GROUP BY DeliveryNo
			) delivery_totals ON delivery_totals.DeliveryNo = cp.DeliveryNo
			WHERE DATE(cp.PaymentDate) BETWEEN '$safeFromDate' AND '$safeToDate'
		");
		if ($paymentResult) {
			$paymentMargin = (float) (($paymentResult->fetch_assoc()['total'] ?? 0));
		}

		$saleMargin = 0.0;
		$saleResult = $connectDB->query("
			SELECT COALESCE(SUM(
				CASE
					WHEN d.total_sale > 0 THEN
						GREATEST(d.amount_paid - COALESCE(cp_totals.cp_sum, 0), 0)
						- (d.total_cost * GREATEST(d.amount_paid - COALESCE(cp_totals.cp_sum, 0), 0) / d.total_sale)
					ELSE 0
				END
			), 0) AS total
			FROM (
				SELECT
					DeliveryNo,
					SUM(TotalSalePrice) AS total_sale,
					SUM(TotalPurchaseCost) AS total_cost,
					SUM(AmountPaid) AS amount_paid,
					MIN(DATE(SaleDate)) AS sale_day
				FROM sales
				GROUP BY DeliveryNo
			) d
			LEFT JOIN (
				SELECT DeliveryNo, COALESCE(SUM(Amount), 0) AS cp_sum
				FROM customer_payments
				GROUP BY DeliveryNo
			) cp_totals ON cp_totals.DeliveryNo = d.DeliveryNo
			WHERE d.sale_day BETWEEN '$safeFromDate' AND '$safeToDate'
		");
		if ($saleResult) {
			$saleMargin = (float) (($saleResult->fetch_assoc()['total'] ?? 0));
		}

		return round($paymentMargin + $saleMargin, 2);
	}

	$today = date('Y-m-d');
	$currentMonthStart = date('Y-m-01');
	$currentMonthEnd = date('Y-m-t');
	$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? $_GET['from_date'] : $currentMonthStart;
	$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? $_GET['to_date'] : $currentMonthEnd;
	$expenseTotal = get_summary_total($connectDB, 'expenses', 'ExpenseDate', 'Amount', $fromDate, $toDate);
	$salesTotal = get_summary_total($connectDB, 'sales', 'SaleDate', 'TotalSalePrice', $fromDate, $toDate);
	$grossProfitTotal = get_realized_gross_profit_total($connectDB, $fromDate, $toDate);
	$loanTotal = get_sales_loan_total($connectDB, $fromDate, $toDate);
	$purchaseTotal = get_summary_total($connectDB, 'purchases', 'PurchaseDate', 'TotalPurchasePrice', $fromDate, $toDate);
	$netTotal = $grossProfitTotal - $expenseTotal;

	$recentPurchases = $connectDB->query("
		SELECT
			Product_ID,
			MAX(ProductName) AS ProductName,
			SUM(Quantity) AS total_quantity,
			AVG(ProductPrice) AS average_purchase_price,
			SUM(TotalPurchasePrice) AS total_purchase_price
		FROM purchases
		WHERE DATE(PurchaseDate) BETWEEN '$fromDate' AND '$toDate'
		GROUP BY Product_ID
		HAVING Product_ID > 0
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
		<p class="summary-note">Gross sales recorded this period.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Total Loan / Pautang</p>
		<div class="summary-value text-warning">&#8369;<?php echo number_format($loanTotal, 2); ?></div>
		<p class="summary-note">Unpaid and partial balances from sales this period.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Total Expenses</p>
		<div class="summary-value">&#8369;<?php echo number_format($expenseTotal, 2); ?></div>
		<p class="summary-note">Operating expenses and related cash outflows.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Stock Purchases</p>
		<div class="summary-value">&#8369;<?php echo number_format($purchaseTotal, 2); ?></div>
		<p class="summary-note">Stock-in costs recorded in the selected date range.</p>
	</div>
	<div class="summary-card">
		<p class="metric-label">Net Income</p>
		<div class="summary-value <?php echo $netTotal >= 0 ? 'text-success' : 'text-danger'; ?>">&#8369;<?php echo number_format($netTotal, 2); ?></div>
		<p class="summary-note">Profit from cash collected on sales and loan payments, minus expenses.</p>
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
									<?php
										$purchaseProductId = (int) ($purchase['Product_ID'] ?? 0);
										$purchaseProductName = trim((string) ($purchase['ProductName'] ?? ''));
									?>
									<tr>
										<td><?php echo htmlspecialchars($purchaseProductName); ?></td>
										<td class="text-end"><?php echo number_format($purchase['total_quantity'], 2); ?></td>
										<td class="text-end">&#8369;<?php echo number_format($purchase['average_purchase_price'], 2); ?></td>
										<td class="text-end">&#8369;<?php echo number_format($purchase['total_purchase_price'], 2); ?></td>
										<td><a class="btn btn-sm btn-info" href="?mainmenu=view_purchase_product&product_id=<?php echo $purchaseProductId; ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>&return_to=<?php echo $returnTo; ?>">View</a></td>
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
								<th>Sales Invoice</th>
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

<div class="row custom-row fixed-footer dashboard-footer-metrics">
	<div class="col-6 col-xl footer-metric">
		<p class="metric-label">Total Sales</p>
		<div class="totalprice">&#8369;<?php echo number_format($salesTotal, 2); ?></div>
	</div>
	<div class="col-6 col-xl footer-metric">
		<p class="metric-label">Total Loan / Pautang</p>
		<div class="totalprice text-warning">&#8369;<?php echo number_format($loanTotal, 2); ?></div>
	</div>
	<div class="col-6 col-xl footer-metric">
		<p class="metric-label">Total Expenses</p>
		<div class="totalprice">&#8369;<?php echo number_format($expenseTotal, 2); ?></div>
	</div>
	<div class="col-6 col-xl footer-metric">
		<p class="metric-label">Stock Purchases</p>
		<div class="totalprice">&#8369;<?php echo number_format($purchaseTotal, 2); ?></div>
	</div>
	<div class="col-6 col-xl footer-metric">
		<p class="metric-label">Net Income</p>
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
