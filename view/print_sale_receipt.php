<?php
	$deliveryNo = (int) ($_GET['deliveryNo'] ?? 0);
	$returnTo = isset($_GET['return_to']) ? (string) $_GET['return_to'] : ('?mainmenu=view_sale&deliveryNo=' . $deliveryNo);
	$saleItems = [];
	$customerName = '';
	$customerAddress = '';
	$saleDate = '';
	$saleNotes = '';
	$grandTotal = 0;
	$totalAmountPaid = 0;
	$totalBalance = 0;
	$amountDue = 0;
	$validItems = [];
	$receiptDueDates = [];
	$isRegisteredCustomer = false;

	if ($deliveryNo > 0) {
		$saleResult = $connectDB->query("SELECT * FROM sales WHERE DeliveryNo = '$deliveryNo' ORDER BY ID ASC");
		if ($saleResult && $saleResult->num_rows > 0) {
			while ($row = $saleResult->fetch_assoc()) {
				$saleItems[] = $row;
			}
		}
	}

	if (!empty($saleItems)) {
		$customerName = trim((string) ($saleItems[0]['CustomerName'] ?? 'Walk-in Customer'));
		if ($customerName === '') {
			$customerName = 'Walk-in Customer';
		}
		$saleDate = $saleItems[0]['SaleDate'] ?? '';
		$saleNotes = trim((string) ($saleItems[0]['Notes'] ?? ''));
		$isRegisteredCustomer = !junkshop_is_walk_in_customer($customerName);

		if ($isRegisteredCustomer) {
			$safeCustomer = mysqli_real_escape_string($connectDB, $customerName);
			$customerResult = $connectDB->query("SELECT Address FROM customers WHERE CustomerName = '$safeCustomer' LIMIT 1");
			if ($customerResult && $customerResult->num_rows > 0) {
				$customerAddress = trim((string) ($customerResult->fetch_assoc()['Address'] ?? ''));
			}
		}

		foreach ($saleItems as $sale) {
			$rowTotal = (float) ($sale['TotalSalePrice'] ?? 0);
			$grandTotal += $rowTotal;

			$rowPaymentStatus = strtoupper(trim((string) ($sale['PaymentStatus'] ?? 'PAID')));
			if (!in_array($rowPaymentStatus, ['PAID', 'PARTIAL', 'UNPAID'], true)) {
				$rowPaymentStatus = 'PAID';
			}
			if (!$isRegisteredCustomer) {
				$rowPaymentStatus = 'PAID';
			}

			$rowAmountPaid = (float) ($sale['AmountPaid'] ?? 0);
			if ($rowPaymentStatus === 'PAID') {
				$rowAmountPaid = $rowTotal;
			} elseif ($rowPaymentStatus === 'UNPAID') {
				$rowAmountPaid = 0;
			} else {
				$rowAmountPaid = min(max($rowAmountPaid, 0), $rowTotal);
			}

			$rowBalance = max($rowTotal - $rowAmountPaid, 0);
			$rowDueDate = trim((string) ($sale['DueDate'] ?? ''));
			if ($rowBalance > 0 && $rowDueDate !== '') {
				$receiptDueDates[] = $rowDueDate;
			}

			$rowAmountDue = $rowTotal;
			if ($isRegisteredCustomer) {
				if ($rowPaymentStatus === 'PARTIAL') {
					$rowAmountDue = $rowAmountPaid;
				} elseif ($rowPaymentStatus === 'UNPAID') {
					$rowAmountDue = 0;
				}
			}

			$totalAmountPaid += $rowAmountPaid;
			$totalBalance += $rowBalance;
			$amountDue += $rowAmountDue;

			$lpgType = strtoupper(trim((string) ($sale['LpgTransactionType'] ?? 'NONE')));
			$lpgCondition = trim((string) ($sale['LpgTankCondition'] ?? ''));
			$unitPrice = (float) ($sale['UnitPrice'] ?? 0);
			$lpgSoldPrices = null;
			if ($lpgType === 'SOLD') {
				$lpgSoldPrices = junkshop_lpg_sold_receipt_prices($connectDB, (int) ($sale['Product_ID'] ?? 0), $unitPrice);
			}

			$validItems[] = [
				'name' => $sale['ProductName'],
				'qty' => (float) ($sale['Quantity'] ?? 0),
				'unit' => junkshop_normalize_base_unit($sale['SaleUnit'] ?? 'pc'),
				'price' => $unitPrice,
				'total' => $rowTotal,
				'lpg_type' => $lpgType,
				'lpg_condition' => $lpgCondition,
				'lpg_refill_price' => $lpgSoldPrices ? (float) ($lpgSoldPrices['refill_price'] ?? 0) : 0,
				'lpg_tank_price' => $lpgSoldPrices ? (float) ($lpgSoldPrices['tank_price'] ?? 0) : 0,
				'payment_status' => $rowPaymentStatus,
				'amount_paid' => $rowAmountPaid,
				'balance' => $rowBalance,
				'due_date' => $rowDueDate,
			];
		}
	}

	$latestDueDate = !empty($receiptDueDates) ? max($receiptDueDates) : '';
	$receiptItemCount = count($validItems);
