<?php
	$deliveryNo = (int) ($_GET['deliveryNo'] ?? 0);
	$returnTo = isset($_GET['return_to']) ? $_GET['return_to'] : '?mainmenu=sales_list';
	$encodedReturnTo = htmlspecialchars($returnTo, ENT_QUOTES);
	$saleDetails = $connectDB->query("SELECT * FROM sales WHERE DeliveryNo = '$deliveryNo' ORDER BY ID ASC");
	$saleAppDetails = $connectDB->query("SELECT * FROM sale_app_links WHERE DeliveryNo = '$deliveryNo' ORDER BY ID ASC");
	$linkedExpenses = $connectDB->query("SELECT * FROM expenses WHERE DeliveryNo = '$deliveryNo' ORDER BY ID ASC");
	$saleItems = [];
	$saleAppItems = [];
	$expenseItems = [];
	$saleTotal = 0;
	$deliveryAppTotal = 0;
	$deliveryExpenseTotal = 0;
	$customerName = '';
	$saleDate = '';
	$latestExpenseDate = date('Y-m-d');
	$products = [];
	$expenseCategories = [];
	$allProducts = $connectDB->query("
		SELECT
			p.Product_ID AS id,
			p.ProductName AS name,
			COALESCE(NULLIF(p.SellingPrice, 0), p.ProductPrice) AS price
		FROM products p
		WHERE p.IsActive = 1
		ORDER BY COALESCE(p.ParentProduct_ID, 0) ASC, p.ProductName ASC
	");
	if ($allProducts && $allProducts->num_rows > 0) {
		while ($row = $allProducts->fetch_assoc()) {
			$products[] = $row;
		}
	}
	$categoryResult = $connectDB->query("SELECT CategoryName FROM expense_categories WHERE IsActive = 1 ORDER BY CategoryName ASC");
	if ($categoryResult && $categoryResult->num_rows > 0) {
		while ($row = $categoryResult->fetch_assoc()) {
			$expenseCategories[] = $row['CategoryName'];
		}
	}
	if ($saleDetails && $saleDetails->num_rows > 0) {
		while ($row = $saleDetails->fetch_assoc()) {
			$saleItems[] = $row;
			$customerName = $row['CustomerName'];
			$saleDate = $row['SaleDate'];
			$saleTotal += (float) $row['TotalSalePrice'];
		}
	}
	if ($saleAppDetails && $saleAppDetails->num_rows > 0) {
		while ($row = $saleAppDetails->fetch_assoc()) {
			$saleAppItems[] = $row;
			$deliveryAppTotal += (float) $row['TotalPurchasePrice'];
		}
	}
	if ($linkedExpenses && $linkedExpenses->num_rows > 0) {
		while ($row = $linkedExpenses->fetch_assoc()) {
			$expenseItems[] = $row;
			$deliveryExpenseTotal += (float) $row['Amount'];
			$latestExpenseDate = $row['ExpenseDate'];
		}
	}
?>

<script>
	const soldDetailProductData = <?php echo json_encode($products); ?>;
</script>

<div class="container-fluid" style="padding-bottom: 80px;">
	<h2>Sale No. <?php echo $deliveryNo; ?></h2>

	<div class="table-card">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>No</th>
						<th>Date</th>
						<th>Customer</th>
						<th>Product</th>
						<th class="text-end">Pcs/Kg</th>
						<th class="text-end">Selling Price</th>
						<th class="text-end">Total</th>
						<th>Notes</th>
						<th>Action</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($saleItems)): ?>
						<?php $count = 1; ?>
						<?php foreach ($saleItems as $sale): ?>
							<tr>
								<td><?php echo $count; ?></td>
								<td><?php echo date('M-d-Y', strtotime($sale['SaleDate'])); ?></td>
								<td><?php echo htmlspecialchars($sale['CustomerName']); ?></td>
								<td><?php echo htmlspecialchars($sale['ProductName']); ?></td>
								<td class="text-end"><?php echo number_format($sale['Quantity'], 2); ?></td>
								<td class="text-end">&#8369;<?php echo number_format($sale['UnitPrice'], 2); ?></td>
								<td class="text-end">&#8369;<?php echo number_format($sale['TotalSalePrice'], 2); ?></td>
								<td><?php echo htmlspecialchars($sale['Notes']); ?></td>
								<td class="text-center">
									<div class="icon-action-group justify-content-center">
									<button
										data-toggle="modal"
										data-target="#editSaleDetails"
										type="button"
										class="icon-action-btn icon-action-btn-edit"
										data-id="<?php echo $sale['ID']; ?>"
										data-date="<?php echo $sale['SaleDate']; ?>"
										data-customer="<?php echo htmlspecialchars($sale['CustomerName'], ENT_QUOTES); ?>"
										data-product="<?php echo htmlspecialchars($sale['ProductName'], ENT_QUOTES); ?>"
										data-quantity="<?php echo $sale['Quantity']; ?>"
										data-price="<?php echo $sale['UnitPrice']; ?>"
										data-total="<?php echo $sale['TotalSalePrice']; ?>"
										data-notes="<?php echo htmlspecialchars($sale['Notes'], ENT_QUOTES); ?>"
										aria-label="Edit sale item <?php echo $count; ?>"
										title="Edit"
									>
										<i class="bi bi-pencil-square" aria-hidden="true"></i>
									</button>
									<a
										href="<?php echo $server; ?>?mainmenu=view_sale&deliveryNo=<?php echo $deliveryNo; ?>&return_to=<?php echo urlencode($returnTo); ?>&delete_product=1&ID=<?php echo $sale['ID']; ?>"
										class="icon-action-btn icon-action-btn-delete"
										onclick='return confirm("Do you want to delete this sold record?")'
										aria-label="Delete sale item <?php echo $count; ?>"
										title="Delete"
									>
										<i class="bi bi-trash3" aria-hidden="true"></i>
									</a>
									<?php if ((int) ($sale['IsReturned'] ?? 0) === 0): ?>
										<a
											href="<?php echo $server; ?>?mainmenu=return_item&sale_id=<?php echo $sale['ID']; ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
											class="icon-action-btn icon-action-btn-view"
											onclick='return confirm("Return this item to inventory?")'
											aria-label="Return sale item <?php echo $count; ?>"
											title="Return Item"
										>
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
										</a>
									<?php else: ?>
										<span class="badge text-bg-warning">Returned</span>
									<?php endif; ?>
									</div>
								</td>
							</tr>
							<?php $count++; ?>
						<?php endforeach; ?>
					<?php else: ?>
							<tr><td colspan="9" class="empty-state">No sale details found.</td></tr>
						<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="dashboard-card margin-top">
		<div class="section-head">
			<div>
				<p class="section-kicker">Attached Cost Basis</p>
				<h3>Average purchase price attached to sale items</h3>
			</div>
		</div>
		<div class="table-card">
			<div class="table-responsive">
				<table class="table table-hover">
					<thead>
						<tr>
							<th>No</th>
							<th>Sale Item</th>
							<th>Attached Cost Basis Product(s)</th>
							<th class="text-end">Pcs/Kg</th>
							<th class="text-end">cost basis</th>
							<th class="text-end">Total</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($saleAppItems)): ?>
							<?php $saleAppCount = 1; ?>
							<?php foreach ($saleAppItems as $saleApp): ?>
								<tr>
									<td><?php echo $saleAppCount; ?></td>
									<td><?php echo htmlspecialchars($saleApp['SaleProductName']); ?></td>
									<td><?php echo htmlspecialchars($saleApp['SourceProducts']); ?></td>
									<td class="text-end"><?php echo number_format($saleApp['Quantity'], 2); ?></td>
									<td class="text-end">&#8369;<?php echo number_format($saleApp['AveragePurchasePrice'], 2); ?></td>
									<td class="text-end">&#8369;<?php echo number_format($saleApp['TotalPurchasePrice'], 2); ?></td>
								</tr>
								<?php $saleAppCount++; ?>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="6" class="empty-state">No cost basis attachments recorded for this sale.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="dashboard-card margin-top">
		<div class="section-head">
			<div>
				<p class="section-kicker">Linked expenses</p>
				<h3>Expenses for this sale</h3>
			</div>
		</div>
		<div class="table-card">
			<div class="table-responsive">
				<table class="table table-hover">
					<thead>
						<tr>
							<th>No</th>
							<th>Date</th>
							<th>Category</th>
							<th>Description</th>
							<th class="text-end">Amount</th>
							<th class="text-center">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($expenseItems)): ?>
							<?php $expenseCount = 1; ?>
							<?php foreach ($expenseItems as $expense): ?>
								<tr>
									<td><?php echo $expenseCount; ?></td>
									<td><?php echo date('M-d-Y', strtotime($expense['ExpenseDate'])); ?></td>
									<td><?php echo htmlspecialchars($expense['ExpenseCategory']); ?></td>
									<td><?php echo htmlspecialchars($expense['Description']); ?></td>
									<td class="text-end">&#8369;<?php echo number_format($expense['Amount'], 2); ?></td>
									<td class="text-center">
										<div class="icon-action-group justify-content-center">
										<button
											data-toggle="modal"
											data-target="#editDeliveryExpense"
											type="button"
											class="icon-action-btn icon-action-btn-edit"
											data-id="<?php echo $expense['ID']; ?>"
											data-date="<?php echo $expense['ExpenseDate']; ?>"
											data-category="<?php echo htmlspecialchars($expense['ExpenseCategory'], ENT_QUOTES); ?>"
											data-description="<?php echo htmlspecialchars($expense['Description'], ENT_QUOTES); ?>"
											data-amount="<?php echo $expense['Amount']; ?>"
											aria-label="Edit linked expense <?php echo $expenseCount; ?>"
											title="Edit"
										>
											<i class="bi bi-pencil-square" aria-hidden="true"></i>
										</button>
										<a
											href="<?php echo $server; ?>?mainmenu=view_sale&deliveryNo=<?php echo $deliveryNo; ?>&return_to=<?php echo urlencode($returnTo); ?>&delete_product=1&expenseID=<?php echo $expense['ID']; ?>"
											class="icon-action-btn icon-action-btn-delete"
											onclick='return confirm("Do you want to delete this linked expense?")'
											aria-label="Delete linked expense <?php echo $expenseCount; ?>"
											title="Delete"
										>
											<i class="bi bi-trash3" aria-hidden="true"></i>
										</a>
										</div>
									</td>
								</tr>
								<?php $expenseCount++; ?>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="6" class="empty-state">No expenses linked to this sale.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="d-flex justify-content-end margin-top">
			<button data-toggle="modal" data-target="#addDeliveryExpense" class="btn btn-outline-secondary">Add Expense</button>
		</div>
	</div>

	<?php if (!empty($saleItems)): ?>
		<div id="deliveryInvoiceTemplate" class="delivery-invoice-template" aria-hidden="true">
			<div class="delivery-invoice" id="deliveryInvoicePrint">
				<div class="delivery-invoice-topbar"></div>
				<div class="delivery-invoice-header">
					<div class="delivery-invoice-brand">
						<p class="delivery-invoice-eyebrow"><?php echo htmlspecialchars($companyName); ?></p>
						<h4>Sale Invoice</h4>
						<?php if ($companyAddressLine1 !== ''): ?><p><?php echo htmlspecialchars($companyAddressLine1); ?></p><?php endif; ?>
						<?php if ($companyAddressLine2 !== ''): ?><p><?php echo htmlspecialchars($companyAddressLine2); ?></p><?php endif; ?>
						<?php if ($companyContactNumber !== ''): ?><p>Contact: <?php echo htmlspecialchars($companyContactNumber); ?></p><?php endif; ?>
					</div>
					<div class="delivery-invoice-meta">
						<div class="delivery-invoice-meta-grid">
							<div>
								<span>Invoice #</span>
								<strong><?php echo $deliveryNo; ?></strong>
							</div>
							<div>
								<span>Date</span>
								<strong><?php echo date('M d, Y', strtotime($saleDate)); ?></strong>
							</div>
							<div>
								<span>Customer</span>
								<strong><?php echo htmlspecialchars($customerName ?: 'Walk-in Customer'); ?></strong>
							</div>
							<div>
								<span>Items</span>
								<strong><?php echo count($saleItems); ?></strong>
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
								<th class="text-end">Selling Price</th>
								<th class="text-end">Qty.</th>
								<th class="text-end">Total</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($saleItems as $index => $sale): ?>
								<tr>
									<td><?php echo $index + 1; ?></td>
									<td>
										<strong><?php echo htmlspecialchars($sale['ProductName']); ?></strong>
										<?php if (!empty($sale['Notes'])): ?>
											<small><?php echo htmlspecialchars($sale['Notes']); ?></small>
										<?php endif; ?>
									</td>
									<td class="text-end">&#8369;<?php echo number_format($sale['UnitPrice'], 2); ?></td>
									<td class="text-end"><?php echo number_format($sale['Quantity'], 2); ?></td>
									<td class="text-end">&#8369;<?php echo number_format($sale['TotalSalePrice'], 2); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if (!empty($expenseItems)): ?>
					<div class="delivery-invoice-expense-section">
						<div class="delivery-invoice-section-head">
							<h5>Linked Expenses</h5>
							<p>Expenses recorded for this sale</p>
						</div>
						<div class="delivery-invoice-table-wrap">
							<table class="delivery-invoice-table delivery-invoice-expense-table">
								<thead>
									<tr>
										<th>Sl.</th>
										<th>Category</th>
										<th>Description</th>
										<th class="text-end">Amount</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($expenseItems as $index => $expense): ?>
										<tr>
											<td><?php echo $index + 1; ?></td>
											<td><strong><?php echo htmlspecialchars($expense['ExpenseCategory']); ?></strong></td>
											<td><?php echo htmlspecialchars($expense['Description'] ?: '-'); ?></td>
											<td class="text-end">&#8369;<?php echo number_format($expense['Amount'], 2); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endif; ?>

				<div class="delivery-invoice-footer">
					<div class="delivery-invoice-notes">
						<p class="delivery-invoice-footer-title">Thank you for your business</p>
						<p>This invoice covers the sale items for this transaction together with the linked operating expenses for full sale tracking.</p>
						<p class="delivery-invoice-signoff">Prepared by <?php echo htmlspecialchars($companyName); ?></p>
					</div>
					<div class="delivery-invoice-totals">
						<div><span>Sub Total</span><strong>&#8369;<?php echo number_format($saleTotal, 2); ?></strong></div>
						<div><span>Purchase Total</span><strong>&#8369;<?php echo number_format($deliveryAppTotal, 2); ?></strong></div>
						<div><span>Linked Expenses</span><strong>&#8369;<?php echo number_format($deliveryExpenseTotal, 2); ?></strong></div>
						<div class="delivery-invoice-grand-total"><span>Net Earning</span><strong>&#8369;<?php echo number_format($saleTotal - $deliveryAppTotal - $deliveryExpenseTotal, 2); ?></strong></div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php $deliveryNetEarning = $saleTotal - $deliveryAppTotal - $deliveryExpenseTotal; ?>

