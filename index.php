<?php
	$mainMenu = isset($_POST['mainmenu']) ? $_POST['mainmenu'] : (isset($_GET['mainmenu']) ? $_GET['mainmenu'] : '');

	require "process/connect.php";

	if ($mainMenu === "save_expenses") {
		include "process/save_expenses.php";
		exit;
	}

	if ($mainMenu === "save_sales") {
		include "process/save_sales.php";
		exit;
	}

	if ($mainMenu === "save_company_profile") {
		include "process/save_company_profile.php";
		exit;
	}

	if ($mainMenu === "return_item") {
		include "process/return_item.php";
		exit;
	}

	if ($mainMenu === "restore_database" && $_SERVER['REQUEST_METHOD'] === 'POST') {
		include "process/restore_database.php";
		exit;
	}


	if ($mainMenu === "purchase_invoice" && isset($_POST['saveTransaction'])) {
		include "process/purchase_product.php";
		exit;
	}

	if ($mainMenu === "sale_invoice" && isset($_POST['saveTransaction'])) {
		include "process/save_sales.php";
		exit;
	}

	$downloadMenus = [
		'download_database_file' => 'process/download_database.php',
		'download_purchase_history' => 'process/download_purchase_history.php',
		'download_expenses' => 'process/download_expenses.php',
		'download_sales' => 'process/download_sales.php',
	];
	if (isset($downloadMenus[$mainMenu])) {
		include $downloadMenus[$mainMenu];
		exit;
	}

	require "view/head.php";

	$activeMenu = $mainMenu;
	$purchaseMenus = ['purchase_product', 'purchase_list', 'view_invoice', 'purchase_invoice', 'view_purchase_product'];
	$expenseMenus = ['expenses', 'expenses_list', 'view_expense'];
	$salesMenus = ['sell_product_others', 'sell_product_lpg', 'sales_list', 'view_sale', 'view_sale_product'];
	$inventoryMenus = ['inventory'];
	$customerMenus = ['customers'];
	$reportMenus = [];
	$settingsMenus = ['company_profile', 'product_list', 'download_database', 'expense_category_list', 'view_expense_category'];

	$pageTitles = [
		'' => 'Dashboard',
		'product_list' => 'Product List',
		'purchase_product' => 'Stock Purchase',
		'purchase_list' => 'Purchase History',
		'view_invoice' => 'Invoice Details',
		'view_purchase_product' => 'Stock Purchase Details',
		'expenses' => 'Expenses',
		'expenses_list' => 'Expenses History',
		'view_expense' => 'Expense Details',
		'expense_category_list' => 'Expense Categories',
		'view_expense_category' => 'Expense Category Details',
		'sell_product_others' => 'Sell Product (Others)',
		'sell_product_lpg' => 'Sell Product (LPG)',
		'sale_invoice' => 'Sale Receipt',
		'sales_list' => 'Sales History',
		'view_sale' => 'Sale Details',
		'view_sale_product' => 'Sold Product Details',
		'inventory' => 'Inventory Monitor',
		'company_profile' => 'Company Profile',
		'download_database' => 'Database Backup',
	];

	$currentTitle = isset($pageTitles[$activeMenu]) ? $pageTitles[$activeMenu] : 'Dashboard';
?>

