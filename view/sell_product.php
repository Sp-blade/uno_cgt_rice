<?php
	$salesMode = ($mainMenu === 'sell_product_lpg') ? 'LPG' : 'OTHERS';
	$isLpgSale = $salesMode === 'LPG';
	$productWhere = $isLpgSale
		? "AND (ProductType = 'LPG' OR ProductName LIKE '%LPG%' OR ProductName LIKE '%tank%')"
		: "AND NOT (ProductType = 'LPG' OR ProductName LIKE '%LPG%' OR ProductName LIKE '%tank%')";

	$products = [];
	$productResult = $connectDB->query("
		SELECT
			p.Product_ID AS id,
			p.ProductName AS name,
			COALESCE(NULLIF(p.SellingPrice, 0), p.ProductPrice) AS price,
			COALESCE(p.ProductBaseUnit, 'pc') AS unit,
			COALESCE(SUM(b.QuantityRemaining), 0) AS stock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
		WHERE p.IsActive = 1 $productWhere
		GROUP BY p.Product_ID, p.ProductName, p.SellingPrice, p.ProductPrice, p.ProductBaseUnit
		ORDER BY p.ProductName ASC
	");
	if ($productResult && $productResult->num_rows > 0) {
		while ($row = $productResult->fetch_assoc()) {
			$products[] = $row;
		}
	}
?>

<script>
	const saleProducts = <?php echo json_encode($products); ?>;
	const isLpgSale = <?php echo $isLpgSale ? 'true' : 'false'; ?>;
</script>

<form class="form new-form" method="POST" action="?mainmenu=sale_invoice" id="saleForm">
	<input type="hidden" name="sales_type" value="<?php echo $salesMode; ?>" />
	<input type="hidden" name="previousmenu" value="<?php echo $isLpgSale ? 'sell_product_lpg' : 'sell_product_others'; ?>" />
	<div class="container-fluid">
		<div class="dashboard-card">
			<div class="dashboard-list-head">
				<div>
					<p class="section-kicker">Sales workflow</p>
					<h3><?php echo $isLpgSale ? 'Sell Product (LPG)' : 'Sell Product (Others)'; ?></h3>
				</div>
				<div class="min-width">
					<label for="sale_date" class="form-label">Sale Date</label>
					<input type="date" class="form-control" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required />
				</div>
			</div>

			<div class="row custom-row margin-top">
				<div class="col-md-3">
					<label for="customer_name">Customer Full Name</label>
					<input type="text" class="form-control" id="customer_name" name="customer_name" required />
				</div>
				<div class="col-md-3">
					<label for="customer_address">Address</label>
					<input type="text" class="form-control" id="customer_address" name="customer_address" />
				</div>
				<div class="col-md-3">
					<label for="customer_google_map">Google Map</label>
					<input type="url" class="form-control" id="customer_google_map" name="customer_google_map" placeholder="https://maps.google.com/..." />
				</div>
				<div class="col-md-3">
					<label for="sale_notes">Notes</label>
					<input type="text" class="form-control" id="sale_notes" name="sale_notes" />
				</div>
			</div>

			<div class="row custom-row margin-top">
				<div class="col-md-4">
					<label for="payment_status">Payment Status</label>
					<select class="form-select" id="payment_status" name="payment_status" onchange="syncPaymentDefaults()">
						<option value="PAID">Paid</option>
						<option value="PARTIAL">Partial Payment</option>
						<option value="UNPAID">Unpaid / Loan</option>
					</select>
				</div>
				<div class="col-md-4">
					<label for="amount_paid">Amount Paid</label>
					<input type="number" step="0.01" min="0" class="form-control" id="amount_paid" name="amount_paid" value="0" />
				</div>
				<div class="col-md-4">
					<label for="due_date">Due Date</label>
					<input type="date" class="form-control" id="due_date" name="due_date" />
				</div>
			</div>

			<datalist id="sale_product_suggestions">
				<?php foreach ($products as $product): ?>
					<option
						value="<?php echo htmlspecialchars($product['name']); ?>"
						data-id="<?php echo (int) $product['id']; ?>"
						data-price="<?php echo htmlspecialchars((string) $product['price']); ?>"
						data-unit="<?php echo htmlspecialchars($product['unit']); ?>"
						data-stock="<?php echo htmlspecialchars((string) $product['stock']); ?>"
					></option>
				<?php endforeach; ?>
			</datalist>

			<div class="row custom-row transaction-entry-row sale-row margin-top">
				<div class="col-sm-1">
					<label>No</label><br>
					<label class="row-count-label">1</label>
				</div>
				<div class="col-sm-3">
					<label>Product Name</label>
					<input type="text" class="form-control" name="product_name[]" list="sale_product_suggestions" onblur="applyProductSelection(this)" required />
					<input type="hidden" name="product_id[]" value="" />
					<input type="hidden" name="sale_unit[]" value="pc" />
					<input type="hidden" name="kg_conversion_qty[]" value="0" />
					<small class="text-muted stock-note">Stock: 0.00</small>
				</div>
				<div class="col-sm-2">
					<label>Qty</label>
					<input type="number" step="0.01" min="0" class="form-control" name="product_quantity[]" required oninput="calculateSaleTotal()" />
				</div>
				<div class="col-sm-2">
					<label>Selling Price</label>
					<input type="number" step="0.01" min="0" class="form-control" name="product_price[]" required oninput="calculateSaleTotal()" />
				</div>
				<?php if ($isLpgSale): ?>
					<div class="col-sm-2">
						<label>Tank</label>
						<select class="form-select" name="lpg_transaction_type[]" onchange="calculateSaleTotal()">
							<option value="SWAPPED">Swapped</option>
							<option value="SOLD">Sold</option>
						</select>
					</div>
					<div class="col-sm-2">
						<label>Tank Payment</label>
						<input type="number" step="0.01" min="0" class="form-control" name="lpg_tank_payment[]" value="0" oninput="calculateSaleTotal()" />
					</div>
					<div class="col-sm-3">
						<label>Tank Condition</label>
						<input type="text" class="form-control" name="lpg_tank_condition[]" placeholder="For swapped tank" />
					</div>
				<?php else: ?>
					<input type="hidden" name="lpg_transaction_type[]" value="NONE" />
					<input type="hidden" name="lpg_tank_payment[]" value="0" />
					<input type="hidden" name="lpg_tank_condition[]" value="" />
				<?php endif; ?>
				<div class="col-sm-2">
					<label>Total Price</label>
					<input type="text" class="form-control" name="total_price[]" readonly />
				</div>
				<div class="col-sm-1 transaction-action-cell">
					<label class="visually-hidden">Delete</label>
					<div class="icon-action-group">
						<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteSaleRow(this)" aria-label="Delete sale row" title="Delete">
							<i class="bi bi-trash3" aria-hidden="true"></i>
						</button>
					</div>
				</div>
			</div>

			<div id="saleRows"></div>
		</div>
	</div>

	<div class="row custom-row fixed-footer">
		<div class="col-md-2 col-12 footer-action">
			<button type="button" class="btn btn-info w-100" id="addSaleRowButton">+ Add</button>
		</div>
		<div class="col-md-2 col-12 footer-action">
			<input type="hidden" id="saleGrandTotalInput" name="grandTotal" value="0.00" />
			<input type="submit" class="btn btn-success w-100" value="Continue">
		</div>
		<div class="col-md-2 col-6 footer-metric">
			<p class="metric-label">Item Count</p>
			<div class="totalprice"><span id="saleItemCount">1</span></div>
		</div>
		<div class="col-md-5 col-12 footer-metric">
			<p class="metric-label">Grand Total</p>
			<div class="totalprice">&#8369;<span id="saleGrandTotal">0.00</span></div>
		</div>
	</div>
</form>

<script>
	let saleRowCount = 2;

	function findProductByName(name) {
		return saleProducts.find(function(product) {
			return product.name === name;
		});
	}

	function applyProductSelection(input) {
		const row = input.closest('.sale-row');
		const product = findProductByName(input.value);
		if (!product) {
			row.querySelector('input[name="product_id[]"]').value = '';
			row.querySelector('.stock-note').textContent = 'Stock: 0.00';
			return;
		}
		row.querySelector('input[name="product_id[]"]').value = product.id;
		row.querySelector('input[name="product_price[]"]').value = Number(product.price || 0).toFixed(2);
		row.querySelector('input[name="sale_unit[]"]').value = product.unit || 'pc';
		row.querySelector('.stock-note').textContent = 'Stock: ' + Number(product.stock || 0).toFixed(2) + ' ' + String(product.unit || 'pc').toUpperCase();
		calculateSaleTotal();
	}

	function calculateSaleTotal() {
		let grandTotal = 0;
		const rows = document.querySelectorAll('.sale-row');
		rows.forEach(function(row) {
			const qty = parseFloat(row.querySelector('input[name="product_quantity[]"]').value) || 0;
			const price = parseFloat(row.querySelector('input[name="product_price[]"]').value) || 0;
			const tankType = row.querySelector('[name="lpg_transaction_type[]"]').value;
			const tankPayment = tankType === 'SOLD' ? (parseFloat(row.querySelector('[name="lpg_tank_payment[]"]').value) || 0) : 0;
			const baseTotal = qty * price;
			row.querySelector('input[name="total_price[]"]').value = baseTotal.toFixed(2);
			grandTotal += baseTotal + tankPayment;
		});
		document.getElementById('saleGrandTotal').textContent = grandTotal.toFixed(2);
		document.getElementById('saleGrandTotalInput').value = grandTotal.toFixed(2);
		if (document.getElementById('payment_status').value === 'PAID') {
			document.getElementById('amount_paid').value = grandTotal.toFixed(2);
		}
		updateSaleRowCounts();
	}

	function syncPaymentDefaults() {
		if (document.getElementById('payment_status').value === 'PAID') {
			calculateSaleTotal();
		}
	}

	function updateSaleRowCounts() {
		document.querySelectorAll('.sale-row').forEach(function(row, index) {
			row.querySelector('.row-count-label').textContent = index + 1;
		});
		document.getElementById('saleItemCount').textContent = document.querySelectorAll('.sale-row').length;
	}

	function deleteSaleRow(button) {
		const rows = document.querySelectorAll('.sale-row');
		if (rows.length <= 1) {
			return;
		}
		button.closest('.sale-row').remove();
		calculateSaleTotal();
	}

	document.getElementById('addSaleRowButton').addEventListener('click', function() {
		const firstRow = document.querySelector('.sale-row');
		const clone = firstRow.cloneNode(true);
		clone.querySelectorAll('input').forEach(function(input) {
			if (input.type === 'hidden' && input.name === 'sale_unit[]') {
				input.value = 'pc';
			} else if (input.type === 'hidden' && input.name === 'kg_conversion_qty[]') {
				input.value = '0';
			} else if (input.name === 'lpg_transaction_type[]') {
				input.value = isLpgSale ? 'SWAPPED' : 'NONE';
			} else if (input.name === 'lpg_tank_payment[]') {
				input.value = '0';
			} else {
				input.value = '';
			}
		});
		clone.querySelectorAll('select').forEach(function(select) {
			select.value = 'SWAPPED';
		});
		clone.querySelector('.stock-note').textContent = 'Stock: 0.00';
		document.getElementById('saleRows').appendChild(clone);
		updateSaleRowCounts();
		clone.querySelector('input[name="product_name[]"]').focus();
	});

	calculateSaleTotal();
</script>
