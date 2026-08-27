<?php
	$expenseCategory = isset($_GET['expense_category']) ? trim($_GET['expense_category']) : '';
	$returnTo = isset($_GET['return_to']) ? $_GET['return_to'] : '?';
	$todayDate = date('Y-m-d');
	$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? $_GET['from_date'] : $todayDate;
	$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? $_GET['to_date'] : $todayDate;
	$safeExpenseCategory = mysqli_real_escape_string($connectDB, $expenseCategory);
	$expensesByCategory = $connectDB->query("
		SELECT 
			ExpenseDate,
			COUNT(ID) AS expense_count,
			SUM(Amount) AS total_amount
		FROM expenses
		WHERE ExpenseCategory = '$safeExpenseCategory'
		AND ExpenseDate BETWEEN '$fromDate' AND '$toDate'
		GROUP BY ExpenseDate
		ORDER BY ExpenseDate DESC
	");
	$expenseTotal = 0;
	$expenseCount = 0;
?>

<div class="container-fluid has-fixed-footer">
	<h2>Expenses: <?php echo htmlspecialchars($expenseCategory); ?></h2>
	<p><strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($fromDate)); ?> to <?php echo date('M d, Y', strtotime($toDate)); ?></p>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Date</th>
						<th class="text-end">Count</th>
						<th class="text-end">Amount</th>
					</tr>
				</thead>
				<tbody>
			<?php if ($expensesByCategory && $expensesByCategory->num_rows > 0): ?>
				<?php while ($expense = $expensesByCategory->fetch_assoc()): ?>
					<?php
						$expenseTotal += (float) $expense['total_amount'];
						$expenseCount += (int) $expense['expense_count'];
					?>
					<tr>
						<td><?php echo date('M-d-Y', strtotime($expense['ExpenseDate'])); ?></td>
						<td class="text-end"><?php echo $expense['expense_count']; ?></td>
						<td class="text-end">&#8369;<?php echo number_format($expense['total_amount'], 2); ?></td>
					</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="3" class="empty-state">No expense records found for this category.</td></tr>
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
		<p class="metric-label">Count</p>
		<div class="totalprice"><?php echo $expenseCount; ?></div>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p class="metric-label">Total Expenses</p>
		<div class="totalprice">&#8369;<?php echo number_format($expenseTotal, 2); ?></div>
	</div>
</div>
