<?php
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo "<script>window.location.href = '?mainmenu=sell_product_others';</script>";
        exit;
    }

    $previousMenu = $_POST['previousmenu'] ?? 'sell_product_others';
    $salesType = strtoupper(trim((string) ($_POST['sales_type'] ?? 'OTHERS')));
    if (!in_array($salesType, ['OTHERS', 'LPG'], true)) {
        $salesType = 'OTHERS';
    }

    $saleDate = $_POST['sale_date'] ?? date('Y-m-d');
    $customerName = trim((string) ($_POST['customer_name'] ?? 'Walk-in Customer'));
    if ($customerName === '') {
        $customerName = 'Walk-in Customer';
    }
    $customerAddress = trim((string) ($_POST['customer_address'] ?? ''));

    $saleNotes = trim((string) ($_POST['sale_notes'] ?? ''));

    $customerType = strtolower(trim((string) ($_POST['customer_type'] ?? 'walk_in')));
    $isRegisteredCustomer = $customerType === 'registered';
    $cashGiven = (float) ($_POST['cash_given'] ?? 0);
    $amountDue = (float) ($_POST['amount_due'] ?? 0);

    $paymentStatuses = isset($_POST['payment_status']) && is_array($_POST['payment_status']) ? $_POST['payment_status'] : [];
    $amountPaids = isset($_POST['amount_paid']) && is_array($_POST['amount_paid']) ? $_POST['amount_paid'] : [];
    $dueDates = isset($_POST['due_date']) && is_array($_POST['due_date']) ? $_POST['due_date'] : [];

    $productNames = isset($_POST['product_name']) && is_array($_POST['product_name']) ? $_POST['product_name'] : [];
    $productQuantities = isset($_POST['product_quantity']) && is_array($_POST['product_quantity']) ? $_POST['product_quantity'] : [];
    $productPrices = isset($_POST['product_price']) && is_array($_POST['product_price']) ? $_POST['product_price'] : [];
    $saleUnits = isset($_POST['sale_unit']) && is_array($_POST['sale_unit']) ? $_POST['sale_unit'] : [];

    $lpgTypes = isset($_POST['lpg_transaction_type']) && is_array($_POST['lpg_transaction_type']) ? $_POST['lpg_transaction_type'] : [];
    $lpgConditions = isset($_POST['lpg_tank_condition']) && is_array($_POST['lpg_tank_condition']) ? $_POST['lpg_tank_condition'] : [];

    $getSaleNo = $connectDB->query("SELECT DeliveryNo FROM sales ORDER BY DeliveryNo DESC LIMIT 1");
    if ($getSaleNo && $getSaleNo->num_rows > 0) {
        $saleNoRow = $getSaleNo->fetch_assoc();
        $saleNo = (int) $saleNoRow['DeliveryNo'] + 1;
    } else {
        $saleNo = 1;
    }

    $grandTotal = 0;
    $totalAmountPaid = 0;
    $totalBalance = 0;
    $validItems = [];
    $receiptDueDates = [];

    foreach ($productNames as $index => $productName) {
        $name = trim((string) $productName);
        $quantity = (float) ($productQuantities[$index] ?? 0);
        $price = (float) ($productPrices[$index] ?? 0);

        if ($name === '' || $quantity <= 0 || $price <= 0) {
            continue;
        }

        $lpgType = isset($lpgTypes[$index]) ? strtoupper(trim($lpgTypes[$index])) : 'NONE';
        $lpgCondition = isset($lpgConditions[$index]) ? trim($lpgConditions[$index]) : '';

        $rowTotal = $quantity * $price;
        $grandTotal += $rowTotal;

        $rowPaymentStatus = strtoupper(trim((string) ($paymentStatuses[$index] ?? 'PAID')));
        if (!in_array($rowPaymentStatus, ['PAID', 'PARTIAL', 'UNPAID'], true)) {
            $rowPaymentStatus = 'PAID';
        }
        if (!$isRegisteredCustomer) {
            $rowPaymentStatus = 'PAID';
        }
        $rowAmountPaid = (float) ($amountPaids[$index] ?? 0);
        if ($rowPaymentStatus === 'PAID') {
            $rowAmountPaid = $rowTotal;
        } elseif ($rowPaymentStatus === 'UNPAID') {
            $rowAmountPaid = 0;
        } else {
            $rowAmountPaid = min(max($rowAmountPaid, 0), $rowTotal);
        }
        $rowBalance = max($rowTotal - $rowAmountPaid, 0);
        $rowDueDate = trim((string) ($dueDates[$index] ?? ''));
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

        $validItems[] = [
            'name' => $name,
            'qty' => $quantity,
            'unit' => junkshop_normalize_base_unit($saleUnits[$index] ?? 'pc'),
            'price' => $price,
            'total' => $rowTotal,
            'lpg_type' => $lpgType,
            'lpg_condition' => $lpgCondition,
            'payment_status' => $rowPaymentStatus,
            'amount_paid' => $rowAmountPaid,
            'balance' => $rowBalance,
            'amount_due' => $rowAmountDue,
            'due_date' => $rowDueDate,
        ];
    }

    if ($amountDue <= 0) {
        $amountDue = array_sum(array_column($validItems, 'amount_due'));
    }
    $displayChange = $cashGiven > 0 ? max(0, $cashGiven - $amountDue) : 0;
    $latestDueDate = !empty($receiptDueDates) ? max($receiptDueDates) : '';
    $receiptItemCount = count($validItems);
