<?php
	$expenseCategories = [];
	$categoryResult = $connectDB->query("SELECT CategoryName FROM expense_categories WHERE IsActive = 1 ORDER BY CategoryName ASC");
	if ($categoryResult && $categoryResult->num_rows > 0) {
		while ($row = $categoryResult->fetch_assoc()) {
			$expenseCategories[] = $row['CategoryName'];
		}
	}
?>

<form class="form new-form" method="POST" action="?mainmenu=save_expenses" id="expensesForm">
	<div class="container-fluid form-page">
		<div class="dashboard-card">
			<div class="dashboard-list-head">
				<div>
					<p class="section-kicker">Expense tracking</p>
					<h3>Log operating expenses</h3>
				</div>
				<div class="min-width">
					<label for="expense_date" class="form-label">Expense Date</label>
					<input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required />
				</div>
			</div>

			<div class="row custom-row expense-row transaction-entry-row">
			<div class="col-sm-1">
				<label>No</label><br>
				<label class="static-expense-row-number">1</label>
			</div>
			<div class="col-sm-3">
				<label>Category</label>
				<input type="text" class="form-control" name="expense_category[]" list="expense_categories" required />
			</div>
			<div class="col-sm-5">
				<label>Description</label>
				<input type="text" class="form-control" name="expense_description[]" placeholder="Diesel refill, labor payment, repair details" />
			</div>
			<div class="col-sm-2">
				<label>Amount</label>
				<input type="number" step="0.01" min="0" class="form-control" name="expense_amount[]" required oninput="calculateExpenseTotal()" />
			</div>
			<div class="col-sm-1">
				<label>Action</label>
				<div class="margin-top-sm icon-action-group">
					<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteExpenseRow(this)" aria-label="Delete expense row" title="Delete">
						<i class="bi bi-trash3" aria-hidden="true"></i>
					</button>
				</div>
			</div>
		</div>

			<datalist id="expense_categories">
			<?php foreach ($expenseCategories as $expenseCategory): ?>
				<option value="<?php echo htmlspecialchars($expenseCategory); ?>"></option>
			<?php endforeach; ?>
			</datalist>

			<div id="expenseFields"></div>
		</div>
	</div>

	<div class="row custom-row fixed-footer">
		<div class="col-md-2 col-12 footer-action">
			<button type="button" class="btn btn-info w-100" id="addExpenseButton">+ Add</button>
		</div>
		<div class="col-md-2 col-12 footer-action">
			<input type="submit" class="btn btn-success w-100" value="Save Expenses">
		</div>
		<div class="col-md-5 col-12 footer-metric">
			<p class="metric-label">Total Expenses</p>
			<div class="totalprice">&#8369;<span id="expenseGrandTotal">0.00</span></div>
		</div>
	</div>
</form>

<script>
	let expenseRowCount = 2;

	document.getElementById('addExpenseButton').addEventListener('click', function() {
		const container = document.getElementById('expenseFields');
		const newRow = document.createElement('div');
		newRow.className = 'row mt-3 expense-row transaction-entry-row';
		newRow.innerHTML = `
			<div class="col-sm-1">
				<br>
				<label class="expenseRowCountLabel">${expenseRowCount}</label>
			</div>
			<div class="col-sm-3">
				<label>Category</label>
				<input type="text" class="form-control" name="expense_category[]" list="expense_categories" required />
			</div>
			<div class="col-sm-5">
				<label>Description</label>
				<input type="text" class="form-control" name="expense_description[]" />
			</div>
			<div class="col-sm-2">
				<label>Amount</label>
				<input type="number" step="0.01" min="0" class="form-control" name="expense_amount[]" required oninput="calculateExpenseTotal()" />
			</div>
			<div class="col-sm-1">
				<label>Action</label>
				<div class="margin-top-sm icon-action-group">
					<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteExpenseRow(this)" aria-label="Delete expense row" title="Delete">
						<i class="bi bi-trash3" aria-hidden="true"></i>
					</button>
				</div>
			</div>
		`;
		container.appendChild(newRow);
		newRow.querySelector('input[name="expense_category[]"]').focus();
		expenseRowCount++;
		updateExpenseRowCounts();
	});

	function deleteExpenseRow(button) {
		const row = button.closest('.expense-row');
		row.remove();
		updateExpenseRowCounts();
		calculateExpenseTotal();
	}

	function updateExpenseRowCounts() {
		const baseRow = document.querySelector('.static-expense-row-number');
		if (baseRow) {
			baseRow.textContent = '1';
		}
		const rows = document.querySelectorAll('#expenseFields .expense-row');
		let count = 2;
		rows.forEach(function(row) {
			row.querySelector('.expenseRowCountLabel').textContent = count;
			count++;
		});
		expenseRowCount = count;
	}

	function calculateExpenseTotal() {
		let total = 0;
		document.querySelectorAll('input[name="expense_amount[]"]').forEach(function(input) {
			total += parseFloat(input.value) || 0;
		});
		document.getElementById('expenseGrandTotal').textContent = total.toFixed(2);
	}
</script>
