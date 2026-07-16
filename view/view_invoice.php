<?php
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $searchInvoice = isset($_GET['searchInvoice']) ? $_GET['searchInvoice'] : '';
    $returnTo = isset($_GET['return_to']) ? $_GET['return_to'] : '?mainmenu=purchase_list';
    $encodedReturnTo = htmlspecialchars($returnTo, ENT_QUOTES);

    $allPurchases = "SELECT * FROM purchases WHERE InvoiceNo = ".$invoiceNo." ORDER BY ID DESC";
    $purchaseList = $connectDB->query($allPurchases);
	
	$products = [];
	$sql_selectProducts = "SELECT Product_ID AS id, ProductName AS name, ProductPrice AS price FROM products WHERE IsActive = 1 AND COALESCE(IsSubProduct, 0) = 0 ORDER BY ProductName ASC";
	$allProducts = $connectDB->query($sql_selectProducts);

	if ($allProducts->num_rows > 0) {
		while ($row = $allProducts->fetch_assoc()) {
			$products[] = $row;
		}
	}
	
	
?>
<div class="container-fluid" style="padding-bottom: 80px;">
	<h2>Invoice Number <?php echo $invoiceNo; ?></h2>

	<div class="table-card">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th scope="col">No</th>
						<th scope="col">Date & Time</th>
						<th scope="col">Product Name</th>
						<th scope="col" class="text-end">Pcs/Kg</th>
						<th scope="col" class="text-end">Purchase Price</th>
						<th scope="col" class="text-end">Total Purchase Price</th>
						<th scope="col" class="text-center">Actions</th>
					</tr>
				</thead>
				<tbody>

		<?php
			$invoiceTotalPrice = 0;
			$productCount = 0;
			while($Product = $purchaseList->fetch_assoc()){
				$productCount++;
				echo "<tr>";
					echo "<td>" . $productCount . "</td>";
					echo "<td>" . junkshop_format_datetime($Product['PurchaseDate']) . "</td>";
					echo "<td>" . $Product['ProductName'] . "</td>";
					echo "<td class='text-end'>" . number_format($Product['Quantity'], 2) . "</td>";
					echo "<td class='text-end'>&#8369;" . number_format($Product['ProductPrice'], 2) . "</td>";
					echo "<td class='text-end'>&#8369;" . number_format($Product['TotalPurchasePrice'], 2) . "</td>";
					echo "<td class='text-center'>";
					echo "<div class='icon-action-group justify-content-center'>";
					echo "<button data-toggle='modal' data-target='#editPurchaseProductDetails'
						 type='button'
						 class='icon-action-btn icon-action-btn-edit'
						 data-id='" . $Product['ID'] . "'
						 data-name='" . $Product['ProductName'] . "'
						 data-quantity='" . $Product['Quantity'] . "'
						 data-price='" . $Product['ProductPrice'] . "'
						 data-purchase-date='" . junkshop_datetime_input_value($Product['PurchaseDate']) . "'
						 aria-label='Edit " . htmlspecialchars($Product['ProductName'], ENT_QUOTES) . "'
						 title='Edit'>
						 <i class='bi bi-pencil-square' aria-hidden='true'></i>
						</button>";
					echo "<a href='" . $server . "?mainmenu=view_invoice&page=" . $page . "&searchInvoice=" . $searchInvoice . "&invoiceNo=". $invoiceNo ."&return_to=" . urlencode($returnTo) . "&delete_product=&ID=" . $Product['ID'] ." '
						 class='icon-action-btn icon-action-btn-delete'
						 onclick='return confirm(\"Do you want to delete this Product ? \")'
						 aria-label='Delete " . htmlspecialchars($Product['ProductName'], ENT_QUOTES) . "'
						 title='Delete'>
							  <i class='bi bi-trash3' aria-hidden='true'></i>
						</a>";
					echo "</div>";
					echo "</td>";
				echo "</tr>";
				
				$purchaseDate = $Product['PurchaseDate'];
				$productNames[] = $Product['ProductName'];
				$productQuantities[] = $Product['Quantity'];
				$productPrices[] = $Product['ProductPrice'];
				$TotalPurchasePrices[] = $Product['TotalPurchasePrice'];
				
				$invoiceTotalPrice = $invoiceTotalPrice + $Product['TotalPurchasePrice'];
			}
		?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<div class="row custom-row fixed-footer" >
    <div class="col-md-2 col-12 footer-action">
        <a class="btn btn-info form-control" href="<?php echo $encodedReturnTo; ?>">Back</a>
    </div>
	<div class="col-md-2 col-12 footer-action">
		<button data-toggle='modal' data-target='#addNewProduct' class='btn btn-outline-secondary form-control'>Add Item</button>
    </div>
	<div class="col-md-2 col-12 footer-action">
        <form class="pagebtn" action="" method="POST">
            <input type="hidden" name="mainmenu" value="purchase_invoice"/>
            <input type="hidden" name="previousmenu" value="view_invoice"/>
            <input type="hidden" name="invoiceNo" value="<?php echo $invoiceNo; ?>"/>
            <input type="hidden" name="purchase_date" value="<?php echo htmlspecialchars($purchaseDate ?? junkshop_normalize_datetime(''), ENT_QUOTES); ?>"/>
            <input type="hidden" name="product_name" value='<?php echo serialize($productNames); ?>' />
            <input type="hidden" name="product_quantity" value='<?php echo serialize($productQuantities); ?>' />
            <input type="hidden" name="product_price" value='<?php echo serialize($productPrices); ?>' />
            <input type="hidden" name="total_price" value='<?php echo serialize($TotalPurchasePrices); ?>' />
            <input type="hidden" name="grandTotal" value="<?php echo $invoiceTotalPrice; ?>"/>
            <input type="submit" name="BtnBack" class="btn btn-outline-secondary form-control" value="Print Receipt"/>
        </form>
    </div>
    <div class="col-md-2 col-6 footer-metric">
        <p><strong>Total Item:</strong><br><span class="totalprice"><?php echo $productCount; ?></span></p>
    </div>
    <div class="col-md-4 col-6 footer-metric">
        <p><strong>Total Purchase Price:</strong><br><span class="totalprice">&#8369;<?php echo number_format($invoiceTotalPrice, 2); ?></span></p>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#editPurchaseProductDetails').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var productId = button.data('id');
            var productName = button.data('name');
            var productQuantity = button.data('quantity');
            var productPrice = button.data('price');
            var purchaseDate = button.data('purchase-date');
            
            var modal = $(this);
            modal.find('input[name="ID"]').val(productId); // Set hidden input value
            modal.find('input[name="product_name"]').val(productName); // Set text input value
            modal.find('input[name="Quantity"]').val(productQuantity); // Set text input value
            modal.find('input[name="product_price"]').val(productPrice); // Set text input value
            modal.find('input[name="purchase_date"]').val(purchaseDate);

            // Calculate total purchase price initially
            var totalPurchasePrice = (productQuantity * productPrice).toFixed(2);
            modal.find('input[name="total_purchase_price"]').val(totalPurchasePrice); // Set total purchase price
        });

        // Update total purchase price automatically on input change inside modal
        $('#editPurchaseProductDetails').on('input', 'input[name="Quantity"], input[name="product_price"]', function () {
            var modal = $('#editPurchaseProductDetails'); // Get the modal
            var quantity = parseFloat(modal.find('input[name="Quantity"]').val()) || 0;
            var price = parseFloat(modal.find('input[name="product_price"]').val()) || 0;
            
            var totalPurchasePrice = (quantity * price).toFixed(2);
            modal.find('input[name="total_purchase_price"]').val(totalPurchasePrice);
        });
		
		$('#addNewProduct').on('input', 'input[name="Quantity"], input[name="product_price"]', function () {
            var modal = $('#addNewProduct'); // Get the modal
            var quantity = parseFloat(modal.find('input[name="Quantity"]').val()) || 0;
            var price = parseFloat(modal.find('input[name="product_price"]').val()) || 0;
            
            var totalPurchasePrice = (quantity * price).toFixed(2);
            modal.find('input[name="total_purchase_price"]').val(totalPurchasePrice);
        });
    });
