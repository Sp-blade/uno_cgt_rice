<?php $receiptItemCount = count(array_filter($productNames, function($name) { return trim((string) $name) !== ''; })); ?>

<div class="receipt-page receipt-printable">
	<div class="receipt-screen-card">
		<div class="receipt-top-card">
			<div class="receipt-top-copy">
				<p class="receipt-kicker">Review Sale</p>
				<h2>Sale Receipt</h2>
				<p class="receipt-subtitle">Confirm the sale before saving it to inventory and customer balance.</p>
			</div>
			<div class="receipt-top-action">
				<button type="button" onclick="saveAndPrintSaleReceipt(this);" class="btn btn-primary w-100">Print & Save</button>
			</div>
		</div>

		<div class="receipt-preview-shell">
			<div class="receipt">
				<div class="receipt-badge noPrint">Sale Receipt</div>

				<div class="header">
					<h1><?php echo htmlspecialchars(strtoupper($companyName)); ?></h1>
					<?php if ($companyAddressLine1 !== ''): ?><p><?php echo htmlspecialchars($companyAddressLine1); ?></p><?php endif; ?>
					<?php if ($companyAddressLine2 !== ''): ?><p><?php echo htmlspecialchars($companyAddressLine2); ?></p><?php endif; ?>
					<?php if ($companyContactNumber !== ''): ?><p>Contact: <?php echo htmlspecialchars($companyContactNumber); ?></p><?php endif; ?>
					<?php if ($companyTinNumber !== ''): ?><p>TIN: <?php echo htmlspecialchars($companyTinNumber); ?></p><?php endif; ?>
				</div>

				<div class="receipt-meta">
					<div>
						<span class="meta-label">Sale No</span>
						<strong>#<?php echo (int) $saleNo; ?></strong>
					</div>
					<div>
						<span class="meta-label">Date</span>
						<strong><?php echo date('M-d-Y', strtotime($saleDate)); ?></strong>
					</div>
				</div>

				<div class="receipt-meta">
					<div>
						<span class="meta-label">Customer</span>
						<strong><?php echo htmlspecialchars($customerName); ?></strong>
					</div>
					<div>
						<span class="meta-label">Payment</span>
						<strong><?php echo htmlspecialchars($paymentStatus); ?></strong>
					</div>
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
						<?php foreach ($productNames as $index => $productName): ?>
							<?php
								$name = trim((string) $productName);
								$quantity = (float) ($productQuantities[$index] ?? 0);
								$price = (float) ($productPrices[$index] ?? 0);
								if ($name === '' || $quantity <= 0 || $price <= 0) {
									continue;
								}
								$rowTotal = isset($totalPrices[$index]) ? (float) $totalPrices[$index] : ($quantity * $price);
								$tankType = strtoupper(trim((string) ($lpgTransactionTypes[$index] ?? 'NONE')));
								$tankPayment = $tankType === 'SOLD' ? (float) ($lpgTankPayments[$index] ?? 0) : 0;
								$tankCondition = trim((string) ($lpgTankConditions[$index] ?? ''));
							?>
							<tr>
								<td>
									<div class="item-name"><?php echo htmlspecialchars($name); ?></div>
									<div class="item-meta"><?php echo number_format($quantity, 2); ?> x &#8369;<?php echo number_format($price, 2); ?></div>
									<?php if ($salesType === 'LPG'): ?>
										<div class="item-meta">
											Tank: <?php echo htmlspecialchars(ucfirst(strtolower($tankType))); ?>
											<?php if ($tankCondition !== ''): ?> | <?php echo htmlspecialchars($tankCondition); ?><?php endif; ?>
											<?php if ($tankPayment > 0): ?> | Tank &#8369;<?php echo number_format($tankPayment, 2); ?><?php endif; ?>
										</div>
									<?php endif; ?>
								</td>
								<td class="amount">&#8369;<?php echo number_format($rowTotal + $tankPayment, 2); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="line"></div>

				<div class="total">
					<p>Grand Total <span>&#8369;<?php echo number_format($grandTotal, 2); ?></span></p>
					<p>Amount Paid <span>&#8369;<?php echo number_format($paymentStatus === 'PAID' ? $grandTotal : $amountPaid, 2); ?></span></p>
					<p>Balance <span>&#8369;<?php echo number_format($balance, 2); ?></span></p>
				</div>

				<div class="footer">
					<p>Thank you for your purchase!</p>
					<?php if ($dueDate !== ''): ?><p>Due date: <?php echo date('M-d-Y', strtotime($dueDate)); ?></p><?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="receipt-page noPrint">
	<div class="receipt-page-actions receipt-floating-actions">
		<form class="form new-form receipt-form-actions" method="POST" id="saleReceiptSaveForm">
			<input type="hidden" name="mainmenu" value="sale_invoice" />
			<input type="hidden" name="previousmenu" value="<?php echo htmlspecialchars($previousMenu); ?>" />
			<input type="hidden" name="sales_type" value="<?php echo htmlspecialchars($salesType); ?>" />
			<input type="hidden" name="sale_date" value="<?php echo htmlspecialchars($saleDate); ?>" />
			<input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($customerName, ENT_QUOTES); ?>" />
			<input type="hidden" name="customer_address" value="<?php echo htmlspecialchars($customerAddress, ENT_QUOTES); ?>" />
			<input type="hidden" name="customer_google_map" value="<?php echo htmlspecialchars($customerGoogleMap, ENT_QUOTES); ?>" />
			<input type="hidden" name="sale_notes" value="<?php echo htmlspecialchars($saleNotes, ENT_QUOTES); ?>" />
			<input type="hidden" name="payment_status" value="<?php echo htmlspecialchars($paymentStatus); ?>" />
			<input type="hidden" name="amount_paid" value="<?php echo htmlspecialchars((string) $amountPaid); ?>" />
			<input type="hidden" name="due_date" value="<?php echo htmlspecialchars($dueDate); ?>" />
			<input type="hidden" name="product_name" value="<?php echo htmlspecialchars(serialize($productNames), ENT_QUOTES); ?>" />
			<input type="hidden" name="product_id" value="<?php echo htmlspecialchars(serialize($productIds), ENT_QUOTES); ?>" />
			<input type="hidden" name="product_quantity" value="<?php echo htmlspecialchars(serialize($productQuantities), ENT_QUOTES); ?>" />
			<input type="hidden" name="product_price" value="<?php echo htmlspecialchars(serialize($productPrices), ENT_QUOTES); ?>" />
			<input type="hidden" name="total_price" value="<?php echo htmlspecialchars(serialize($totalPrices), ENT_QUOTES); ?>" />
			<input type="hidden" name="sale_unit" value="<?php echo htmlspecialchars(serialize($saleUnits), ENT_QUOTES); ?>" />
			<input type="hidden" name="kg_conversion_qty" value="<?php echo htmlspecialchars(serialize($kgConversionQuantities), ENT_QUOTES); ?>" />
			<input type="hidden" name="lpg_transaction_type" value="<?php echo htmlspecialchars(serialize($lpgTransactionTypes), ENT_QUOTES); ?>" />
			<input type="hidden" name="lpg_tank_condition" value="<?php echo htmlspecialchars(serialize($lpgTankConditions), ENT_QUOTES); ?>" />
			<input type="hidden" name="lpg_tank_payment" value="<?php echo htmlspecialchars(serialize($lpgTankPayments), ENT_QUOTES); ?>" />
			<input type="hidden" id="saleReceiptSaveAction" value="Save Sale" />
			<button class="btn btn-success receipt-action-btn" type="submit" name="saveTransaction" value="Save Sale">Save Sale</button>
			<button class="btn btn-primary receipt-action-btn" type="button" onclick="saveAndPrintSaleReceipt(this);">Print & Save</button>
			<button onclick="history.go(-1)" class="btn btn-light receipt-action-btn" type="button">Edit</button>
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
		</form>
	</div>