?>

<div class="receipt-page receipt-printable">
	<div class="receipt-screen-card">
		<div class="receipt-top-card">
			<div class="receipt-top-copy">
				<p class="receipt-kicker">Print Preview</p>
				<h2>Sale Receipt</h2>
				<p class="receipt-subtitle">Optimized for 48mm thermal paper printing.</p>
			</div>
			<div class="receipt-top-action">
				<?php if ($previousMenu === 'sell_product_others' || $previousMenu === 'sell_product_lpg'): ?>
					<button type="button" onclick="saveAndPrintSaleReceipt(this);" class="btn btn-primary w-100">Print Receipt</button>
				<?php else: ?>
					<button type="button" onclick="window.print();" class="btn btn-primary w-100">Print Receipt</button>
				<?php endif; ?>
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
						<span class="meta-label">Invoice</span>
						<strong>#<?php echo str_pad($saleNo, 5, '0', STR_PAD_LEFT); ?></strong>
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
									<?php if ($isRegisteredCustomer && $item['payment_status'] !== 'PAID'): ?>
										<?php
											$paymentLabel = $item['payment_status'] === 'PARTIAL' ? 'Partial Payment' : 'Loan / Unpaid';
										?>
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

					<?php if ($cashGiven > 0): ?>
						<p class="total-emphasis">Cash Given <span class="total-value">&#8369;<?php echo number_format($cashGiven, 2); ?></span></p>
						<p class="total-emphasis">Change <span class="total-value">&#8369;<?php echo number_format($displayChange, 2); ?></span></p>
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
		<?php if ($previousMenu === 'sell_product_others' || $previousMenu === 'sell_product_lpg'): ?>
			<form class="form new-form receipt-form-actions" method="POST" id="saleReceiptSaveForm">
				<?php foreach ($_POST as $key => $value): ?>
					<?php if (is_array($value)): ?>
						<?php foreach ($value as $val): ?>
							<input type="hidden" name="<?php echo htmlspecialchars($key); ?>[]" value="<?php echo htmlspecialchars((string) $val); ?>" />
						<?php endforeach; ?>
					<?php else: ?>
						<input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars((string) $value); ?>" />
					<?php endif; ?>
				<?php endforeach; ?>
				<input type="hidden" name="mainmenu" value="sale_invoice" />
				<input type="hidden" name="previousmenu" value="<?php echo htmlspecialchars($previousMenu); ?>" />
				<input type="hidden" id="saleReceiptSaveAction" value="Save Transaction" />
				<button class="btn btn-success receipt-action-btn" type="button" onclick="saveAndPrintSaleReceipt(this);">Print Receipt</button>
				<button class="btn btn-outline-secondary receipt-action-btn" type="button" onclick="history.go(-1)">Edit</button>
				<button onclick="history.go(-1)" class="btn btn-light receipt-action-btn" type="button">Cancel</button>
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
		<?php else: ?>
			<div class="receipt-form-actions">
				<button onclick="history.go(-1)" class="btn btn-light receipt-action-btn" type="button">Back</button>
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
		<?php endif; ?>
	</div>
</div>

<?php if ($previousMenu === 'sell_product_others' || $previousMenu === 'sell_product_lpg'): ?>
	<script>
		let saleReceiptSaved = false;
		let saleReceiptSaving = false;
		let saleReceiptAwaitingSave = false;
		let saleReceiptShouldRedirect = false;
		const sellProductUrl = '<?php echo $server; ?>?mainmenu=sell_product_others';

		function redirectToSellProduct() {
			if (!saleReceiptShouldRedirect) {
				return;
			}

			saleReceiptShouldRedirect = false;
			window.location.href = sellProductUrl;
		}

		window.addEventListener('afterprint', redirectToSellProduct);

		function saveAndPrintSaleReceipt(button) {
			const saveForm = document.getElementById('saleReceiptSaveForm');
			if (!saveForm) {
				window.print();
				return;
			}

			if (saleReceiptSaved) {
				saleReceiptShouldRedirect = true;
				window.print();
				window.setTimeout(redirectToSellProduct, 800);
				return;
			}

			if (saleReceiptSaving) {
				return;
			}

			saleReceiptSaving = true;
			const printButtons = document.querySelectorAll('button[onclick*="saveAndPrintSaleReceipt"]');
			printButtons.forEach(function (printButton) {
				printButton.disabled = true;
				printButton.dataset.originalText = printButton.textContent;
				printButton.textContent = 'Saving...';
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
				printButtons.forEach(function (printButton) {
					printButton.disabled = false;
					printButton.textContent = printButton.dataset.originalText || 'Print Receipt';
				});
				saleReceiptShouldRedirect = true;
				window.print();
				window.setTimeout(redirectToSellProduct, 800);
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
<?php endif; ?>