</script>

<!-- Modal Edit -->
<div class="modal fade" id="editPurchaseProductDetails" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Purchase Product Details</h4>
            </div>
            <div class="modal-body">
            <form class="form new-form" method="GET">
                <input type="hidden" name="edit_product" value="" />
                <input type="hidden" name="mainmenu" value="view_invoice" />
                <input type="hidden" name="invoiceNo" value="<?php echo $invoiceNo; ?>" />
                <input type="hidden" name="edit_purchase_details" value="edit_purchase_details" />
                <input type="hidden" name="page" value="<?php echo $page; ?>" />
                <input type="hidden" name="searchInvoice" value="<?php echo $searchInvoice; ?>" />
                <input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />
                <input type="hidden" name="ID" value=""/> <!-- Hidden input for Product ID -->

                <label for="product_name">Product Name:</label>
                <input type="text" class="form-control" id="product_name" name="product_name" value="" readonly />

                <label for="purchase_date">Purchase Date & Time:</label>
                <input type="datetime-local" class="form-control" id="purchase_date" name="purchase_date" value="" required />

                <label for="Quantity">Pcs/Kg:</label>
                <input type="number" step="0.01" class="form-control" id="Quantity" name="Quantity" value="" required />
                
                <label for="product_price">Price:</label>
                <input type="number" step="0.01" class="form-control" id="product_price" name="product_price" value="" required />
                
                <label for="total_purchase_price">Total Purchase Price:</label>
                <input type="number" step="0.01" class="form-control" id="total_purchase_price" name="total_purchase_price" value="" readonly />
                
            </div>
            <div class="modal-footer">
                <input type="submit" class="btn btn-success" name="save_changes" value="Save">
                <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add New Product -->
