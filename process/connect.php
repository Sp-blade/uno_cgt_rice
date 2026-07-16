<?php
	date_default_timezone_set('Asia/Manila');

	$hosts = array("127.0.0.1", "localhost");
	$user = "root";
	$pass = "";
	$db = "unocgtricedb";
	
	$connectDB = null;
	$lastConnectionError = "";
	foreach ($hosts as $host) {
		$connectDB = @new mysqli($host, $user, $pass);
		if (!$connectDB->connect_error) {
			break;
		}
		$lastConnectionError = $connectDB->connect_error;
		$connectDB = null;
	}
	if (!$connectDB){
		die("Database connection failed. Please check XAMPP MySQL/MariaDB user access. Last error: " . $lastConnectionError);
	}
	$connectDB->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
	$connectDB->select_db($db);
	$connectDB->query("SET time_zone = '+08:00'");

	if (!function_exists('junkshop_normalize_datetime')) {
		function junkshop_normalize_datetime($value) {
			$value = trim((string) $value);
			if ($value === '') {
				return date('Y-m-d H:i:s');
			}

			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				return $value . ' ' . date('H:i:s');
			}

			$value = str_replace('T', ' ', $value);
			$timestamp = strtotime($value);
			if ($timestamp === false) {
				return date('Y-m-d H:i:s');
			}

			return date('Y-m-d H:i:s', $timestamp);
		}
	}

	if (!function_exists('junkshop_format_datetime')) {
		function junkshop_format_datetime($value) {
			$timestamp = strtotime((string) $value);
			if ($timestamp === false) {
				return '';
			}

			return date('M-d-Y h:i A', $timestamp);
		}
	}

	if (!function_exists('junkshop_datetime_input_value')) {
		function junkshop_datetime_input_value($value) {
			$timestamp = strtotime((string) $value);
			if ($timestamp === false) {
				$timestamp = time();
			}

			return date('Y-m-d\TH:i', $timestamp);
		}
	}

	$productsTableExists = false;
	$purchasesTableExists = false;
	$createProductsTableSql = "CREATE TABLE IF NOT EXISTS products (
		Product_ID int(11) NOT NULL AUTO_INCREMENT,
		ProductName varchar(255) NOT NULL,
		ProductType varchar(255) NOT NULL DEFAULT '',
		ProductBaseUnit varchar(20) NOT NULL DEFAULT 'pc',
		ProductPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		SellingPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		StockLimit decimal(12,2) NOT NULL DEFAULT 0.00,
		CanConvertToKg tinyint(1) NOT NULL DEFAULT 0,
		KgEquivalentQty decimal(12,2) NOT NULL DEFAULT 0.00,
		ParentProduct_ID int(11) NOT NULL DEFAULT 0,
		IsSubProduct tinyint(1) NOT NULL DEFAULT 0,
		IsActive tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (Product_ID),
		UNIQUE KEY ProductName (ProductName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createProductsTableSql);
	$productTypeColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'ProductType'");
	if (!$productTypeColumnCheck || $productTypeColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN ProductType varchar(255) NOT NULL DEFAULT '' AFTER ProductName");
	}
	$productBaseUnitColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'ProductBaseUnit'");
	if (!$productBaseUnitColumnCheck || $productBaseUnitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN ProductBaseUnit varchar(20) NOT NULL DEFAULT 'pc' AFTER ProductType");
	}
	$productSellingPriceColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'SellingPrice'");
	if (!$productSellingPriceColumnCheck || $productSellingPriceColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN SellingPrice decimal(12,2) NOT NULL DEFAULT 0.00 AFTER ProductPrice");
	}
	$productStockLimitColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'StockLimit'");
	if (!$productStockLimitColumnCheck || $productStockLimitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN StockLimit decimal(12,2) NOT NULL DEFAULT 0.00 AFTER SellingPrice");
	}
	$productCanConvertColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'CanConvertToKg'");
	if (!$productCanConvertColumnCheck || $productCanConvertColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN CanConvertToKg tinyint(1) NOT NULL DEFAULT 0 AFTER SellingPrice");
	}
	$productKgEquivalentColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'KgEquivalentQty'");
	if (!$productKgEquivalentColumnCheck || $productKgEquivalentColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN KgEquivalentQty decimal(12,2) NOT NULL DEFAULT 0.00 AFTER CanConvertToKg");
	}
	$productParentColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'ParentProduct_ID'");
	if (!$productParentColumnCheck || $productParentColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN ParentProduct_ID int(11) NOT NULL DEFAULT 0 AFTER KgEquivalentQty");
	}
	$productIsSubColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'IsSubProduct'");
	if (!$productIsSubColumnCheck || $productIsSubColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN IsSubProduct tinyint(1) NOT NULL DEFAULT 0 AFTER ParentProduct_ID");
	}
	$productIsActiveColumnCheck = $connectDB->query("SHOW COLUMNS FROM products LIKE 'IsActive'");
	if (!$productIsActiveColumnCheck || $productIsActiveColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE products ADD COLUMN IsActive tinyint(1) NOT NULL DEFAULT 1 AFTER IsSubProduct");
	}

	$createPurchasesTableSql = "CREATE TABLE IF NOT EXISTS purchases (
		ID int(11) NOT NULL AUTO_INCREMENT,
		PurchaseDate datetime NOT NULL,
		InvoiceNo int(11) NOT NULL,
		ProductName varchar(255) NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		ProductPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		TotalPurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Product_ID int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY (ID),
		KEY PurchaseDate (PurchaseDate),
		KEY InvoiceNo (InvoiceNo),
		KEY Product_ID (Product_ID)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createPurchasesTableSql);
	$purchaseDateColumnCheck = $connectDB->query("SHOW COLUMNS FROM purchases LIKE 'PurchaseDate'");
	if ($purchaseDateColumnCheck && $purchaseDateColumnCheck->num_rows > 0) {
		$purchaseDateColumn = $purchaseDateColumnCheck->fetch_assoc();
		if (strtolower((string) ($purchaseDateColumn['Type'] ?? '')) === 'date') {
			$connectDB->query("ALTER TABLE purchases MODIFY PurchaseDate datetime NOT NULL");
		}
	}

	$productsTableCheck = $connectDB->query("SHOW TABLES LIKE 'products'");
	if ($productsTableCheck && $productsTableCheck->num_rows > 0) {
		$productsTableExists = true;
	}
	$purchasesTableCheck = $connectDB->query("SHOW TABLES LIKE 'purchases'");
	if ($purchasesTableCheck && $purchasesTableCheck->num_rows > 0) {
		$purchasesTableExists = true;
	}

	$createIncomeTableSql = "CREATE TABLE IF NOT EXISTS income (
		ID int(11) NOT NULL AUTO_INCREMENT,
		ProductName varchar(255) NOT NULL,
		PurchaseDate_From date NOT NULL,
		PurchaseDate_To date NOT NULL,
		SellingPrice float NOT NULL DEFAULT 0,
		Less float NOT NULL DEFAULT 0,
		PRIMARY KEY (ID),
		UNIQUE KEY ProductName (ProductName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createIncomeTableSql);

	$createExpensesTableSql = "CREATE TABLE IF NOT EXISTS expenses (
		ID int(11) NOT NULL AUTO_INCREMENT,
		ExpenseDate date NOT NULL,
		ExpenseNo int(11) NOT NULL,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		ExpenseCategory varchar(255) NOT NULL,
		Description varchar(255) NOT NULL,
		Amount decimal(12,2) NOT NULL DEFAULT 0.00,
		PRIMARY KEY (ID),
		KEY ExpenseDate (ExpenseDate),
		KEY ExpenseNo (ExpenseNo),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createExpensesTableSql);
	$expensesDeliveryColumnCheck = $connectDB->query("SHOW COLUMNS FROM expenses LIKE 'DeliveryNo'");
	if (!$expensesDeliveryColumnCheck || $expensesDeliveryColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE expenses ADD COLUMN DeliveryNo int(11) NOT NULL DEFAULT 0 AFTER ExpenseNo");
		$connectDB->query("ALTER TABLE expenses ADD KEY DeliveryNo (DeliveryNo)");
	}

	$createExpenseCategoriesTableSql = "CREATE TABLE IF NOT EXISTS expense_categories (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CategoryName varchar(255) NOT NULL,
		IsActive tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (ID),
		UNIQUE KEY CategoryName (CategoryName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createExpenseCategoriesTableSql);
	$expenseCategoryIsActiveColumnCheck = $connectDB->query("SHOW COLUMNS FROM expense_categories LIKE 'IsActive'");
	if (!$expenseCategoryIsActiveColumnCheck || $expenseCategoryIsActiveColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE expense_categories ADD COLUMN IsActive tinyint(1) NOT NULL DEFAULT 1 AFTER CategoryName");
	}

	$createSalesTableSql = "CREATE TABLE IF NOT EXISTS sales (
		ID int(11) NOT NULL AUTO_INCREMENT,
		SaleDate date NOT NULL,
		DeliveryNo int(11) NOT NULL,
		CustomerName varchar(255) NOT NULL,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		SaleUnit varchar(20) NOT NULL DEFAULT 'pc',
		KgConversionQty decimal(12,2) NOT NULL DEFAULT 0.00,
		UnitPrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Less decimal(12,2) NOT NULL DEFAULT 0.00,
		TotalSalePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Notes varchar(255) NOT NULL,
		SalesType enum('OTHERS','LPG') NOT NULL DEFAULT 'OTHERS',
		PaymentStatus enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID',
		AmountPaid decimal(12,2) NOT NULL DEFAULT 0.00,
		DueDate date DEFAULT NULL,
		LpgTransactionType enum('NONE','SWAPPED','SOLD') NOT NULL DEFAULT 'NONE',
		LpgTankCondition varchar(255) NOT NULL DEFAULT '',
		LpgTankPayment decimal(12,2) NOT NULL DEFAULT 0.00,
		IsReturned tinyint(1) NOT NULL DEFAULT 0,
		ReturnedAt datetime DEFAULT NULL,
		PRIMARY KEY (ID),
		KEY SaleDate (SaleDate),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createSalesTableSql);
	$salesLessColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'Less'");
	if (!$salesLessColumnCheck || $salesLessColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD COLUMN Less decimal(12,2) NOT NULL DEFAULT 0.00 AFTER UnitPrice");
	}
	$salesUnitColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'SaleUnit'");
	if (!$salesUnitColumnCheck || $salesUnitColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD COLUMN SaleUnit varchar(20) NOT NULL DEFAULT 'pc' AFTER Quantity");
	}
	$salesKgConversionColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE 'KgConversionQty'");
	if (!$salesKgConversionColumnCheck || $salesKgConversionColumnCheck->num_rows === 0) {
		$connectDB->query("ALTER TABLE sales ADD COLUMN KgConversionQty decimal(12,2) NOT NULL DEFAULT 0.00 AFTER SaleUnit");
	}
	$salesExtraColumns = [
		'SalesType' => "ALTER TABLE sales ADD COLUMN SalesType enum('OTHERS','LPG') NOT NULL DEFAULT 'OTHERS' AFTER Notes",
		'PaymentStatus' => "ALTER TABLE sales ADD COLUMN PaymentStatus enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID' AFTER Notes",
		'AmountPaid' => "ALTER TABLE sales ADD COLUMN AmountPaid decimal(12,2) NOT NULL DEFAULT 0.00 AFTER PaymentStatus",
		'DueDate' => "ALTER TABLE sales ADD COLUMN DueDate date DEFAULT NULL AFTER AmountPaid",
		'LpgTransactionType' => "ALTER TABLE sales ADD COLUMN LpgTransactionType enum('NONE','SWAPPED','SOLD') NOT NULL DEFAULT 'NONE' AFTER DueDate",
		'LpgTankCondition' => "ALTER TABLE sales ADD COLUMN LpgTankCondition varchar(255) NOT NULL DEFAULT '' AFTER LpgTransactionType",
		'LpgTankPayment' => "ALTER TABLE sales ADD COLUMN LpgTankPayment decimal(12,2) NOT NULL DEFAULT 0.00 AFTER LpgTankCondition",
		'IsReturned' => "ALTER TABLE sales ADD COLUMN IsReturned tinyint(1) NOT NULL DEFAULT 0 AFTER LpgTankPayment",
		'ReturnedAt' => "ALTER TABLE sales ADD COLUMN ReturnedAt datetime DEFAULT NULL AFTER IsReturned",
	];
	foreach ($salesExtraColumns as $columnName => $alterSql) {
		$salesColumnCheck = $connectDB->query("SHOW COLUMNS FROM sales LIKE '$columnName'");
		if (!$salesColumnCheck || $salesColumnCheck->num_rows === 0) {
			$connectDB->query($alterSql);
		}
	}

	$createInventoryBatchesTableSql = "CREATE TABLE IF NOT EXISTS inventory_batches (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		BatchDate datetime NOT NULL,
		BatchLabel enum('OLD','NEW') NOT NULL DEFAULT 'NEW',
		QuantityIn decimal(12,2) NOT NULL DEFAULT 0.00,
		QuantityRemaining decimal(12,2) NOT NULL DEFAULT 0.00,
		UnitCost decimal(12,2) NOT NULL DEFAULT 0.00,
		SourceType varchar(30) NOT NULL DEFAULT 'PURCHASE',
		SourceReference varchar(100) NOT NULL DEFAULT '',
		CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		KEY Product_ID (Product_ID),
		KEY ProductName (ProductName),
		KEY BatchDate (BatchDate),
		KEY BatchLabel (BatchLabel)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createInventoryBatchesTableSql);

	$createInventoryMovementsTableSql = "CREATE TABLE IF NOT EXISTS inventory_movements (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		Batch_ID int(11) NOT NULL DEFAULT 0,
		MovementDate datetime NOT NULL,
		MovementType enum('IN','OUT','RETURN') NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		ReferenceType varchar(30) NOT NULL DEFAULT '',
		ReferenceNo varchar(100) NOT NULL DEFAULT '',
		Notes varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (ID),
		KEY Product_ID (Product_ID),
		KEY Batch_ID (Batch_ID),
		KEY MovementDate (MovementDate),
		KEY MovementType (MovementType)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createInventoryMovementsTableSql);

	$createReturnsTableSql = "CREATE TABLE IF NOT EXISTS product_returns (
		ID int(11) NOT NULL AUTO_INCREMENT,
		Sale_ID int(11) NOT NULL DEFAULT 0,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		ReturnDate datetime NOT NULL,
		Product_ID int(11) NOT NULL DEFAULT 0,
		ProductName varchar(255) NOT NULL,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		Reason varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (ID),
		KEY Sale_ID (Sale_ID),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createReturnsTableSql);

	$createCustomerAccountsTableSql = "CREATE TABLE IF NOT EXISTS customer_accounts (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CustomerName varchar(255) NOT NULL,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		SaleDate date NOT NULL,
		DueDate date DEFAULT NULL,
		TotalAmount decimal(12,2) NOT NULL DEFAULT 0.00,
		AmountPaid decimal(12,2) NOT NULL DEFAULT 0.00,
		Balance decimal(12,2) NOT NULL DEFAULT 0.00,
		PaymentStatus enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID',
		UpdatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		UNIQUE KEY DeliveryNo (DeliveryNo),
		KEY CustomerName (CustomerName),
		KEY DueDate (DueDate),
		KEY PaymentStatus (PaymentStatus)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCustomerAccountsTableSql);

	$createCustomersTableSql = "CREATE TABLE IF NOT EXISTS customers (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CustomerName varchar(255) NOT NULL,
		Address varchar(255) NOT NULL DEFAULT '',
		GoogleMap varchar(500) NOT NULL DEFAULT '',
		CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UpdatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (ID),
		UNIQUE KEY CustomerName (CustomerName)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCustomersTableSql);
	$connectDB->query("
		INSERT IGNORE INTO customers (CustomerName)
		SELECT DISTINCT CustomerName
		FROM customer_accounts
		WHERE CustomerName <> ''
	");

	$createSaleAppLinksTableSql = "CREATE TABLE IF NOT EXISTS sale_app_links (
		ID int(11) NOT NULL AUTO_INCREMENT,
		DeliveryNo int(11) NOT NULL DEFAULT 0,
		SaleRowKey varchar(50) NOT NULL,
		SaleProductName varchar(255) NOT NULL,
		SourceProducts text NOT NULL,
		AveragePurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		Quantity decimal(12,2) NOT NULL DEFAULT 0.00,
		TotalPurchasePrice decimal(12,2) NOT NULL DEFAULT 0.00,
		PRIMARY KEY (ID),
		KEY DeliveryNo (DeliveryNo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createSaleAppLinksTableSql);

	$createCompanyProfileTableSql = "CREATE TABLE IF NOT EXISTS company_profile (
		ID int(11) NOT NULL AUTO_INCREMENT,
		CompanyName varchar(255) NOT NULL,
		AddressLine1 varchar(255) NOT NULL,
		AddressLine2 varchar(255) NOT NULL,
		ContactNumber varchar(255) NOT NULL,
		TinNumber varchar(255) NOT NULL,
		PRIMARY KEY (ID)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
	$connectDB->query($createCompanyProfileTableSql);

	$companyProfileCount = $connectDB->query("SELECT COUNT(*) AS total FROM company_profile");
	$companyProfileRowCount = $companyProfileCount ? (int) (($companyProfileCount->fetch_assoc()['total'] ?? 0)) : 0;
	if ($companyProfileRowCount === 0) {
		$connectDB->query("INSERT INTO company_profile (CompanyName, AddressLine1, AddressLine2, ContactNumber, TinNumber) VALUES ('UNO CGT Rice Trading', '170 R. Martinez Street, Brgy 9', 'Nasugbu, Batangas, 4231', '0921-500-6487', '220-138-725-000')");
	}

	if ($productsTableExists) {
		if ($purchasesTableExists) {
			$seedIncomeTableSql = "INSERT INTO income (ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less)
				SELECT
					p.ProductName,
					COALESCE(
						(SELECT MIN(PurchaseDate) FROM purchases pu WHERE pu.ProductName = p.ProductName),
						CURDATE()
					) AS PurchaseDate_From,
					COALESCE(
						(SELECT MAX(PurchaseDate) FROM purchases pu WHERE pu.ProductName = p.ProductName),
						CURDATE()
					) AS PurchaseDate_To,
					ROUND(p.ProductPrice * 1.20, 2) AS SellingPrice,
					5.00 AS Less
				FROM products p
				LEFT JOIN income i ON i.ProductName = p.ProductName
				WHERE i.ID IS NULL";
		} else {
			$seedIncomeTableSql = "INSERT INTO income (ProductName, PurchaseDate_From, PurchaseDate_To, SellingPrice, Less)
				SELECT
					p.ProductName,
					CURDATE() AS PurchaseDate_From,
					CURDATE() AS PurchaseDate_To,
					ROUND(p.ProductPrice * 1.20, 2) AS SellingPrice,
					5.00 AS Less
				FROM products p
				LEFT JOIN income i ON i.ProductName = p.ProductName
				WHERE i.ID IS NULL";
		}

		$connectDB->query($seedIncomeTableSql);
	}

	$connectDB->query("
		UPDATE products p
		LEFT JOIN income i ON i.ProductName = p.ProductName
		SET p.SellingPrice = CASE
			WHEN p.SellingPrice > 0 THEN p.SellingPrice
			WHEN i.SellingPrice IS NOT NULL AND i.SellingPrice > 0 THEN ROUND(i.SellingPrice, 2)
			ELSE ROUND(p.ProductPrice * 1.20, 2)
		END
		WHERE COALESCE(p.IsSubProduct, 0) = 0
	");

	$legacySoldProductsCheck = $connectDB->query("SHOW TABLES LIKE 'sold_products'");
	if ($legacySoldProductsCheck && $legacySoldProductsCheck->num_rows > 0) {
		$connectDB->query("
			INSERT INTO products (ProductName, ProductType, ProductBaseUnit, ProductPrice, SellingPrice, CanConvertToKg, KgEquivalentQty, ParentProduct_ID, IsSubProduct, IsActive)
			SELECT
				sp.ProductName,
				sp.ProductType,
				sp.ProductBaseUnit,
				0.00,
				sp.ProductPrice,
				sp.CanConvertToKg,
				sp.KgEquivalentQty,
				0,
				1,
				sp.IsActive
			FROM sold_products sp
			LEFT JOIN products p ON p.ProductName = sp.ProductName
			WHERE p.Product_ID IS NULL
		");
	}

	$seedExpenseCategoriesSql = "INSERT IGNORE INTO expense_categories (CategoryName) VALUES
		('Employee Salary'),
		('Gas'),
		('Delivery Expenses'),
		('LPG Handling'),
		('Cold Storage'),
		('Office Supplies'),
		('Electric Bill'),
		('Water Bill'),
		('Internet Bill'),
		('Others')";
	$connectDB->query($seedExpenseCategoriesSql);
	$connectDB->query("UPDATE products SET ProductType = 'Rice' WHERE ProductType = '' AND (ProductName LIKE '%rice%' OR ProductName LIKE '%bigas%' OR ProductName LIKE '%dinorado%' OR ProductName LIKE '%sinandomeng%')");
	$connectDB->query("UPDATE products SET ProductType = 'LPG' WHERE ProductType = '' AND (ProductName LIKE '%lpg%' OR ProductName LIKE '%gasul%' OR ProductName LIKE '%tank%')");
	$connectDB->query("UPDATE products SET ProductType = 'Frozen Foods' WHERE ProductType = '' AND (ProductName LIKE '%frozen%' OR ProductName LIKE '%hotdog%' OR ProductName LIKE '%tocino%' OR ProductName LIKE '%longganisa%')");
	$connectDB->query("UPDATE products SET ProductType = 'Eggs' WHERE ProductType = '' AND ProductName LIKE '%egg%'");

	$companyProfileResult = $connectDB->query("SELECT * FROM company_profile ORDER BY ID ASC LIMIT 1");
	$companyProfile = $companyProfileResult && $companyProfileResult->num_rows > 0
		? $companyProfileResult->fetch_assoc()
		: [
			'ID' => 1,
			'CompanyName' => "UNO CGT Rice Trading",
			'AddressLine1' => '170 R. Martinez Street, Brgy 9',
			'AddressLine2' => 'Nasugbu, Batangas, 4231',
			'ContactNumber' => '0921-500-6487',
			'TinNumber' => '220-138-725-000',
		];

	$companyProfileId = (int) ($companyProfile['ID'] ?? 1);
	$companyName = trim((string) ($companyProfile['CompanyName'] ?? 'UNO CGT Rice Trading'));
	$companyAddressLine1 = trim((string) ($companyProfile['AddressLine1'] ?? ''));
	$companyAddressLine2 = trim((string) ($companyProfile['AddressLine2'] ?? ''));
	$companyContactNumber = trim((string) ($companyProfile['ContactNumber'] ?? ''));
	$companyTinNumber = trim((string) ($companyProfile['TinNumber'] ?? ''));

	if (!function_exists('junkshop_company_initials')) {
		function junkshop_company_initials($name) {
			$cleanName = preg_replace('/[^A-Za-z0-9 ]+/', '', (string) $name);
			$parts = preg_split('/\s+/', trim($cleanName));
			$initials = '';
			foreach ($parts as $part) {
				if ($part === '') {
					continue;
				}
				$initials .= strtoupper(substr($part, 0, 1));
				if (strlen($initials) >= 2) {
					break;
				}
			}
			return $initials !== '' ? $initials : 'RT';
		}
	}

	$companyInitials = junkshop_company_initials($companyName);

	$server = "/uno_cgt_rice/";
?>
