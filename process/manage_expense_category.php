<?php
	$categoryName = mysqli_real_escape_string($connectDB, trim($_GET['category_name'] ?? ''));
	$categoryId = (int) ($_GET['categoryID'] ?? 0);
	$targetActive = isset($_GET['target_active']) ? (int) $_GET['target_active'] : 1;

	if (isset($_GET['add_expense_category']) && $categoryName !== '') {
		$sql = "INSERT INTO expense_categories (CategoryName, IsActive) VALUES ('$categoryName', '1')";
		if ($connectDB->query($sql)) {
			echo "<div class='alert alert-success' role='alert'>" . htmlspecialchars($categoryName) . " has been successfully added</div>";
		} else {
			echo "<div class='alert alert-danger' role='alert'>Unable to add expense category</div>";
		}
	}

	if (isset($_GET['toggle_expense_category_status']) && $categoryId > 0) {
		$sql = "UPDATE expense_categories SET IsActive = '$targetActive' WHERE ID = '$categoryId'";
		if ($connectDB->query($sql)) {
			$statusLabel = $targetActive === 1 ? 'active' : 'inactive';
			echo "<div class='alert alert-success' role='alert'>Expense category status updated to " . htmlspecialchars($statusLabel) . "</div>";
		} else {
			echo "<div class='alert alert-danger' role='alert'>Unable to update expense category status</div>";
		}
	}
?>
