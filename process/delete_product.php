<?php
	$product_id = mysqli_real_escape_string($connectDB, $_GET['productID'] ?? $_GET['ID'] ?? 0);
	$invoice_no = (int) ($_GET['invoiceNo'] ?? 0);
	$expense_no = (int) ($_GET['expenseNo'] ?? 0);
	$delivery_no = (int) ($_GET['deliveryNo'] ?? 0);
		
	
	switch($mainMenu){
	
		case "product_list":
	
			$sql_delete_product="DELETE FROM products WHERE Product_ID='$product_id'";
				
			if($connectDB->query($sql_delete_product))
			{
				echo "<div class='alert alert-success' role='alert'> Product has been successfully deleted</div>";
			}
			else{
				echo "<div class='alert alert-danger' role='alert'> Error in deleting the product</div>";
			}
			break;
			
		case "view_invoice":
			$connectDB->begin_transaction();

			try {
				$deleteResult = junkshop_delete_purchase_records($connectDB, "ID='$product_id'");
				if (!$deleteResult['success']) {
					throw new Exception($deleteResult['message']);
				}

				$connectDB->commit();
				echo "<div class='alert alert-success' role='alert'> Product has been successfully deleted</div>";
			} catch (Exception $exception) {
				$connectDB->rollback();
				$errorMessage = trim($exception->getMessage());
				if ($errorMessage === '') {
					$errorMessage = 'Error in deleting the product';
				}
				echo "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($errorMessage, ENT_QUOTES) . "</div>";
			}
			break;

		case "purchase_list":
			$connectDB->begin_transaction();

			try {
				$deleteResult = junkshop_delete_purchase_records($connectDB, "InvoiceNo='$invoice_no'");
				if (!$deleteResult['success']) {
					throw new Exception($deleteResult['message']);
				}

				$connectDB->commit();
				echo "<div class='alert alert-success' role='alert'> Purchase history has been successfully deleted</div>";
			} catch (Exception $exception) {
				$connectDB->rollback();
				$errorMessage = trim($exception->getMessage());
				if ($errorMessage === '') {
					$errorMessage = 'Error in deleting the purchase history';
				}
				echo "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($errorMessage, ENT_QUOTES) . "</div>";
			}
			break;

		case "view_expense":

			$sql_delete_product="DELETE FROM expenses WHERE ID='$product_id'";

			if($connectDB->query($sql_delete_product))
			{
				echo "<div class='alert alert-success' role='alert'> Expense has been successfully deleted</div>";
			}
			else{
				echo "<div class='alert alert-danger' role='alert'> Error in deleting the expense</div>";
			}
			break;

		case "expenses_list":

			$sql_delete_product="DELETE FROM expenses WHERE ExpenseNo='$expense_no'";

			if($connectDB->query($sql_delete_product))
			{
				echo "<div class='alert alert-success' role='alert'> Expense history has been successfully deleted</div>";
			}
			else{
				echo "<div class='alert alert-danger' role='alert'> Error in deleting the expense history</div>";
			}
			break;

		case "sales_list":
			$connectDB->begin_transaction();

			try {
				if (!$connectDB->query("DELETE FROM sale_app_links WHERE DeliveryNo='$delivery_no'")) {
					throw new Exception('app');
				}

				if (!$connectDB->query("DELETE FROM expenses WHERE DeliveryNo='$delivery_no'")) {
					throw new Exception('expense');
				}

				if (!$connectDB->query("DELETE FROM sales WHERE DeliveryNo='$delivery_no'")) {
					throw new Exception('sale');
				}

				junkshop_delete_sale_loan_records($connectDB, $delivery_no, true);

				$connectDB->commit();
				echo "<div class='alert alert-success' role='alert'> Sales history has been successfully deleted</div>";
			} catch (Exception $exception) {
				$connectDB->rollback();
				echo "<div class='alert alert-danger' role='alert'> Error in deleting the sold / delivery history</div>";
			}
			break;

		case "view_sale":
			if (isset($_GET['expenseID'])) {
				$expense_id = mysqli_real_escape_string($connectDB, $_GET['expenseID']);
				$sql_delete_product="DELETE FROM expenses WHERE ID='$expense_id'";

				if($connectDB->query($sql_delete_product))
				{
					echo "<div class='alert alert-success' role='alert'> Linked expense has been successfully deleted</div>";
				}
				else{
					echo "<div class='alert alert-danger' role='alert'> Error in deleting the linked expense</div>";
				}
				break;
			}

			$sale_details_result = $connectDB->query("SELECT DeliveryNo, ProductName FROM sales WHERE ID='$product_id' LIMIT 1");
			$sale_details = $sale_details_result ? $sale_details_result->fetch_assoc() : null;

			if (!$sale_details) {
				echo "<div class='alert alert-danger' role='alert'> Error in deleting the sold record</div>";
				break;
			}

			$sale_delivery_no = (int) ($sale_details['DeliveryNo'] ?? 0);
			$sale_product_name = mysqli_real_escape_string($connectDB, $sale_details['ProductName'] ?? '');

			$connectDB->begin_transaction();

			try {
				if (!$connectDB->query("DELETE FROM sale_app_links WHERE DeliveryNo='$sale_delivery_no' AND SaleProductName='$sale_product_name'")) {
					throw new Exception('app');
				}

				if (!$connectDB->query("DELETE FROM sales WHERE ID='$product_id'")) {
					throw new Exception('sale');
				}

				junkshop_sync_customer_account_for_delivery($connectDB, $sale_delivery_no);

				$connectDB->commit();
				echo "<div class='alert alert-success' role='alert'> Sold/Delivery record has been successfully deleted</div>";
			} catch (Exception $exception) {
				$connectDB->rollback();
				echo "<div class='alert alert-danger' role='alert'> Error in deleting the sold record</div>";
			}
			break;
	}
?>