<div class="row custom-row fixed-footer">
	<div class="col-md-2 col-12 footer-action">
		<a class="btn btn-info form-control" href="<?php echo $encodedReturnTo; ?>">Back</a>
	</div>
	<div class="col-md-2 col-12 footer-action">
		<?php if (!empty($saleItems)): ?>
			<button type="button" class="btn btn-outline-secondary form-control" onclick="printDeliveryInvoice()">Print Sale Invoice</button>
		<?php endif; ?>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p><strong>Sale Total:</strong><br><span class="totalprice">&#8369;<?php echo number_format($saleTotal, 2); ?></span></p>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p><strong>Purchase Total:</strong><br><span class="totalprice">&#8369;<?php echo number_format($deliveryAppTotal, 2); ?></span></p>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p><strong>Sale Expenses:</strong><br><span class="totalprice">&#8369;<?php echo number_format($deliveryExpenseTotal, 2); ?></span></p>
	</div>
	<div class="col-md-2 col-6 footer-metric">
		<p><strong>Net Earning:</strong><br><span class="totalprice <?php echo $deliveryNetEarning >= 0 ? 'text-success' : 'text-danger'; ?>">&#8369;<?php echo number_format($deliveryNetEarning, 2); ?></span></p>
	</div>
</div>

<script>
	$(document).ready(function () {
		$('#editSaleDetails').on('show.bs.modal', function (event) {
			var button = $(event.relatedTarget);
			var modal = $(this);
			modal.find('input[name="ID"]').val(button.data('id'));
			modal.find('input[name="sale_date"]').val(button.data('date'));
			modal.find('input[name="customer_name"]').val(button.data('customer'));
			modal.find('input[name="product_name"]').val(button.data('product'));
			modal.find('input[name="Quantity"]').val(button.data('quantity'));
			modal.find('input[name="unit_price"]').val(button.data('price'));
			modal.find('input[name="total_sale_price"]').val(button.data('total'));
			modal.find('input[name="sale_notes"]').val(button.data('notes'));
		});

		$('#editDeliveryExpense').on('show.bs.modal', function (event) {
			var button = $(event.relatedTarget);
			var modal = $(this);
			modal.find('input[name="expenseID"]').val(button.data('id'));
			modal.find('input[name="expense_date"]').val(button.data('date'));
			modal.find('input[name="expense_category"]').val(button.data('category'));
			modal.find('input[name="expense_description"]').val(button.data('description'));
			modal.find('input[name="expense_amount"]').val(button.data('amount'));
		});

		$('#editSaleDetails').on('input', 'input[name="Quantity"], input[name="unit_price"]', function () {
			var modal = $('#editSaleDetails');
			var quantity = parseFloat(modal.find('input[name="Quantity"]').val()) || 0;
			var price = parseFloat(modal.find('input[name="unit_price"]').val()) || 0;
			modal.find('input[name="total_sale_price"]').val((quantity * price).toFixed(2));
		});
	});

	function validateSoldDetailSelection(inputElement) {
		const selectedProduct = soldDetailProductData.find(function(product) {
			return product.name === inputElement.value;
		});

		if (!selectedProduct) {
			alert('Please select a valid product from the suggestions.');
			inputElement.value = '';
			document.getElementById('detail_product_id').value = '';
			document.getElementById('detail_product_price').value = '';
			document.getElementById('detail_total_sale_price').value = '';
			return;
		}

		document.getElementById('detail_product_id').value = selectedProduct.id || '';
		document.getElementById('detail_product_price').value = selectedProduct.price || 0;
		calculateDetailSaleTotal();
	}

	function calculateDetailSaleTotal() {
		const quantity = parseFloat(document.getElementById('detail_quantity').value) || 0;
		const price = parseFloat(document.getElementById('detail_product_price').value) || 0;
		document.getElementById('detail_total_sale_price').value = (quantity * price).toFixed(2);
	}

	function printDeliveryInvoice() {
		const invoiceTemplate = document.getElementById('deliveryInvoiceTemplate');
		if (!invoiceTemplate) {
			return;
		}

		const stylesheetLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
			.map(function(link) {
				return link.outerHTML;
			})
			.join('');

		const inlineStyles = Array.from(document.querySelectorAll('style'))
			.map(function(styleTag) {
				return styleTag.outerHTML;
			})
			.join('');

		const printWindow = window.open('', '_blank', 'width=1200,height=900');
		if (!printWindow) {
			alert('Please allow pop-ups so the invoice can be printed.');
			return;
		}

		printWindow.document.open();
		printWindow.document.write(`
			<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<title>Sale Invoice #<?php echo $deliveryNo; ?></title>
				${stylesheetLinks}
				${inlineStyles}
				<style>
					html, body {
						background: #eef4f8;
						-webkit-print-color-adjust: exact !important;
						print-color-adjust: exact !important;
					}

					body {
						margin: 0;
						padding: 32px;
					}

					.delivery-print-stage {
						width: 8in;
						max-width: 8in;
						margin: 0 auto;
					}

					.delivery-print-stage .delivery-invoice {
						max-width: none;
						box-sizing: border-box;
						padding: 18px 20px 20px !important;
						border: 1px solid rgba(148, 163, 184, 0.24);
						border-radius: 24px;
						box-shadow: none !important;
					}

					.delivery-print-stage .delivery-invoice-topbar {
						margin-bottom: 14px !important;
					}

					.delivery-print-stage .delivery-invoice-header {
						display: flex !important;
						flex-direction: row !important;
						justify-content: space-between !important;
						align-items: flex-start !important;
						gap: 18px !important;
						margin-bottom: 16px !important;
					}

					.delivery-print-stage .delivery-invoice-brand,
					.delivery-print-stage .delivery-invoice-meta {
						flex: 1 1 0 !important;
					}

					.delivery-print-stage .delivery-invoice-brand p,
					.delivery-print-stage .delivery-invoice-notes p {
						margin-bottom: 0.22rem !important;
						font-size: 0.95rem !important;
					}

					.delivery-print-stage .delivery-invoice-meta-grid {
						display: grid !important;
						grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
						gap: 10px !important;
						padding: 14px !important;
					}

					.delivery-print-stage .delivery-invoice-footer {
						display: grid !important;
						grid-template-columns: minmax(0, 1.3fr) minmax(240px, 0.9fr) !important;
						gap: 16px !important;
						align-items: end !important;
					}

					.delivery-print-stage .delivery-invoice-table-wrap,
					.delivery-print-stage .delivery-invoice-totals,
					.delivery-print-stage .delivery-invoice-footer,
					.delivery-print-stage .delivery-invoice-notes {
						break-inside: avoid;
					}

					.delivery-print-stage .delivery-invoice-table th,
					.delivery-print-stage .delivery-invoice-table td {
						padding: 0.56rem 0.68rem !important;
						font-size: 0.95rem !important;
					}

					.delivery-print-stage .delivery-invoice-table thead th {
						font-size: 0.72rem !important;
					}

					.delivery-print-stage .delivery-invoice-section-head {
						display: flex !important;
						align-items: end !important;
						justify-content: space-between !important;
						gap: 12px !important;
						margin-bottom: 8px !important;
					}

					.delivery-print-stage .delivery-invoice-section-head h5,
					.delivery-print-stage .delivery-invoice-footer-title {
						font-size: 0.95rem !important;
					}

					.delivery-print-stage .delivery-invoice-section-head p,
					.delivery-print-stage .delivery-invoice-notes p {
						font-size: 0.85rem !important;
					}

					.delivery-print-stage .delivery-invoice-expense-section,
					.delivery-print-stage .delivery-invoice-table-wrap {
						margin-bottom: 14px !important;
					}

					.delivery-print-stage .delivery-invoice-signoff {
						margin-top: 0.9rem !important;
						padding-top: 0.55rem !important;
					}

					.delivery-print-stage .delivery-invoice-totals > div {
						padding: 0.75rem 0.9rem !important;
					}

					.delivery-print-stage .delivery-invoice-grand-total strong {
						font-size: 1.1rem !important;
					}

					@page {
						size: Letter portrait;
						margin: 0.2in;
					}

					@media print {
						html, body {
							background: #fff !important;
						}

						body {
							padding: 0;
						}

						.delivery-print-stage {
							width: 8in;
							max-width: 8in;
							margin: 0;
						}

						.delivery-print-stage .delivery-invoice {
							page-break-inside: avoid;
						}
					}
				</style>
			</head>
			<body>
				<div class="delivery-print-stage">
					${invoiceTemplate.innerHTML}
				</div>
			</body>
			</html>
		`);
		printWindow.document.close();

		printWindow.onload = function() {
			setTimeout(function() {
				printWindow.focus();
				printWindow.print();
			}, 250);
		};
	}
