<?php
	$saleId = (int) ($_GET['sale_id'] ?? 0);
	$returnReason = trim(mysqli_real_escape_string($connectDB, $_GET['return_reason'] ?? 'Returned item'));
	$returnTo = $_GET['return_to'] ?? '?mainmenu=sales_list';

	if ($saleId <= 0) {
		header("Location: " . $returnTo);
		die();
	}

	$saleResult = $connectDB->query("SELECT * FROM sales WHERE ID = '$saleId' LIMIT 1");
	if (!$saleResult || $saleResult->num_rows === 0) {
		header("Location: " . $returnTo);
		die();
	}

	$sale = $saleResult->fetch_assoc();
	if ((int) ($sale['IsReturned'] ?? 0) === 1) {
		header("Location: " . $returnTo);
		die();
	}

	$productId = (int) ($sale['Product_ID'] ?? 0);
	$productName = mysqli_real_escape_string($connectDB, $sale['ProductName'] ?? '');
	$quantity = mysqli_real_escape_string($connectDB, number_format((float) ($sale['Quantity'] ?? 0), 2, '.', ''));
	$deliveryNo = (int) ($sale['DeliveryNo'] ?? 0);
	$returnDate = date('Y-m-d H:i:s');

	$connectDB->query("
		INSERT INTO inventory_batches (Product_ID, ProductName, BatchDate, BatchLabel, QuantityIn, QuantityRemaining, UnitCost, SourceType, SourceReference)
		VALUES ('$productId', '$productName', '$returnDate', 'OLD', '$quantity', '$quantity', '0.00', 'RETURN', 'SaleID#$saleId')
	");
	$batchId = $connectDB->insert_id;
	$connectDB->query("
		INSERT INTO inventory_movements (Product_ID, ProductName, Batch_ID, MovementDate, MovementType, Quantity, ReferenceType, ReferenceNo, Notes)
		VALUES ('$productId', '$productName', '$batchId', '$returnDate', 'RETURN', '$quantity', 'RETURN', 'SaleID#$saleId', '$returnReason')
	");
	$connectDB->query("
		INSERT INTO product_returns (Sale_ID, DeliveryNo, ReturnDate, Product_ID, ProductName, Quantity, Reason)
		VALUES ('$saleId', '$deliveryNo', '$returnDate', '$productId', '$productName', '$quantity', '$returnReason')
	");
	$connectDB->query("UPDATE sales SET IsReturned = 1, ReturnedAt = '$returnDate' WHERE ID = '$saleId'");

	header("Location: " . $returnTo);
	die();
?>