<div class="app-shell">
	<aside class="app-sidebar">
		<div class="sidebar-brand">
			<div class="sidebar-brand-mark"><?php echo htmlspecialchars($companyInitials); ?></div>
			<div>
				<p class="sidebar-kicker">Operations Suite</p>
				<h1><?php echo htmlspecialchars($companyName); ?></h1>
			</div>
		</div>

		<div class="sidebar-surface">
			<nav class="nav flex-column app-nav">
				<a class="nav-link <?php echo ($activeMenu == '') ? 'active' : ''; ?>" href="?">
					<i class="bi bi-grid-1x2-fill"></i>
					<span>Dashboard</span>
				</a>

				<div class="nav-section">
					<button class="nav-link nav-section-toggle <?php echo in_array($activeMenu, $salesMenus) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#navSales" aria-expanded="<?php echo in_array($activeMenu, $salesMenus) ? 'true' : 'false'; ?>">
						<span><i class="bi bi-cart-check"></i> Sales</span>
						<i class="bi bi-chevron-down"></i>
					</button>
					<div id="navSales" class="collapse <?php echo in_array($activeMenu, $salesMenus) ? 'show' : ''; ?>">
						<div class="nav-submenu">
							<a class="sub-link <?php echo ($activeMenu == 'sell_product_others') ? 'active' : ''; ?>" href="?mainmenu=sell_product_others">Sell Product (Others)</a>
							<a class="sub-link <?php echo ($activeMenu == 'sell_product_lpg') ? 'active' : ''; ?>" href="?mainmenu=sell_product_lpg">Sell Product (LPG)</a>
							<a class="sub-link <?php echo (in_array($activeMenu, ['sales_list', 'view_sale'])) ? 'active' : ''; ?>" href="?mainmenu=sales_list">Sales History</a>
						</div>
					</div>
				</div>

				<div class="nav-section">
					<button class="nav-link nav-section-toggle <?php echo in_array($activeMenu, $purchaseMenus) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#navPurchases" aria-expanded="<?php echo in_array($activeMenu, $purchaseMenus) ? 'true' : 'false'; ?>">
						<span><i class="bi bi-bag-check"></i> Stock In</span>
						<i class="bi bi-chevron-down"></i>
					</button>
					<div id="navPurchases" class="collapse <?php echo in_array($activeMenu, $purchaseMenus) ? 'show' : ''; ?>">
						<div class="nav-submenu">
							<a class="sub-link <?php echo ($activeMenu == 'purchase_product') ? 'active' : ''; ?>" href="?mainmenu=purchase_product">Add Stock Purchase</a>
							<a class="sub-link <?php echo ($activeMenu == 'purchase_list') ? 'active' : ''; ?>" href="?mainmenu=purchase_list">Purchase History</a>
						</div>
					</div>
				</div>

				<div class="nav-section">
					<button class="nav-link nav-section-toggle <?php echo in_array($activeMenu, $expenseMenus) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#navExpenses" aria-expanded="<?php echo in_array($activeMenu, $expenseMenus) ? 'true' : 'false'; ?>">
						<span><i class="bi bi-receipt-cutoff"></i> Expenses</span>
						<i class="bi bi-chevron-down"></i>
					</button>
					<div id="navExpenses" class="collapse <?php echo in_array($activeMenu, $expenseMenus) ? 'show' : ''; ?>">
						<div class="nav-submenu">
							<a class="sub-link <?php echo ($activeMenu == 'expenses') ? 'active' : ''; ?>" href="?mainmenu=expenses">Add Expenses</a>
							<a class="sub-link <?php echo (in_array($activeMenu, ['expenses_list', 'view_expense'])) ? 'active' : ''; ?>" href="?mainmenu=expenses_list">Expenses History</a>
						</div>
					</div>
				</div>

				<a class="nav-link <?php echo in_array($activeMenu, $inventoryMenus) ? 'active' : ''; ?>" href="?mainmenu=inventory">
					<i class="bi bi-box-seam"></i>
					<span>Inventory</span>
				</a>

				<a class="nav-link <?php echo in_array($activeMenu, $customerMenus) ? 'active' : ''; ?>" href="?mainmenu=customers">
					<i class="bi bi-people"></i>
					<span>Customers</span>
				</a>

				<div class="nav-section">
					<button class="nav-link nav-section-toggle <?php echo in_array($activeMenu, $settingsMenus) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#navSettings" aria-expanded="<?php echo in_array($activeMenu, $settingsMenus) ? 'true' : 'false'; ?>">
						<span><i class="bi bi-building-gear"></i> Settings</span>
						<i class="bi bi-chevron-down"></i>
					</button>
					<div id="navSettings" class="collapse <?php echo in_array($activeMenu, $settingsMenus) ? 'show' : ''; ?>">
						<div class="nav-submenu">
							<a class="sub-link <?php echo ($activeMenu == 'product_list') ? 'active' : ''; ?>" href="?mainmenu=product_list">Product List</a>
							<a class="sub-link <?php echo (in_array($activeMenu, ['expense_category_list', 'view_expense_category'])) ? 'active' : ''; ?>" href="?mainmenu=expense_category_list">Expense Category List</a>
							<a class="sub-link <?php echo ($activeMenu == 'download_database') ? 'active' : ''; ?>" href="?mainmenu=download_database">Database Backup</a>
							<a class="sub-link <?php echo ($activeMenu == 'company_profile') ? 'active' : ''; ?>" href="?mainmenu=company_profile">Company Profile</a>
						</div>
					</div>
				</div>
			</nav>
		</div>
	</aside>

	<main class="app-main">
		<div class="mobile-topbar d-lg-none">
			<button class="btn btn-outline-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
				<i class="bi bi-list"></i> Menu
			</button>
		</div>

		<header class="page-hero">
			<div>
				<p class="page-kicker">Rice trading management</p>
				<h2><?php echo htmlspecialchars($currentTitle); ?></h2>
			</div>
			<div class="page-hero-badge">
				<span class="badge text-bg-light">Bootstrap 5 UI</span>
			</div>
		</header>

		<section class="page-body">
			<?php
				switch($mainMenu){
					case "view_invoice":
						if(isset($_GET['edit_product'])){
							include "process/edit_product.php";
						}
						if(isset($_GET['add_new_product'])){
							include "process/add_new_product.php";
						}
						if(isset($_GET['delete_product'])){
							include "process/delete_product.php";
						}
						$invoiceNo = $_GET['invoiceNo'];
						include "view/view_invoice.php";
					break;

					case "product_list":
						if(isset($_GET['edit_product'])){
							include "process/edit_product.php";
						}
						if(isset($_GET['add_new_product']) || isset($_GET['toggle_product_status'])){
							include "process/add_new_product.php";
						}
						include "view/product_list.php";
					break;

					case "expense_category_list":
						if(isset($_GET['add_expense_category']) || isset($_GET['toggle_expense_category_status'])){
							include "process/manage_expense_category.php";
						}
						include "view/expense_category_list.php";
					break;

					case "purchase_product":
						include "view/purchase_product.php";
					break;

					case "purchase_invoice":
						include "process/purchase_product.php";
						include "view/receipt.php";
					break;

					case "sale_invoice":
						include "process/sale_product.php";
						include "view/sale_receipt.php";
					break;

					case "purchase_list":
						if(isset($_GET['delete_product'])){
							include "process/delete_product.php";
						}
						include "view/purchase_list.php";
					break;

					case "view_purchase_product":
						include "view/view_purchase_product.php";
					break;

					case "expenses":
						include "view/expenses.php";
					break;

					case "expenses_list":
						if(isset($_GET['delete_product'])){
							include "process/delete_product.php";
						}
						include "view/expenses_list.php";
					break;

					case "view_expense":
						if(isset($_GET['edit_product'])){
							include "process/edit_product.php";
						}
						if(isset($_GET['add_new_product'])){
							include "process/add_new_product.php";
						}
						if(isset($_GET['delete_product'])){
							include "process/delete_product.php";
						}
						include "view/view_expense.php";
					break;

					case "view_expense_category":
						include "view/view_expense_category.php";
					break;

					case "sell_product_others":
					case "sell_product_lpg":
					case "sold_product":
						include "view/sell_product.php";
					break;

					case "sales_list":
						if(isset($_GET['delete_product'])){
							include "process/delete_product.php";
						}
						include "view/sales_list.php";
					break;

					case "view_sale":
						if(isset($_GET['edit_product'])){
							include "process/edit_product.php";
						}
						if(isset($_GET['add_new_product'])){
							include "process/add_new_product.php";
						}
						if(isset($_GET['delete_product'])){
							include "process/delete_product.php";
						}
						include "view/view_sale.php";
					break;

					case "view_sale_product":
						include "view/view_sale_product.php";
					break;

					case "inventory":
						include "view/inventory.php";
					break;

					case "customers":
						include "view/customers.php";
					break;

					case "company_profile":
						include "view/company_profile.php";
					break;

					case "download_database":
						include "view/download_database.php";
					break;

					default:
						include "view/dashboard.php";
					break;
				}
				$connectDB->close();
			?>
		</section>
	</main>