<div class="modal fade" id="addNewProduct" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Transaction</h4>
            </div>
			<form class="form new-form" method="GET">
            <div class="modal-body">
                
                    <input type="hidden" name="mainmenu" value="view_invoice" />
					<input type="hidden" name="searchInvoice" value="<?php echo $searchInvoice; ?>"/>
					<input type="hidden" name="page" value="<?php echo $page; ?>"/>
					<input type="hidden" name="return_to" value="<?php echo $encodedReturnTo; ?>" />
					<input type="hidden" name="invoiceNo" value="<?php echo $invoiceNo; ?>" />
					<input type="hidden" name="purchase_date" value="<?php echo htmlspecialchars($purchaseDate ?? junkshop_normalize_datetime(''), ENT_QUOTES); ?>" />
                    
                    <label for="product_name">Product Name:</label>
					<input
                        type="text"
                        class="form-control"
                        name="product_name"
                        list="product_suggestions"
                        onblur="validateSelection(this)"
                        required
                    />
                    <input type="hidden" name="product_id" value="" />
                    <datalist id="product_suggestions">
                        <?php foreach ($products as $product): ?>
                            <option value="<?= htmlspecialchars($product['name']) ?>"
                                    data-id="<?= $product['id'] ?>"
                                    data-price="<?= $product['price'] ?>">
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    
                    <label for="Quantity">Pcs/Kg:</label>
                    <input type="number" step="0.01" class="form-control" name="Quantity" value="" required oninput="calculateTotal()" />
                    
                    <label for="product_price">Price:</label>
                    <input type="number" step="0.01" class="form-control" name="product_price" value="" required oninput="calculateTotal()" />
                    
                    <label for="total_purchase_price">Total Price:</label>
                    <input type="text" class="form-control" name="total_purchase_price" readonly />
                    
                
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-sm-6">
                        <input type="submit" class="btn btn-success form-control" name="add_new_product" value="Add Product">
                    </div>
                    <div class="col-sm-6">
                        <button type="button" class="btn btn-danger form-control" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
			</form>
        </div>
    </div>
</div>



<script>
	// Function to validate the selected product
// Function to validate the selected product
function validateSelection(inputElement) {
    const datalist = document.getElementById('product_suggestions');
    const options = Array.from(datalist.options);
    const valid = options.some(option => option.value === inputElement.value);

    if (!valid) {
        alert('Please select a valid product from the suggestions.');
        inputElement.value = ''; // Clear invalid input
        const modalContent = inputElement.closest('.modal-content');
        const productIdInput = modalContent.querySelector('input[name="product_id"]');
        if (productIdInput) {
            productIdInput.value = '';
        }
    } else {
        const selectedOption = options.find(option => option.value === inputElement.value);

        // Find the input field for price inside the same modal (related to the Product Name input)
        const modalContent = inputElement.closest('.modal-content');
        const priceInput = modalContent.querySelector('input[name="product_price"]');
        const productIdInput = modalContent.querySelector('input[name="product_id"]');
        if (priceInput) {
            // Populate the price input field with the selected product's price
            priceInput.value = selectedOption.getAttribute('data-price') || 0;
        }
        if (productIdInput) {
            productIdInput.value = selectedOption.getAttribute('data-id') || '';
        }
    }
}

</script>
