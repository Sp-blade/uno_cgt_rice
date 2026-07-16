<?php
	$products = [];
	$expenseCategories = [];
	$allProducts = $connectDB->query("
		SELECT
			p.Product_ID AS id,
			p.ProductName AS name,
			COALESCE(NULLIF(p.SellingPrice, 0), p.ProductPrice) AS price,
			COALESCE(p.ProductBaseUnit, 'pc') AS base_unit,
			COALESCE(p.CanConvertToKg, 0) AS can_convert_to_kg,
			COALESCE(p.KgEquivalentQty, 0) AS kg_equivalent_qty
		FROM products p
		WHERE p.IsActive = 1
		ORDER BY COALESCE(p.ParentProduct_ID, 0) ASC, p.ProductName ASC
	");
	if ($allProducts && $allProducts->num_rows > 0) {
		while ($row = $allProducts->fetch_assoc()) {
			$products[] = $row;
		}
	}

	$categoryResult = $connectDB->query("SELECT CategoryName FROM expense_categories WHERE IsActive = 1 ORDER BY CategoryName ASC");
	if ($categoryResult && $categoryResult->num_rows > 0) {
		while ($row = $categoryResult->fetch_assoc()) {
			$expenseCategories[] = $row['CategoryName'];
		}
	}
?>

<script>
	const soldProductData = <?php echo json_encode($products); ?>;
</script>

<style>
	.average-price-launcher {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		padding: 0.9rem 1.1rem;
		border-radius: 999px;
		font-weight: 700;
		color: #fff;
	}

	.average-price-launcher i,
	.average-price-launcher span {
		color: #fff;
	}

	.delivery-tool-actions {
		display: flex;
		justify-content: flex-end;
		flex-wrap: wrap;
		gap: 0.75rem;
	}

	.sale-unit-cell {
		display: flex;
		flex-direction: column;
		gap: 0.3rem;
	}

	.sale-unit-meta {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.4rem;
	}

	.sale-app-detail {
		display: none;
		margin-top: 0.15rem;
		padding: 0.45rem 0.6rem;
		border-radius: 12px;
		background: rgba(15, 23, 42, 0.92);
		color: #f8fafc;
		font-size: 0.8rem;
		font-weight: 600;
		line-height: 1.45;
		white-space: normal;
	}

	.sale-unit-cell.show-app-detail .sale-app-detail {
		display: block;
	}

	.sale-action-cell {
		flex: 0 0 auto;
	}

	.sale-unit-cell .sale-unit-select {
		min-width: 0;
	}

	.sale-unit-note,
	.sale-app-summary {
		display: inline-flex;
		align-items: center;
		font-size: 0.88rem;
		line-height: 1.3;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: 100%;
	}

	.sale-unit-note {
		color: #64748b;
	}

	.sale-app-summary {
		padding: 0.2rem 0.55rem;
		border-radius: 999px;
		font-weight: 700;
		background: rgba(226, 232, 240, 0.65);
		color: #475569;
		cursor: pointer;
		border: 0;
	}

	.sale-app-summary.is-attached {
		background: rgba(20, 184, 166, 0.12);
		color: #0f766e;
	}

	@media (min-width: 992px) {
		.sale-row {
			display: flex;
			flex-wrap: nowrap;
			align-items: flex-start;
		}

		.sale-number-cell {
			flex: 0 0 56px;
			max-width: 56px;
		}

		.sale-product-cell {
			flex: 1.7 1 0;
			min-width: 220px;
		}

		.sale-quantity-cell,
		.sale-price-cell,
		.sale-total-cell {
			flex: 1 1 0;
			min-width: 140px;
		}

		.sale-unit-cell {
			flex: 1.2 1 0;
			min-width: 220px;
		}

		.sale-action-cell {
			width: 96px;
			min-width: 96px;
		}
	}

	.average-price-chatbox {
		position: fixed;
		right: 28px;
		bottom: 118px;
		width: min(420px, calc(100vw - 32px));
		max-height: min(72vh, 760px);
		display: none;
		flex-direction: column;
		overflow: hidden;
		border-radius: 24px;
		border: 1px solid rgba(148, 163, 184, 0.24);
		background: rgba(255, 255, 255, 0.96);
		backdrop-filter: blur(22px);
		box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
		z-index: 1041;
	}

	.average-price-chatbox.is-open {
		display: flex;
	}

	.average-price-chatbox::after {
		content: '';
		position: absolute;
		right: 36px;
		bottom: -10px;
		width: 20px;
		height: 20px;
		background: rgba(255, 255, 255, 0.96);
		border-right: 1px solid rgba(148, 163, 184, 0.24);
		border-bottom: 1px solid rgba(148, 163, 184, 0.24);
		transform: rotate(45deg);
	}

	.average-price-chatbox-header {
		padding: 1rem 1.1rem;
		color: #fff;
		background: linear-gradient(145deg, #0f766e 0%, #115e59 100%);
	}

	.average-price-chatbox-header h4 {
		margin-bottom: 0.2rem;
		font-size: 1.05rem;
		color: #fff;
	}

	.average-price-chatbox-body {
		padding: 1rem 1.1rem 1.1rem;
		overflow-y: auto;
	}

	.average-price-search,
	.average-price-result-card,
	.average-price-empty-state {
		border: 1px solid rgba(148, 163, 184, 0.2);
		border-radius: 18px;
		background: #f8fafc;
	}

	.average-price-search,
	.average-price-result-card,
	.average-price-empty-state {
		padding: 0.9rem;
	}

	.average-price-product-list {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
	}

	.average-price-combobox {
		padding: 0.9rem;
		border: 1px solid rgba(148, 163, 184, 0.2);
		border-radius: 18px;
		background: #f8fafc;
	}

	.average-price-combobox-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.75rem;
		margin-bottom: 0.75rem;
	}

	.average-price-combobox-control {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem;
		padding: 0.7rem 0.8rem;
		border-radius: 18px;
		border: 1px solid rgba(148, 163, 184, 0.24);
		background: #fff;
		cursor: text;
	}

	.average-price-combobox-control:focus-within {
		border-color: rgba(15, 118, 110, 0.45);
		box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.12);
	}

	.average-price-selected-list {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
		flex: 1 1 auto;
	}

	.average-price-search-input {
		flex: 1 1 140px;
		min-width: 120px;
		border: 0;
		outline: 0;
		background: transparent;
		padding: 0.2rem 0;
		font-size: 1rem;
		color: #0f172a;
	}

	.average-price-search-input::placeholder {
		color: #94a3b8;
	}

	.average-price-dropdown {
		margin-top: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.45rem;
		max-height: 220px;
		overflow-y: auto;
	}

	.average-price-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.45rem;
		padding: 0.45rem 0.75rem;
		border-radius: 999px;
		border: 1px solid rgba(15, 118, 110, 0.18);
		background: rgba(20, 184, 166, 0.08);
		color: #0f172a;
		font-weight: 600;
	}

	.average-price-chip button {
		border: 0;
		background: transparent;
		color: #115e59;
		font-size: 0.95rem;
		line-height: 1;
		padding: 0;
	}

	.average-price-product-item {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: space-between;
		text-align: left;
		border: 1px solid rgba(148, 163, 184, 0.24);
		border-radius: 14px;
		background: #fff;
		padding: 0.7rem 0.85rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
	}

	.average-price-product-item.is-selected {
		background: #0f766e;
		border-color: #0f766e;
		color: #fff;
	}

	.average-price-product-item-label {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.average-price-product-item-check {
		font-size: 0.88rem;
		font-weight: 700;
		opacity: 0.9;
	}

	.average-price-result-card + .average-price-result-card {
		margin-top: 0.8rem;
	}

	.average-price-result-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.75rem;
	}

	.average-price-result-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.75rem;
		margin-top: 0.8rem;
	}

	.average-price-stat {
		padding: 0.8rem;
		border-radius: 16px;
		background: #fff;
		border: 1px solid rgba(148, 163, 184, 0.2);
	}

	.average-price-stat-label {
		display: block;
		font-size: 0.72rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: #64748b;
		margin-bottom: 0.25rem;
	}

	.average-price-stat-value {
		font-size: 1.05rem;
		font-weight: 800;
		color: #0f172a;
	}

	.average-price-meta {
		font-size: 0.88rem;
		color: #64748b;
	}

	.average-price-feedback {
		font-size: 0.92rem;
		margin-bottom: 0.8rem;
	}

	.average-price-apply-panel {
		border: 1px solid rgba(15, 118, 110, 0.18);
		border-radius: 18px;
		background: linear-gradient(135deg, rgba(20, 184, 166, 0.08), rgba(248, 250, 252, 0.96));
		padding: 0.9rem;
	}

	.average-price-apply-grid {
		align-items: end;
	}

	.average-price-apply-note {
		margin-top: 0.5rem;
	}

	.average-price-apply-summary {
		margin-top: 0.75rem;
		padding: 0.8rem;
		border-radius: 16px;
		background: rgba(255, 255, 255, 0.92);
		border: 1px solid rgba(148, 163, 184, 0.18);
	}

	.average-price-apply-summary-value {
		display: block;
		font-size: 1.2rem;
		font-weight: 800;
		color: #0f172a;
	}

	.sale-app-summary.is-missing {
		background: rgba(254, 226, 226, 0.92);
		color: #dc2626 !important;
	}

	.sale-row.app-missing .icon-action-btn-edit {
		border-color: rgba(220, 38, 38, 0.35);
		background: rgba(254, 226, 226, 0.9);
		color: #dc2626;
	}

	@media (max-width: 767.98px) {
		.sale-unit-note,
		.sale-app-summary {
			white-space: normal;
		}

		.average-price-chatbox {
			right: 16px;
			left: 16px;
			width: auto;
			bottom: 16px;
			max-height: 64vh;
		}

		.average-price-result-grid {
			grid-template-columns: 1fr;
		}

		.delivery-tool-actions > * {
			width: 100%;
		}
	}
