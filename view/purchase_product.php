<?php

$products = [];
$sql_selectProducts = "
	SELECT
		p.Product_ID AS id,
		p.ProductName AS name,
		COALESCE(p.ProductType, '') AS type,
		COALESCE(p.ProductBaseUnit, 'pc') AS unit,
		COALESCE((
			SELECT pu.ProductPrice
			FROM purchases pu
			WHERE pu.Product_ID = p.Product_ID
			ORDER BY pu.PurchaseDate DESC, pu.ID DESC
			LIMIT 1
		), 0) AS price
	FROM products p
	WHERE p.IsActive = 1 AND COALESCE(p.IsSubProduct, 0) = 0
	ORDER BY p.ProductName ASC
";
$allProducts = $connectDB->query($sql_selectProducts);

if ($allProducts->num_rows > 0) {
    while ($row = $allProducts->fetch_assoc()) {
        $row['unit'] = junkshop_normalize_base_unit($row['unit'] ?? 'pc');
        $products[] = $row;
    }
}

// Handling the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_date = junkshop_normalize_datetime($_POST['purchase_date'] ?? '');
    $product_names = $_POST['product_name'] ?? [];
    $product_quantities = $_POST['product_quantity'] ?? [];
    $product_prices = $_POST['product_price'] ?? [];
    $grand_total = $_POST['grandTotal'] ?? 0.00;

    // Process the data as needed (e.g., store in the database)
    // For debugging purposes:
    error_log("Purchase Date: $purchase_date");
    error_log("Products: " . print_r($product_names, true));
    error_log("Quantities: " . print_r($product_quantities, true));
    error_log("Prices: " . print_r($product_prices, true));
    error_log("Grand Total: $grand_total");
}

$purchaseStatus = isset($_GET['purchase_status']) ? trim((string) $_GET['purchase_status']) : '';
?>

<script>
    const productData = <?php echo json_encode($products); ?>;
</script>
<form class="form new-form" method="POST" id="productForm">
	<div class="container-fluid">
		<div class="dashboard-card">
			<div class="dashboard-list-head">
				<div>
					<p class="section-kicker">Purchasing workflow</p>
					<h3>Purchase Stocks</h3>
				</div>
				<div class="min-width">
					<label for="purchase_date" class="form-label">Purchase Date & Time</label>
					<input type="datetime-local" class="form-control" id="purchase_date" name="purchase_date" value="<?= date('Y-m-d\TH:i') ?>" required />
				</div>
			</div>

			<?php if ($purchaseStatus === 'saved'): ?>
				<div class="alert alert-success purchase-stock-status-alert" role="alert">Stocks have been successfully added.</div>
			<?php elseif ($purchaseStatus === 'lpg_error'): ?>
				<div class="alert alert-danger purchase-stock-status-alert" role="alert"><?php echo htmlspecialchars(trim((string) ($_GET['purchase_error'] ?? 'Unable to save LPG stock in.'))); ?></div>
			<?php endif; ?>
			<div class="purchase-entry">
			<div class="row custom-row transaction-entry-row purchase-row">
				<div class="col-sm-1">
					<label>No</label><br>
					<label>1</label>
				</div>
				<div class="col-sm-4">
					<label for="product_name">Product Name:</label>
					<input 
						type="text" 
						class="form-control" 
						name="product_name[]" 
						list="product_suggestions" 
						onblur="validateSelection(this)" 
						required
					/>
					<input type="hidden" name="product_id[]" value="" />
					<datalist id="product_suggestions">
						<?php foreach ($products as $product): ?>
							<option value="<?= htmlspecialchars($product['name']) ?>" 
									data-id="<?= $product['id'] ?>" 
									data-price="<?= $product['price'] ?>"
									data-unit="<?= htmlspecialchars($product['unit']) ?>"
									data-type="<?= htmlspecialchars($product['type'] ?? '') ?>">
							</option>
						<?php endforeach; ?>
					</datalist>
				</div>
				<div class="col-sm-2">
					<label for="product_quantity" class="purchase-qty-label">Base Unit:</label>
					<input type="number" step="0.01" class="form-control" name="product_quantity[]" value="" required oninput="calculateTotal()" />
				</div>
				<div class="col-sm-2">
					<label for="product_price" class="purchase-price-label">Purchase Price:</label>
					<input type="number" step="0.01" class="form-control" name="product_price[]" value="" required oninput="calculateTotal()" />
				</div>
				<div class="col-sm-2">
					<label for="total_price">Total Price:</label>
					<input type="text" class="form-control" name="total_price[]" readonly />
				</div>
				<div class="col-sm-1 transaction-action-cell">
					<label class="visually-hidden" for="delete">Delete</label>
					<div class="icon-action-group">
						<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteRow(this)" aria-label="Delete purchase row" title="Delete">
							<i class="bi bi-trash3" aria-hidden="true"></i>
						</button>
					</div>
				</div>
			</div>
			<div class="row custom-row g-3 lpg-purchase-options mt-2" style="display: none;">
				<div class="col-md-4">
					<label>LPG Stock In Type</label>
					<select class="form-select" name="lpg_stock_in_type[]" onchange="toggleLpgPurchaseOptions(this)">
						<option value="FULL_TANK">New Tank with LPG</option>
						<option value="REFILL">Refill Empty Tank</option>
					</select>
				</div>
				<div class="col-md-4 lpg-empty-condition-wrap" style="display: none;">
					<label>Empty Tank Used</label>
					<select class="form-select" name="lpg_empty_tank_condition[]">
						<option value="">Select condition</option>
						<option value="NEW">New</option>
						<option value="OLD">Old</option>
					</select>
				</div>
				<div class="col-md-4 lpg-tank-price-wrap" style="display: none;">
					<label>Tank Purchase Price</label>
					<input type="number" step="0.01" min="0" class="form-control" name="lpg_tank_purchase_price[]" value="0" oninput="calculateTotal()" />
					<small class="text-muted">Per tank. Inventory cost for swap sales uses refill price only.</small>
				</div>
			</div>
			</div>
			
			<div id="dynamicFields"></div>
		</div>
	</div>

	<div class="row custom-row fixed-footer">
		<div class="col-md-2 col-12 footer-action">
			<button type="button" class="btn btn-info w-100" id="addFieldButton">+ Add</button>
		</div>
		<div class="col-md-2 col-12 footer-action">
			<input type="hidden" id="grandTotalInput" name="grandTotal" value="0.00" />
			<input type="hidden" name="mainmenu" value="purchase_invoice" />
			<input type="hidden" name="previousmenu" value="purchase_product" />
				
			<input type="submit" class="btn btn-success w-100" name="saveTransaction" value="Save">
		</div>
		<div class="col-md-2 col-6 footer-metric">
			<p class="metric-label">Item Count</p>
			<div class="totalprice"><span id="purchaseItemCount">1</span></div>
		</div>
		<div class="col-md-5 col-12 footer-metric">
			<p class="metric-label">Grand Total</p>
			<div class="totalprice">&#8369;<span id="grandTotal">0.00</span></div>
		</div>
	</div>