</script>

<div class="modal fade" id="addSaleTransaction" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Add Sale Transaction</h4>
			</div>
			<div class="modal-body">
				<form class="form new-form" method="GET">
					<input type="hidden" name="add_new_product" value="add_new_product" />
					<input type="hidden" name="mainmenu" value="view_sale" />
					<input type="hidden" name="deliveryNo" value="<?php echo $deliveryNo; ?>" />
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />
					<input type="hidden" name="product_id" id="detail_product_id" value="" />

					<label for="detail_sale_date">Date:</label>
					<input type="date" class="form-control" id="detail_sale_date" name="sale_date" value="<?php echo $saleDate ? htmlspecialchars($saleDate) : date('Y-m-d'); ?>" required />

					<label for="detail_customer_name">Customer:</label>
					<input type="text" class="form-control" id="detail_customer_name" name="customer_name" value="<?php echo htmlspecialchars($customerName); ?>" required />

					<label for="detail_product_name">Product Name:</label>
					<input type="text" class="form-control" id="detail_product_name" name="product_name" list="detail_sold_product_suggestions" onblur="validateSoldDetailSelection(this)" required />
					<datalist id="detail_sold_product_suggestions">
						<?php foreach ($products as $product): ?>
							<option value="<?php echo htmlspecialchars($product['name']); ?>"></option>
						<?php endforeach; ?>
					</datalist>

					<label for="detail_quantity">Pcs/Kg:</label>
					<input type="number" step="0.01" min="0" class="form-control" id="detail_quantity" name="Quantity" value="" oninput="calculateDetailSaleTotal()" required />

					<label for="detail_product_price">Selling Price:</label>
					<input type="number" step="0.01" min="0" class="form-control" id="detail_product_price" name="product_price" value="" oninput="calculateDetailSaleTotal()" required />

					<label for="detail_total_sale_price">Total:</label>
					<input type="number" step="0.01" class="form-control" id="detail_total_sale_price" name="total_sale_price" value="" readonly />

					<label for="detail_sale_notes">Notes:</label>
					<input type="text" class="form-control" id="detail_sale_notes" name="sale_notes" value="" />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" value="Save">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
				</div>
				</form>
		</div>
	</div>