</style>

<form class="form new-form" method="POST" action="?mainmenu=save_sales" id="salesForm">
	<div class="container-fluid">
		<div class="dashboard-card">
			<div class="section-head">
				<div>
					<p class="section-kicker">Sales workflow</p>
					<h3>Record product sales</h3>
				</div>
			</div>

			<div class="row custom-row margin-top">
				<div class="col-md-4">
					<label for="sale_date">Delivery Date</label>
					<input type="date" class="form-control" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required />
				</div>
				<div class="col-md-4">
					<label for="customer_name">Customer</label>
					<input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Customer / buyer name" required />
				</div>
				<div class="col-md-4">
					<label for="sale_notes">Notes</label>
					<input type="text" class="form-control" id="sale_notes" name="sale_notes" placeholder="Optional delivery note" />
				</div>
			</div>

			<div class="row custom-row margin-top">
				<div class="col-md-4">
					<label for="payment_status">Payment Status</label>
					<select class="form-select" id="payment_status" name="payment_status">
						<option value="PAID">Paid</option>
						<option value="PARTIAL">Partial Payment</option>
						<option value="UNPAID">Unpaid / Loan</option>
					</select>
				</div>
				<div class="col-md-4">
					<label for="amount_paid">Amount Paid</label>
					<input type="number" step="0.01" min="0" class="form-control" id="amount_paid" name="amount_paid" value="0" />
				</div>
				<div class="col-md-4">
					<label for="due_date">Due Date</label>
					<input type="date" class="form-control" id="due_date" name="due_date" />
				</div>
			</div>

			<div class="section-head margin-top">
				<div>
					<p class="section-kicker">Products</p>
					<h3>Delivery items</h3>
				</div>
			</div>

			<div class="row custom-row sale-row transaction-entry-row" data-sale-row-key="1">
				<div class="col-sm-1 sale-number-cell">
					<label>No</label><br>
					<label class="static-sale-row-number">1</label>
				</div>
				<div class="col-sm-3 sale-product-cell">
					<label>Product Name</label>
					<input type="text" class="form-control" name="product_name[]" list="sold_product_suggestions" onblur="validateSoldSelection(this)" required />
					<input type="hidden" name="product_id[]" value="" />
					<input type="hidden" name="app_sale_row_key[]" value="1" />
					<input type="hidden" name="app_sale_product[]" value="" />
					<input type="hidden" name="app_source_products[]" value="" />
					<input type="hidden" name="sale_unit[]" value="pc" />
					<input type="hidden" name="kg_conversion_qty[]" value="" />
					<input type="hidden" name="app_average_price_base[]" value="" />
					<input type="hidden" name="app_average_price[]" value="" />
					<input type="hidden" name="app_quantity[]" value="" />
					<input type="hidden" name="app_total_purchase_price[]" value="" />
					<datalist id="sold_product_suggestions">
						<?php foreach ($products as $product): ?>
							<option value="<?php echo htmlspecialchars($product['name']); ?>" data-id="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>"></option>
						<?php endforeach; ?>
					</datalist>
				</div>
				<div class="col-sm-2 sale-quantity-cell">
					<label>Pcs/Kg</label>
					<input type="number" step="0.01" min="0" class="form-control" name="product_quantity[]" required oninput="calculateSaleTotal()" />
				</div>
				<div class="col-sm-2 sale-price-cell">
					<label>Selling Price</label>
					<input type="number" step="0.01" min="0" class="form-control" name="product_price[]" required oninput="calculateSaleTotal()" />
				</div>
				<div class="col-sm-2 sale-total-cell">
					<label>Total</label>
					<input type="text" class="form-control" name="total_price[]" readonly />
				</div>
				<div class="col-sm-2">
					<label>LPG Tank</label>
					<select class="form-select form-select-sm" name="lpg_transaction_type[]" onchange="calculateSaleTotal()">
						<option value="NONE">None</option>
						<option value="SWcost basisED">Swapped</option>
						<option value="SOLD">Sold</option>
					</select>
				</div>
				<div class="col-sm-2">
					<label>Tank Condition</label>
					<input type="text" class="form-control form-control-sm" name="lpg_tank_condition[]" placeholder="For swapped tanks" />
				</div>
				<div class="col-sm-2">
					<label>Tank Payment</label>
					<input type="number" step="0.01" min="0" class="form-control form-control-sm" name="lpg_tank_payment[]" value="0" oninput="calculateSaleTotal()" />
				</div>
				<div class="col-sm-3 sale-unit-cell">
					<label>Unit</label>
					<select class="form-select form-select-sm sale-unit-select" disabled>
						<option value="pc">Pcs</option>
						<option value="kg">KG</option>
					</select>
					<div class="sale-unit-meta">
						<small class="text-muted sale-unit-note">Select a product to set the unit.</small>
					</div>
				</div>
				<div class="col-sm-1 sale-action-cell">
					<label>Action</label>
					<div class="margin-top-sm icon-action-group">
						<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteSaleRow(this)" aria-label="Delete delivery row" title="Delete">
							<i class="bi bi-trash3" aria-hidden="true"></i>
						</button>
					</div>
				</div>
			</div>

			<div id="saleFields"></div>

			<div class="margin-top-sm" id="saleAttachmentError" style="display:none;"></div>

			<div class="d-flex justify-content-end margin-top">
				<button type="button" class="btn btn-outline-secondary" id="addSaleButton">+ Add Delivery Item</button>
			</div>

			<div class="section-head margin-top">
				<div>
					<p class="section-kicker">Linked expenses</p>
					<h3>Expenses for this delivery</h3>
				</div>
			</div>

			<datalist id="delivery_expense_categories">
				<?php foreach ($expenseCategories as $expenseCategory): ?>
					<option value="<?php echo htmlspecialchars($expenseCategory); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<div class="row custom-row delivery-expense-row transaction-entry-row">
				<div class="col-sm-1">
					<label>No</label><br>
					<label class="static-delivery-expense-row-number">1</label>
				</div>
				<div class="col-sm-3">
					<label>Category</label>
					<input type="text" class="form-control" name="delivery_expense_category[]" list="delivery_expense_categories" />
				</div>
				<div class="col-sm-5">
					<label>Description</label>
					<input type="text" class="form-control" name="delivery_expense_description[]" placeholder="Gas, toll, helper fee, unloading, etc." />
				</div>
				<div class="col-sm-2">
					<label>Amount</label>
					<input type="number" step="0.01" min="0" class="form-control" name="delivery_expense_amount[]" oninput="calculateExpenseTotal(); calculateNetTotal();" />
				</div>
				<div class="col-sm-1">
					<label>Action</label>
					<div class="margin-top-sm icon-action-group">
						<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteDeliveryExpenseRow(this)" aria-label="Delete linked expense row" title="Delete">
							<i class="bi bi-trash3" aria-hidden="true"></i>
						</button>
					</div>
				</div>
			</div>

			<div id="deliveryExpenseFields"></div>

			<div class="delivery-tool-actions margin-top">
				<button type="button" class="btn btn-outline-secondary" id="addExpenseLinkButton">+ Add Expense</button>
				<button type="button" class="btn btn-primary average-price-launcher" id="averagePriceLauncher" aria-expanded="false" aria-controls="averagePriceChatbox">
					<i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
					<span>Average Purchase Price</span>
				</button>
			</div>
		</div>
	</div>

	<div class="row custom-row fixed-footer">
		<div class="col-md-2 col-12 footer-action">
			<input type="submit" class="btn btn-success w-100" value="Save Delivery">
		</div>
		<div class="col-md-2 col-6 footer-metric">
			<p class="metric-label">Delivery Total</p>
			<div class="totalprice">&#8369;<span id="saleGrandTotal">0.00</span></div>
		</div>
		<div class="col-md-2 col-6 footer-metric">
			<p class="metric-label">Purchase Total</p>
			<div class="totalprice">&#8369;<span id="deliveryPurchaseTotal">0.00</span></div>
		</div>
		<div class="col-md-2 col-6 footer-metric">
			<p class="metric-label">Linked Expenses</p>
			<div class="totalprice">&#8369;<span id="deliveryExpenseTotal">0.00</span></div>
		</div>
		<div class="col-md-2 col-12 footer-metric">
			<p class="metric-label">Net Earning</p>
			<div class="totalprice" id="deliveryNetTotalWrap">&#8369;<span id="deliveryNetTotal">0.00</span></div>
		</div>
	</div>
