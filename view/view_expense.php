<?php
	$expenseNo = (int) ($_GET['expenseNo'] ?? 0);
	$returnTo = isset($_GET['return_to']) ? $_GET['return_to'] : '?mainmenu=expenses_list';
	$encodedReturnTo = htmlspecialchars($returnTo, ENT_QUOTES);
	$expenseDetails = $connectDB->query("SELECT * FROM expenses WHERE ExpenseNo = '$expenseNo' ORDER BY ID ASC");
	$expenseTotal = 0;
	$expenseItemCount = 0;
	$expenseCategories = [];
	$categoryResult = $connectDB->query("SELECT CategoryName FROM expense_categories WHERE IsActive = 1 ORDER BY CategoryName ASC");
	if ($categoryResult && $categoryResult->num_rows > 0) {
		while ($row = $categoryResult->fetch_assoc()) {
			$expenseCategories[] = $row['CategoryName'];
		}
	}
	$summaryExpenseDate = date('Y-m-d');
	$expenseSummaryResult = $connectDB->query("SELECT COUNT(ID) AS item_count, COALESCE(SUM(Amount), 0) AS total_amount, MIN(ExpenseDate) AS expense_date FROM expenses WHERE ExpenseNo = '$expenseNo'");
	if ($expenseSummaryResult) {
		$expenseSummary = $expenseSummaryResult->fetch_assoc();
		$expenseItemCount = (int) ($expenseSummary['item_count'] ?? 0);
		$expenseTotal = (float) ($expenseSummary['total_amount'] ?? 0);
		$summaryExpenseDate = $expenseSummary['expense_date'] ?? $summaryExpenseDate;
	}
	$latestExpenseDate = $summaryExpenseDate;
?>

<div class="container-fluid has-fixed-footer">
	<div class="dashboard-card">
		<div class="dashboard-list-head">
			<div>
				<p class="section-kicker">Expense</p>
				<h3>Expense No. <?php echo $expenseNo; ?></h3>
			</div>
			<div class="detail-head-meta">
				<div class="detail-head-meta-grid detail-head-meta-grid--single">
					<div>
						<p class="page-kicker">Expense Date</p>
						<h3><?php echo $summaryExpenseDate !== '' ? date('M-d-Y', strtotime($summaryExpenseDate)) : 'Not available'; ?></h3>
					</div>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>No</th>
						<th>Category</th>
						<th>Description</th>
						<th class="text-end">Amount</th>
						<th class="text-center">Actions</th>
					</tr>
				</thead>
				<tbody>
			<?php if ($expenseDetails && $expenseDetails->num_rows > 0): ?>
				<?php $count = 1; ?>
					<?php while ($expense = $expenseDetails->fetch_assoc()): ?>
						<?php $latestExpenseDate = $expense['ExpenseDate']; ?>
						<tr>
						<td><?php echo $count; ?></td>
						<td><?php echo htmlspecialchars($expense['ExpenseCategory']); ?></td>
						<td><?php echo htmlspecialchars($expense['Description']); ?></td>
						<td class="text-end">&#8369;<?php echo number_format($expense['Amount'], 2); ?></td>
						<td class="text-center">
							<div class="icon-action-group justify-content-center">
							<button
								data-toggle="modal"
								data-target="#editExpenseDetails"
								type="button"
								class="icon-action-btn icon-action-btn-edit"
								data-id="<?php echo $expense['ID']; ?>"
								data-date="<?php echo $expense['ExpenseDate']; ?>"
								data-category="<?php echo htmlspecialchars($expense['ExpenseCategory'], ENT_QUOTES); ?>"
								data-description="<?php echo htmlspecialchars($expense['Description'], ENT_QUOTES); ?>"
								data-amount="<?php echo $expense['Amount']; ?>"
								aria-label="Edit expense <?php echo $count; ?>"
								title="Edit"
							>
								<i class="bi bi-pencil-square" aria-hidden="true"></i>
							</button>
							<a
								href="<?php echo $server; ?>?mainmenu=view_expense&expenseNo=<?php echo $expenseNo; ?>&return_to=<?php echo urlencode($returnTo); ?>&delete_product=1&ID=<?php echo $expense['ID']; ?>"
								class="icon-action-btn icon-action-btn-delete"
								onclick='return confirm("Do you want to delete this expense?")'
								aria-label="Delete expense <?php echo $count; ?>"
								title="Delete"
							>
								<i class="bi bi-trash3" aria-hidden="true"></i>
							</a>
							</div>
						</td>
					</tr>
					<?php $count++; ?>
				<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="5" class="empty-state">No expense details found.</td></tr>
			<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="row custom-row fixed-footer footer-layout-dual">
	<div class="col-md-2 col-12 footer-action">
		<a class="btn btn-info w-100" href="<?php echo $encodedReturnTo; ?>">Back</a>
	</div>
	<div class="col-md-2 col-12 footer-action">
		<button data-toggle="modal" data-target="#addExpenseTransaction" class="btn btn-info w-100">Add Transaction</button>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p class="metric-label">Items</p>
		<div class="totalprice"><?php echo $expenseItemCount; ?></div>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p class="metric-label">Total Expenses</p>
		<div class="totalprice">&#8369;<?php echo number_format($expenseTotal, 2); ?></div>
	</div>
