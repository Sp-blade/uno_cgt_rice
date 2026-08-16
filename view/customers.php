<?php
	$walkInCustomerName = junkshop_walk_in_customer_name();
	$safeWalkInCustomerName = mysqli_real_escape_string($connectDB, $walkInCustomerName);
	$searchCustomer = isset($_GET['searchCustomer']) ? trim((string) $_GET['searchCustomer']) : '';
	$safeSearchCustomer = mysqli_real_escape_string($connectDB, $searchCustomer);
	$whereParts = ["c.CustomerName <> '$safeWalkInCustomerName'"];
	if ($safeSearchCustomer !== '') {
		$whereParts[] = "(c.CustomerName LIKE '%$safeSearchCustomer%' OR c.Address LIKE '%$safeSearchCustomer%')";
	}
	$whereCustomer = 'WHERE ' . implode(' AND ', $whereParts);

	$customerResult = $connectDB->query("
		SELECT
			c.CustomerName,
			c.Address,
			c.GoogleMap,
			COALESCE(SUM(a.Balance), 0) AS Balance,
			MAX(a.DueDate) AS LatestDueDate,
			COUNT(a.ID) AS AccountCount
		FROM customers c
		LEFT JOIN customer_accounts a ON a.CustomerName = c.CustomerName
		$whereCustomer
		GROUP BY c.CustomerName, c.Address, c.GoogleMap
		ORDER BY c.CustomerName ASC
	");

	$customerAccounts = [];
	$accountsResult = $connectDB->query("
		SELECT
			CustomerName,
			DeliveryNo,
			SaleDate,
			DueDate,
			TotalAmount,
			AmountPaid,
			Balance,
			PaymentStatus
		FROM customer_accounts
		WHERE CustomerName <> '$safeWalkInCustomerName'
		ORDER BY CustomerName ASC, CASE WHEN DueDate IS NULL THEN 1 ELSE 0 END, DueDate ASC, SaleDate ASC
	");
	if ($accountsResult && $accountsResult->num_rows > 0) {
		while ($row = $accountsResult->fetch_assoc()) {
			$name = trim((string) ($row['CustomerName'] ?? ''));
			if ($name === '') {
				continue;
			}
			if (!isset($customerAccounts[$name])) {
				$customerAccounts[$name] = [];
			}
			$customerAccounts[$name][] = [
				'delivery_no' => (int) ($row['DeliveryNo'] ?? 0),
				'sale_date' => trim((string) ($row['SaleDate'] ?? '')),
				'due_date' => trim((string) ($row['DueDate'] ?? '')),
				'total_amount' => round((float) ($row['TotalAmount'] ?? 0), 2),
				'amount_paid' => round((float) ($row['AmountPaid'] ?? 0), 2),
				'balance' => round((float) ($row['Balance'] ?? 0), 2),
				'payment_status' => trim((string) ($row['PaymentStatus'] ?? 'PAID')),
			];
		}
	}

	$customerPayments = [];
	$paymentsResult = $connectDB->query("
		SELECT
			ID,
			CustomerName,
			DeliveryNo,
			PaymentDate,
			Amount,
			Notes
		FROM customer_payments
		WHERE CustomerName <> '$safeWalkInCustomerName'
		ORDER BY PaymentDate DESC, ID DESC
	");
	if ($paymentsResult && $paymentsResult->num_rows > 0) {
		while ($row = $paymentsResult->fetch_assoc()) {
			$name = trim((string) ($row['CustomerName'] ?? ''));
			if ($name === '') {
				continue;
			}
			if (!isset($customerPayments[$name])) {
				$customerPayments[$name] = [];
			}
			$customerPayments[$name][] = [
				'id' => (int) ($row['ID'] ?? 0),
				'delivery_no' => (int) ($row['DeliveryNo'] ?? 0),
				'payment_date' => trim((string) ($row['PaymentDate'] ?? '')),
				'amount' => round((float) ($row['Amount'] ?? 0), 2),
				'notes' => trim((string) ($row['Notes'] ?? '')),
			];
		}
	}

	$customerLpgLoans = [];
	$lpgLoansResult = $connectDB->query("
		SELECT
			ID,
			CustomerName,
			ProductName,
			Quantity,
			LoanDate,
			DeliveryNo,
			LoanCondition
		FROM lpg_tank_loans
		WHERE Status = 'LENT'
		ORDER BY CustomerName ASC, LoanDate DESC, ID DESC
	");
	if ($lpgLoansResult && $lpgLoansResult->num_rows > 0) {
		while ($row = $lpgLoansResult->fetch_assoc()) {
			$name = trim((string) ($row['CustomerName'] ?? ''));
			if ($name === '') {
				continue;
			}
			if (!isset($customerLpgLoans[$name])) {
				$customerLpgLoans[$name] = [];
			}
			$customerLpgLoans[$name][] = [
				'id' => (int) ($row['ID'] ?? 0),
				'product_name' => trim((string) ($row['ProductName'] ?? '')),
				'quantity' => round((float) ($row['Quantity'] ?? 0), 2),
				'loan_date' => trim((string) ($row['LoanDate'] ?? '')),
				'delivery_no' => (int) ($row['DeliveryNo'] ?? 0),
				'loan_condition' => trim((string) ($row['LoanCondition'] ?? '')),
			];
		}
	}

	$connectDB->query("DELETE FROM customers WHERE CustomerName = '$safeWalkInCustomerName'");

	$customerStatus = isset($_GET['customer_status']) ? trim((string) $_GET['customer_status']) : '';
	$paymentStatus = isset($_GET['payment_status']) ? trim((string) $_GET['payment_status']) : '';
	$dueDateStatus = isset($_GET['due_date_status']) ? trim((string) $_GET['due_date_status']) : '';
	$lpgReturnStatus = isset($_GET['lpg_return_status']) ? trim((string) $_GET['lpg_return_status']) : '';
	$focusedCustomer = isset($_GET['customer']) ? trim((string) $_GET['customer']) : '';
?>

<div class="dashboard-card">
	<div class="section-head">
		<div>
			<p class="section-kicker">Customers</p>
			<h3>Customer Details</h3>
			<p class="text-muted mb-0">Record loan payments and review payment history for registered customers.</p>
		</div>
		<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCustomerModal">Add Customer</button>
	</div>

	<?php if ($customerStatus === 'saved'): ?>
		<div class="alert alert-success margin-top" role="alert">Customer saved successfully.</div>
	<?php elseif ($customerStatus === 'updated'): ?>
		<div class="alert alert-success margin-top" role="alert">Customer details updated successfully.</div>
	<?php elseif ($customerStatus === 'missing'): ?>
		<div class="alert alert-danger margin-top" role="alert">Customer full name is required. Walk-in customers are not stored here.</div>
	<?php endif; ?>

	<?php if ($paymentStatus === 'saved'): ?>
		<div class="alert alert-success margin-top" role="alert">
			Payment recorded successfully<?php echo $focusedCustomer !== '' ? ' for ' . htmlspecialchars($focusedCustomer) : ''; ?>.
		</div>
	<?php elseif ($paymentStatus === 'updated'): ?>
		<div class="alert alert-success margin-top" role="alert">
			Payment updated successfully<?php echo $focusedCustomer !== '' ? ' for ' . htmlspecialchars($focusedCustomer) : ''; ?>.
		</div>
	<?php elseif ($paymentStatus === 'deleted'): ?>
		<div class="alert alert-success margin-top" role="alert">
			Payment deleted successfully<?php echo $focusedCustomer !== '' ? ' for ' . htmlspecialchars($focusedCustomer) : ''; ?>.
		</div>
	<?php elseif ($paymentStatus === 'invalid_amount'): ?>
		<div class="alert alert-danger margin-top" role="alert">Enter a payment amount greater than zero.</div>
	<?php elseif ($paymentStatus === 'no_balance'): ?>
		<div class="alert alert-warning margin-top" role="alert">This customer has no outstanding balance to pay.</div>
	<?php elseif ($paymentStatus === 'invalid_customer'): ?>
		<div class="alert alert-danger margin-top" role="alert">Select a valid registered customer for loan payment.</div>
	<?php elseif ($paymentStatus === 'invalid_payment'): ?>
		<div class="alert alert-danger margin-top" role="alert">The selected payment record could not be found.</div>
	<?php endif; ?>

	<?php if ($dueDateStatus === 'updated'): ?>
		<div class="alert alert-success margin-top" role="alert">
			Due date updated successfully<?php echo $focusedCustomer !== '' ? ' for ' . htmlspecialchars($focusedCustomer) : ''; ?>.
		</div>
	<?php elseif ($dueDateStatus === 'invalid'): ?>
		<div class="alert alert-danger margin-top" role="alert">Unable to update the due date for this loan.</div>
	<?php endif; ?>

	<?php if ($lpgReturnStatus === 'returned'): ?>
		<div class="alert alert-success margin-top" role="alert">
			LPG tank marked as returned<?php echo $focusedCustomer !== '' ? ' for ' . htmlspecialchars($focusedCustomer) : ''; ?>.
		</div>
	<?php elseif ($lpgReturnStatus === 'error'): ?>
		<div class="alert alert-danger margin-top" role="alert"><?php echo htmlspecialchars(trim((string) ($_GET['lpg_return_error'] ?? 'Unable to return lent LPG tank.'))); ?></div>
	<?php endif; ?>

	<form method="GET" class="list-toolbar margin-top">
		<input type="hidden" name="mainmenu" value="customers" />
		<input type="text" class="searchbox" name="searchCustomer" placeholder="Search customer / address" value="<?php echo htmlspecialchars($searchCustomer); ?>" />
		<button type="submit" class="btn btn-primary">Search</button>
	</form>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Customer Full Name</th>
						<th>Address</th>
						<th>Google Map</th>
						<th class="text-end">Balance</th>
						<th>Latest Due Date</th>
						<th class="text-center">Lent Tanks</th>
						<th class="text-center">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($customerResult && $customerResult->num_rows > 0): ?>
						<?php while ($customer = $customerResult->fetch_assoc()): ?>
							<?php
								$customerName = trim((string) ($customer['CustomerName'] ?? ''));
								$customerBalance = round((float) ($customer['Balance'] ?? 0), 2);
								$dueDate = trim((string) ($customer['LatestDueDate'] ?? ''));
								$customerLpgLoanList = $customerLpgLoans[$customerName] ?? [];
								$lentTankCount = count($customerLpgLoanList);
							?>
							<tr>
								<td><?php echo htmlspecialchars($customerName); ?></td>
								<td><?php echo htmlspecialchars($customer['Address'] !== '' ? $customer['Address'] : 'No address'); ?></td>
								<td>
									<?php if (trim((string) $customer['GoogleMap']) !== ''): ?>
										<a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($customer['GoogleMap']); ?>" target="_blank" rel="noopener">Open Map</a>
									<?php else: ?>
										<span class="text-muted">No map link</span>
									<?php endif; ?>
								</td>
								<td class="text-end fw-semibold <?php echo $customerBalance > 0 ? 'text-danger' : ''; ?>">
									&#8369;<?php echo number_format($customerBalance, 2); ?>
								</td>
								<td>
									<?php echo $dueDate !== '' ? date('M-d-Y', strtotime($dueDate)) : 'No due date'; ?>
								</td>
								<td class="text-center">
									<?php if ($lentTankCount > 0): ?>
										<button
											type="button"
											class="btn btn-sm btn-outline-warning"
											data-bs-toggle="modal"
											data-bs-target="#lpgLoansModal"
											data-customer-name="<?php echo htmlspecialchars($customerName, ENT_QUOTES); ?>"
										>
											<?php echo (int) $lentTankCount; ?> tank<?php echo $lentTankCount === 1 ? '' : 's'; ?>
										</button>
									<?php else: ?>
										<span class="text-muted">None</span>
									<?php endif; ?>
								</td>
								<td class="text-center">
									<div class="icon-action-group justify-content-center">
										<button
											type="button"
											class="icon-action-btn icon-action-btn-edit"
											data-bs-toggle="modal"
											data-bs-target="#editCustomerModal"
											data-customer-name="<?php echo htmlspecialchars($customerName, ENT_QUOTES); ?>"
											data-customer-address="<?php echo htmlspecialchars($customer['Address'] ?? '', ENT_QUOTES); ?>"
											data-customer-map="<?php echo htmlspecialchars($customer['GoogleMap'] ?? '', ENT_QUOTES); ?>"
											aria-label="Edit customer <?php echo htmlspecialchars($customerName, ENT_QUOTES); ?>"
											title="Edit"
										>
											<i class="bi bi-pencil-square" aria-hidden="true"></i>
										</button>
										<?php if ($customerBalance > 0): ?>
											<button
												type="button"
												class="btn btn-sm btn-success"
												data-bs-toggle="modal"
												data-bs-target="#payLoanModal"
												data-customer-name="<?php echo htmlspecialchars($customerName, ENT_QUOTES); ?>"
												data-customer-balance="<?php echo htmlspecialchars((string) $customerBalance, ENT_QUOTES); ?>"
											>
												Pay Loan
											</button>
										<?php endif; ?>
										<button
											type="button"
											class="btn btn-sm btn-outline-secondary"
											data-bs-toggle="modal"
											data-bs-target="#paymentHistoryModal"
											data-customer-name="<?php echo htmlspecialchars($customerName, ENT_QUOTES); ?>"
										>
											History
										</button>
									</div>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="7" class="empty-state">No customer details found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form method="POST" action="?mainmenu=save_customer" class="form new-form">
				<div class="modal-header">
					<h4 class="modal-title">Add Customer</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($searchCustomer, ENT_QUOTES); ?>" />

					<div class="row g-3">
						<div class="col-md-12">
							<label for="add_customer_name" class="form-label">Customer Full Name</label>
							<input type="text" class="form-control" id="add_customer_name" name="customer_name" required />
						</div>
						<div class="col-md-12">
							<label for="add_customer_address" class="form-label">Address</label>
							<input type="text" class="form-control" id="add_customer_address" name="customer_address" />
						</div>
						<div class="col-md-12">
							<label for="add_customer_google_map" class="form-label">Google Map</label>
							<input type="url" class="form-control" id="add_customer_google_map" name="customer_google_map" placeholder="https://maps.google.com/..." />
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save Customer</button>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form method="POST" action="?mainmenu=save_customer" class="form new-form">
				<div class="modal-header">
					<h4 class="modal-title">Edit Customer Details</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="original_customer_name" id="edit_original_customer_name" value="" />
					<input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($searchCustomer, ENT_QUOTES); ?>" />

					<div class="row g-3">
						<div class="col-md-12">
							<label for="edit_customer_name" class="form-label">Customer Full Name</label>
							<input type="text" class="form-control" id="edit_customer_name" name="customer_name" readonly />
						</div>
						<div class="col-md-12">
							<label for="edit_customer_address" class="form-label">Address</label>
							<input type="text" class="form-control" id="edit_customer_address" name="customer_address" />
						</div>
						<div class="col-md-12">
							<label for="edit_customer_google_map" class="form-label">Google Map</label>
							<input type="url" class="form-control" id="edit_customer_google_map" name="customer_google_map" placeholder="https://maps.google.com/..." />
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save Changes</button>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="payLoanModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form method="POST" action="?mainmenu=save_customer_payment" class="form new-form">
				<div class="modal-header">
					<h4 class="modal-title">Record Loan Payment</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="customer_name" id="pay_customer_name" value="" />
					<input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($searchCustomer, ENT_QUOTES); ?>" />

					<div class="row g-3 mb-3">
						<div class="col-md-6">
							<label class="form-label">Customer</label>
							<input type="text" class="form-control" id="pay_customer_display" readonly />
						</div>
						<div class="col-md-6">
							<label class="form-label">Outstanding Balance</label>
							<input type="text" class="form-control" id="pay_customer_balance" readonly />
						</div>
					</div>

					<label for="pay_delivery_no" class="form-label">Apply To</label>
					<select class="form-select mb-3" id="pay_delivery_no" name="delivery_no">
						<option value="0">Oldest unpaid sale first</option>
					</select>
					<small class="text-muted d-block mb-3">Leave as oldest unpaid sale to apply payment automatically by due date.</small>

					<div class="row g-3">
						<div class="col-md-4">
							<label for="payment_date" class="form-label">Payment Date &amp; Time</label>
							<input type="datetime-local" class="form-control" id="payment_date" name="payment_date" value="<?php echo junkshop_datetime_input_value(date('Y-m-d H:i:s')); ?>" required />
						</div>
						<div class="col-md-4">
							<label for="payment_amount" class="form-label">Payment Amount</label>
							<input type="number" step="0.01" min="0.01" class="form-control" id="payment_amount" name="payment_amount" required />
						</div>
						<div class="col-md-4">
							<label for="payment_notes" class="form-label">Notes</label>
							<input type="text" class="form-control" id="payment_notes" name="payment_notes" placeholder="Optional note" />
						</div>
					</div>

					<div class="table-card margin-top">
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<thead>
									<tr>
										<th>Sale No</th>
										<th>Sale Date</th>
										<th>Due Date</th>
										<th>Status</th>
										<th class="text-end">Total</th>
										<th class="text-end">Paid</th>
										<th class="text-end">Balance</th>
									</tr>
								</thead>
								<tbody id="pay_open_accounts_body">
									<tr><td colspan="7" class="empty-state">Select a customer to view open loans.</td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save Payment</button>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Payment History</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
					<h5 id="history_customer_name" class="mb-0"></h5>
					<p id="history_customer_balance" class="mb-0 text-muted"></p>
				</div>

				<h6 class="mb-2">Open Loans</h6>
				<div class="table-card margin-top">
					<div class="table-responsive">
						<table class="table table-sm table-hover">
							<thead>
								<tr>
									<th>Sale No</th>
									<th>Sale Date</th>
									<th>Due Date</th>
									<th>Status</th>
									<th class="text-end">Total</th>
									<th class="text-end">Paid</th>
									<th class="text-end">Balance</th>
									<th class="text-center">Actions</th>
								</tr>
							</thead>
							<tbody id="history_open_accounts_body">
								<tr><td colspan="8" class="empty-state">No open loans.</td></tr>
							</tbody>
						</table>
					</div>
				</div>

				<h6 class="mb-2 mt-4">Payment History</h6>
				<div class="table-card margin-top">
					<div class="table-responsive">
						<table class="table table-sm table-hover">
							<thead>
								<tr>
									<th>Date</th>
									<th>Sale No</th>
									<th class="text-end">Amount</th>
									<th>Notes</th>
									<th class="text-center">Actions</th>
								</tr>
							</thead>
							<tbody id="history_payments_body">
								<tr><td colspan="5" class="empty-state">No payments recorded yet.</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="historyPayLoanButton" style="display: none;">Pay Loan</button>
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form method="POST" action="?mainmenu=save_customer_payment" class="form new-form">
				<div class="modal-header">
					<h4 class="modal-title">Edit Payment</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="payment_id" id="edit_payment_id" value="" />
					<input type="hidden" name="customer_name" id="edit_payment_customer_name" value="" />
					<input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($searchCustomer, ENT_QUOTES); ?>" />

					<div class="row g-3 mb-3">
						<div class="col-md-6">
							<label class="form-label">Customer</label>
							<input type="text" class="form-control" id="edit_payment_customer_display" readonly />
						</div>
						<div class="col-md-6">
							<label for="edit_payment_delivery_no" class="form-label">Apply To</label>
							<select class="form-select" id="edit_payment_delivery_no" name="delivery_no">
								<option value="0">Oldest unpaid sale first</option>
							</select>
						</div>
					</div>

					<div class="row g-3">
						<div class="col-md-4">
							<label for="edit_payment_date" class="form-label">Payment Date &amp; Time</label>
							<input type="datetime-local" class="form-control" id="edit_payment_date" name="payment_date" required />
						</div>
						<div class="col-md-4">
							<label for="edit_payment_amount" class="form-label">Payment Amount</label>
							<input type="number" step="0.01" min="0.01" class="form-control" id="edit_payment_amount" name="payment_amount" required />
						</div>
						<div class="col-md-4">
							<label for="edit_payment_notes" class="form-label">Notes</label>
							<input type="text" class="form-control" id="edit_payment_notes" name="payment_notes" placeholder="Optional note" />
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save Payment</button>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editDueDateModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form method="POST" action="?mainmenu=update_customer_due_date" class="form new-form">
				<div class="modal-header">
					<h4 class="modal-title">Edit Due Date</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="customer_name" id="edit_due_date_customer_name" value="" />
					<input type="hidden" name="delivery_no" id="edit_due_date_delivery_no" value="" />
					<input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($searchCustomer, ENT_QUOTES); ?>" />

					<div class="mb-3">
						<label class="form-label">Customer</label>
						<input type="text" class="form-control" id="edit_due_date_customer_display" readonly />
					</div>
					<div class="mb-3">
						<label class="form-label">Sale No</label>
						<input type="text" class="form-control" id="edit_due_date_sale_no" readonly />
					</div>
					<div>
						<label for="edit_due_date_value" class="form-label">Due Date</label>
						<input type="date" class="form-control" id="edit_due_date_value" name="due_date" />
						<small class="text-muted">Leave blank to clear the due date.</small>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save Due Date</button>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="lpgLoansModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Lent LPG Tanks</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-3"><strong id="lpg_loans_customer_name"></strong></p>
				<div class="table-responsive">
					<table class="table table-sm">
						<thead>
							<tr>
								<th>Product</th>
								<th>Condition</th>
								<th class="text-end">Qty</th>
								<th>Loan Date</th>
								<th>Sale #</th>
								<th class="text-center">Return</th>
							</tr>
						</thead>
						<tbody id="lpgLoansTableBody">
							<tr><td colspan="6" class="empty-state">No lent tanks.</td></tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
	const customerAccountsData = <?php echo json_encode($customerAccounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const customerPaymentsData = <?php echo json_encode($customerPayments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const customerLpgLoansData = <?php echo json_encode($customerLpgLoans, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	let activeHistoryCustomer = { name: '', balance: 0 };

	function formatCurrency(value) {
		const amount = Number(value || 0);
		return '\u20B1' + amount.toLocaleString('en-PH', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function formatDateTime(value) {
		if (!value) {
			return '—';
		}
		const date = new Date(String(value).replace(' ', 'T'));
		if (Number.isNaN(date.getTime())) {
			return value;
		}
		return date.toLocaleString('en-PH', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function formatDate(value) {
		if (!value) {
			return '—';
		}
		const date = new Date(String(value).replace(' ', 'T'));
		if (Number.isNaN(date.getTime())) {
			return value;
		}
		return date.toLocaleDateString('en-PH', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function getOpenAccounts(customerName) {
		return (customerAccountsData[customerName] || []).filter(function (account) {
			return Number(account.balance || 0) > 0;
		});
	}

	function getCustomerBalance(customerName) {
		return getOpenAccounts(customerName).reduce(function (total, account) {
			return total + Number(account.balance || 0);
		}, 0);
	}

	function renderOpenAccountsTable(targetId, accounts, emptyMessage) {
		const tbody = document.getElementById(targetId);
		if (!tbody) {
			return;
		}

		if (!accounts.length) {
			tbody.innerHTML = '<tr><td colspan="7" class="empty-state">' + escapeHtml(emptyMessage) + '</td></tr>';
			return;
		}

		tbody.innerHTML = accounts.map(function (account) {
			return `
				<tr>
					<td>${escapeHtml(account.delivery_no)}</td>
					<td>${escapeHtml(formatDateTime(account.sale_date))}</td>
					<td>${escapeHtml(formatDate(account.due_date))}</td>
					<td><span class="badge text-bg-secondary">${escapeHtml(account.payment_status || 'UNPAID')}</span></td>
					<td class="text-end">${escapeHtml(formatCurrency(account.total_amount))}</td>
					<td class="text-end">${escapeHtml(formatCurrency(account.amount_paid))}</td>
					<td class="text-end fw-semibold">${escapeHtml(formatCurrency(account.balance))}</td>
				</tr>
			`;
		}).join('');
	}

	function renderHistoryOpenAccountsTable(customerName) {
		const tbody = document.getElementById('history_open_accounts_body');
		const accounts = getOpenAccounts(customerName);

		if (!tbody) {
			return;
		}

		if (!accounts.length) {
			tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No open loans.</td></tr>';
			return;
		}

		tbody.innerHTML = accounts.map(function (account) {
			return `
				<tr>
					<td>${escapeHtml(account.delivery_no)}</td>
					<td>${escapeHtml(formatDateTime(account.sale_date))}</td>
					<td>${escapeHtml(formatDate(account.due_date))}</td>
					<td><span class="badge text-bg-secondary">${escapeHtml(account.payment_status || 'UNPAID')}</span></td>
					<td class="text-end">${escapeHtml(formatCurrency(account.total_amount))}</td>
					<td class="text-end">${escapeHtml(formatCurrency(account.amount_paid))}</td>
					<td class="text-end fw-semibold">${escapeHtml(formatCurrency(account.balance))}</td>
					<td class="text-center">
						<button
							type="button"
							class="icon-action-btn icon-action-btn-edit js-edit-due-date"
							data-customer-name="${escapeHtml(customerName)}"
							data-delivery-no="${escapeHtml(account.delivery_no)}"
							data-due-date="${escapeHtml(account.due_date || '')}"
							aria-label="Edit due date"
							title="Edit Due Date"
						>
							<i class="bi bi-pencil-square" aria-hidden="true"></i>
						</button>
					</td>
				</tr>
			`;
		}).join('');
	}

	function renderPaymentHistoryTable(customerName) {
		const tbody = document.getElementById('history_payments_body');
		const payments = customerPaymentsData[customerName] || [];
		const searchCustomer = <?php echo json_encode($searchCustomer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

		if (!tbody) {
			return;
		}

		if (!payments.length) {
			tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No payments recorded yet.</td></tr>';
			return;
		}

		tbody.innerHTML = payments.map(function (payment) {
			const deleteHref = '?mainmenu=delete_customer_payment&payment_id=' + encodeURIComponent(payment.id) +
				'&customer=' + encodeURIComponent(customerName) +
				(searchCustomer ? '&searchCustomer=' + encodeURIComponent(searchCustomer) : '');
			const recordLabel = 'Payment ' + formatCurrency(payment.amount) + ' on ' + formatDateTime(payment.payment_date);

			return `
				<tr>
					<td>${escapeHtml(formatDateTime(payment.payment_date))}</td>
					<td>${payment.delivery_no > 0 ? escapeHtml(payment.delivery_no) : '—'}</td>
					<td class="text-end fw-semibold">${escapeHtml(formatCurrency(payment.amount))}</td>
					<td>${escapeHtml(payment.notes || '—')}</td>
					<td class="text-center">
						<div class="icon-action-group justify-content-center">
							<button
								type="button"
								class="icon-action-btn icon-action-btn-edit js-edit-payment"
								data-payment-id="${escapeHtml(payment.id)}"
								data-customer-name="${escapeHtml(customerName)}"
								data-delivery-no="${escapeHtml(payment.delivery_no)}"
								data-payment-date="${escapeHtml(payment.payment_date)}"
								data-payment-amount="${escapeHtml(payment.amount)}"
								data-payment-notes="${escapeHtml(payment.notes || '')}"
								aria-label="Edit payment"
								title="Edit"
							>
								<i class="bi bi-pencil-square" aria-hidden="true"></i>
							</button>
							<a
								class="icon-action-btn icon-action-btn-delete js-history-delete"
								href="${escapeHtml(deleteHref)}"
								data-record-label="${escapeHtml(recordLabel)}"
								aria-label="Delete payment"
								title="Delete"
							>
								<i class="bi bi-trash3" aria-hidden="true"></i>
							</a>
						</div>
					</td>
				</tr>
			`;
		}).join('');
	}

	function toDatetimeLocalValue(value) {
		if (!value) {
			return '';
		}
		const normalized = String(value).trim().replace(' ', 'T');
		if (normalized.length >= 16) {
			return normalized.slice(0, 16);
		}
		return normalized;
	}

	function toDateInputValue(value) {
		if (!value) {
			return '';
		}
		return String(value).trim().slice(0, 10);
	}

	function openPayLoanModal(customerName, customerBalance) {
		const openAccounts = getOpenAccounts(customerName);

		document.getElementById('pay_customer_name').value = customerName;
		document.getElementById('pay_customer_display').value = customerName;
		document.getElementById('pay_customer_balance').value = formatCurrency(customerBalance);
		document.getElementById('payment_amount').value = customerBalance > 0 ? customerBalance.toFixed(2) : '';
		document.getElementById('payment_notes').value = '';

		populateDeliveryOptions('pay_delivery_no', customerName, 0);
		renderOpenAccountsTable('pay_open_accounts_body', openAccounts, 'No open loans for this customer.');

		bootstrap.Modal.getOrCreateInstance(document.getElementById('payLoanModal')).show();
	}

	function populateDeliveryOptions(selectId, customerName, selectedDeliveryNo) {
		const select = document.getElementById(selectId);
		if (!select) {
			return;
		}

		const accounts = customerAccountsData[customerName] || [];
		const openAccounts = accounts.filter(function (account) {
			return Number(account.balance || 0) > 0;
		});
		const optionAccounts = openAccounts.slice();
		const selectedAccount = accounts.find(function (account) {
			return Number(account.delivery_no) === Number(selectedDeliveryNo);
		});
		if (selectedAccount && !optionAccounts.some(function (account) {
			return Number(account.delivery_no) === Number(selectedAccount.delivery_no);
		})) {
			optionAccounts.push(selectedAccount);
		}

		select.innerHTML = '<option value="0">Oldest unpaid sale first</option>';
		optionAccounts.forEach(function (account) {
			const option = document.createElement('option');
			option.value = String(account.delivery_no);
			option.textContent = 'Sale #' + account.delivery_no + ' - Balance ' + formatCurrency(account.balance);
			select.appendChild(option);
		});
		select.value = String(selectedDeliveryNo || 0);
	}

	document.getElementById('payLoanModal').addEventListener('show.bs.modal', function (event) {
		const button = event.relatedTarget;
		if (!button) {
			return;
		}

		const customerName = button.getAttribute('data-customer-name') || '';
		const customerBalance = Number(button.getAttribute('data-customer-balance') || 0);
		openPayLoanModal(customerName, customerBalance);
	});

	document.getElementById('addCustomerModal').addEventListener('show.bs.modal', function () {
		document.getElementById('add_customer_name').value = '';
		document.getElementById('add_customer_address').value = '';
		document.getElementById('add_customer_google_map').value = '';
	});

	document.getElementById('editCustomerModal').addEventListener('show.bs.modal', function (event) {
		const button = event.relatedTarget;
		document.getElementById('edit_original_customer_name').value = button.getAttribute('data-customer-name') || '';
		document.getElementById('edit_customer_name').value = button.getAttribute('data-customer-name') || '';
		document.getElementById('edit_customer_address').value = button.getAttribute('data-customer-address') || '';
		document.getElementById('edit_customer_google_map').value = button.getAttribute('data-customer-map') || '';
	});

	document.getElementById('paymentHistoryModal').addEventListener('show.bs.modal', function (event) {
		const button = event.relatedTarget;
		const customerName = button.getAttribute('data-customer-name') || '';
		const customerBalance = getCustomerBalance(customerName);

		activeHistoryCustomer = {
			name: customerName,
			balance: customerBalance
		};

		document.getElementById('history_customer_name').textContent = customerName;
		document.getElementById('history_customer_balance').textContent = customerBalance > 0
			? 'Outstanding balance: ' + formatCurrency(customerBalance)
			: 'No outstanding balance';

		const payLoanButton = document.getElementById('historyPayLoanButton');
		if (payLoanButton) {
			payLoanButton.style.display = customerBalance > 0 ? 'inline-block' : 'none';
		}

		renderHistoryOpenAccountsTable(customerName);
		renderPaymentHistoryTable(customerName);
	});

	document.getElementById('historyPayLoanButton').addEventListener('click', function () {
		if (!activeHistoryCustomer.name || activeHistoryCustomer.balance <= 0) {
			return;
		}

		const historyModal = bootstrap.Modal.getInstance(document.getElementById('paymentHistoryModal'));
		if (historyModal) {
			historyModal.hide();
		}

		openPayLoanModal(activeHistoryCustomer.name, activeHistoryCustomer.balance);
	});

	document.getElementById('paymentHistoryModal').addEventListener('click', function (event) {
		const editDueDateButton = event.target.closest('.js-edit-due-date');
		if (editDueDateButton) {
			const customerName = editDueDateButton.getAttribute('data-customer-name') || '';
			const deliveryNo = editDueDateButton.getAttribute('data-delivery-no') || '';
			document.getElementById('edit_due_date_customer_name').value = customerName;
			document.getElementById('edit_due_date_customer_display').value = customerName;
			document.getElementById('edit_due_date_delivery_no').value = deliveryNo;
			document.getElementById('edit_due_date_sale_no').value = deliveryNo;
			document.getElementById('edit_due_date_value').value = toDateInputValue(editDueDateButton.getAttribute('data-due-date') || '');

			bootstrap.Modal.getOrCreateInstance(document.getElementById('editDueDateModal')).show();
			return;
		}

		const editButton = event.target.closest('.js-edit-payment');
		if (!editButton) {
			return;
		}

		const customerName = editButton.getAttribute('data-customer-name') || '';
		const deliveryNo = Number(editButton.getAttribute('data-delivery-no') || 0);
		document.getElementById('edit_payment_id').value = editButton.getAttribute('data-payment-id') || '';
		document.getElementById('edit_payment_customer_name').value = customerName;
		document.getElementById('edit_payment_customer_display').value = customerName;
		document.getElementById('edit_payment_date').value = toDatetimeLocalValue(editButton.getAttribute('data-payment-date') || '');
		document.getElementById('edit_payment_amount').value = Number(editButton.getAttribute('data-payment-amount') || 0).toFixed(2);
		document.getElementById('edit_payment_notes').value = editButton.getAttribute('data-payment-notes') || '';
		populateDeliveryOptions('edit_payment_delivery_no', customerName, deliveryNo);

		const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editPaymentModal'));
		editModal.show();
	});

	document.getElementById('lpgLoansModal').addEventListener('show.bs.modal', function (event) {
		const button = event.relatedTarget;
		const customerName = button ? (button.getAttribute('data-customer-name') || '') : '';
		const loans = customerLpgLoansData[customerName] || [];
		document.getElementById('lpg_loans_customer_name').textContent = customerName;
		const tbody = document.getElementById('lpgLoansTableBody');
		if (!loans.length) {
			tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No lent tanks.</td></tr>';
			return;
		}
		tbody.innerHTML = loans.map(function (loan) {
			const loanDate = loan.loan_date ? new Date(loan.loan_date.replace(' ', 'T')).toLocaleString() : '';
			const loanCondition = loan.loan_condition ? escapeHtml(loan.loan_condition) : '—';
			return '<tr>' +
				'<td>' + escapeHtml(loan.product_name || '') + '</td>' +
				'<td>' + loanCondition + '</td>' +
				'<td class="text-end">' + Number(loan.quantity || 0).toFixed(2) + '</td>' +
				'<td>' + escapeHtml(loanDate) + '</td>' +
				'<td>' + Number(loan.delivery_no || 0) + '</td>' +
				'<td class="text-center">' +
					'<form method="POST" action="?mainmenu=return_lpg_tank" class="d-inline-flex gap-2 align-items-center">' +
						'<input type="hidden" name="loan_id" value="' + Number(loan.id || 0) + '" />' +
						'<input type="hidden" name="customer_name" value="' + escapeHtml(customerName) + '" />' +
						'<input type="hidden" name="search_customer" value="<?php echo htmlspecialchars($searchCustomer, ENT_QUOTES); ?>" />' +
						'<select class="form-select form-select-sm" name="return_condition" required style="min-width: 90px;">' +
							'<option value="">Condition</option>' +
							'<option value="NEW">New</option>' +
							'<option value="OLD">Old</option>' +
						'</select>' +
						'<button type="submit" class="btn btn-sm btn-success">Return</button>' +
					'</form>' +
				'</td>' +
			'</tr>';
		}).join('');
	});

	document.addEventListener('click', function (event) {
		const deleteButton = event.target.closest('.js-history-delete');
		if (deleteButton) {
			const recordLabel = deleteButton.getAttribute('data-record-label') || 'this record';
			const confirmation = window.prompt('Type DELETE to permanently remove ' + recordLabel + '.');
			if (confirmation !== 'DELETE') {
				event.preventDefault();
			}
			return;
		}
	});
</script>
