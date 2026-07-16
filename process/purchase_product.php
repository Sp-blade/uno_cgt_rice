<?php
	$previousMenu = $_POST['previousmenu'];
	$productNames = isset($_POST['product_name']) ? $_POST['product_name'] : [];
	$productIds = isset($_POST['product_id']) ? $_POST['product_id'] : [];
	$productQuantities = isset($_POST['product_quantity']) ? $_POST['product_quantity'] : [];
	$productPrices = isset($_POST['product_price']) ? $_POST['product_price'] : [];
	$totalPrices = isset($_POST['total_price']) ? $_POST['total_price'] : [];
	$grandTotal = $_POST['grandTotal'];
	$purchaseDate = junkshop_normalize_datetime($_POST['purchase_date'] ?? '');
	$transactionType = isset($_POST['saveTransaction']) ? $_POST['saveTransaction'] : '';

	// Ensure that product-related data is arrays
	if (!is_array($productNames) || !is_array($productQuantities) || !is_array($productPrices) || !is_array($totalPrices)) {
		$productNames = unserialize($_POST['product_name']);
		$productIds = isset($_POST['product_id']) ? unserialize($_POST['product_id']) : [];
		$productQuantities = unserialize($_POST['product_quantity']);
		$productPrices = unserialize($_POST['product_price']);
		$totalPrices = unserialize($_POST['total_price']);
	}

	$purchasesHasProductId = false;
	$checkProductIdColumn = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'Product_ID'");
	if ($checkProductIdColumn && $checkProductIdColumn->num_rows > 0) {
		$purchasesHasProductId = true;
	}

	
	switch($previousMenu){
			
		case "purchase_product":
			$sql_getInvoice = "SELECT InvoiceNo FROM purchases ORDER BY InvoiceNo DESC LIMIT 1";
			$getInvoice = $connectDB->query($sql_getInvoice);
			
			if ($getInvoice->num_rows > 0) {
				$invoices = $getInvoice->fetch_assoc();
				$invoice = $invoices['InvoiceNo'];  // Get the last invoice number
				$invoice++;
			} else {
				$invoice = 1;
			}
		break;
		case "view_invoice":
			$invoice = $_POST['invoiceNo'];
		break;
	}
	
	if(array_key_exists('saveTransaction', $_POST)) {
            saveTransaction($connectDB,$server,$invoice,$previousMenu,$productNames,$productIds,$productQuantities,$productPrices,$purchaseDate,$totalPrices,$transactionType,$purchasesHasProductId);
        }
	
	function saveTransaction($connectDB,$server,$invoice,$previousMenu,$productNames,$productIds,$productQuantities,$productPrices,$purchaseDate,$totalPrices,$transactionType,$purchasesHasProductId){
		$totalInsertedPurchaseAmount = 0;
		foreach ($productNames as $index => $productName) {
			$productId = isset($productIds[$index]) ? (int) $productIds[$index] : 0;
			$productQuantity = isset($productQuantities[$index]) ? $productQuantities[$index] : 0;
			$productPrice = isset($productPrices[$index]) ? $productPrices[$index] : 0;
			$totalPrice = isset($totalPrices[$index]) ? $totalPrices[$index] : 0;

			// Prepare the SQL query to insert the product into the 'purchases' table
			if ($purchasesHasProductId) {
				$sql_purchaseProduct = "INSERT INTO purchases (Product_ID, PurchaseDate, InvoiceNo, ProductName, Quantity, ProductPrice, TotalPurchasePrice) VALUES ('$productId', '$purchaseDate', '$invoice', '$productName', '$productQuantity', '$productPrice', '$totalPrice')";
			} else {
				$sql_purchaseProduct = "INSERT INTO purchases VALUES (null,'$purchaseDate', '$invoice', '$productName', '$productQuantity', '$productPrice', '$totalPrice')";
			}

			// Execute the query
			if ($connectDB->query($sql_purchaseProduct)) {
				// Save succeeded; redirect happens after all rows are inserted.
				$totalInsertedPurchaseAmount += (float) $totalPrice;
				$safeProductName = mysqli_real_escape_string($connectDB, $productName);
				$safePurchaseDate = mysqli_real_escape_string($connectDB, $purchaseDate);
				$safeQuantity = mysqli_real_escape_string($connectDB, number_format((float) $productQuantity, 2, '.', ''));
				$safeProductPrice = mysqli_real_escape_string($connectDB, number_format((float) $productPrice, 2, '.', ''));
				$olderBatchCountResult = $connectDB->query("SELECT COUNT(*) AS total FROM inventory_batches WHERE Product_ID = '$productId' OR ProductName = '$safeProductName'");
				$olderBatchCount = $olderBatchCountResult ? (int) (($olderBatchCountResult->fetch_assoc()['total'] ?? 0)) : 0;
				$batchLabel = $olderBatchCount > 0 ? 'NEW' : 'OLD';
				$sourceReference = mysqli_real_escape_string($connectDB, "Invoice#$invoice");
				$connectDB->query("
					INSERT INTO inventory_batches (Product_ID, ProductName, BatchDate, BatchLabel, QuantityIn, QuantityRemaining, UnitCost, SourceType, SourceReference)
					VALUES ('$productId', '$safeProductName', '$safePurchaseDate', '$batchLabel', '$safeQuantity', '$safeQuantity', '$safeProductPrice', 'PURCHASE', '$sourceReference')
				");
				$batchId = $connectDB->insert_id;
				$connectDB->query("
					INSERT INTO inventory_movements (Product_ID, ProductName, Batch_ID, MovementDate, MovementType, Quantity, ReferenceType, ReferenceNo, Notes)
					VALUES ('$productId', '$safeProductName', '$batchId', '$safePurchaseDate', 'IN', '$safeQuantity', 'PURCHASE', '$sourceReference', '$batchLabel stock received')
				");
			} else {
				// If there is an error, output the error
				echo 'Error in query: ' . $connectDB->error;
			}
		}

		if($transactionType == "Edit"){
		
			header("Location: " . $server . "?mainmenu=view_invoice&invoiceNo=" . $invoice );
			die();
			
		}
		else
		{
			header("Location: " . $server . "?mainmenu=" . $previousMenu );
			die();
		}
		
	}
?>
