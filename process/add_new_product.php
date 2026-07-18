<?php
	
	$productName = mysqli_real_escape_string($connectDB, trim($_GET['product_name'] ?? ''));
	$productType = mysqli_real_escape_string($connectDB, trim($_GET['product_type'] ?? ''));
	$productBaseUnit = junkshop_normalize_base_unit($_GET['product_base_unit'] ?? 'pc');
	$productPrice = mysqli_real_escape_string($connectDB,$_GET['product_price'] ?? 0);
	$sellingPrice = mysqli_real_escape_string($connectDB, $_GET['selling_price'] ?? 0);
	$alternateSellingPriceValue = (float) ($_GET['alternate_selling_price'] ?? 0);
	$stockLimit = mysqli_real_escape_string($connectDB, $_GET['stock_limit'] ?? 0);
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
	$productId = (int) ($_GET['product_id'] ?? 0);
	$targetActive = isset($_GET['target_active']) ? (int) $_GET['target_active'] : 1;
	$purchasesHasProductId = false;
	$checkProductIdColumn = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'Product_ID'");
	if ($checkProductIdColumn && $checkProductIdColumn->num_rows > 0) {
		$purchasesHasProductId = true;
	}
		
		
	
	
	switch($mainMenu){
		
		case "view_invoice":
			$invoiceNo = mysqli_real_escape_string($connectDB,$_GET['invoiceNo']);
			$quantity = mysqli_real_escape_string($connectDB,$_GET['Quantity']);
			$totalPrice = mysqli_real_escape_string($connectDB,$_GET['total_purchase_price']);
			$purchaseDate = junkshop_normalize_datetime($_GET['purchase_date'] ?? '');
			if ($purchasesHasProductId) {
				$sql_addNewTransaction = "INSERT INTO purchases (Product_ID, PurchaseDate, InvoiceNo, ProductName, Quantity, ProductPrice, TotalPurchasePrice) VALUES ('$productId','$purchaseDate','$invoiceNo','$productName','$quantity','$productPrice','$totalPrice')";
			} else {
				$sql_addNewTransaction = "INSERT INTO purchases VALUES (null,'$purchaseDate','$invoiceNo','$productName','$quantity','$productPrice','$totalPrice')";
			}
			if($connectDB->query($sql_addNewTransaction)){
					
				echo "<div class='alert alert-success' role='alert'>" . $productName . " has been successfully added</div>";
				
					
			}
			else
			{
				echo 'error in query';
					
			}
		break;

		case "view_expense":
			$expenseNo = mysqli_real_escape_string($connectDB, $_GET['expenseNo']);
			$expenseDate = mysqli_real_escape_string($connectDB, $_GET['expense_date']);
			$expenseCategory = mysqli_real_escape_string($connectDB, $_GET['expense_category']);
			$expenseDescription = mysqli_real_escape_string($connectDB, $_GET['expense_description'] ?? '');
			$expenseAmount = mysqli_real_escape_string($connectDB, $_GET['expense_amount']);

			$sql_addExpense = "INSERT INTO expenses (ExpenseNo, ExpenseDate, ExpenseCategory, Description, Amount) VALUES ('$expenseNo', '$expenseDate', '$expenseCategory', '$expenseDescription', '$expenseAmount')";
			if($connectDB->query($sql_addExpense)){
				echo "<div class='alert alert-success' role='alert'>Expense has been successfully added</div>";
			}
			else
			{
				echo 'error in query';
			}
		break;

		case "view_sale":
			if (isset($_GET['expense_category']) || isset($_GET['expense_amount'])) {
				$deliveryNo = mysqli_real_escape_string($connectDB, $_GET['deliveryNo']);
				$expenseDate = mysqli_real_escape_string($connectDB, $_GET['expense_date'] ?? date('Y-m-d'));
				$expenseCategory = mysqli_real_escape_string($connectDB, $_GET['expense_category'] ?? '');
				$expenseDescription = mysqli_real_escape_string($connectDB, $_GET['expense_description'] ?? '');
				$expenseAmount = mysqli_real_escape_string($connectDB, $_GET['expense_amount'] ?? 0);

				$getExpenseNo = $connectDB->query("SELECT ExpenseNo FROM expenses ORDER BY ExpenseNo DESC LIMIT 1");
				if ($getExpenseNo && $getExpenseNo->num_rows > 0) {
					$expenseRow = $getExpenseNo->fetch_assoc();
					$expenseNo = (int) $expenseRow['ExpenseNo'] + 1;
				} else {
					$expenseNo = 1;
				}

				$sql_addExpense = "INSERT INTO expenses (ExpenseDate, ExpenseNo, DeliveryNo, ExpenseCategory, Description, Amount) VALUES ('$expenseDate', '$expenseNo', '$deliveryNo', '$expenseCategory', '$expenseDescription', '$expenseAmount')";
				if($connectDB->query($sql_addExpense)){
					echo "<div class='alert alert-success' role='alert'>Linked expense has been successfully added</div>";
				}
				else
				{
					echo 'error in query';
				}
			} else {
				$deliveryNo = mysqli_real_escape_string($connectDB, $_GET['deliveryNo']);
				$saleDate = mysqli_real_escape_string($connectDB, junkshop_normalize_datetime($_GET['sale_date'] ?? ''));
				$customerName = mysqli_real_escape_string($connectDB, $_GET['customer_name']);
				$saleNotes = mysqli_real_escape_string($connectDB, $_GET['sale_notes'] ?? '');
				$quantity = mysqli_real_escape_string($connectDB, $_GET['Quantity']);
				$totalPrice = mysqli_real_escape_string($connectDB, $_GET['total_sale_price']);

				$sql_addSale = "INSERT INTO sales (DeliveryNo, SaleDate, CustomerName, Product_ID, ProductName, Quantity, UnitPrice, Less, TotalSalePrice, Notes) VALUES ('$deliveryNo', '$saleDate', '$customerName', '$productId', '$productName', '$quantity', '$productPrice', '0', '$totalPrice', '$saleNotes')";
				if($connectDB->query($sql_addSale)){
					echo "<div class='alert alert-success' role='alert'>Sold/Delivery record has been successfully added</div>";
				}
				else
				{
					echo 'error in query';
				}
			}
		break;
		
		case "product_list":
			if (isset($_GET['toggle_product_status']) && $productId > 0) {
				$sql_toggleProduct = "UPDATE products SET IsActive = '$targetActive' WHERE Product_ID = '$productId'";
				if ($connectDB->query($sql_toggleProduct)) {
					$statusLabel = $targetActive === 1 ? 'active' : 'inactive';
					echo "<div class='alert alert-success' role='alert'>Product status updated to " . htmlspecialchars($statusLabel) . "</div>";
				} else {
					echo "<div class='alert alert-danger' role='alert'>Unable to update product status</div>";
				}
				break;
			}

			$sql_addProduct = "INSERT INTO products (ProductName, ProductType, ProductBaseUnit, ProductPrice, SellingPrice, AlternateSellingPrice, StockLimit, CanConvertToKg, KgEquivalentQty, ParentProduct_ID, IsSubProduct, IsActive) VALUES ('$productName','$productType','$productBaseUnit','$productPrice','$sellingPrice','$alternateSellingPrice','$stockLimit','$canConvertToKg','$kgEquivalentQty','$parentProductId','$isSubProduct','1')";
			if($connectDB->query($sql_addProduct)){
				if ($isSubProduct === 0) {
					$connectDB->query("INSERT INTO income (ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less) VALUES ('$productName', CURDATE(), CURDATE(), '$sellingPrice', 5.00) ON DUPLICATE KEY UPDATE SellingPrice = '$sellingPrice'");
				}
				echo "<div class='alert alert-success' role='alert'>" . htmlspecialchars($productName) . " has been successfully added</div>";
			}
			else
			{
				echo "<div class='alert alert-danger' role='alert'>Unable to add product. The product name may already exist.</div>";
			}
		break;
		
	}
?>
