<?php
	$searchCustomer = isset($_GET['searchCustomer']) ? trim((string) $_GET['searchCustomer']) : '';
	$safeSearchCustomer = mysqli_real_escape_string($connectDB, $searchCustomer);
	$whereCustomer = $safeSearchCustomer !== ''
		? "WHERE c.CustomerName LIKE '%$safeSearchCustomer%' OR c.Address LIKE '%$safeSearchCustomer%'"
		: "";

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
	$customerStatus = isset($_GET['customer_status']) ? trim((string) $_GET['customer_status']) : '';
?>

<div class="dashboard-card">
	<div class="section-head">
		<div>
			<p class="section-kicker">Customers</p>
			<h3>Customer Details</h3>
		</div>
	</div>

	<?php if ($customerStatus === 'saved'): ?>
		<div class="alert alert-success margin-top" role="alert">Customer saved successfully.</div>
	<?php elseif ($customerStatus === 'missing'): ?>
		<div class="alert alert-danger margin-top" role="alert">Customer full name is required.</div>
	<?php endif; ?>

	<form method="POST" action="?mainmenu=save_customer" class="row custom-row margin-top">
		<div class="col-md-3">
			<label for="customer_name">Customer Full Name</label>
			<input type="text" class="form-control" id="customer_name" name="customer_name" required />
		</div>
		<div class="col-md-3">
			<label for="customer_address">Address</label>
			<input type="text" class="form-control" id="customer_address" name="customer_address" />
		</div>
		<div class="col-md-4">
			<label for="customer_google_map">Google Map</label>
			<input type="url" class="form-control" id="customer_google_map" name="customer_google_map" placeholder="https://maps.google.com/..." />
		</div>
		<div class="col-md-2 d-flex align-items-end">
			<button type="submit" class="btn btn-success w-100">Add Customer</button>
		</div>
	</form>

	<form method="GET" class="list-toolbar">
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
					</tr>
				</thead>
				<tbody>
					<?php if ($customerResult && $customerResult->num_rows > 0): ?>
						<?php while ($customer = $customerResult->fetch_assoc()): ?>
							<tr>
								<td><?php echo htmlspecialchars($customer['CustomerName']); ?></td>
								<td><?php echo htmlspecialchars($customer['Address'] !== '' ? $customer['Address'] : 'No address'); ?></td>
								<td>
									<?php if (trim((string) $customer['GoogleMap']) !== ''): ?>
										<a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($customer['GoogleMap']); ?>" target="_blank" rel="noopener">Open Map</a>
									<?php else: ?>
										<span class="text-muted">No map link</span>
									<?php endif; ?>
								</td>
								<td class="text-end fw-semibold">&#8369;<?php echo number_format($customer['Balance'], 2); ?></td>
								<td>
									<?php
										$dueDate = trim((string) ($customer['LatestDueDate'] ?? ''));
										echo $dueDate !== '' ? date('M-d-Y', strtotime($dueDate)) : 'No due date';
									?>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="5" class="empty-state">No customer details found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
