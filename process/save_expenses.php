<?php
	$expenseDate = mysqli_real_escape_string($connectDB, $_POST['expense_date'] ?? date('Y-m-d'));
	$expenseCategories = isset($_POST['expense_category']) ? $_POST['expense_category'] : [];
	$expenseDescriptions = isset($_POST['expense_description']) ? $_POST['expense_description'] : [];
	$expenseAmounts = isset($_POST['expense_amount']) ? $_POST['expense_amount'] : [];

	if (empty($expenseCategories) || empty($expenseAmounts)) {
		header("Location: " . $server . "?mainmenu=expenses");
		die();
	}

	$getExpenseNo = $connectDB->query("SELECT ExpenseNo FROM expenses ORDER BY ExpenseNo DESC LIMIT 1");
	if ($getExpenseNo && $getExpenseNo->num_rows > 0) {
		$expenseRow = $getExpenseNo->fetch_assoc();
		$expenseNo = (int) $expenseRow['ExpenseNo'] + 1;
	} else {
		$expenseNo = 1;
	}

	$totalSavedExpenseAmount = 0;
	foreach ($expenseCategories as $index => $expenseCategory) {
		$category = trim(mysqli_real_escape_string($connectDB, $expenseCategory));
		$description = trim(mysqli_real_escape_string($connectDB, $expenseDescriptions[$index] ?? ''));
		$amount = (float) ($expenseAmounts[$index] ?? 0);

		if ($category === '' || $amount <= 0) {
			continue;
		}

		$description = $description === '' ? $category : $description;
		$sql = "INSERT INTO expenses (ExpenseDate, ExpenseNo, ExpenseCategory, Description, Amount)
				VALUES ('$expenseDate', '$expenseNo', '$category', '$description', '$amount')";
		if ($connectDB->query($sql)) {
			$totalSavedExpenseAmount += $amount;
		}
	}

	header("Location: " . $server . "#dashboard-expenses");
	die();
?>