</form>

<div class="average-price-chatbox" id="averagePriceChatbox" aria-hidden="true">
	<div class="average-price-chatbox-header d-flex justify-content-between align-items-start gap-3">
		<div>
			<h4>Average Purchase Price</h4>
			<p class="mb-0 small">Choose a date range and one or more products to compute a weighted average.</p>
		</div>
		<button type="button" class="btn btn-sm btn-light" id="averagePriceCloseButton">Close</button>
	</div>
	<div class="average-price-chatbox-body">
		<div class="row g-3">
			<div class="col-sm-6">
				<label for="averageDateFrom">Date From</label>
				<input type="date" class="form-control" id="averageDateFrom" value="<?php echo date('Y-m-01'); ?>" />
			</div>
			<div class="col-sm-6">
				<label for="averageDateTo">Date To</label>
				<input type="date" class="form-control" id="averageDateTo" value="<?php echo date('Y-m-d'); ?>" />
			</div>
		</div>

		<div class="average-price-combobox margin-top">
			<div class="average-price-combobox-header">
				<label for="averageProductSearch" class="mb-0"><strong>Select Product(s)</strong></label>
				<button type="button" class="btn btn-sm btn-outline-secondary" id="clearAverageProductsButton">Clear</button>
			</div>
			<div class="average-price-combobox-control" id="averageProductCombobox">
				<div class="average-price-selected-list" id="averageSelectedProducts">
					<span class="text-muted">No products selected yet.</span>
				</div>
				<input type="text" class="average-price-search-input" id="averageProductSearch" placeholder="Search Solid, Tanso, etc." autocomplete="off" />
			</div>
			<div class="average-price-dropdown" id="averageProductList"></div>
		</div>

		<div class="average-price-apply-panel margin-top">
			<div class="row g-3 average-price-apply-grid">
				<div class="col-sm-4">
					<label for="averageApplyUnit">Cost Unit</label>
					<select class="form-select" id="averageApplyUnit">
						<option value="kg" selected>KG</option>
						<option value="pc">Pcs</option>
					</select>
				</div>
				<div class="col-sm-4">
					<label for="averageApplyQuantity">Pcs/Kg to Less</label>
					<input type="number" step="0.01" min="0" class="form-control" id="averageApplyQuantity" value="0.00" />
				</div>
				<div class="col-sm-4">
					<label for="averageComputedExpenseTotal">Total for Expense</label>
					<input type="text" class="form-control" id="averageComputedExpenseTotal" value="P0.00" readonly />
				</div>
			</div>
			<small class="average-price-meta d-block average-price-apply-note" id="averageApplyUnitNote">Computed in KG basis.</small>
			<div class="average-price-apply-summary">
				<span class="average-price-stat-label">Applied Formula</span>
				<span class="average-price-apply-summary-value" id="averageApplyFormula">P0.00 x 1.00 = P0.00</span>
				<div class="average-price-meta">This total is what will be added to the linked expense amount.</div>
			</div>
		</div>

		<div class="d-grid gap-2 margin-top">
			<button type="button" class="btn btn-outline-success" id="averagePriceApplyButton" disabled>Add Cost To Expenses</button>
		</div>

		<div class="average-price-feedback margin-top" id="averagePriceFeedback"></div>
	</div>
</div>