</form>
<script>
   let rowCount = 2;

const purchaseUnitLabels = { pc: 'Pcs', kg: 'KG', sack: 'Sack', tray: 'Tray', pack: 'Pack', tank: 'Tank' };

function productIsLpgPurchase(product) {
    if (!product) return false;
    const type = String(product.type || '').toUpperCase();
    const name = String(product.name || '').toLowerCase();
    return type === 'LPG' || name.includes('lpg') || name.includes('gasul');
}

function updatePurchasePriceLabel(row, isLpgRefill) {
    if (!row) {
        return;
    }
    const label = row.querySelector('.purchase-price-label');
    if (label) {
        label.textContent = isLpgRefill ? 'Refill / LPG Price:' : 'Purchase Price:';
    }
}

function configureLpgPurchaseRow(row, product) {
    const entry = row.closest('.purchase-entry') || row;
    const panel = entry.querySelector('.lpg-purchase-options');
    if (!panel) return;
    const isLpg = productIsLpgPurchase(product);
    panel.style.display = isLpg ? 'flex' : 'none';
    const stockInSelect = entry.querySelector('select[name="lpg_stock_in_type[]"]');
    if (!isLpg) {
        if (stockInSelect) stockInSelect.value = 'FULL_TANK';
        const conditionSelect = entry.querySelector('select[name="lpg_empty_tank_condition[]"]');
        const tankPriceInput = entry.querySelector('input[name="lpg_tank_purchase_price[]"]');
        if (conditionSelect) conditionSelect.value = '';
        if (tankPriceInput) tankPriceInput.value = '0';
        updatePurchasePriceLabel(row, false);
        toggleLpgPurchaseOptions(stockInSelect);
    } else {
        updatePurchasePriceLabel(row, stockInSelect ? stockInSelect.value === 'REFILL' : false);
        toggleLpgPurchaseOptions(stockInSelect);
    }
}

