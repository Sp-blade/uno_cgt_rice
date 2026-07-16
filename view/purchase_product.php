<?php

$products = [];
$sql_selectProducts = "SELECT Product_ID AS id, ProductName AS name, ProductPrice AS price FROM products WHERE IsActive = 1 AND COALESCE(IsSubProduct, 0) = 0 ORDER BY ProductName ASC";
$allProducts = $connectDB->query($sql_selectProducts);

if ($allProducts->num_rows > 0) {
    while ($row = $allProducts->fetch_assoc()) {
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
					<h3>Record purchased products</h3>
				</div>
				<div class="min-width">
					<label for="purchase_date" class="form-label">Purchase Date & Time</label>
					<input type="datetime-local" class="form-control" id="purchase_date" name="purchase_date" value="<?= date('Y-m-d\TH:i') ?>" required />
				</div>
			</div>
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
									data-price="<?= $product['price'] ?>">
							</option>
						<?php endforeach; ?>
					</datalist>
				</div>
				<div class="col-sm-2">
					<label for="product_quantity">Pcs/Kg:</label>
					<input type="number" step="0.01" class="form-control" name="product_quantity[]" value="" required oninput="calculateTotal()" />
				</div>
				<div class="col-sm-2">
					<label for="product_price">Price:</label>
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
				
			<input type="submit" class="btn btn-success w-100" name="purchase" value="Continue">
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
   let rowCount = 2; // 

document.getElementById('addFieldButton').addEventListener('click', function() {
    const container = document.getElementById('dynamicFields');
    const newRow = document.createElement('div');
    newRow.className = 'row mt-3 transaction-entry-row purchase-row';

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
        <label for="product_quantity">Pcs/Kg:</label>
        <input type="number" step="0.01" class="form-control" name="product_quantity[]" value="" required oninput="calculateTotal()" />
    `;

    // Price column
    const priceCol = document.createElement('div');
    priceCol.className = 'col-sm-2';
    priceCol.innerHTML = ` 
        <label for="product_price">Price:</label>
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

    // Append the new row to the container
    container.appendChild(newRow);

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
    const row = button.closest('.row');
    row.remove();
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
        inputElement.value = ''; // Clear invalid input
        const row = inputElement.closest('.row');
        const productIdInput = row.querySelector('input[name="product_id[]"]');
        if (productIdInput) {
            productIdInput.value = '';
        }
    } else {
        const selectedOption = options.find(option => option.value === inputElement.value);
        const row = inputElement.closest('.row');
        const priceInput = row.querySelector('input[name="product_price[]"]');
        const productIdInput = row.querySelector('input[name="product_id[]"]');
        priceInput.value = selectedOption.getAttribute('data-price') || 0;
        if (productIdInput) {
            productIdInput.value = selectedOption.getAttribute('data-id') || '';
        }
        calculateTotal();
    }
}

// Function to calculate the total amount for all rows
function calculateTotal() {
    let grandTotal = 0;
    const quantities = document.querySelectorAll('input[name="product_quantity[]"]');
    const prices = document.querySelectorAll('input[name="product_price[]"]');
    const totalPrices = document.querySelectorAll('input[name="total_price[]"]');

    for (let i = 0; i < quantities.length; i++) {
        const quantity = parseFloat(quantities[i].value) || 0;
        const price = parseFloat(prices[i].value) || 0;
        const total = quantity * price;
        totalPrices[i].value = total.toFixed(2);
        grandTotal += total;
    }

    // Update grand total in the footer
    document.getElementById('grandTotal').innerText = grandTotal.toFixed(2);
    document.getElementById('grandTotalInput').value = grandTotal.toFixed(2);
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
