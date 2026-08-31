<?php

	$formInput = static function ($key, $default = '') {
		if (isset($_POST[$key])) {
			return $_POST[$key];
		}
		if (isset($_GET[$key])) {
			return $_GET[$key];
		}
		return $default;
	};
	$formInputIsset = static function ($key) {
		return isset($_POST[$key]) || isset($_GET[$key]);
	};
	$formCheckboxEnabled = static function ($key) use ($formInput, $formInputIsset) {
		if (!$formInputIsset($key)) {
			return 0;
		}

		$value = $formInput($key, '0');
		if (is_array($value)) {
			$value = end($value);
		}

		return (string) $value !== '0' && (string) $value !== '' ? 1 : 0;
	};

	$product_id = mysqli_real_escape_string($connectDB, $formInput('productID', $formInput('ID', 0)));
	$productPrice = mysqli_real_escape_string($connectDB, $formInput('product_price', $formInput('total_purchase_price', 0)));
	$sellingPrice = mysqli_real_escape_string($connectDB, $formInput('selling_price', 0));
	$alternateSellingPriceValue = (float) $formInput('alternate_selling_price', 0);
	$stockLimit = mysqli_real_escape_string($connectDB, $formInput('stock_limit', 0));
	$productName = mysqli_real_escape_string($connectDB, $formInput('product_name', ''));
	$productType = mysqli_real_escape_string($connectDB, $formInput('product_type', ''));
	$productBaseUnit = junkshop_normalize_base_unit($formInput('product_base_unit', 'pc'));
	$isSubProduct = 0;
	$parentProductId = 0;
	$canConvertToKg = $formCheckboxEnabled('can_convert_to_kg');
	$kgEquivalentQty = (float) $formInput('kg_equivalent_qty', 0);
	$alternateSaleUnitRaw = trim((string) $formInput('alternate_sale_unit', ''));
	$alternateSaleUnit = $alternateSaleUnitRaw !== '' ? junkshop_normalize_base_unit($alternateSaleUnitRaw) : '';
	if ($alternateSaleUnit === junkshop_normalize_base_unit($productBaseUnit)) {
		$alternateSaleUnit = '';
	}
	if (!junkshop_can_convert_units($productBaseUnit, $canConvertToKg, $kgEquivalentQty, $alternateSaleUnit)) {
		$canConvertToKg = 0;
		$kgEquivalentQty = 0;
		$alternateSellingPriceValue = 0;
		$alternateSaleUnit = '';
	}
	$productBaseUnit = mysqli_real_escape_string($connectDB, $productBaseUnit);
	$alternateSaleUnit = mysqli_real_escape_string($connectDB, $alternateSaleUnit);
	$kgEquivalentQty = mysqli_real_escape_string($connectDB, number_format($kgEquivalentQty, 2, '.', ''));
	$alternateSellingPrice = mysqli_real_escape_string($connectDB, number_format($alternateSellingPriceValue, 2, '.', ''));
	$lpgRefillPriceValue = round((float) $formInput('lpg_refill_price', 0), 2);
	$lpgNewTankPriceValue = round((float) $formInput('lpg_new_tank_price', 0), 2);
	$lpgRefillPrice = mysqli_real_escape_string($connectDB, number_format($lpgRefillPriceValue, 2, '.', ''));
	$lpgNewTankPrice = mysqli_real_escape_string($connectDB, number_format($lpgNewTankPriceValue, 2, '.', ''));
	$isLpgProductType = strtoupper(trim((string) $formInput('product_type', ''))) === 'LPG';
	if ($isLpgProductType && $lpgRefillPriceValue > 0) {
		$sellingPrice = $lpgRefillPrice;
	}
	$oldProductName = mysqli_real_escape_string($connectDB, $formInput('old_product_name', $productName));
	$purchasesHasProductId = false;
	$checkProductIdColumn = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'Product_ID'");
	if ($checkProductIdColumn && $checkProductIdColumn->num_rows > 0) {
		$purchasesHasProductId = true;
	}

	switch($mainMenu){

		case "product_list":

			$product_id = (int) $formInput('productID', 0);
			$productName = trim((string) $formInput('product_name', ''));
			if ($product_id <= 0) {
				echo "<div class='alert alert-danger' role='alert'>Invalid product ID.</div>";
				break;
			}
			if ($productName === '') {
				echo "<div class='alert alert-danger' role='alert'>Product name is required.</div>";
				break;
			}

			$safeProductId = mysqli_real_escape_string($connectDB, (string) $product_id);
			$safeProductName = mysqli_real_escape_string($connectDB, $productName);
			$duplicateCheck = $connectDB->query("SELECT Product_ID FROM products WHERE ProductName = '$safeProductName' AND Product_ID <> '$safeProductId' LIMIT 1");
			if ($duplicateCheck && $duplicateCheck->num_rows > 0) {
				echo "<div class='alert alert-danger' role='alert'>Another product already uses that name. Choose a different product name.</div>";
				break;
			}

			$sql_editProduct = "UPDATE products SET ProductName = '$safeProductName', ProductType = '$productType', ProductBaseUnit = '$productBaseUnit', SellingPrice = '$sellingPrice', AlternateSellingPrice = '$alternateSellingPrice', LpgRefillPrice = '$lpgRefillPrice', LpgNewTankPrice = '$lpgNewTankPrice', StockLimit = '$stockLimit', CanConvertToKg = '$canConvertToKg', KgEquivalentQty = '$kgEquivalentQty', AlternateSaleUnit = '$alternateSaleUnit', ParentProduct_ID = '$parentProductId', IsSubProduct = '$isSubProduct' WHERE Product_ID = '$safeProductId'";

			if ($connectDB->query($sql_editProduct)){
				if ($isSubProduct === 0) {
					$incomeHasProductId = false;
					$incomeProductIdCheck = $connectDB->query("SHOW COLUMNS FROM income LIKE 'Product_ID'");
					if ($incomeProductIdCheck && $incomeProductIdCheck->num_rows > 0) {
						$incomeHasProductId = true;
					}
					if ($incomeHasProductId) {
						$connectDB->query("UPDATE income SET ProductName = '$safeProductName', SellingPrice = '$sellingPrice' WHERE Product_ID = '$safeProductId'");
						if ($connectDB->affected_rows === 0) {
							$connectDB->query("INSERT INTO income (Product_ID, ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less) VALUES ('$safeProductId', '$safeProductName', CURDATE(), CURDATE(), '$sellingPrice', 5.00) ON DUPLICATE KEY UPDATE Product_ID = '$safeProductId', SellingPrice = '$sellingPrice'");
						}
					} else {
						$connectDB->query("INSERT INTO income (ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less) VALUES ('$safeProductName', CURDATE(), CURDATE(), '$sellingPrice', 5.00) ON DUPLICATE KEY UPDATE SellingPrice = '$sellingPrice'");
					}
				}
				junkshop_sync_product_current_name($connectDB, $product_id, $productName);
				if ($isSubProduct === 0 && junkshop_product_is_lpg($connectDB, $product_id, $productName)) {
					$parentActive = 1;
					$activeResult = $connectDB->query("SELECT IsActive FROM products WHERE Product_ID = '$safeProductId' LIMIT 1");
					if ($activeResult && ($activeRow = $activeResult->fetch_assoc())) {
						$parentActive = (int) ($activeRow['IsActive'] ?? 1);
					}
					junkshop_ensure_lpg_tank_product($connectDB, $product_id, $productName, $productBaseUnit, $parentActive);
				}
				echo "<div class='alert alert-success' role='alert'>Product details updated successfully</div>";
			} else {
				echo "<div class='alert alert-danger' role='alert'>Unable to update product details. " . htmlspecialchars($connectDB->error) . "</div>";
			}
		break;

		case "view_invoice":

			$productTotalPrice = mysqli_real_escape_string($connectDB,$_GET['total_purchase_price']);
			$quantity = mysqli_real_escape_string($connectDB,$_GET['Quantity']);
			$purchaseDate = junkshop_normalize_datetime($_GET['purchase_date'] ?? '');
			$purchaseDate = mysqli_real_escape_string($connectDB, $purchaseDate);
			$connectDB->begin_transaction();

			try {
				$syncResult = junkshop_sync_purchase_inventory(
					$connectDB,
					(int) $product_id,
					(float) $quantity,
					(float) $productPrice,
					$purchaseDate
				);
				if (!$syncResult['success']) {
					throw new Exception($syncResult['message']);
				}

				$sql_editProduct = "update purchases set PurchaseDate = '$purchaseDate', Quantity = '$quantity', ProductPrice = '$productPrice', TotalPurchasePrice = '$productTotalPrice'  where ID = '$product_id'";

				if (!$connectDB->query($sql_editProduct)) {
					throw new Exception('Unable to update purchase record.');
				}

				$connectDB->commit();
				echo "<div class='alert alert-success' role='alert'>" . htmlspecialchars($productName) . " is successfully edited</div>";
			} catch (Exception $exception) {
				$connectDB->rollback();
				$errorMessage = trim($exception->getMessage());
				if ($errorMessage === '') {
					$errorMessage = 'Unable to update purchase record.';
				}
				echo "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($errorMessage, ENT_QUOTES) . "</div>";
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
