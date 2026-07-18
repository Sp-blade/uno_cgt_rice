<?php

	$product_id = mysqli_real_escape_string($connectDB, $_GET['productID'] ?? $_GET['ID'] ?? 0);
	$productPrice = mysqli_real_escape_string($connectDB, $_GET['product_price'] ?? $_GET['total_purchase_price'] ?? 0);
	$sellingPrice = mysqli_real_escape_string($connectDB, $_GET['selling_price'] ?? $_GET['product_price'] ?? 0);
	$alternateSellingPriceValue = (float) ($_GET['alternate_selling_price'] ?? 0);
	$stockLimit = mysqli_real_escape_string($connectDB, $_GET['stock_limit'] ?? 0);
	$productName = mysqli_real_escape_string($connectDB,$_GET['product_name'] ?? '');
	$productType = mysqli_real_escape_string($connectDB, $_GET['product_type'] ?? '');
	$productBaseUnit = junkshop_normalize_base_unit($_GET['product_base_unit'] ?? 'pc');
	$isSubProduct = 0;
	$parentProductId = 0;
	$canConvertToKg = isset($_GET['can_convert_to_kg']) ? 1 : 0;
	$kgEquivalentQty = (float) ($_GET['kg_equivalent_qty'] ?? 0);
	if (!junkshop_can_convert_units($productBaseUnit, $canConvertToKg, $kgEquivalentQty)) {
		$canConvertToKg = 0;
		$kgEquivalentQty = 0;
		$alternateSellingPriceValue = 0;
	}
	$productBaseUnit = mysqli_real_escape_string($connectDB, $productBaseUnit);
	$kgEquivalentQty = mysqli_real_escape_string($connectDB, number_format($kgEquivalentQty, 2, '.', ''));
	$alternateSellingPrice = mysqli_real_escape_string($connectDB, number_format($alternateSellingPriceValue, 2, '.', ''));
	$oldProductName = mysqli_real_escape_string($connectDB, $_GET['old_product_name'] ?? $productName);
	$purchasesHasProductId = false;
	$checkProductIdColumn = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'Product_ID'");
	if ($checkProductIdColumn && $checkProductIdColumn->num_rows > 0) {
		$purchasesHasProductId = true;
	}

	switch($mainMenu){

		case "product_list":

			$product_id = mysqli_real_escape_string($connectDB, $_GET['productID']);

			$sql_editProduct = "UPDATE products SET ProductType = '$productType', ProductBaseUnit = '$productBaseUnit', ProductPrice = '$productPrice', SellingPrice = '$sellingPrice', AlternateSellingPrice = '$alternateSellingPrice', StockLimit = '$stockLimit', CanConvertToKg = '$canConvertToKg', KgEquivalentQty = '$kgEquivalentQty', ParentProduct_ID = '$parentProductId', IsSubProduct = '$isSubProduct' WHERE Product_ID = '$product_id'";

			if ($connectDB->query($sql_editProduct)){
				if ($isSubProduct === 0) {
					$connectDB->query("INSERT INTO income (ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less) VALUES ('$productName', CURDATE(), CURDATE(), '$sellingPrice', 5.00) ON DUPLICATE KEY UPDATE SellingPrice = '$sellingPrice'");
				}
				echo "<div class='alert alert-success' role='alert'>Product details updated successfully</div>";
			}
			else
			{
				echo 'Error in Query';
			}
		break;

		case "view_invoice":

			$productTotalPrice = mysqli_real_escape_string($connectDB,$_GET['total_purchase_price']);
			$quantity = mysqli_real_escape_string($connectDB,$_GET['Quantity']);
			$purchaseDate = junkshop_normalize_datetime($_GET['purchase_date'] ?? '');
			$purchaseDate = mysqli_real_escape_string($connectDB, $purchaseDate);

			$sql_editProduct = "update purchases set PurchaseDate = '$purchaseDate', Quantity = '$quantity', ProductPrice = '$productPrice', TotalPurchasePrice = '$productTotalPrice'  where ID = '$product_id'";
				
			if ($connectDB->query($sql_editProduct)){
					
				echo "<div class='alert alert-success' role='alert'>" . $productName ." is successfully edited</div>";
				
			}
			else
			{
				echo 'Error in Query';
			}
		break;
			
		case "view_expense":

			$expenseDate = mysqli_real_escape_string($connectDB, $_GET['expense_date']);
			$expenseCategory = mysqli_real_escape_string($connectDB, $_GET['expense_category']);
			$expenseDescription = mysqli_real_escape_string($connectDB, $_GET['expense_description']);
			$expenseAmount = mysqli_real_escape_string($connectDB, $_GET['expense_amount']);

			$sql_editExpense = "UPDATE expenses SET ExpenseDate = '$expenseDate', ExpenseCategory = '$expenseCategory', Description = '$expenseDescription', Amount = '$expenseAmount' WHERE ID = '$product_id'";

			if ($connectDB->query($sql_editExpense)){
				echo "<div class='alert alert-success' role='alert'>Expense is successfully edited</div>";
			}
			else
			{
				echo 'Error in Query';
			}

		break;

		case "view_sale":
			if (isset($_GET['expenseID'])) {
				$expenseId = mysqli_real_escape_string($connectDB, $_GET['expenseID']);
				$expenseDate = mysqli_real_escape_string($connectDB, $_GET['expense_date']);
				$expenseCategory = mysqli_real_escape_string($connectDB, $_GET['expense_category']);
				$expenseDescription = mysqli_real_escape_string($connectDB, $_GET['expense_description']);
				$expenseAmount = mysqli_real_escape_string($connectDB, $_GET['expense_amount']);

				$sql_editExpense = "UPDATE expenses SET ExpenseDate = '$expenseDate', ExpenseCategory = '$expenseCategory', Description = '$expenseDescription', Amount = '$expenseAmount' WHERE ID = '$expenseId'";

				if ($connectDB->query($sql_editExpense)){
					echo "<div class='alert alert-success' role='alert'>Linked expense is successfully edited</div>";
				}
				else
				{
					echo 'Error in Query';
				}

				break;
			}

			$saleDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($_GET['sale_date'] ?? ''));
			$deliveryNo = mysqli_real_escape_string($connectDB, $_GET['deliveryNo'] ?? 0);
			$customerName = mysqli_real_escape_string($connectDB, $_GET['customer_name']);
			$quantity = mysqli_real_escape_string($connectDB, $_GET['Quantity']);
			$unitPrice = mysqli_real_escape_string($connectDB, $_GET['unit_price']);
			$totalSalePrice = mysqli_real_escape_string($connectDB, $_GET['total_sale_price']);
			$saleUnit = junkshop_normalize_base_unit($_GET['sale_unit'] ?? 'pc');
			$kgConversionQtyForSale = (float) ($_GET['kg_conversion_qty'] ?? 0);
			if ($saleUnit === 'pc') {
				$kgConversionQtyForSale = 0;
			}
			$saleUnit = mysqli_real_escape_string($connectDB, $saleUnit);
			$kgConversionQtyForSale = mysqli_real_escape_string($connectDB, number_format($kgConversionQtyForSale, 2, '.', ''));
			$saleNotes = mysqli_real_escape_string($connectDB, $_GET['sale_notes']);

			$sql_editSale = "UPDATE sales SET SaleDate = '$saleDate', CustomerName = '$customerName', Quantity = '$quantity', SaleUnit = '$saleUnit', KgConversionQty = '$kgConversionQtyForSale', UnitPrice = '$unitPrice', Less = '0', TotalSalePrice = '$totalSalePrice', Notes = '$saleNotes' WHERE ID = '$product_id'";

			if ($connectDB->query($sql_editSale)){
				$safeQuantity = (float) $quantity;
				$safeProductName = mysqli_real_escape_string($connectDB, $productName);
				$saleAppLinks = $connectDB->query("SELECT ID, AveragePurchasePrice FROM sale_app_links WHERE DeliveryNo = '$deliveryNo' AND SaleProductName = '$safeProductName'");
				if ($saleAppLinks && $saleAppLinks->num_rows > 0) {
					while ($saleAppLink = $saleAppLinks->fetch_assoc()) {
						$appLinkId = (int) ($saleAppLink['ID'] ?? 0);
						$averagePurchasePrice = (float) ($saleAppLink['AveragePurchasePrice'] ?? 0);
						$totalPurchasePrice = $safeQuantity * $averagePurchasePrice;
						$connectDB->query("UPDATE sale_app_links SET Quantity = '$safeQuantity', TotalPurchasePrice = '$totalPurchasePrice' WHERE ID = '$appLinkId'");
					}
				}
				echo "<div class='alert alert-success' role='alert'>Sold/Delivery record is successfully edited</div>";
			}
			else
			{
				echo 'Error in Query';
			}

		break;
	}
?>
