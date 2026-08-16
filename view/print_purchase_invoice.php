<?php
	$invoiceNo = (int) ($_GET['invoiceNo'] ?? 0);
	$returnTo = isset($_GET['return_to']) ? (string) $_GET['return_to'] : ('?mainmenu=view_invoice&invoiceNo=' . $invoiceNo);
	$purchaseItems = [];
	$invoiceTotalPrice = 0;
	$productCount = 0;
	$purchaseDate = junkshop_normalize_datetime('');

	if ($invoiceNo > 0) {
		$purchaseResult = $connectDB->query("SELECT * FROM purchases WHERE InvoiceNo = '$invoiceNo' ORDER BY ID ASC");
		if ($purchaseResult && $purchaseResult->num_rows > 0) {
			while ($row = $purchaseResult->fetch_assoc()) {
				$purchaseItems[] = $row;
				$purchaseDate = $row['PurchaseDate'];
				$invoiceTotalPrice += (float) ($row['TotalPurchasePrice'] ?? 0);
				$productCount++;
			}
		}
	}
?>

<?php if ($productCount === 0): ?>
	<div class="receipt-page">
		<div class="receipt-screen-card">
			<p class="mb-0">No purchase invoice records were found.</p>
			<a class="btn btn-light mt-3" href="<?php echo htmlspecialchars($returnTo, ENT_QUOTES); ?>">Back</a>
		</div>
	</div>
<?php else: ?>
	<div class="receipt-page purchase-invoice-printable">
		<div class="receipt-screen-card">
			<div class="receipt-top-card">
				<div class="receipt-top-copy">
					<p class="receipt-kicker">Print Preview</p>
					<h2>Purchase Invoice</h2>
					<p class="receipt-subtitle">Full-page invoice for this purchase transaction.</p>
				</div>
				<div class="receipt-top-action">
					<button type="button" onclick="window.print();" class="btn btn-primary w-100">Print Invoice</button>
				</div>
			</div>
			<div class="receipt-preview-shell purchase-invoice-preview-shell">
				<div class="delivery-invoice">
					<div class="delivery-invoice-topbar"></div>
					<div class="delivery-invoice-header">
						<div class="delivery-invoice-brand">
							<p class="delivery-invoice-eyebrow"><?php echo htmlspecialchars($companyName ?? 'Company'); ?></p>
							<h4>Purchase Invoice</h4>
							<?php if (!empty($companyAddressLine1)): ?><p><?php echo htmlspecialchars($companyAddressLine1); ?></p><?php endif; ?>
							<?php if (!empty($companyAddressLine2)): ?><p><?php echo htmlspecialchars($companyAddressLine2); ?></p><?php endif; ?>
							<?php if (!empty($companyContactNumber)): ?><p>Contact: <?php echo htmlspecialchars($companyContactNumber); ?></p><?php endif; ?>
						</div>
						<div class="delivery-invoice-meta">
							<div class="delivery-invoice-meta-grid">
								<div>
									<span>Invoice #</span>
									<strong><?php echo $invoiceNo; ?></strong>
								</div>
								<div>
									<span>Date & Time</span>
									<strong><?php echo junkshop_format_datetime($purchaseDate); ?></strong>
								</div>
								<div>
									<span>Items</span>
									<strong><?php echo $productCount; ?></strong>
								</div>
							</div>
						</div>
					</div>

					<div class="delivery-invoice-table-wrap">
						<table class="delivery-invoice-table">
							<thead>
								<tr>
									<th>Sl.</th>
									<th>Item Description</th>
									<th class="text-end">Purchase Price</th>
									<th class="text-end">Qty.</th>
									<th class="text-end">Total</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($purchaseItems as $index => $item): ?>
									<tr>
										<td><?php echo $index + 1; ?></td>
										<td><strong><?php echo htmlspecialchars($item['ProductName']); ?></strong></td>
										<td class="text-end">&#8369;<?php echo number_format((float) $item['ProductPrice'], 2); ?></td>
										<td class="text-end"><?php echo number_format((float) $item['Quantity'], 2); ?></td>
										<td class="text-end">&#8369;<?php echo number_format((float) $item['TotalPurchasePrice'], 2); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div class="delivery-invoice-footer">
						<div class="delivery-invoice-notes">
							<p class="delivery-invoice-footer-title">Thank you for your business</p>
							<p>This invoice covers the purchase items for this transaction.</p>
							<p class="delivery-invoice-signoff">Prepared by <?php echo htmlspecialchars($companyName ?? 'Company'); ?></p>
						</div>
						<div class="delivery-invoice-totals">
							<div class="delivery-invoice-grand-total"><span>Grand Total</span><strong>&#8369;<?php echo number_format($invoiceTotalPrice, 2); ?></strong></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="receipt-page noPrint">
		<div class="receipt-page-actions receipt-floating-actions">
			<div class="receipt-form-actions">
				<button type="button" onclick="window.print();" class="btn btn-success receipt-action-btn">Print Invoice</button>
				<a href="<?php echo htmlspecialchars($returnTo, ENT_QUOTES); ?>" class="btn btn-light receipt-action-btn">Back</a>
				<div class="receipt-footer-metrics">
					<div class="receipt-footer-metric">
						<p class="metric-label">Item Count</p>
						<div class="totalprice"><?php echo $productCount; ?></div>
					</div>
					<div class="receipt-footer-metric">
						<p class="metric-label">Total Purchase</p>
						<div class="totalprice">&#8369;<?php echo number_format($invoiceTotalPrice, 2); ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