</div>

<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
	<div class="offcanvas-header">
		<h5 class="offcanvas-title" id="mobileSidebarLabel"><?php echo htmlspecialchars($companyName); ?></h5>
		<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
	<div class="offcanvas-body">
		<nav class="nav flex-column app-nav">
			<a class="nav-link <?php echo ($activeMenu == '') ? 'active' : ''; ?>" href="?">Dashboard</a>
			<a class="nav-link <?php echo ($activeMenu == 'sell_product_others') ? 'active' : ''; ?>" href="?mainmenu=sell_product_others">Sell Product (Others)</a>
			<a class="nav-link <?php echo ($activeMenu == 'sell_product_lpg') ? 'active' : ''; ?>" href="?mainmenu=sell_product_lpg">Sell Product (LPG)</a>
			<a class="nav-link <?php echo ($activeMenu == 'sales_list') ? 'active' : ''; ?>" href="?mainmenu=sales_list">Sales History</a>
			<a class="nav-link <?php echo ($activeMenu == 'purchase_product') ? 'active' : ''; ?>" href="?mainmenu=purchase_product">Add Stock Purchase</a>
			<a class="nav-link <?php echo ($activeMenu == 'purchase_list') ? 'active' : ''; ?>" href="?mainmenu=purchase_list">Purchase History</a>
			<a class="nav-link <?php echo ($activeMenu == 'expenses') ? 'active' : ''; ?>" href="?mainmenu=expenses">Expenses</a>
			<a class="nav-link <?php echo ($activeMenu == 'expenses_list') ? 'active' : ''; ?>" href="?mainmenu=expenses_list">Expenses History</a>
			<a class="nav-link <?php echo ($activeMenu == 'inventory') ? 'active' : ''; ?>" href="?mainmenu=inventory">Inventory</a>
			<a class="nav-link <?php echo ($activeMenu == 'customers') ? 'active' : ''; ?>" href="?mainmenu=customers">Customers</a>
			<a class="nav-link <?php echo ($activeMenu == 'product_list') ? 'active' : ''; ?>" href="?mainmenu=product_list">Product List</a>
			<a class="nav-link <?php echo (in_array($activeMenu, ['expense_category_list', 'view_expense_category'])) ? 'active' : ''; ?>" href="?mainmenu=expense_category_list">Expense Categories</a>
			<a class="nav-link <?php echo ($activeMenu == 'download_database') ? 'active' : ''; ?>" href="?mainmenu=download_database">Database Backup</a>
			<a class="nav-link <?php echo ($activeMenu == 'company_profile') ? 'active' : ''; ?>" href="?mainmenu=company_profile">Company Profile</a>
		</nav>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-toggle]').forEach(function (element) {
			const value = element.getAttribute('data-toggle');
			if (value && !element.getAttribute('data-bs-toggle')) {
				element.setAttribute('data-bs-toggle', value);
			}
		});

		document.querySelectorAll('[data-target]').forEach(function (element) {
			const value = element.getAttribute('data-target');
			if (value && !element.getAttribute('data-bs-target')) {
				element.setAttribute('data-bs-target', value);
			}
		});

		document.querySelectorAll('[data-dismiss="modal"]').forEach(function (element) {
			element.setAttribute('data-bs-dismiss', 'modal');
		});
	});
</script>
</body>
</html>