function toggleLpgPurchaseOptions(select) {
    if (!select) return;
    const entry = select.closest('.purchase-entry');
    const wrap = entry ? entry.querySelector('.lpg-empty-condition-wrap') : null;
    const tankWrap = entry ? entry.querySelector('.lpg-tank-price-wrap') : null;
    const conditionSelect = entry ? entry.querySelector('select[name="lpg_empty_tank_condition[]"]') : null;
    const tankPriceInput = entry ? entry.querySelector('input[name="lpg_tank_purchase_price[]"]') : null;
    const purchaseRow = entry ? entry.querySelector('.purchase-row') : null;
    const isRefill = select.value === 'REFILL';
    const isFullTank = select.value === 'FULL_TANK';
    if (wrap) wrap.style.display = isRefill ? 'block' : 'none';
    if (tankWrap) tankWrap.style.display = isFullTank ? 'block' : 'none';
    if (conditionSelect) {
        conditionSelect.required = isRefill;
        if (!isRefill) conditionSelect.value = '';
    }
    if (tankPriceInput) {
        tankPriceInput.required = isFullTank;
        if (!isFullTank) tankPriceInput.value = '0';
    }
    if (purchaseRow) {
        updatePurchasePriceLabel(purchaseRow, isRefill);
    }
    calculateTotal();
}

function getPurchaseUnitLabel(unit) {
    const normalized = String(unit || 'pc').toLowerCase();
    return purchaseUnitLabels[normalized] || 'Base Unit';
}

function updatePurchaseQtyLabel(row, unit) {
    if (!row) {
        return;
    }
    const label = row.querySelector('.purchase-qty-label');
    if (label) {
        label.textContent = getPurchaseUnitLabel(unit) + ':';
    }
}

function resetPurchaseRow(row) {
    if (!row) {
        return;
    }
    updatePurchaseQtyLabel(row, 'pc');
    const productIdInput = row.querySelector('input[name="product_id[]"]');
    if (productIdInput) {
        productIdInput.value = '';
    }
    configureLpgPurchaseRow(row, null);
}

document.getElementById('addFieldButton').addEventListener('click', function() {
    const container = document.getElementById('dynamicFields');
    const entry = document.createElement('div');
    entry.className = 'purchase-entry mt-3';

    const newRow = document.createElement('div');
    newRow.className = 'row transaction-entry-row purchase-row';

    // Create a column for the row number
    const countCol = document.createElement('div');
    countCol.className = 'col-sm-1';
    countCol.innerHTML = `
		<br>
        <label class="rowCountLabel">${rowCount}</label> <!-- Display the current row count -->
    `;

    // Product Name column
    const nameCol = document.createElement('div');
    nameCol.className = 'col-sm-4';
    nameCol.innerHTML = ` 
        <label for="product_name">Product Name:</label>
        <input 
            type="text" 
            class="form-control" 
            name="product_name[]" 
            list="product_suggestions" 
            onblur="validateSelection(this)" 
            required
        />
        <input type="hidden" name="product_id[]" value="" />
    `;

    // Quantity column
    const quantityCol = document.createElement('div');
    quantityCol.className = 'col-sm-2';
    quantityCol.innerHTML = ` 
        <label for="product_quantity" class="purchase-qty-label">Base Unit:</label>
        <input type="number" step="0.01" class="form-control" name="product_quantity[]" value="" required oninput="calculateTotal()" />
    `;

    // Price column
    const priceCol = document.createElement('div');
    priceCol.className = 'col-sm-2';
    priceCol.innerHTML = ` 
        <label for="product_price" class="purchase-price-label">Purchase Price:</label>
        <input type="number" step="0.01" class="form-control" name="product_price[]" value="" required oninput="calculateTotal()" />
    `;

    // Total Price column
    const totalCol = document.createElement('div');
    totalCol.className = 'col-sm-2';
    totalCol.innerHTML = ` 
        <label for="total_price">Total Price:</label>
        <input type="text" class="form-control" name="total_price[]" readonly />
    `;

    // Delete button column
    const deleteCol = document.createElement('div');
    deleteCol.className = 'col-sm-1 transaction-action-cell';
    deleteCol.innerHTML = ` 
        <label class="visually-hidden" for="delete">Delete</label>
        <div class="icon-action-group">
            <button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteRow(this)" aria-label="Delete purchase row" title="Delete">
                <i class="bi bi-trash3" aria-hidden="true"></i>
            </button>
        </div>
    `;

    // Append all columns to the new row
    newRow.appendChild(countCol);
    newRow.appendChild(nameCol);
    newRow.appendChild(quantityCol);
    newRow.appendChild(priceCol);
    newRow.appendChild(totalCol);
    newRow.appendChild(deleteCol);

    const lpgRow = document.createElement('div');
    lpgRow.className = 'row custom-row g-3 lpg-purchase-options mt-2';
    lpgRow.style.display = 'none';
    lpgRow.innerHTML = `
        <div class="col-md-4">
            <label>LPG Stock In Type</label>
            <select class="form-select" name="lpg_stock_in_type[]" onchange="toggleLpgPurchaseOptions(this)">
                <option value="FULL_TANK">New Tank with LPG</option>
                <option value="REFILL">Refill Empty Tank</option>
            </select>
        </div>
        <div class="col-md-4 lpg-empty-condition-wrap" style="display: none;">
            <label>Empty Tank Used</label>
            <select class="form-select" name="lpg_empty_tank_condition[]">
                <option value="">Select condition</option>
                <option value="NEW">New</option>
                <option value="OLD">Old</option>
            </select>
        </div>
        <div class="col-md-4 lpg-tank-price-wrap" style="display: none;">
            <label>Tank Purchase Price</label>
            <input type="number" step="0.01" min="0" class="form-control" name="lpg_tank_purchase_price[]" value="0" oninput="calculateTotal()" />
            <small class="text-muted">Per tank. Inventory cost for swap sales uses refill price only.</small>
        </div>
    `;

    entry.appendChild(newRow);
    entry.appendChild(lpgRow);
    container.appendChild(entry);
    configureLpgPurchaseRow(newRow, null);

    // Focus on the first input of the newly added row
    newRow.querySelector('input[name="product_name[]"]').focus();

    // Increment the row count for the next row
    rowCount++;

    // Update the row count display in all rows
    updateRowCounts();
	scrollToBottom();
});

