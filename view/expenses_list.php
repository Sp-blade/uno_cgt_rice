<?php
	$searchExpense = isset($_GET['searchExpense']) ? mysqli_real_escape_string($connectDB, $_GET['searchExpense']) : '';
	$sql = "SELECT ExpenseDate, ExpenseNo, MAX(DeliveryNo) AS delivery_no, COUNT(ID) AS item_count, SUM(Amount) AS total_amount,
			GROUP_CONCAT(DISTINCT CASE WHEN Description IS NOT NULL AND Description <> '' THEN Description ELSE ExpenseCategory END ORDER BY ID SEPARATOR ', ') AS expense_summary
		FROM expenses
		WHERE ExpenseNo LIKE '%$searchExpense%' OR ExpenseCategory LIKE '%$searchExpense%' OR Description LIKE '%$searchExpense%'
		GROUP BY ExpenseDate, ExpenseNo
		ORDER BY ExpenseDate DESC, ExpenseNo DESC";
	$expenseList = $connectDB->query($sql);
?>

<div class="dashboard-card">
	<div class="section-head">
		<div>
			<p class="section-kicker">History</p>
			<h3>Expense history</h3>
		</div>
	</div>

	<form method="GET" class="list-toolbar">
		<input type="hidden" name="mainmenu" value="expenses_list" />
		<input type="text" class="searchbox" name="searchExpense" placeholder="Search Expense No / Category" value="<?php echo htmlspecialchars($searchExpense); ?>" />
		<button type="submit" class="btn btn-primary">Search</button>
	</form>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Date</th>
						<th>Expense No</th>
						<th>Summary</th>
						<th>Linked Delivery</th>
						<th>Items</th>
						<th>Total Amount</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($expenseList && $expenseList->num_rows > 0): ?>
						<?php while ($expense = $expenseList->fetch_assoc()): ?>
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
								<td>
									<?php if ((int) $expense['delivery_no'] > 0): ?>
										<a class="btn btn-outline-secondary btn-sm" href="?mainmenu=view_sale&deliveryNo=<?php echo $expense['delivery_no']; ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
											Delivery #<?php echo $expense['delivery_no']; ?>
										</a>
									<?php else: ?>
										<span class="text-muted">Not linked</span>
									<?php endif; ?>
								</td>
								<td><?php echo $expense['item_count']; ?></td>
								<td>&#8369;<?php echo number_format($expense['total_amount'], 2); ?></td>
								<td class="text-center">
									<div class="icon-action-group justify-content-center">
										<a
											class="icon-action-btn icon-action-btn-view"
											href="?mainmenu=view_expense&expenseNo=<?php echo $expense['ExpenseNo']; ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
											aria-label="View expense <?php echo $expense['ExpenseNo']; ?>"
											title="View"
										>
											<i class="bi bi-eye" aria-hidden="true"></i>
										</a>
										<a
											class="icon-action-btn icon-action-btn-delete js-history-delete"
											href="?mainmenu=expenses_list&delete_product=1&expenseNo=<?php echo $expense['ExpenseNo']; ?>&searchExpense=<?php echo urlencode($searchExpense); ?>"
											data-record-label="Expense No. <?php echo $expense['ExpenseNo']; ?>"
											aria-label="Delete expense <?php echo $expense['ExpenseNo']; ?>"
											title="Delete"
										>
											<i class="bi bi-trash3" aria-hidden="true"></i>
										</a>
									</div>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="7" class="empty-state">No expense history found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.js-history-delete').forEach(function (button) {
			button.addEventListener('click', function (event) {
				const recordLabel = button.getAttribute('data-record-label') || 'this record';
				const confirmation = window.prompt('Type DELETE to permanently remove ' + recordLabel + '.');
				if (confirmation !== 'DELETE') {
					event.preventDefault();
				}
			});
		});
	});
</script>