</div>

<script>
	let saleReceiptSaved = false;
	let saleReceiptSaving = false;
	let saleReceiptAwaitingSave = false;
	const salesListUrl = '<?php echo $server; ?>?mainmenu=sales_list';

	function saveAndPrintSaleReceipt(button) {
		const saveForm = document.getElementById('saleReceiptSaveForm');
		if (!saveForm) {
			window.print();
			return;
		}
		if (saleReceiptSaved) {
			window.print();
			window.setTimeout(function () { window.location.href = salesListUrl; }, 800);
			return;
		}
		if (saleReceiptSaving) {
			return;
		}
		saleReceiptSaving = true;
		document.querySelectorAll('button').forEach(function(actionButton) {
			if (actionButton.type !== 'button' && actionButton.type !== 'submit') {
				return;
			}
			actionButton.disabled = true;
		});

		let saveFrame = document.getElementById('saleReceiptSaveFrame');
		if (!saveFrame) {
			saveFrame = document.createElement('iframe');
			saveFrame.id = 'saleReceiptSaveFrame';
			saveFrame.name = 'saleReceiptSaveFrame';
			saveFrame.style.display = 'none';
			document.body.appendChild(saveFrame);
		}
		saveFrame.onload = function () {
			if (!saleReceiptAwaitingSave) {
				return;
			}
			saleReceiptAwaitingSave = false;
			saleReceiptSaved = true;
			saleReceiptSaving = false;
			window.print();
			window.setTimeout(function () { window.location.href = salesListUrl; }, 800);
		};
		saveForm.target = 'saleReceiptSaveFrame';
		const saveAction = document.getElementById('saleReceiptSaveAction');
		if (saveAction) {
			saveAction.setAttribute('name', 'saveTransaction');
		}
		saleReceiptAwaitingSave = true;
		saveForm.submit();
	}
</script>