// Function to delete a row
function deleteRow(button) {
    const entry = button.closest('.purchase-entry');
    if (entry) {
        entry.remove();
    } else {
        button.closest('.row').remove();
    }
    calculateTotal();

    // Update row counts after deletion
    updateRowCounts();
}

// Function to update the row count displayed in each row
function updateRowCounts() {
    const rows = document.querySelectorAll('.row.mt-3');
    let count = 2;
    rows.forEach(row => {
        row.querySelector('.rowCountLabel').innerText = count;
        count++;
    });
    document.getElementById('purchaseItemCount').innerText = document.querySelectorAll('.purchase-row').length;
}

// Function to validate the selected product
function validateSelection(inputElement) {
    const datalist = document.getElementById('product_suggestions');
    const options = Array.from(datalist.options);
    const valid = options.some(option => option.value === inputElement.value);

    if (!valid) {
        alert('Please select a valid product from the suggestions.');
        inputElement.value = '';
        resetPurchaseRow(inputElement.closest('.row'));
    } else {
        const selectedOption = options.find(option => option.value === inputElement.value);
        const row = inputElement.closest('.row');
        const priceInput = row.querySelector('input[name="product_price[]"]');
        const productIdInput = row.querySelector('input[name="product_id[]"]');
        const selectedUnit = selectedOption.getAttribute('data-unit') || 'pc';
        priceInput.value = selectedOption.getAttribute('data-price') || '';
        if (productIdInput) {
            productIdInput.value = selectedOption.getAttribute('data-id') || '';
        }
        updatePurchaseQtyLabel(row, selectedUnit);
        const product = productData.find(item => item.name === inputElement.value);
        configureLpgPurchaseRow(row, product || null);
        calculateTotal();
    }
}

// Function to calculate the total amount for all rows
function calculateTotal() {
    let grandTotal = 0;
    const entries = document.querySelectorAll('.purchase-entry');

    entries.forEach(function (entry) {
        const quantityInput = entry.querySelector('input[name="product_quantity[]"]');
        const priceInput = entry.querySelector('input[name="product_price[]"]');
        const totalInput = entry.querySelector('input[name="total_price[]"]');
        const stockInSelect = entry.querySelector('select[name="lpg_stock_in_type[]"]');
        const tankPriceInput = entry.querySelector('input[name="lpg_tank_purchase_price[]"]');
        if (!quantityInput || !priceInput || !totalInput) {
            return;
        }

        const quantity = parseFloat(quantityInput.value) || 0;
        const refillPrice = parseFloat(priceInput.value) || 0;
        const tankPrice = stockInSelect && stockInSelect.value === 'FULL_TANK'
            ? (parseFloat(tankPriceInput?.value) || 0)
            : 0;
        const lineTotal = quantity * (refillPrice + tankPrice);
        totalInput.value = lineTotal.toFixed(2);
        grandTotal += lineTotal;
    });

    document.getElementById('grandTotal').innerText = grandTotal.toFixed(2);
    document.getElementById('grandTotalInput').value = grandTotal.toFixed(2);
    document.getElementById('purchaseItemCount').innerText = document.querySelectorAll('.purchase-row').length;
}

document.addEventListener('DOMContentLoaded', function() {
    // Prevent form submission on Enter key
    const form = document.getElementById('productForm');
    form.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();  // Prevent the form from submitting
            document.getElementById('addFieldButton').click();  // Trigger Add button click
        }
    });

    document.getElementById('purchaseItemCount').innerText = document.querySelectorAll('.purchase-row').length;
    calculateTotal();
});

function scrollToBottom() {
        window.scrollTo(0, document.body.scrollHeight);
    }

</script>
