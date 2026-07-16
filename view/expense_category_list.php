<?php
	$allCategories = "SELECT * FROM expense_categories ORDER BY CategoryName ASC";
	$categoryList = $connectDB->query($allCategories);
?>

<div class="dashboard-card">
	<div class="dashboard-list-head">
		<div>
			<p class="section-kicker">Setup</p>
			<h3>Expense category list</h3>
		</div>
		<button data-bs-toggle="modal" data-bs-target="#addExpenseCategory" class="btn btn-info">Add Expense Category</button>
	</div>

	<div class="table-card">
		<div class="table-responsive">
			<table class="table table-hover margin-top">
				<thead>
					<tr>
						<th>No</th>
						<th>Category Name</th>
						<th class="text-center">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php $count = 0; ?>
					<?php while ($category = $categoryList->fetch_assoc()): ?>
						<?php $count++; ?>
						<tr>
							<td><?php echo $count; ?></td>
							<td><?php echo htmlspecialchars($category['CategoryName']); ?></td>
							<td class="text-center">
								<form method="GET" class="d-inline-flex align-items-center gap-2">
									<input type="hidden" name="mainmenu" value="expense_category_list" />
									<input type="hidden" name="toggle_expense_category_status" value="1" />
									<input type="hidden" name="categoryID" value="<?php echo (int) $category['ID']; ?>" />
									<input type="hidden" name="target_active" value="<?php echo (int) (!$category['IsActive']); ?>" />
									<div class="form-check form-switch m-0 d-inline-flex align-items-center">
										<input
											class="form-check-input"
											type="checkbox"
											role="switch"
											<?php echo ((int) $category['IsActive'] === 1) ? 'checked' : ''; ?>
											onchange="this.form.querySelector('input[name=&quot;target_active&quot;]').value = this.checked ? '1' : '0'; this.form.submit();"
										/>
									</div>
									<span class="badge <?php echo ((int) $category['IsActive'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
										<?php echo ((int) $category['IsActive'] === 1) ? 'Active' : 'Inactive'; ?>
									</span>
								</form>
							</td>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="modal fade" id="addExpenseCategory" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form class="form new-form" method="GET">
				<div class="modal-header">
					<h4 class="modal-title">Add Expense Category</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="mainmenu" value="expense_category_list" />
					<input type="hidden" name="add_expense_category" value="1" />
					<label for="new_category_name" class="form-label">Category Name</label>
					<input type="text" class="form-control" id="new_category_name" name="category_name" value="" required />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" value="Save">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>