?>

<?php if ($receiptItemCount === 0): ?>
	<div class="receipt-page">
		<div class="receipt-screen-card">
			<p class="mb-0">No sale receipt records were found.</p>
			<a class="btn btn-light mt-3" href="<?php echo htmlspecialchars($returnTo, ENT_QUOTES); ?>">Back</a>
		</div>
	</div>
<?php else: ?>
	<div class="receipt-page receipt-printable">
		<div class="receipt-screen-card">
			<div class="receipt-top-card">
				<div class="receipt-top-copy">
					<p class="receipt-kicker">Print Preview</p>
					<h2>Sale Receipt</h2>
					<p class="receipt-subtitle">Optimized for 48mm thermal paper printing.</p>
				</div>
				<div class="receipt-top-action">
					<button type="button" onclick="window.print();" class="btn btn-primary w-100">Print Receipt</button>
				</div>
			</div>
			<div class="receipt-preview-shell">
				<div class="receipt">
					<div class="receipt-badge noPrint">48mm Receipt</div>

					<div class="header">
						<h1><?php echo htmlspecialchars(strtoupper($companyName)); ?></h1>
						<?php if ($companyAddressLine1 !== ''): ?><p><?php echo htmlspecialchars($companyAddressLine1); ?></p><?php endif; ?>
						<?php if ($companyAddressLine2 !== ''): ?><p><?php echo htmlspecialchars($companyAddressLine2); ?></p><?php endif; ?>
						<?php if ($companyContactNumber !== ''): ?><p>Contact: <?php echo htmlspecialchars($companyContactNumber); ?></p><?php endif; ?>
						<?php if ($companyTinNumber !== ''): ?><p>TIN: <?php echo htmlspecialchars($companyTinNumber); ?></p><?php endif; ?>
					</div>

					<div class="receipt-meta">
						<div>
							<span class="meta-label">Sales Invoice</span>
							<strong>#<?php echo str_pad((string) $deliveryNo, 5, '0', STR_PAD_LEFT); ?></strong>
						</div>
						<div>
							<span class="meta-label">Date & Time</span>
							<strong><?php echo junkshop_format_datetime($saleDate); ?></strong>
						</div>
					</div>

					<div class="receipt-meta receipt-meta-customer">
						<div>
							<span class="meta-label">Customer</span>
							<strong><?php echo htmlspecialchars($customerName); ?></strong>
						</div>
						<?php if ($customerAddress !== ''): ?>
							<div>
								<span class="meta-label">Address</span>
								<strong><?php echo htmlspecialchars($customerAddress); ?></strong>
							</div>
						<?php endif; ?>
					</div>

					<div class="line"></div>

					<table class="product-table">
						<thead>
							<tr>
								<th>Item</th>
								<th class="amount">Total</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($validItems as $item): ?>
								<tr>
									<td>
										<div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
										<div class="item-meta"><?php echo number_format($item['qty'], 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($item['unit'])); ?> x &#8369;<?php echo number_format($item['price'], 2); ?></div>
										<?php if ($item['lpg_type'] === 'SWAPPED'): ?>
											<div class="item-meta">Tank: Swapped<?php echo $item['lpg_condition'] !== '' ? ' (' . htmlspecialchars($item['lpg_condition']) . ')' : ''; ?></div>
										<?php endif; ?>
										<?php if ($item['lpg_type'] === 'SOLD'): ?>
											<div class="item-meta">Refill Price: &#8369;<?php echo number_format((float) ($item['lpg_refill_price'] ?? 0), 2); ?></div>
											<div class="item-meta">Tank Price: &#8369;<?php echo number_format((float) ($item['lpg_tank_price'] ?? 0), 2); ?></div>
										<?php endif; ?>
										<?php if ($isRegisteredCustomer && $item['payment_status'] !== 'PAID'): ?>
											<?php $paymentLabel = $item['payment_status'] === 'PARTIAL' ? 'Partial Payment' : 'Loan / Unpaid'; ?>
											<div class="item-meta"><?php echo htmlspecialchars($paymentLabel); ?> | Paid: &#8369;<?php echo number_format($item['amount_paid'], 2); ?> | Balance: &#8369;<?php echo number_format($item['balance'], 2); ?></div>
											<?php if ($item['due_date'] !== ''): ?>
												<div class="item-meta">Due: <?php echo date('M d, Y', strtotime($item['due_date'])); ?></div>
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<td class="amount">&#8369;<?php echo number_format($item['total'], 2); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="line"></div>

					<div class="total">
						<p class="total-emphasis">Grand Total <span class="total-value">&#8369;<?php echo number_format($grandTotal, 2); ?></span></p>
						<p class="total-emphasis">Amount Due <span class="total-value">&#8369;<?php echo number_format($amountDue, 2); ?></span></p>

						<?php if ($isRegisteredCustomer && $totalBalance > 0): ?>
							<p>Total Paid <span>&#8369;<?php echo number_format($totalAmountPaid, 2); ?></span></p>
							<p>Balance <span>&#8369;<?php echo number_format($totalBalance, 2); ?></span></p>
							<?php if ($latestDueDate !== ''): ?>
								<p>Due Date <span><?php echo date('M d, Y', strtotime($latestDueDate)); ?></span></p>
							<?php endif; ?>
						<?php endif; ?>
					</div>

					<div class="footer">
						<p>Thank you for your purchase!</p>
						<?php if ($saleNotes !== ''): ?><p>Note: <?php echo htmlspecialchars($saleNotes); ?></p><?php endif; ?>
						<?php if ($latestDueDate !== ''): ?><p>Latest due date: <?php echo date('M d, Y', strtotime($latestDueDate)); ?></p><?php endif; ?>
						<p>Please keep this receipt for your records.</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="receipt-page noPrint">
		<div class="receipt-page-actions receipt-floating-actions">
			<div class="receipt-form-actions">
				<button type="button" onclick="window.print();" class="btn btn-success receipt-action-btn">Print Receipt</button>
				<a href="<?php echo htmlspecialchars($returnTo, ENT_QUOTES); ?>" class="btn btn-light receipt-action-btn">Back</a>
				<div class="receipt-footer-metrics">
					<div class="receipt-footer-metric">
						<p class="metric-label">Item Count</p>
						<div class="totalprice"><?php echo $receiptItemCount; ?></div>
					</div>
					<div class="receipt-footer-metric">
						<p class="metric-label">Total Sale</p>
						<div class="totalprice">&#8369;<?php echo number_format($grandTotal, 2); ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