</div>

<script>
	$(document).ready(function () {
		$('#editExpenseDetails').on('show.bs.modal', function (event) {
			var button = $(event.relatedTarget);
			var modal = $(this);
			modal.find('input[name="ID"]').val(button.data('id'));
			modal.find('input[name="expense_date"]').val(button.data('date'));
			modal.find('input[name="expense_category"]').val(button.data('category'));
			modal.find('input[name="expense_description"]').val(button.data('description'));
			modal.find('input[name="expense_amount"]').val(button.data('amount'));
		});
	});
</script>

<div class="modal fade" id="addExpenseTransaction" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Add Expense Transaction</h4>
			</div>
			<div class="modal-body">
				<form class="form new-form" method="GET">
					<input type="hidden" name="add_new_product" value="add_new_product" />
					<input type="hidden" name="mainmenu" value="view_expense" />
					<input type="hidden" name="expenseNo" value="<?php echo $expenseNo; ?>" />
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />

					<label for="add_expense_date">Date:</label>
					<input type="date" class="form-control" id="add_expense_date" name="expense_date" value="<?php echo htmlspecialchars($latestExpenseDate); ?>" required />

					<label for="add_expense_category">Category:</label>
					<input type="text" class="form-control" id="add_expense_category" name="expense_category" list="expense_category_options" required />
					<datalist id="expense_category_options">
						<?php foreach ($expenseCategories as $expenseCategory): ?>
							<option value="<?php echo htmlspecialchars($expenseCategory); ?>"></option>
						<?php endforeach; ?>
					</datalist>

					<label for="add_expense_description">Description:</label>
					<input type="text" class="form-control" id="add_expense_description" name="expense_description" value="" />

					<label for="add_expense_amount">Amount:</label>
					<input type="number" step="0.01" min="0" class="form-control" id="add_expense_amount" name="expense_amount" value="" required />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" value="Save">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
				</div>
				</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editExpenseDetails" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Edit Expense</h4>
			</div>
			<div class="modal-body">
				<form class="form new-form" method="GET">
					<input type="hidden" name="edit_product" value="edit_product" />
					<input type="hidden" name="mainmenu" value="view_expense" />
					<input type="hidden" name="expenseNo" value="<?php echo $expenseNo; ?>" />
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />
					<input type="hidden" name="ID" value="" />

					<label for="expense_date">Date:</label>
					<input type="date" class="form-control" id="expense_date" name="expense_date" value="" required />

					<label for="expense_category">Category:</label>
					<input type="text" class="form-control" id="expense_category" name="expense_category" value="" required />

					<label for="expense_description">Description:</label>
					<input type="text" class="form-control" id="expense_description" name="expense_description" value="" required />

					<label for="expense_amount">Amount:</label>
					<input type="number" step="0.01" class="form-control" id="expense_amount" name="expense_amount" value="" required />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" name="save_changes" value="Save">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
				</div>
				</form>
		</div>
	</div>
</div>