</div>

<div class="modal fade" id="addDeliveryExpense" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Add Linked Expense</h4>
			</div>
			<div class="modal-body">
				<form class="form new-form" method="GET">
					<input type="hidden" name="add_new_product" value="add_new_product" />
					<input type="hidden" name="mainmenu" value="view_sale" />
					<input type="hidden" name="deliveryNo" value="<?php echo $deliveryNo; ?>" />
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />

					<label for="linked_expense_date">Date:</label>
					<input type="date" class="form-control" id="linked_expense_date" name="expense_date" value="<?php echo htmlspecialchars($latestExpenseDate ?: ($saleDate ?: date('Y-m-d'))); ?>" required />

					<label for="linked_expense_category">Category:</label>
					<input type="text" class="form-control" id="linked_expense_category" name="expense_category" list="delivery_expense_category_options" required />
					<datalist id="delivery_expense_category_options">
						<?php foreach ($expenseCategories as $expenseCategory): ?>
							<option value="<?php echo htmlspecialchars($expenseCategory); ?>"></option>
						<?php endforeach; ?>
					</datalist>

					<label for="linked_expense_description">Description:</label>
					<input type="text" class="form-control" id="linked_expense_description" name="expense_description" value="" />

					<label for="linked_expense_amount">Amount:</label>
					<input type="number" step="0.01" min="0" class="form-control" id="linked_expense_amount" name="expense_amount" value="" required />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" value="Save">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
				</div>
				</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editDeliveryExpense" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Edit Linked Expense</h4>
			</div>
			<div class="modal-body">
				<form class="form new-form" method="GET">
					<input type="hidden" name="edit_product" value="edit_product" />
					<input type="hidden" name="mainmenu" value="view_sale" />
					<input type="hidden" name="deliveryNo" value="<?php echo $deliveryNo; ?>" />
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />
					<input type="hidden" name="expenseID" value="" />

					<label for="edit_expense_date">Date:</label>
					<input type="date" class="form-control" id="edit_expense_date" name="expense_date" value="" required />

					<label for="edit_expense_category">Category:</label>
					<input type="text" class="form-control" id="edit_expense_category" name="expense_category" list="delivery_expense_category_options" value="" required />

					<label for="edit_expense_description">Description:</label>
					<input type="text" class="form-control" id="edit_expense_description" name="expense_description" value="" required />

					<label for="edit_expense_amount">Amount:</label>
					<input type="number" step="0.01" class="form-control" id="edit_expense_amount" name="expense_amount" value="" required />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" name="save_changes" value="Save">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
				</div>
				</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editSaleDetails" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Edit Sale Record</h4>
			</div>
			<div class="modal-body">
				<form class="form new-form" method="GET">
					<input type="hidden" name="edit_product" value="edit_product" />
					<input type="hidden" name="mainmenu" value="view_sale" />
					<input type="hidden" name="deliveryNo" value="<?php echo $deliveryNo; ?>" />
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />
					<input type="hidden" name="ID" value="" />

					<label for="sale_date">Date:</label>
					<input type="date" class="form-control" id="sale_date" name="sale_date" value="" required />

					<label for="customer_name">Customer:</label>
					<input type="text" class="form-control" id="customer_name" name="customer_name" value="" required />

					<label for="product_name">Product Name:</label>
					<input type="text" class="form-control" id="product_name" name="product_name" value="" readonly />

					<label for="Quantity">Pcs/Kg:</label>
					<input type="number" step="0.01" class="form-control" id="Quantity" name="Quantity" value="" required />

					<label for="unit_price">Selling Price:</label>
					<input type="number" step="0.01" class="form-control" id="unit_price" name="unit_price" value="" required />

					<label for="total_sale_price">Total:</label>
					<input type="number" step="0.01" class="form-control" id="total_sale_price" name="total_sale_price" value="" readonly />

					<label for="sale_notes">Notes:</label>
					<input type="text" class="form-control" id="sale_notes" name="sale_notes" value="" />
				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" name="save_changes" value="Save">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
				</div>
				</form>
		</div>
	</div>
</div>