<script>
	let saleRowCount = 2;
	let saleRowKeyCounter = 2;
	let deliveryExpenseRowCount = 2;
	let selectedAverageProducts = [];
	let latestAverageSummary = null;
	let latestAveragePayload = null;
	let averageMode = 'expense';
	let activeSaleRow = null;
	const salesForm = document.getElementById('salesForm');
	const saleAttachmentError = document.getElementById('saleAttachmentError');
	const soldProductLookup = soldProductData.reduce(function(map, product) {
		map[product.name] = {
			id: Number(product.id || 0),
			name: product.name,
			price: Number(product.price || 0),
			baseUnit: String(product.base_unit || 'pc').toLowerCase() === 'kg' ? 'kg' : 'pc',
			canConvertToKg: Number(product.can_convert_to_kg || 0) === 1,
			kgEquivalentQty: Number(product.kg_equivalent_qty || 0)
		};
		return map;
	}, {});

	const averageLauncher = document.getElementById('averagePriceLauncher');
	const averageChatbox = document.getElementById('averagePriceChatbox');
	const averageCloseButton = document.getElementById('averagePriceCloseButton');
	const averageDateFromInput = document.getElementById('averageDateFrom');
	const averageDateToInput = document.getElementById('averageDateTo');
	const averageProductSearchInput = document.getElementById('averageProductSearch');
	const averageSelectedProductsWrap = document.getElementById('averageSelectedProducts');
	const averageProductList = document.getElementById('averageProductList');
	const averageApplyButton = document.getElementById('averagePriceApplyButton');
	const averageFeedback = document.getElementById('averagePriceFeedback');
	const clearAverageProductsButton = document.getElementById('clearAverageProductsButton');
	const averageApplyUnitSelect = document.getElementById('averageApplyUnit');
	const averageApplyUnitNote = document.getElementById('averageApplyUnitNote');
	const averageApplyQuantityInput = document.getElementById('averageApplyQuantity');
	const averageComputedExpenseTotalInput = document.getElementById('averageComputedExpenseTotal');
	const averageApplyFormula = document.getElementById('averageApplyFormula');
	let averageComputeTimer = null;

	function getSaleRows() {
		return Array.from(document.querySelectorAll('.sale-row'));
	}

	function getRowProduct(row) {
		if (!row) {
			return null;
		}
		const productName = (row.querySelector('input[name="product_name[]"]').value || '').trim();
		return soldProductLookup[productName] || null;
	}

	function getRowAppQuantity(row) {
		if (!row) {
			return 0;
		}
		const saleQuantity = parseFloat(row.querySelector('input[name="product_quantity[]"]').value) || 0;
		return saleQuantity;
	}

	function getAttachmentAveragePrice(row, averagePriceBase) {
		if (!row) {
			return Number(averagePriceBase || 0);
		}

		const product = getRowProduct(row);
		const saleUnit = (row.querySelector('input[name="sale_unit[]"]').value || 'pc').toLowerCase();
		const kgConversionQty = parseFloat(row.querySelector('input[name="kg_conversion_qty[]"]').value) || 0;
		const averagePrice = Number(averagePriceBase || 0);

		if (product && product.baseUnit === 'pc' && saleUnit === 'pc' && product.canConvertToKg && kgConversionQty > 0) {
			return averagePrice / kgConversionQty;
		}

		return averagePrice;
	}

	function syncSaleUnitControls(row, preferredUnit) {
		if (!row) {
			return;
		}
		const product = getRowProduct(row);
		const saleUnitInput = row.querySelector('input[name="sale_unit[]"]');
		const kgConversionInput = row.querySelector('input[name="kg_conversion_qty[]"]');
		const saleUnitSelect = row.querySelector('.sale-unit-select');
		const saleUnitNote = row.querySelector('.sale-unit-note');

		if (!saleUnitInput || !kgConversionInput || !saleUnitSelect || !saleUnitNote) {
			return;
		}

		if (!product) {
			saleUnitInput.value = 'pc';
			kgConversionInput.value = '';
			saleUnitSelect.innerHTML = '<option value="pc">Pcs</option><option value="kg">KG</option>';
			saleUnitSelect.value = 'pc';
			saleUnitSelect.disabled = true;
			saleUnitNote.textContent = 'Select a product to set the unit.';
			return;
		}

		if (product.baseUnit === 'kg') {
			saleUnitSelect.innerHTML = '<option value="kg">KG</option>';
			saleUnitSelect.value = 'kg';
			saleUnitSelect.disabled = true;
			saleUnitInput.value = 'kg';
			kgConversionInput.value = '';
			saleUnitNote.textContent = 'Base: KG';
			return;
		}

		if (product.canConvertToKg && product.kgEquivalentQty > 0) {
			saleUnitSelect.innerHTML = '<option value="pc">Pcs</option><option value="kg">KG</option>';
			const nextUnit = preferredUnit === 'kg' || saleUnitInput.value === 'kg' ? 'kg' : 'pc';
			saleUnitSelect.value = nextUnit;
			saleUnitSelect.disabled = false;
			saleUnitInput.value = nextUnit;
			kgConversionInput.value = product.kgEquivalentQty.toFixed(2);
			saleUnitNote.textContent = '1 KG = ' + formatNumber(product.kgEquivalentQty) + ' Pcs';
			return;
		}

		saleUnitSelect.innerHTML = '<option value="pc">Pcs</option>';
		saleUnitSelect.value = 'pc';
		saleUnitSelect.disabled = true;
		saleUnitInput.value = 'pc';
		kgConversionInput.value = '';
		saleUnitNote.textContent = 'Base: Pcs';
	}

	function syncSalePriceFromProduct(row) {
		if (!row) {
			return;
		}
		const product = getRowProduct(row);
		if (!product) {
			return;
		}

		const saleUnit = (row.querySelector('input[name="sale_unit[]"]').value || product.baseUnit || 'pc').toLowerCase();
		let price = product.price;
		if (product.baseUnit === 'pc' && saleUnit === 'kg' && product.canConvertToKg && product.kgEquivalentQty > 0) {
			price = product.price * product.kgEquivalentQty;
		}
		row.querySelector('input[name="product_price[]"]').value = price.toFixed(2);
	}

	function recalculateSaleApp(row) {
		if (!row) {
			return;
		}
		const saleRowKey = row.getAttribute('data-sale-row-key') || '';
		const productName = (row.querySelector('input[name="product_name[]"]').value || '').trim();
		const quantity = parseFloat(row.querySelector('input[name="product_quantity[]"]').value) || 0;
		const appQuantity = getRowAppQuantity(row);
		const averagePriceBase = parseFloat(row.querySelector('input[name="app_average_price_base[]"]').value) || parseFloat(row.querySelector('input[name="app_average_price[]"]').value) || 0;
		const averagePrice = getAttachmentAveragePrice(row, averagePriceBase);
		const totalPurchasePrice = appQuantity * averagePrice;
		row.querySelector('input[name="app_sale_row_key[]"]').value = saleRowKey;
		row.querySelector('input[name="app_sale_product[]"]').value = productName;
		row.querySelector('input[name="app_average_price[]"]').value = averagePrice > 0 ? averagePrice.toFixed(2) : '';
		const quantityInput = row.querySelector('input[name="app_quantity[]"]');
		quantityInput.value = appQuantity > 0 ? appQuantity.toFixed(2) : '';
		row.querySelector('input[name="app_total_purchase_price[]"]').value = totalPurchasePrice > 0 ? totalPurchasePrice.toFixed(2) : '';
		const sourceProducts = (row.querySelector('input[name="app_source_products[]"]').value || '').trim();
		const summary = row.querySelector('.sale-app-summary');
		const detail = row.querySelector('.sale-app-detail');
		if (sourceProducts === '' || averagePrice <= 0 || appQuantity <= 0) {
			summary.textContent = 'No cost basis';
			if (detail) {
				detail.textContent = 'Attach cost basis to track purchase cost for this delivery row.';
			}
		} else {
			summary.textContent = 'Cost Basis Attached';
			if (detail) {
				detail.textContent = 'Cost: ' + sourceProducts + ' | Avg ' + formatCurrency(averagePrice) + ' | Total ' + formatCurrency(totalPurchasePrice);
			}
		}
		updateSaleRowAttachmentState(row);

		if (activeSaleRow === row && averageMode === 'attachment') {
			averageApplyQuantityInput.value = appQuantity > 0 ? appQuantity.toFixed(2) : '0.00';
			refreshAverageComputedTotal();
		}
	}

	function rowNeedsAttachment(row) {
		if (!row) {
			return false;
		}
		const productName = (row.querySelector('input[name="product_name[]"]').value || '').trim();
		const quantity = parseFloat(row.querySelector('input[name="product_quantity[]"]').value) || 0;
		const price = parseFloat(row.querySelector('input[name="product_price[]"]').value) || 0;
		return productName !== '' && quantity > 0 && price > 0;
	}

	function rowHasAttachment(row) {
		if (!rowNeedsAttachment(row)) {
			return true;
		}
		const sourceProducts = (row.querySelector('input[name="app_source_products[]"]').value || '').trim();
		const averagePrice = parseFloat(row.querySelector('input[name="app_average_price[]"]').value) || 0;
		const quantity = parseFloat(row.querySelector('input[name="app_quantity[]"]').value) || 0;
		const totalPurchasePrice = parseFloat(row.querySelector('input[name="app_total_purchase_price[]"]').value) || 0;
		return sourceProducts !== '' && averagePrice > 0 && quantity > 0 && totalPurchasePrice > 0;
	}

	function updateSaleRowAttachmentState(row) {
		const summary = row.querySelector('.sale-app-summary');
		const missingAttachment = rowNeedsAttachment(row) && !rowHasAttachment(row);
		const attached = rowHasAttachment(row) && (row.querySelector('input[name="app_source_products[]"]').value || '').trim() !== '';
		row.classList.toggle('app-missing', missingAttachment);
		if (summary) {
			summary.classList.toggle('is-missing', missingAttachment);
			summary.classList.toggle('is-attached', attached && !missingAttachment);
		}
		return !missingAttachment;
	}

	function toggleSaleAppDetail(trigger) {
		const row = trigger.closest('.sale-row');
		if (!row) {
			return;
		}

		const cell = row.querySelector('.sale-unit-cell');
		if (!cell) {
			return;
		}

		const willShow = !cell.classList.contains('show-app-detail');
		document.querySelectorAll('.sale-unit-cell.show-app-detail').forEach(function(openCell) {
			openCell.classList.remove('show-app-detail');
			const openButton = openCell.querySelector('.sale-app-summary');
			if (openButton) {
				openButton.setAttribute('aria-expanded', 'false');
			}
		});

		cell.classList.toggle('show-app-detail', willShow);
		trigger.setAttribute('aria-expanded', willShow ? 'true' : 'false');
	}

	function validateSaleAttachments() {
		let firstInvalidRow = null;
		getSaleRows().forEach(function(row) {
			const isValid = updateSaleRowAttachmentState(row);
			if (!isValid && !firstInvalidRow) {
				firstInvalidRow = row;
			}
		});

		const isValid = firstInvalidRow === null;
		saleAttachmentError.style.display = isValid ? 'none' : 'block';
		if (firstInvalidRow) {
			firstInvalidRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
			const attachButton = firstInvalidRow.querySelector('.icon-action-btn-edit');
			if (attachButton) {
				attachButton.focus();
			}
		}
		return isValid;
	}

	function syncAllSaleAppRows() {
		getSaleRows().forEach(function(row) {
			recalculateSaleApp(row);
		});
	}

	function calculateAppAttachmentTotal() {
		let total = 0;
		document.querySelectorAll('input[name="app_total_purchase_price[]"]').forEach(function(input) {
			total += parseFloat(input.value) || 0;
		});
		document.getElementById('deliveryPurchaseTotal').textContent = total.toFixed(2);
	}

	function resetAverageToExpenseMode() {
		averageMode = 'expense';
		activeSaleRow = null;
		averageApplyButton.textContent = 'Add Computed Total To Expenses';
		averageApplyQuantityInput.readOnly = false;
		averageApplyQuantityInput.value = '0.00';
		updateAverageApplyUnitState();
	}

	function openAverageForSaleRow(button) {
		const row = button.closest('.sale-row');
		recalculateSaleApp(row);

		const saleProduct = (row.querySelector('input[name="product_name[]"]').value || '').trim();
		const quantity = getRowAppQuantity(row);
		if (saleProduct === '' || quantity <= 0) {
			setAverageFeedback('Choose the product and quantity first before attaching cost basis.', 'error');
			return;
		}

		averageMode = 'attachment';
		activeSaleRow = row;
		averageApplyButton.textContent = 'Attach cost basis To Delivery Item';
		averageApplyQuantityInput.readOnly = true;
		averageApplyQuantityInput.value = quantity.toFixed(2);
		const existingSources = (row.querySelector('input[name="app_source_products[]"]').value || '').trim();
		selectedAverageProducts = existingSources === '' ? [] : existingSources.split(',').map(function(item) {
			return item.trim();
		}).filter(Boolean);
		updateAverageApplyUnitState();
		updateAverageProductUi();
		refreshAverageComputedTotal();
		setAverageFeedback('Compute the average purchase price, then attach it to ' + saleProduct + '.', 'info');
		toggleAverageChatbox(true);
	}

	document.getElementById('addSaleButton').addEventListener('click', function() {
		const container = document.getElementById('saleFields');
		const newRow = document.createElement('div');
		newRow.className = 'row mt-3 sale-row transaction-entry-row';
		newRow.setAttribute('data-sale-row-key', String(saleRowKeyCounter));
		newRow.innerHTML = `
			<div class="col-sm-1 sale-number-cell">
				<br>
				<label class="saleRowCountLabel">${saleRowCount}</label>
			</div>
			<div class="col-sm-3 sale-product-cell">
				<label>Product Name</label>
				<input type="text" class="form-control" name="product_name[]" list="sold_product_suggestions" onblur="validateSoldSelection(this)" required />
				<input type="hidden" name="product_id[]" value="" />
				<input type="hidden" name="app_sale_row_key[]" value="${saleRowKeyCounter}" />
				<input type="hidden" name="app_sale_product[]" value="" />
				<input type="hidden" name="app_source_products[]" value="" />
				<input type="hidden" name="sale_unit[]" value="pc" />
				<input type="hidden" name="kg_conversion_qty[]" value="" />
				<input type="hidden" name="app_average_price_base[]" value="" />
				<input type="hidden" name="app_average_price[]" value="" />
				<input type="hidden" name="app_quantity[]" value="" />
				<input type="hidden" name="app_total_purchase_price[]" value="" />
			</div>
			<div class="col-sm-2 sale-quantity-cell">
				<label>Pcs/Kg</label>
				<input type="number" step="0.01" min="0" class="form-control" name="product_quantity[]" required oninput="calculateSaleTotal()" />
			</div>
			<div class="col-sm-2 sale-price-cell">
				<label>Selling Price</label>
				<input type="number" step="0.01" min="0" class="form-control" name="product_price[]" required oninput="calculateSaleTotal()" />
			</div>
			<div class="col-sm-2 sale-total-cell">
				<label>Total</label>
				<input type="text" class="form-control" name="total_price[]" readonly />
			</div>
			<div class="col-sm-2">
				<label>LPG Tank</label>
				<select class="form-select form-select-sm" name="lpg_transaction_type[]" onchange="calculateSaleTotal()">
					<option value="NONE">None</option>
					<option value="SWcost basisED">Swapped</option>
					<option value="SOLD">Sold</option>
				</select>
			</div>
			<div class="col-sm-2">
				<label>Tank Condition</label>
				<input type="text" class="form-control form-control-sm" name="lpg_tank_condition[]" placeholder="For swapped tanks" />
			</div>
			<div class="col-sm-2">
				<label>Tank Payment</label>
				<input type="number" step="0.01" min="0" class="form-control form-control-sm" name="lpg_tank_payment[]" value="0" oninput="calculateSaleTotal()" />
			</div>
			<div class="col-sm-3 sale-unit-cell">
				<label>Unit</label>
				<select class="form-select form-select-sm sale-unit-select" disabled>
					<option value="pc">Pcs</option>
					<option value="kg">KG</option>
				</select>
				<div class="sale-unit-meta">
					<small class="text-muted sale-unit-note">Select a product to set the unit.</small>
				</div>
			</div>
			<div class="col-sm-1 sale-action-cell">
				<label>Action</label>
				<div class="margin-top-sm icon-action-group">
					<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteSaleRow(this)" aria-label="Delete delivery row" title="Delete">
						<i class="bi bi-trash3" aria-hidden="true"></i>
					</button>
				</div>
			</div>
		`;
		container.appendChild(newRow);
		saleRowKeyCounter++;
		saleRowCount++;
		updateSaleRowCounts();
		newRow.querySelector('input[name="product_name[]"]').focus();
	});

	document.getElementById('addExpenseLinkButton').addEventListener('click', function() {
		const container = document.getElementById('deliveryExpenseFields');
		const newRow = document.createElement('div');
		newRow.className = 'row mt-3 delivery-expense-row transaction-entry-row';
		newRow.innerHTML = `
			<div class="col-sm-1">
				<br>
				<label class="deliveryExpenseRowCountLabel">${deliveryExpenseRowCount}</label>
			</div>
			<div class="col-sm-3">
				<label>Category</label>
				<input type="text" class="form-control" name="delivery_expense_category[]" list="delivery_expense_categories" />
			</div>
			<div class="col-sm-5">
				<label>Description</label>
				<input type="text" class="form-control" name="delivery_expense_description[]" placeholder="Gas, toll, helper fee, unloading, etc." />
			</div>
			<div class="col-sm-2">
				<label>Amount</label>
				<input type="number" step="0.01" min="0" class="form-control" name="delivery_expense_amount[]" oninput="calculateExpenseTotal()" />
			</div>
			<div class="col-sm-1">
				<label>Action</label>
				<div class="margin-top-sm icon-action-group">
					<button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteDeliveryExpenseRow(this)" aria-label="Delete linked expense row" title="Delete">
						<i class="bi bi-trash3" aria-hidden="true"></i>
					</button>
				</div>
			</div>
		`;
		container.appendChild(newRow);
		newRow.querySelector('input[name="delivery_expense_category[]"]').focus();
		deliveryExpenseRowCount++;
		updateDeliveryExpenseRowCounts();
	});

	function validateSoldSelection(inputElement) {
		const options = Array.from(document.getElementById('sold_product_suggestions').options);
		const valid = options.some(function(option) {
			return option.value === inputElement.value;
		});

		if (!valid) {
			alert('Please select a valid product from the suggestions.');
			inputElement.value = '';
			const row = inputElement.closest('.sale-row');
			row.querySelector('input[name="product_id[]"]').value = '';
			syncSaleUnitControls(row, 'pc');
			row.querySelector('input[name="product_price[]"]').value = '';
			calculateSaleTotal();
			return;
		}

		const selectedOption = options.find(function(option) {
			return option.value === inputElement.value;
		});
		const row = inputElement.closest('.row');
		row.querySelector('input[name="product_id[]"]').value = selectedOption.getAttribute('data-id') || '';
		syncSaleUnitControls(row);
		syncSalePriceFromProduct(row);
		recalculateSaleApp(row);
		calculateSaleTotal();
	}

	function toggleAverageChatbox(forceOpen) {
		const willOpen = typeof forceOpen === 'boolean' ? forceOpen : !averageChatbox.classList.contains('is-open');
		averageChatbox.classList.toggle('is-open', willOpen);
		averageChatbox.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
		averageLauncher.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
		if (willOpen) {
			averageProductSearchInput.focus();
		}
	}

	function formatCurrency(value) {
		return 'P' + Number(value || 0).toFixed(2);
	}

	function formatNumber(value) {
		return Number(value || 0).toFixed(2);
	}

	function formatDisplayDate(dateValue) {
		if (!dateValue) {
			return '-';
		}
		const date = new Date(dateValue + 'T00:00:00');
		return date.toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}

	function renderAverageProductList() {
		const keyword = averageProductSearchInput.value.trim().toLowerCase();
		const filteredProducts = soldProductData.filter(function(product) {
			return product.name.toLowerCase().includes(keyword);
		});

		if (filteredProducts.length === 0) {
			averageProductList.innerHTML = '<div class="average-price-empty-state w-100"><p class="mb-0 text-muted">No matching products found.</p></div>';
			return;
		}

		averageProductList.innerHTML = filteredProducts.map(function(product) {
			const isSelected = selectedAverageProducts.includes(product.name);
			return '<button type="button" class="average-price-product-item' + (isSelected ? ' is-selected' : '') + '" data-product-name="' + escapeHtml(product.name) + '"><span class="average-price-product-item-label">' + escapeHtml(product.name) + '</span><span class="average-price-product-item-check">' + (isSelected ? 'Selected' : 'Add') + '</span></button>';
		}).join('');
	}

	function renderSelectedAverageProducts() {
		if (selectedAverageProducts.length === 0) {
			averageSelectedProductsWrap.innerHTML = '<span class="text-muted">No products selected yet.</span>';
			return;
		}

		averageSelectedProductsWrap.innerHTML = selectedAverageProducts.map(function(productName) {
			return '<span class="average-price-chip">' + escapeHtml(productName) + '<button type="button" data-remove-product="' + escapeHtml(productName) + '" aria-label="Remove ' + escapeHtml(productName) + '">&times;</button></span>';
		}).join('');
	}

	function updateAverageProductUi() {
		renderAverageProductList();
		renderSelectedAverageProducts();
	}

	function setAverageFeedback(message, type) {
		const classMap = {
			error: 'text-danger',
			success: 'text-success',
			info: 'text-muted'
		};
		averageFeedback.className = 'average-price-feedback ' + (classMap[type] || 'text-muted');
		averageFeedback.textContent = message;
	}

	function updateAverageApplyUnitState() {
		if (averageMode === 'attachment' && activeSaleRow) {
			const rowUnit = (activeSaleRow.querySelector('input[name="sale_unit[]"]').value || 'kg').toLowerCase();
			averageApplyUnitSelect.innerHTML = rowUnit === 'pc'
				? '<option value="pc">Pcs</option>'
				: '<option value="kg">KG</option>';
			averageApplyUnitSelect.value = rowUnit;
			averageApplyUnitSelect.disabled = true;
			averageApplyUnitNote.textContent = rowUnit === 'pc' ? 'Using the sold item Pcs basis.' : 'Using the sold item KG basis.';
			return;
		}

		const selectedProducts = selectedAverageProducts.map(function(productName) {
			return soldProductLookup[productName] || null;
		}).filter(Boolean);

		if (selectedProducts.length === 1) {
			const product = selectedProducts[0];
			if (product.baseUnit === 'pc' && product.canConvertToKg && product.kgEquivalentQty > 0) {
				const currentUnit = averageApplyUnitSelect.value === 'pc' ? 'pc' : 'kg';
				averageApplyUnitSelect.innerHTML = '<option value="pc">Pcs</option><option value="kg">KG</option>';
				averageApplyUnitSelect.value = currentUnit;
				averageApplyUnitSelect.disabled = false;
				averageApplyUnitNote.textContent = 'Switch between Pcs and KG basis for this product.';
				return;
			}

			if (product.baseUnit === 'pc') {
				averageApplyUnitSelect.innerHTML = '<option value="pc">Pcs</option>';
				averageApplyUnitSelect.value = 'pc';
				averageApplyUnitSelect.disabled = true;
				averageApplyUnitNote.textContent = 'This product uses Pcs basis only.';
				return;
			}
		}

		averageApplyUnitSelect.innerHTML = '<option value="kg">KG</option>';
		averageApplyUnitSelect.value = 'kg';
		averageApplyUnitSelect.disabled = true;
		averageApplyUnitNote.textContent = selectedProducts.length > 1 ? 'Mixed products are computed in KG basis.' : 'Computed in KG basis.';
	}

	function getAverageApplyQuantity() {
		const quantity = parseFloat(averageApplyQuantityInput.value);
		return quantity > 0 ? quantity : 0;
	}

	function getAverageComputedExpenseAmount() {
		const averagePriceBase = Number(latestAverageSummary && latestAverageSummary.average_price ? latestAverageSummary.average_price : 0);
		let averagePrice = averagePriceBase;
		if (averageMode === 'attachment' && activeSaleRow) {
			averagePrice = getAttachmentAveragePrice(activeSaleRow, averagePriceBase);
		} else if (averageApplyUnitSelect.value === 'pc' && selectedAverageProducts.length === 1) {
			const product = soldProductLookup[selectedAverageProducts[0]] || null;
			if (product && product.baseUnit === 'pc' && product.canConvertToKg && product.kgEquivalentQty > 0) {
				averagePrice = averagePriceBase / product.kgEquivalentQty;
			}
		}
		return averagePrice * getAverageApplyQuantity();
	}

	function refreshAverageComputedTotal() {
		const averagePriceBase = Number(latestAverageSummary && latestAverageSummary.average_price ? latestAverageSummary.average_price : 0);
		let averagePrice = averagePriceBase;
		if (averageMode === 'attachment' && activeSaleRow) {
			averagePrice = getAttachmentAveragePrice(activeSaleRow, averagePriceBase);
		} else if (averageApplyUnitSelect.value === 'pc' && selectedAverageProducts.length === 1) {
			const product = soldProductLookup[selectedAverageProducts[0]] || null;
			if (product && product.baseUnit === 'pc' && product.canConvertToKg && product.kgEquivalentQty > 0) {
				averagePrice = averagePriceBase / product.kgEquivalentQty;
			}
		}
		const quantity = getAverageApplyQuantity();
		const computedTotal = getAverageComputedExpenseAmount();

		averageComputedExpenseTotalInput.value = formatCurrency(computedTotal);
		averageApplyFormula.textContent = formatCurrency(averagePrice) + ' x ' + formatNumber(quantity) + ' = ' + formatCurrency(computedTotal);
	}

	function findBestAverageExpenseRow() {
		const rows = Array.from(document.querySelectorAll('.delivery-expense-row'));
		const matchingRow = rows.find(function(row) {
			const category = (row.querySelector('input[name="delivery_expense_category[]"]').value || '').trim().toLowerCase();
			const description = (row.querySelector('input[name="delivery_expense_description[]"]').value || '').trim().toLowerCase();
			return category === 'others' && description === 'less - app';
		});

		if (matchingRow) {
			return matchingRow;
		}

		const emptyRow = rows.find(function(row) {
			const category = (row.querySelector('input[name="delivery_expense_category[]"]').value || '').trim();
			const description = (row.querySelector('input[name="delivery_expense_description[]"]').value || '').trim();
			const amount = (row.querySelector('input[name="delivery_expense_amount[]"]').value || '').trim();
			return category === '' && description === '' && amount === '';
		});

		if (emptyRow) {
			return emptyRow;
		}

		document.getElementById('addExpenseLinkButton').click();
		const rowsAfterAdd = document.querySelectorAll('.delivery-expense-row');
		return rowsAfterAdd[rowsAfterAdd.length - 1] || null;
	}

	function applyAverageToExpense() {
		if (!latestAverageSummary) {
			setAverageFeedback('Compute an average first before adding it to expenses.', 'error');
			return;
		}

		const quantity = getAverageApplyQuantity();
		if (quantity <= 0) {
			setAverageFeedback('Enter the quantity or KG to multiply with the average purchase price.', 'error');
			averageApplyQuantityInput.focus();
			return;
		}

		const targetRow = findBestAverageExpenseRow();
		if (!targetRow) {
			setAverageFeedback('Unable to create or find an expense row for cost basis.', 'error');
			return;
		}

		const computedAmount = getAverageComputedExpenseAmount();
		const appProductsLabel = selectedAverageProducts.length > 0 ? selectedAverageProducts.join(', ') + ' ' : '';

		targetRow.querySelector('input[name="delivery_expense_category[]"]').value = 'Others';
		targetRow.querySelector('input[name="delivery_expense_description[]"]').value = 'Less - Cost ' + appProductsLabel + '(' + formatNumber(quantity) + ' ' + averageApplyUnitSelect.value.toUpperCase() + ')';
		targetRow.querySelector('input[name="delivery_expense_amount[]"]').value = computedAmount.toFixed(2);
		calculateExpenseTotal();
		toggleAverageChatbox(false);
		targetRow.querySelector('input[name="delivery_expense_amount[]"]').focus();
		setAverageFeedback('Computed cost basis total added to expenses as Others / Less - Cost.', 'success');
	}

	function applyAverageToAttachment() {
		if (!latestAverageSummary) {
			setAverageFeedback('Compute an average first before attaching it to a delivery item.', 'error');
			return;
		}

		if (!activeSaleRow) {
			setAverageFeedback('Select a delivery row first.', 'error');
			return;
		}

		recalculateSaleApp(activeSaleRow);
		const linkedSaleProduct = (activeSaleRow.querySelector('input[name="app_sale_product[]"]').value || '').trim();
		const quantity = parseFloat(activeSaleRow.querySelector('input[name="app_quantity[]"]').value) || 0;
		if (linkedSaleProduct === '' || quantity <= 0) {
			setAverageFeedback('Choose a valid delivery item with quantity before attaching cost basis.', 'error');
			return;
		}

		activeSaleRow.querySelector('input[name="app_source_products[]"]').value = selectedAverageProducts.join(', ');
		activeSaleRow.querySelector('input[name="app_average_price_base[]"]').value = Number(latestAverageSummary.average_price || 0).toFixed(2);
		recalculateSaleApp(activeSaleRow);
		calculateAppAttachmentTotal();
		calculateNetTotal();
		toggleAverageChatbox(false);
		setAverageFeedback('cost products attached to ' + linkedSaleProduct + '.', 'success');
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	async function computeAveragePurchasePrice() {
		const dateFrom = averageDateFromInput.value;
		const dateTo = averageDateToInput.value;

		if (!dateFrom || !dateTo) {
			setAverageFeedback('Please select both Date From and Date To.', 'error');
			return;
		}

		if (selectedAverageProducts.length === 0) {
			setAverageFeedback('Please select at least one product.', 'error');
			return;
		}

		setAverageFeedback('Updating average purchase price...', 'info');
		averageApplyButton.disabled = true;

		try {
			const params = new URLSearchParams({
				mainmenu: 'average_purchase_price',
				date_from: dateFrom,
				date_to: dateTo
			});

			selectedAverageProducts.forEach(function(productName) {
				params.append('products[]', productName);
			});

			const response = await fetch('?' + params.toString(), {
				headers: {
					'Accept': 'application/json'
				}
			});
			const payload = await response.json();

			if (!response.ok || !payload.success) {
				throw new Error(payload.message || 'Unable to compute average purchase price.');
			}

			latestAveragePayload = payload;
			latestAverageSummary = payload.has_data ? payload.summary : null;
			averageApplyButton.disabled = !payload.has_data;
			refreshAverageComputedTotal();
			setAverageFeedback(payload.message, payload.has_data ? 'success' : 'info');
		} catch (error) {
			latestAveragePayload = null;
			latestAverageSummary = null;
			averageApplyButton.disabled = true;
			refreshAverageComputedTotal();
			setAverageFeedback(error.message, 'error');
		} finally {
			// no-op
		}
	}

	function scheduleAverageComputation() {
		if (averageComputeTimer) {
			window.clearTimeout(averageComputeTimer);
		}

		averageComputeTimer = window.setTimeout(function() {
			averageComputeTimer = null;
			if (!averageChatbox.classList.contains('is-open')) {
				return;
			}
			if (!averageDateFromInput.value || !averageDateToInput.value || selectedAverageProducts.length === 0) {
				latestAveragePayload = null;
				latestAverageSummary = null;
				averageApplyButton.disabled = true;
				refreshAverageComputedTotal();
				return;
			}
			computeAveragePurchasePrice();
		}, 250);
	}

	function calculateSaleTotal() {
		let grandTotal = 0;
		const quantities = document.querySelectorAll('input[name="product_quantity[]"]');
		const prices = document.querySelectorAll('input[name="product_price[]"]');
		const totals = document.querySelectorAll('input[name="total_price[]"]');
		const lpgTypes = document.querySelectorAll('select[name="lpg_transaction_type[]"]');
		const lpgPayments = document.querySelectorAll('input[name="lpg_tank_payment[]"]');

		quantities.forEach(function(quantityInput, index) {
			const quantity = parseFloat(quantityInput.value) || 0;
			const price = parseFloat(prices[index].value) || 0;
			const lpgPayment = lpgTypes[index] && lpgTypes[index].value === 'SOLD' ? (parseFloat(lpgPayments[index].value) || 0) : 0;
			const total = (quantity * price) + lpgPayment;
			totals[index].value = total.toFixed(2);
			grandTotal += total;
		});

		document.getElementById('saleGrandTotal').textContent = grandTotal.toFixed(2);
		syncAllSaleAppRows();
		calculateAppAttachmentTotal();
		calculateNetTotal();
	}

	function calculateExpenseTotal() {
		let total = 0;
		document.querySelectorAll('input[name="delivery_expense_amount[]"]').forEach(function(input) {
			total += parseFloat(input.value) || 0;
		});
		document.getElementById('deliveryExpenseTotal').textContent = total.toFixed(2);
		calculateNetTotal();
	}

	function calculateNetTotal() {
		const salesTotal = parseFloat(document.getElementById('saleGrandTotal').textContent) || 0;
		const purchaseTotal = parseFloat(document.getElementById('deliveryPurchaseTotal').textContent) || 0;
		const expenseTotal = parseFloat(document.getElementById('deliveryExpenseTotal').textContent) || 0;
		const netTotal = salesTotal - purchaseTotal - expenseTotal;
		document.getElementById('deliveryNetTotal').textContent = netTotal.toFixed(2);
		document.getElementById('deliveryNetTotalWrap').classList.toggle('text-danger', netTotal < 0);
		document.getElementById('deliveryNetTotalWrap').classList.toggle('text-success', netTotal >= 0);
	}

	function deleteSaleRow(button) {
		const row = button.closest('.sale-row');
		if (activeSaleRow === row) {
			resetAverageToExpenseMode();
		}
		row.remove();
		updateSaleRowCounts();
		calculateSaleTotal();
	}

	function deleteDeliveryExpenseRow(button) {
		const row = button.closest('.delivery-expense-row');
		row.remove();
		updateDeliveryExpenseRowCounts();
		calculateExpenseTotal();
	}

	function updateSaleRowCounts() {
		const baseRow = document.querySelector('.static-sale-row-number');
		if (baseRow) {
			baseRow.textContent = '1';
		}
		const rows = document.querySelectorAll('#saleFields .sale-row');
		let count = 2;
		rows.forEach(function(row) {
			row.querySelector('.saleRowCountLabel').textContent = count;
			count++;
		});
		saleRowCount = count;
	}

	function updateDeliveryExpenseRowCounts() {
		const baseRow = document.querySelector('.static-delivery-expense-row-number');
		if (baseRow) {
			baseRow.textContent = '1';
		}
		const rows = document.querySelectorAll('#deliveryExpenseFields .delivery-expense-row');
		let count = 2;
		rows.forEach(function(row) {
			row.querySelector('.deliveryExpenseRowCountLabel').textContent = count;
			count++;
		});
		deliveryExpenseRowCount = count;
	}

	averageLauncher.addEventListener('click', function() {
		resetAverageToExpenseMode();
		toggleAverageChatbox(true);
		scheduleAverageComputation();
		refreshAverageComputedTotal();
	});

	averageCloseButton.addEventListener('click', function() {
		toggleAverageChatbox(false);
	});

	averageProductSearchInput.addEventListener('input', renderAverageProductList);
	averageDateFromInput.addEventListener('change', scheduleAverageComputation);
	averageDateToInput.addEventListener('change', scheduleAverageComputation);

	averageProductList.addEventListener('click', function(event) {
		const button = event.target.closest('[data-product-name]');
		if (!button) {
			return;
		}

		const productName = button.getAttribute('data-product-name');
		if (selectedAverageProducts.includes(productName)) {
			selectedAverageProducts = selectedAverageProducts.filter(function(name) {
				return name !== productName;
			});
		} else {
			selectedAverageProducts.push(productName);
		}

		updateAverageProductUi();
		updateAverageApplyUnitState();
		scheduleAverageComputation();
		refreshAverageComputedTotal();
	});

	averageSelectedProductsWrap.addEventListener('click', function(event) {
		const button = event.target.closest('[data-remove-product]');
		if (!button) {
			return;
		}

		const productName = button.getAttribute('data-remove-product');
		selectedAverageProducts = selectedAverageProducts.filter(function(name) {
			return name !== productName;
		});
		updateAverageProductUi();
		updateAverageApplyUnitState();
		scheduleAverageComputation();
		refreshAverageComputedTotal();
	});

	salesForm.addEventListener('change', function(event) {
		if (!event.target.classList.contains('sale-unit-select')) {
			return;
		}

		const row = event.target.closest('.sale-row');
		const selectedUnit = event.target.value === 'kg' ? 'kg' : 'pc';
		row.querySelector('input[name="sale_unit[]"]').value = selectedUnit;
		syncSaleUnitControls(row, selectedUnit);
		syncSalePriceFromProduct(row);
		updateAverageApplyUnitState();
		calculateSaleTotal();
	});

	clearAverageProductsButton.addEventListener('click', function() {
		selectedAverageProducts = [];
		averageProductSearchInput.value = '';
		updateAverageProductUi();
		updateAverageApplyUnitState();
		scheduleAverageComputation();
		refreshAverageComputedTotal();
		averageProductSearchInput.focus();
	});

	averageApplyUnitSelect.addEventListener('change', refreshAverageComputedTotal);
	averageApplyQuantityInput.addEventListener('input', refreshAverageComputedTotal);
	averageApplyButton.addEventListener('click', function() {
		if (averageMode === 'attachment') {
			applyAverageToAttachment();
			return;
		}
		applyAverageToExpense();
	});

	salesForm.addEventListener('click', function(event) {
		const trigger = event.target.closest('.sale-app-summary');
		if (trigger) {
			toggleSaleAppDetail(trigger);
			return;
		}

		if (!event.target.closest('.sale-unit-cell')) {
			document.querySelectorAll('.sale-unit-cell.show-app-detail').forEach(function(openCell) {
				openCell.classList.remove('show-app-detail');
				const openButton = openCell.querySelector('.sale-app-summary');
				if (openButton) {
					openButton.setAttribute('aria-expanded', 'false');
				}
			});
		}
	});

	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape' && averageChatbox.classList.contains('is-open')) {
			toggleAverageChatbox(false);
		}
		if (event.key === 'Escape') {
			document.querySelectorAll('.sale-unit-cell.show-app-detail').forEach(function(openCell) {
				openCell.classList.remove('show-app-detail');
				const openButton = openCell.querySelector('.sale-app-summary');
				if (openButton) {
					openButton.setAttribute('aria-expanded', 'false');
				}
			});
		}
	});

	salesForm.addEventListener('submit', function(event) {
		syncAllSaleAppRows();
		calculateAppAttachmentTotal();
		calculateNetTotal();
	});

	updateAverageProductUi();
	updateAverageApplyUnitState();
	refreshAverageComputedTotal();
	getSaleRows().forEach(function(row) {
		syncSaleUnitControls(row);
	});
	syncAllSaleAppRows();
	calculateSaleTotal();
	calculateExpenseTotal();
</script>
