<?php $receiptItemCount = count($productNames); ?>

<div class="receipt-page receipt-printable">
	<div class="receipt-screen-card">
		<div class="receipt-top-card">
			<div class="receipt-top-copy">
				<p class="receipt-kicker">Print Preview</p>
				<h2>Purchase Receipt</h2>
				<p class="receipt-subtitle">Optimized for 48mm thermal paper printing.</p>
			</div>
			<div class="receipt-top-action">
				<?php if ($previousMenu == "purchase_product"): ?>
					<button type="button" onclick="saveAndPrintPurchaseReceipt(this);" class="btn btn-primary w-100">Print Receipt</button>
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
						<span class="meta-label">Sales Invoice</span>
						<strong>#<?php echo $invoice; ?></strong>
					</div>
					<div>
						<span class="meta-label">Date & Time</span>
						<strong><?php echo junkshop_format_datetime($purchaseDate); ?></strong>
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
							<tr>
								<td>
									<div class="item-name"><?php echo htmlspecialchars($productName); ?></div>
									<div class="item-meta"><?php echo number_format($productQuantities[$index], 2); ?> x &#8369;<?php echo number_format($productPrices[$index], 2); ?></div>
								</td>
								<td class="amount">&#8369;<?php echo number_format($totalPrices[$index], 2); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="line"></div>

				<div class="total">
					<p class="total-emphasis">Grand Total <span class="total-value">&#8369;<?php echo number_format($grandTotal, 2); ?></span></p>
				</div>

				<div class="footer">
					<p>Thank you for choosing us!</p>
					<p>Please keep this receipt for your records.</p>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="receipt-page noPrint">
	<div class="receipt-page-actions receipt-floating-actions">
		<?php if ($previousMenu == "purchase_product"): ?>
			<form class="form new-form receipt-form-actions" method="POST" id="purchaseReceiptSaveForm">
				<input type="hidden" name="product_name" value="<?php echo htmlspecialchars(serialize($productNames), ENT_QUOTES); ?>" />
				<input type="hidden" name="product_id" value="<?php echo htmlspecialchars(serialize($productIds ?? []), ENT_QUOTES); ?>" />
				<input type="hidden" name="product_quantity" value="<?php echo htmlspecialchars(serialize($productQuantities), ENT_QUOTES); ?>" />
				<input type="hidden" name="product_price" value="<?php echo htmlspecialchars(serialize($productPrices), ENT_QUOTES); ?>" />
				<input type="hidden" name="total_price" value="<?php echo htmlspecialchars(serialize($totalPrices), ENT_QUOTES); ?>" />
				<input type="hidden" name="grandTotal" value="<?php echo $grandTotal; ?>" />
				<input type="hidden" name="purchase_date" value="<?php echo htmlspecialchars($purchaseDate); ?>" />
				<input type="hidden" name="mainmenu" value="purchase_invoice" />
				<input type="hidden" name="previousmenu" value="<?php echo htmlspecialchars($previousMenu); ?>" />
				<input type="hidden" id="purchaseReceiptSaveAction" value="Save Transaction" />
				<button class="btn btn-success receipt-action-btn" type="button" onclick="saveAndPrintPurchaseReceipt(this);">Print Receipt</button>
				<button class="btn btn-outline-secondary receipt-action-btn" type="submit" name="saveTransaction" value="Edit">Edit</button>
				<button onclick="history.go(-1)" class="btn btn-light receipt-action-btn" type="button"><?php echo $previousMenu == 'view_invoice' ? 'Back' : 'Cancel'; ?></button>
				<div class="receipt-footer-metrics">
					<div class="receipt-footer-metric">
						<p class="metric-label">Item Count</p>
						<div class="totalprice"><?php echo $receiptItemCount; ?></div>
					</div>
					<div class="receipt-footer-metric">
						<p class="metric-label">Total Purchase</p>
						<div class="totalprice">&#8369;<?php echo number_format($grandTotal, 2); ?></div>
					</div>
				</div>
			</form>
		<?php else: ?>
			<div class="receipt-form-actions">
				<button onclick="history.go(-1)" class="btn btn-light receipt-action-btn" type="button"><?php echo $previousMenu == 'view_invoice' ? 'Back' : 'Cancel'; ?></button>
				<div class="receipt-footer-metrics">
					<div class="receipt-footer-metric">
						<p class="metric-label">Item Count</p>
						<div class="totalprice"><?php echo $receiptItemCount; ?></div>
					</div>
					<div class="receipt-footer-metric">
						<p class="metric-label">Total Purchase</p>
						<div class="totalprice">&#8369;<?php echo number_format($grandTotal, 2); ?></div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php if ($previousMenu == "purchase_product"): ?>
	<script>
		let purchaseReceiptSaved = false;
		let purchaseReceiptSaving = false;
		let purchaseReceiptAwaitingSave = false;
		let purchaseReceiptShouldRedirect = false;
		const purchaseProductUrl = '<?php echo $server; ?>?mainmenu=purchase_product';

		function redirectToPurchaseProduct() {
			if (!purchaseReceiptShouldRedirect) {
				return;
			}

			purchaseReceiptShouldRedirect = false;
			window.location.href = purchaseProductUrl;
		}

		window.addEventListener('afterprint', redirectToPurchaseProduct);

		function saveAndPrintPurchaseReceipt(button) {
			const saveForm = document.getElementById('purchaseReceiptSaveForm');
			if (!saveForm) {
				window.print();
				return;
			}

			if (purchaseReceiptSaved) {
				purchaseReceiptShouldRedirect = true;
				window.print();
				window.setTimeout(redirectToPurchaseProduct, 800);
				return;
			}

			if (purchaseReceiptSaving) {
				return;
			}

			purchaseReceiptSaving = true;
			const printButtons = document.querySelectorAll('button[onclick*="saveAndPrintPurchaseReceipt"]');
			printButtons.forEach(function (printButton) {
				printButton.disabled = true;
				printButton.dataset.originalText = printButton.textContent;
				printButton.textContent = 'Saving...';
			});

			let saveFrame = document.getElementById('purchaseReceiptSaveFrame');
			if (!saveFrame) {
				saveFrame = document.createElement('iframe');
				saveFrame.id = 'purchaseReceiptSaveFrame';
				saveFrame.name = 'purchaseReceiptSaveFrame';
				saveFrame.style.display = 'none';
				document.body.appendChild(saveFrame);
			}

			saveFrame.onload = function () {
				if (!purchaseReceiptAwaitingSave) {
					return;
				}

				purchaseReceiptAwaitingSave = false;
				purchaseReceiptSaved = true;
				purchaseReceiptSaving = false;
				printButtons.forEach(function (printButton) {
					printButton.disabled = false;
					printButton.textContent = printButton.dataset.originalText || 'Print Receipt';
				});
				purchaseReceiptShouldRedirect = true;
				window.print();
				window.setTimeout(redirectToPurchaseProduct, 800);
			};

			saveForm.target = 'purchaseReceiptSaveFrame';
			const saveAction = document.getElementById('purchaseReceiptSaveAction');
			if (saveAction) {
				saveAction.setAttribute('name', 'saveTransaction');
			}
			purchaseReceiptAwaitingSave = true;
			saveForm.submit();
		}
	</script>
<?php endif; ?>
