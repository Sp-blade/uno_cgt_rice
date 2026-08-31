<?php
	$searchProduct = isset($_GET['searchProduct']) ? trim((string) $_GET['searchProduct']) : '';
	$safeSearchProduct = mysqli_real_escape_string($connectDB, $searchProduct);
	$hideSyncedTankProductsSql = "UPPER(COALESCE(p.ProductType, '')) <> 'LPG TANK'";

	$allProductSql = "
		SELECT
			p.*,
			COALESCE(parent.ProductName, '') AS ParentProductName
		FROM products p
		LEFT JOIN products parent ON parent.Product_ID = p.ParentProduct_ID
	";
	if ($safeSearchProduct !== '') {
		$allProductSql .= " WHERE ($hideSyncedTankProductsSql) AND (p.ProductName LIKE '%$safeSearchProduct%' OR p.ProductType LIKE '%$safeSearchProduct%' OR p.ProductBaseUnit LIKE '%$safeSearchProduct%' OR parent.ProductName LIKE '%$safeSearchProduct%')";
	} else {
		$allProductSql .= " WHERE $hideSyncedTankProductsSql";
	}
	$allProductSql .= " ORDER BY COALESCE(NULLIF(p.ParentProduct_ID, 0), p.Product_ID) ASC, p.IsSubProduct ASC, p.ProductName ASC";
	$productListResult = $connectDB->query($allProductSql);

	$products = [];
	if ($productListResult && $productListResult->num_rows > 0) {
		while ($row = $productListResult->fetch_assoc()) {
			$row['Product_ID'] = (int) ($row['Product_ID'] ?? 0);
			$row['ProductName'] = trim((string) ($row['ProductName'] ?? ''));
			$row['ParentProductName'] = trim((string) ($row['ParentProductName'] ?? ''));
			$row['ProductType'] = trim((string) ($row['ProductType'] ?? ''));
			$row['ProductBaseUnit'] = junkshop_normalize_base_unit($row['ProductBaseUnit'] ?? 'pc');
			$row['ProductPrice'] = (float) ($row['ProductPrice'] ?? 0);
			$row['SellingPrice'] = (float) ($row['SellingPrice'] ?? 0);
			$row['AlternateSellingPrice'] = (float) ($row['AlternateSellingPrice'] ?? 0);
			$row['LpgRefillPrice'] = (float) ($row['LpgRefillPrice'] ?? 0);
			$row['LpgNewTankPrice'] = (float) ($row['LpgNewTankPrice'] ?? 0);
			$row['IsLpgProduct'] = junkshop_product_is_lpg($connectDB, (int) ($row['Product_ID'] ?? 0), $row['ProductName']);
			$row['StockLimit'] = (float) ($row['StockLimit'] ?? 0);
			$row['CanConvertToKg'] = (int) ($row['CanConvertToKg'] ?? 0);
			$row['KgEquivalentQty'] = (float) ($row['KgEquivalentQty'] ?? 0);
			$alternateSaleUnit = trim((string) ($row['AlternateSaleUnit'] ?? ''));
			$row['AlternateSaleUnit'] = $alternateSaleUnit !== '' ? junkshop_normalize_base_unit($alternateSaleUnit) : '';
			$row['ParentProduct_ID'] = (int) ($row['ParentProduct_ID'] ?? 0);
			$row['IsSubProduct'] = (int) ($row['IsSubProduct'] ?? 0);
			$row['IsActive'] = (int) ($row['IsActive'] ?? 1);
			$products[] = $row;
		}
	}

	$parentProductOptions = [];
	$parentProductResult = $connectDB->query("SELECT Product_ID, ProductName FROM products WHERE COALESCE(IsSubProduct, 0) = 0 ORDER BY ProductName ASC");
	if ($parentProductResult && $parentProductResult->num_rows > 0) {
		while ($row = $parentProductResult->fetch_assoc()) {
			$parentProductOptions[] = [
				'id' => (int) ($row['Product_ID'] ?? 0),
				'name' => trim((string) ($row['ProductName'] ?? '')),
			];
		}
	}

	$productTypeSuggestions = ['Rice', 'LPG', 'Frozen Foods', 'Eggs'];
	$baseUnitOptions = junkshop_base_unit_options();
	$productTypeResult = $connectDB->query("SELECT DISTINCT ProductType FROM products WHERE ProductType <> '' ORDER BY ProductType ASC");
	if ($productTypeResult && $productTypeResult->num_rows > 0) {
		while ($row = $productTypeResult->fetch_assoc()) {
			$productType = trim((string) ($row['ProductType'] ?? ''));
			if ($productType !== '' && !in_array($productType, $productTypeSuggestions, true)) {
				$productTypeSuggestions[] = $productType;
			}
		}
	}

	$activeExportProducts = [];
	$activeExportResult = $connectDB->query("
		SELECT *
		FROM products
		WHERE IsActive = 1
			AND COALESCE(IsSubProduct, 0) = 0
			AND UPPER(COALESCE(ProductType, '')) <> 'LPG TANK'
		ORDER BY ProductType ASC, ProductName ASC
	");
	if ($activeExportResult && $activeExportResult->num_rows > 0) {
		while ($row = $activeExportResult->fetch_assoc()) {
			$productName = trim((string) ($row['ProductName'] ?? ''));
			if ($productName === '') {
				continue;
			}

			$productType = trim((string) ($row['ProductType'] ?? ''));
			$productUnit = junkshop_normalize_base_unit($row['ProductBaseUnit'] ?? 'pc');
			$isLpgProduct = junkshop_product_is_lpg($connectDB, (int) ($row['Product_ID'] ?? 0), $productName);
			$baseSellingPrice = $isLpgProduct
				? ((float) ($row['LpgRefillPrice'] ?? 0) > 0 ? (float) $row['LpgRefillPrice'] : (float) ($row['SellingPrice'] ?? 0))
				: (float) ($row['SellingPrice'] ?? 0);
			$canConvert = junkshop_can_convert_units(
				$productUnit,
				(int) ($row['CanConvertToKg'] ?? 0),
				(float) ($row['KgEquivalentQty'] ?? 0),
				trim((string) ($row['AlternateSaleUnit'] ?? ''))
			);
			$alternateUnit = junkshop_get_alternate_sale_unit($productUnit, trim((string) ($row['AlternateSaleUnit'] ?? '')));
			$equivQty = (float) ($row['KgEquivalentQty'] ?? 0);
			$posterQty = rtrim(rtrim(number_format($equivQty, 2, '.', ''), '0'), '.');
			$unitDetail = '1 ' . junkshop_unit_label($productUnit);
			if ($canConvert && $alternateUnit !== null && $posterQty !== '') {
				$unitDetail .= ' / ' . $posterQty . ' ' . junkshop_unit_label($alternateUnit);
			}

			$activeExportProducts[] = [
				'id' => (int) ($row['Product_ID'] ?? 0),
				'name' => $productName,
				'type' => $productType !== '' ? $productType : 'Products',
				'unit' => $productUnit,
				'unit_label' => junkshop_unit_label($productUnit),
				'unit_detail' => $unitDetail,
				'price' => round($baseSellingPrice, 2),
			];
		}
	}

	$productPriceListConfig = [
		'companyName' => $companyName,
		'addressLine1' => $companyAddressLine1,
		'addressLine2' => $companyAddressLine2,
		'contactNumber' => $companyContactNumber,
		'generatedDate' => date('F j, Y'),
		'products' => $activeExportProducts,
		'catalogTitle' => 'Price List',
		'priceLabel' => 'Selling Price',
		'fileSlug' => 'product-price-list',
	];
?>

<style>
	.product-list-reorder-note {
		margin: 16px 0 0;
		padding: 12px 14px;
		border: 1px solid #d7dde5;
		border-radius: 12px;
		background: #f8fafc;
		color: #4f5b67;
	}

	.product-list-row {
		cursor: move;
	}

	.product-list-row.is-dragging {
		opacity: 0.45;
	}

	.product-list-row.drag-target {
		outline: 2px dashed #1f6fb2;
		outline-offset: -2px;
		background: #eef6ff;
	}

	.product-list-drag-cell {
		width: 42px;
		text-align: center;
		color: #6c7a89;
		font-weight: 700;
		letter-spacing: 1px;
		cursor: grab;
		user-select: none;
	}

	.product-list-drag-cell:active {
		cursor: grabbing;
	}

	.product-list-actions-cell {
		cursor: default;
	}
</style>

<div class="dashboard-card">
	<div class="dashboard-list-head">
		<div>
			<p class="section-kicker">Inventory management</p>
			<h3>Product List</h3>
		</div>
		<div class="d-flex flex-wrap justify-content-end gap-2">
			<button type="button" class="btn btn-primary" onclick="downloadStyledProductPriceListJpg()">
				<i class="bi bi-image"></i>
				<span>Download JPG Pages</span>
			</button>
			<button data-bs-toggle="modal" data-bs-target="#addNewProduct" class="btn btn-info">Add Product</button>
		</div>
	</div>

	<form method="GET" class="list-toolbar">
		<input type="hidden" name="mainmenu" value="product_list" />
		<input type="text" class="searchbox" name="searchProduct" placeholder="Search Product / Category / Unit" value="<?php echo htmlspecialchars($searchProduct); ?>" />
		<button type="submit" class="btn btn-primary">Search</button>
	</form>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover product-list-table">
				<thead>
					<tr>
						<th>Move</th>
						<th>No</th>
						<th>Product Name</th>
						<th>Type / Category</th>
						<th>Base Unit</th>
						<th>Base Selling Price</th>
						<th>Alternate / LPG Price</th>
						<th>Stock Limit (Base Unit)</th>
						<th>Unit Conversion</th>
						<th class="text-center">Action</th>
						<th class="text-center">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if (count($products) === 0): ?>
						<tr>
							<td colspan="11" class="empty-state">No products matched your search.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($products as $index => $Product): ?>
							<?php
								$alternateUnitForProduct = junkshop_get_alternate_sale_unit($Product['ProductBaseUnit'], $Product['AlternateSaleUnit']);
								$posterBaseSellingPrice = !empty($Product['IsLpgProduct'])
									? ($Product['LpgRefillPrice'] > 0 ? $Product['LpgRefillPrice'] : $Product['SellingPrice'])
									: $Product['SellingPrice'];
								$posterCanConvert = junkshop_can_convert_units(
									$Product['ProductBaseUnit'],
									$Product['CanConvertToKg'],
									$Product['KgEquivalentQty'],
									$Product['AlternateSaleUnit']
								);
								$posterQty = rtrim(rtrim(number_format($Product['KgEquivalentQty'], 2, '.', ''), '0'), '.');
								$posterUnitDetail = '1 ' . junkshop_unit_label($Product['ProductBaseUnit']);
								if ($posterCanConvert && $alternateUnitForProduct !== null && $posterQty !== '') {
									$posterUnitDetail .= ' / ' . $posterQty . ' ' . junkshop_unit_label($alternateUnitForProduct);
								}
							?>
							<tr
								class="product-list-row"
								data-product-id="<?php echo (int) $Product['Product_ID']; ?>"
								data-product-name="<?php echo htmlspecialchars($Product['ProductName'], ENT_QUOTES); ?>"
								data-product-type="<?php echo htmlspecialchars($Product['ProductType'] !== '' ? $Product['ProductType'] : 'Products', ENT_QUOTES); ?>"
								data-product-unit="<?php echo htmlspecialchars($Product['ProductBaseUnit'], ENT_QUOTES); ?>"
								data-unit-label="<?php echo htmlspecialchars(junkshop_unit_label($Product['ProductBaseUnit']), ENT_QUOTES); ?>"
								data-unit-detail="<?php echo htmlspecialchars($posterUnitDetail, ENT_QUOTES); ?>"
								data-base-selling-price="<?php echo htmlspecialchars((string) round($posterBaseSellingPrice, 2), ENT_QUOTES); ?>"
								data-selling-price="<?php echo htmlspecialchars((string) round($Product['SellingPrice'], 2), ENT_QUOTES); ?>"
								data-alternate-selling-price="<?php echo htmlspecialchars((string) round($Product['AlternateSellingPrice'], 2), ENT_QUOTES); ?>"
								data-lpg-refill-price="<?php echo htmlspecialchars((string) round($Product['LpgRefillPrice'] > 0 ? $Product['LpgRefillPrice'] : $Product['SellingPrice'], 2), ENT_QUOTES); ?>"
								data-lpg-new-tank-price="<?php echo htmlspecialchars((string) round($Product['LpgNewTankPrice'] > 0 ? $Product['LpgNewTankPrice'] : $Product['SellingPrice'], 2), ENT_QUOTES); ?>"
								data-is-lpg="<?php echo !empty($Product['IsLpgProduct']) ? '1' : '0'; ?>"
								data-stock-limit="<?php echo htmlspecialchars((string) round($Product['StockLimit'], 2), ENT_QUOTES); ?>"
								data-product-active="<?php echo (int) $Product['IsActive']; ?>"
								data-product-exportable="<?php echo ($Product['IsSubProduct'] === 0) ? '1' : '0'; ?>"
							>
								<td class="product-list-drag-cell" draggable="true" title="Drag to reorder">::</td>
								<td class="product-list-row-number"><?php echo $index + 1; ?></td>
								<td>
									<div class="d-flex flex-column gap-1">
										<span><?php echo htmlspecialchars($Product['ProductName']); ?></span>
									</div>
								</td>
								<td><?php echo htmlspecialchars($Product['ProductType'] !== '' ? $Product['ProductType'] : 'Uncategorized'); ?></td>
								<td>
									<?php
										$unitBadgeClass = 'text-bg-secondary';
										if ($Product['ProductBaseUnit'] === 'kg') {
											$unitBadgeClass = 'text-bg-success';
										} elseif ($Product['ProductBaseUnit'] === 'sack') {
											$unitBadgeClass = 'text-bg-primary';
										} elseif ($Product['ProductBaseUnit'] === 'tray') {
											$unitBadgeClass = 'text-bg-warning';
										} elseif ($Product['ProductBaseUnit'] === 'pack') {
											$unitBadgeClass = 'text-bg-info';
										} elseif ($Product['ProductBaseUnit'] === 'tank') {
											$unitBadgeClass = 'text-bg-dark';
										}
									?>
									<span class="badge <?php echo $unitBadgeClass; ?>">
										<?php echo junkshop_unit_label($Product['ProductBaseUnit']); ?>
									</span>
								</td>
								<td>
									<?php if (!empty($Product['IsLpgProduct'])): ?>
										<div>&#8369;<?php echo number_format($Product['LpgRefillPrice'] > 0 ? $Product['LpgRefillPrice'] : $Product['SellingPrice'], 2); ?></div>
										<small class="text-muted">Refill / Swap Out</small>
									<?php else: ?>
										<div>&#8369;<?php echo number_format($Product['SellingPrice'], 2); ?></div>
										<small class="text-muted"><?php echo htmlspecialchars(junkshop_unit_label($Product['ProductBaseUnit'])); ?> Selling Price</small>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($Product['IsLpgProduct'])): ?>
										<div>&#8369;<?php echo number_format($Product['LpgNewTankPrice'] > 0 ? $Product['LpgNewTankPrice'] : $Product['SellingPrice'], 2); ?></div>
										<small class="text-muted">New Tank with LPG</small>
									<?php elseif ($Product['CanConvertToKg'] === 1 && $Product['KgEquivalentQty'] > 0 && $alternateUnitForProduct !== null): ?>
										<div>&#8369;<?php echo number_format($Product['AlternateSellingPrice'], 2); ?></div>
										<small class="text-muted"><?php echo htmlspecialchars(junkshop_unit_label($alternateUnitForProduct)); ?> Selling Price</small>
									<?php else: ?>
										<span class="text-muted">Not set</span>
									<?php endif; ?>
								</td>
								<td><?php echo number_format($Product['StockLimit'], 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($Product['ProductBaseUnit'])); ?></td>
								<td>
									<?php if (!empty($Product['IsLpgProduct'])): ?>
										<span class="text-muted">LPG tank pricing</span>
									<?php elseif ($Product['CanConvertToKg'] === 1 && $Product['KgEquivalentQty'] > 0): ?>
										<span class="badge text-bg-info"><?php echo htmlspecialchars(junkshop_conversion_label($Product['ProductBaseUnit'], $Product['KgEquivalentQty'], $Product['AlternateSaleUnit'])); ?></span>
									<?php else: ?>
										<span class="text-muted">Not set</span>
									<?php endif; ?>
								</td>
								<td class="text-center product-list-actions-cell">
									<div class="icon-action-group justify-content-center">
										<button
											type="button"
											class="icon-action-btn icon-action-btn-edit"
											data-id="<?php echo $Product['Product_ID']; ?>"
											data-name="<?php echo htmlspecialchars($Product['ProductName']); ?>"
											data-type="<?php echo htmlspecialchars($Product['ProductType']); ?>"
											data-base-unit="<?php echo htmlspecialchars((string) $Product['ProductBaseUnit']); ?>"
											data-selling-price="<?php echo $Product['SellingPrice']; ?>"
											data-alternate-selling-price="<?php echo $Product['AlternateSellingPrice']; ?>"
											data-stock-limit="<?php echo $Product['StockLimit']; ?>"
											data-can-convert="<?php echo $Product['CanConvertToKg']; ?>"
											data-kg-equivalent="<?php echo $Product['KgEquivalentQty']; ?>"
											data-alternate-unit="<?php echo htmlspecialchars((string) $Product['AlternateSaleUnit'], ENT_QUOTES); ?>"
											data-lpg-refill-price="<?php echo $Product['LpgRefillPrice'] > 0 ? $Product['LpgRefillPrice'] : $Product['SellingPrice']; ?>"
											data-lpg-new-tank-price="<?php echo $Product['LpgNewTankPrice'] > 0 ? $Product['LpgNewTankPrice'] : $Product['SellingPrice']; ?>"
											data-is-lpg="<?php echo !empty($Product['IsLpgProduct']) ? '1' : '0'; ?>"
											data-parent-id="<?php echo (int) $Product['ParentProduct_ID']; ?>"
											data-scope="<?php echo ($Product['IsSubProduct'] === 1) ? 'sub' : 'purchase'; ?>"
											aria-label="Edit <?php echo htmlspecialchars($Product['ProductName']); ?>"
											title="Edit"
										>
											<i class="bi bi-pencil-square" aria-hidden="true"></i>
										</button>
									</div>
								</td>
								<td class="text-center">
									<form method="GET" class="d-inline-flex align-items-center gap-2">
										<input type="hidden" name="mainmenu" value="product_list" />
										<input type="hidden" name="toggle_product_status" value="1" />
										<input type="hidden" name="product_id" value="<?php echo (int) $Product['Product_ID']; ?>" />
										<input type="hidden" name="target_active" value="<?php echo (int) (!$Product['IsActive']); ?>" />
										<div class="form-check form-switch m-0 d-inline-flex align-items-center">
											<input
												class="form-check-input"
												type="checkbox"
												role="switch"
												<?php echo ($Product['IsActive'] === 1) ? 'checked' : ''; ?>
												onchange="this.form.querySelector('input[name=&quot;target_active&quot;]').value = this.checked ? '1' : '0'; this.form.submit();"
											/>
										</div>
										<span class="badge <?php echo ($Product['IsActive'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
											<?php echo ($Product['IsActive'] === 1) ? 'Active' : 'Inactive'; ?>
										</span>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
	const productPriceListConfig = <?php echo json_encode($productPriceListConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const productListOrderStorageKey = 'junkshop_product_list_order_v2';
	const parentProductLookup = <?php echo json_encode($parentProductOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.reduce(function(map, product) {
		map[String(product.id)] = product;
		return map;
	}, {});

	$(document).ready(function () {
		function populateEditProductModal(button) {
			var productId = button.attr('data-id') || button.data('id');
			var productName = button.attr('data-name') || button.data('name') || '';
			var productType = button.attr('data-type') || button.data('type') || '';
			var productBaseUnit = button.attr('data-base-unit') || 'pc';
			var canConvert = button.attr('data-can-convert') || '0';
			var kgEquivalent = button.attr('data-kg-equivalent') || '0';
			var alternateUnit = button.attr('data-alternate-unit') || '';
			var sellingPrice = button.attr('data-selling-price') || 0;
			var alternateSellingPrice = button.attr('data-alternate-selling-price') || 0;
			var lpgRefillPrice = button.attr('data-lpg-refill-price') || 0;
			var lpgNewTankPrice = button.attr('data-lpg-new-tank-price') || 0;
			var isLpgProduct = String(button.attr('data-is-lpg') || '0') === '1';
			var stockLimit = button.attr('data-stock-limit') || 0;

			var modal = $('#editProductDetails');
			modal.find('input[name="productID"]').val(productId);
			modal.find('input[name="product_name"]').val(productName);
			modal.find('input[name="product_type"]').val(productType);
			modal.find('select[name="product_base_unit"]').val(productBaseUnit);
			modal.find('input[name="can_convert_to_kg"][type="checkbox"]').prop('checked', Number(canConvert) === 1);
			modal.find('input[name="kg_equivalent_qty"]').val(kgEquivalent);
			modal.find('select[name="alternate_sale_unit"]').val(alternateUnit);
			modal.find('input[name="selling_price"]').val(sellingPrice);
			modal.find('input[name="alternate_selling_price"]').val(alternateSellingPrice);
			modal.find('input[name="lpg_refill_price"]').val(lpgRefillPrice);
			modal.find('input[name="lpg_new_tank_price"]').val(lpgNewTankPrice);
			modal.find('input[name="stock_limit"]').val(stockLimit);
			toggleProductLpgPricing(modal[0], productType || (isLpgProduct ? 'LPG' : ''));
			toggleProductConversionFields(modal[0]);
		}

		$(document).on('click', '.icon-action-btn-edit', function (event) {
			event.preventDefault();
			event.stopPropagation();
			var button = $(this);
			var modalElement = document.getElementById('editProductDetails');
			if (!modalElement || typeof bootstrap === 'undefined') {
				return;
			}
			populateEditProductModal(button);
			bootstrap.Modal.getOrCreateInstance(modalElement).show();
		});

		$('#editProductDetails').on('show.bs.modal', function (event) {
			var trigger = event.relatedTarget;
			if (!trigger || !trigger.classList.contains('icon-action-btn-edit')) {
				return;
			}
			populateEditProductModal($(trigger));
		});

		$('#addNewProduct').on('show.bs.modal', function () {
			var modal = $(this);
			modal.find('input[name="selling_price"]').val('');
			modal.find('input[name="alternate_selling_price"]').val('');
			modal.find('input[name="lpg_refill_price"]').val('');
			modal.find('input[name="lpg_new_tank_price"]').val('');
			modal.find('input[name="stock_limit"]').val('0');
			modal.find('select[name="product_base_unit"]').val('pc');
			modal.find('select[name="alternate_sale_unit"]').val('');
			modal.find('input[name="can_convert_to_kg"][type="checkbox"]').prop('checked', false);
			modal.find('input[name="kg_equivalent_qty"]').val('0');
			toggleProductLpgPricing(modal[0], modal.find('input[name="product_type"]').val());
			toggleProductConversionFields(modal[0]);
		});

		$(document).on('input change', '#edit_product_type, #new_product_type', function () {
			var modalElement = this.closest('.modal');
			toggleProductLpgPricing(modalElement, this.value);
			toggleProductConversionFields(modalElement);
		});

		function isFieldVisibleForValidation(field) {
			if (!field || field.type === 'hidden' || field.disabled) {
				return false;
			}

			var node = field;
			while (node && node !== document.body) {
				var style = window.getComputedStyle(node);
				if (style.display === 'none' || style.visibility === 'hidden') {
					return false;
				}
				node = node.parentElement;
			}

			return field.offsetParent !== null || field.getClientRects().length > 0;
		}

		function prepareProductFormForSubmit(form) {
			if (!form) {
				return;
			}

			form.querySelectorAll('[required]').forEach(function (field) {
				if (!isFieldVisibleForValidation(field)) {
					field.removeAttribute('required');
				}
			});

			var typeInput = form.querySelector('input[name="product_type"]');
			if (typeInput && String(typeInput.value || '').trim().toUpperCase() === 'LPG') {
				var refillInput = form.querySelector('input[name="lpg_refill_price"]');
				var sellingInput = form.querySelector('input[name="selling_price"]');
				if (refillInput && sellingInput) {
					sellingInput.value = refillInput.value;
				}
			}

			var convertCheckbox = form.querySelector('input[name="can_convert_to_kg"][type="checkbox"]');
			if (convertCheckbox && !convertCheckbox.checked) {
				var alternateUnitSelect = form.querySelector('select[name="alternate_sale_unit"]');
				var conversionQtyInput = form.querySelector('input[name="kg_equivalent_qty"]');
				var alternatePriceInput = form.querySelector('input[name="alternate_selling_price"]');
				if (alternateUnitSelect) {
					alternateUnitSelect.value = '';
				}
				if (conversionQtyInput) {
					conversionQtyInput.value = '0';
				}
				if (alternatePriceInput) {
					alternatePriceInput.value = '';
				}
			}
		}

		$(document).on('submit', '#editProductDetails form, #addNewProduct form', function () {
			prepareProductFormForSubmit(this);
		});

		$(document).on('change', '#edit_product_base_unit, #new_product_base_unit, #edit_can_convert_to_kg, #new_can_convert_to_kg, #edit_alternate_sale_unit, #new_alternate_sale_unit', function () {
			toggleProductConversionFields(this.closest('.modal'));
		});

		initializeProductListSorting();
	});

	function formatProductCurrency(value) {
		var amount = Number(value || 0);
		return '\u20B1' + amount.toLocaleString('en-PH', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function slugifyFileName(value) {
		return String(value || 'price-list')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '')
			|| 'price-list';
	}

	function getUnitLabel(unit) {
		var labels = { pc: 'Pcs', kg: 'KG', sack: 'Sack', tray: 'Tray', pack: 'Pack', tank: 'Tank' };
		var normalized = String(unit || 'pc').toLowerCase();
		return labels[normalized] || normalized.toUpperCase();
	}

	function getAlternateUnitLabel(baseUnit, alternateUnit) {
		var baseLabel = getUnitLabel(baseUnit);
		var alternateLabel = getUnitLabel(alternateUnit);
		if (!alternateUnit) {
			return 'Alternate Qty';
		}
		if (String(baseUnit).toLowerCase() === 'pc' && String(alternateUnit).toLowerCase() === 'kg') {
			return alternateLabel + ' per ' + baseLabel;
		}
		return alternateLabel + ' per ' + baseLabel;
	}

	function getBasePriceLabel(baseUnit) {
		var normalized = String(baseUnit || 'pc').toLowerCase();
		var labels = { pc: 'Pcs Selling Price', kg: 'KG Selling Price', sack: 'Sack Selling Price', tray: 'Tray Selling Price', pack: 'Pack Selling Price', tank: 'Tank Selling Price' };
		return labels[normalized] || 'Base Unit Selling Price';
	}

	function getAlternatePriceLabel(alternateUnit) {
		return getUnitLabel(alternateUnit) + ' Selling Price';
	}

	function refreshAlternateUnitOptions(modalElement, baseUnit, selectedAlternateUnit) {
		var alternateSelect = modalElement.querySelector('select[name="alternate_sale_unit"]');
		if (!alternateSelect) {
			return;
		}

		var normalizedBase = String(baseUnit || 'pc').toLowerCase();
		var units = ['pc', 'kg', 'sack', 'tray', 'pack', 'tank'];
		var html = '<option value="">Select unit</option>';
		units.forEach(function (unit) {
			if (unit === normalizedBase) {
				return;
			}
			html += '<option value="' + unit + '">' + getUnitLabel(unit) + '</option>';
		});
		alternateSelect.innerHTML = html;
		if (selectedAlternateUnit && selectedAlternateUnit !== normalizedBase) {
			alternateSelect.value = selectedAlternateUnit;
		}
	}

	function toggleProductLpgPricing(modalElement, productType) {
		if (!modalElement) {
			return;
		}
		var isLpg = String(productType || '').trim().toUpperCase() === 'LPG';
		var lpgWrap = modalElement.querySelector('.product-lpg-pricing-wrap');
		var standardPriceWrap = modalElement.querySelector('.product-standard-pricing-wrap');
		var alternatePriceWrap = modalElement.querySelector('.product-alternate-price-wrap');
		var conversionWrap = modalElement.querySelector('.product-conversion-wrap');
		if (lpgWrap) {
			lpgWrap.style.display = isLpg ? 'block' : 'none';
		}
		if (standardPriceWrap) {
			standardPriceWrap.style.display = isLpg ? 'none' : 'block';
		}
		if (isLpg && conversionWrap) {
			conversionWrap.style.display = 'none';
		}
		if (isLpg && alternatePriceWrap) {
			alternatePriceWrap.style.display = 'none';
		}
		var refillInput = modalElement.querySelector('input[name="lpg_refill_price"]');
		var newTankInput = modalElement.querySelector('input[name="lpg_new_tank_price"]');
		if (refillInput) {
			refillInput.required = isLpg;
		}
		if (newTankInput) {
			newTankInput.required = isLpg;
		}
		var sellingInput = modalElement.querySelector('input[name="selling_price"]');
		if (sellingInput) {
			sellingInput.required = !isLpg;
		}
	}

	function toggleProductConversionFields(modalElement) {
		if (!modalElement) {
			return;
		}

		var baseUnitSelect = modalElement.querySelector('select[name="product_base_unit"]');
		var conversionWrap = modalElement.querySelector('.product-conversion-wrap');
		var conversionHint = modalElement.querySelector('.product-conversion-hint');
		var conversionLabel = modalElement.querySelector('.product-conversion-label');
		var alternateUnitWrap = modalElement.querySelector('.product-alternate-unit-wrap');
		var basePriceLabel = modalElement.querySelector('.product-base-price-label');
		var alternatePriceWrap = modalElement.querySelector('.product-alternate-price-wrap');
		var alternatePriceLabel = modalElement.querySelector('.product-alternate-price-label');
		var alternatePriceInput = modalElement.querySelector('input[name="alternate_selling_price"]');
		var alternateUnitSelect = modalElement.querySelector('select[name="alternate_sale_unit"]');
		var convertCheckbox = modalElement.querySelector('input[name="can_convert_to_kg"][type="checkbox"]');
		var conversionQtyWrap = modalElement.querySelector('.product-conversion-qty-wrap');
		var conversionQtyInput = modalElement.querySelector('input[name="kg_equivalent_qty"]');
		if (!baseUnitSelect || !conversionWrap) {
			return;
		}

		var baseUnit = String(baseUnitSelect.value || 'pc').toLowerCase();
		var productTypeInput = modalElement.querySelector('input[name="product_type"]');
		var isLpg = productTypeInput && String(productTypeInput.value || '').trim().toUpperCase() === 'LPG';
		if (isLpg) {
			conversionWrap.style.display = 'none';
			if (alternatePriceWrap) {
				alternatePriceWrap.style.display = 'none';
			}
			return;
		}
		var selectedAlternateUnit = alternateUnitSelect ? String(alternateUnitSelect.value || '').toLowerCase() : '';
		refreshAlternateUnitOptions(modalElement, baseUnit, selectedAlternateUnit);
		selectedAlternateUnit = alternateUnitSelect ? String(alternateUnitSelect.value || '').toLowerCase() : '';

		var conversionEnabled = !!convertCheckbox && convertCheckbox.checked;
		var hasAlternateUnit = selectedAlternateUnit !== '' && selectedAlternateUnit !== baseUnit;
		conversionWrap.style.display = 'block';
		if (alternateUnitWrap) {
			alternateUnitWrap.style.display = conversionEnabled ? 'block' : 'none';
		}
		if (conversionQtyWrap) {
			conversionQtyWrap.style.display = conversionEnabled ? 'block' : 'none';
		}
		if (conversionQtyInput) {
			conversionQtyInput.required = conversionEnabled && hasAlternateUnit;
			if (!conversionEnabled) {
				conversionQtyInput.value = '0';
			}
		}
		if (basePriceLabel) {
			basePriceLabel.textContent = getBasePriceLabel(baseUnit);
		}
		if (alternatePriceWrap) {
			alternatePriceWrap.style.display = conversionEnabled && hasAlternateUnit ? 'block' : 'none';
		}
		if (alternatePriceLabel) {
			alternatePriceLabel.textContent = hasAlternateUnit ? getAlternatePriceLabel(selectedAlternateUnit) : 'Alternate Unit Selling Price';
		}
		if (alternatePriceInput) {
			alternatePriceInput.required = conversionEnabled && hasAlternateUnit;
			if (!conversionEnabled) {
				alternatePriceInput.value = '';
			}
		}
		if (alternateUnitSelect) {
			alternateUnitSelect.required = conversionEnabled;
		}
		if (conversionHint) {
			if (conversionEnabled && hasAlternateUnit) {
				if (baseUnit === 'pc' && selectedAlternateUnit === 'kg') {
					conversionHint.textContent = 'Example: 1 KG = 50 Pcs lets you sell by kilogram or by piece.';
				} else {
					conversionHint.textContent = 'Example: 1 ' + getUnitLabel(baseUnit) + ' = 50 ' + getUnitLabel(selectedAlternateUnit) + ' lets you sell using either unit.';
				}
			} else if (conversionEnabled) {
				conversionHint.textContent = 'Choose which unit this product can also be sold in.';
			} else {
				conversionHint.textContent = 'Enable this if the product can be sold in another unit.';
			}
		}
		if (conversionLabel) {
			conversionLabel.textContent = hasAlternateUnit
				? getAlternateUnitLabel(baseUnit, selectedAlternateUnit)
				: 'Conversion Qty';
		}
	}

	function drawRoundedRectangle(ctx, x, y, width, height, radius) {
		var safeRadius = Math.min(radius, width / 2, height / 2);
		ctx.beginPath();
		ctx.moveTo(x + safeRadius, y);
		ctx.lineTo(x + width - safeRadius, y);
		ctx.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
		ctx.lineTo(x + width, y + height - safeRadius);
		ctx.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
		ctx.lineTo(x + safeRadius, y + height);
		ctx.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
		ctx.lineTo(x, y + safeRadius);
		ctx.quadraticCurveTo(x, y, x + safeRadius, y);
		ctx.closePath();
	}

	function getActivePriceListProducts() {
		var activeProducts = getActivePriceListProductsFromTable();
		if (activeProducts.length > 0) {
			return activeProducts;
		}

		if (!productPriceListConfig || !Array.isArray(productPriceListConfig.products)) {
			return [];
		}

		activeProducts = productPriceListConfig.products.filter(function (product) {
			return product && String(product.name || '').trim() !== '';
		});

		return sortProductsBySavedOrder(activeProducts);
	}

	function getActivePriceListProductsFromTable() {
		var rows = Array.prototype.slice.call(document.querySelectorAll('.product-list-table .product-list-row'));
		if (!rows.length) {
			return [];
		}

		return rows.map(function (row) {
			var dataset = row.dataset || {};
			return {
				id: Number(dataset.productId || 0),
				name: String(dataset.productName || '').trim(),
				type: String(dataset.productType || 'Products').trim() || 'Products',
				unit: String(dataset.productUnit || 'pc').toLowerCase(),
				unit_label: String(dataset.unitLabel || 'Pcs').trim() || 'Pcs',
				unit_detail: String(dataset.unitDetail || '').trim(),
				price: Number(dataset.baseSellingPrice || dataset.sellingPrice || 0),
				active: Number(dataset.productActive || 0),
				exportable: Number(dataset.productExportable || 0)
			};
		}).filter(function (product) {
			return product.name !== '' && product.active === 1 && product.exportable === 1;
		});
	}

	function getSavedProductOrder() {
		try {
			var savedOrder = JSON.parse(localStorage.getItem(productListOrderStorageKey) || '[]');
			return Array.isArray(savedOrder) ? savedOrder.map(String) : [];
		} catch (error) {
			return [];
		}
	}

	function sortProductsBySavedOrder(products) {
		var savedOrder = getSavedProductOrder();
		if (!savedOrder.length) {
			return products.slice();
		}

		var orderMap = {};
		savedOrder.forEach(function (id, index) {
			orderMap[String(id)] = index;
		});

		return products.slice().sort(function (left, right) {
			var leftId = String(left.id || '');
			var rightId = String(right.id || '');
			var leftIndex = Object.prototype.hasOwnProperty.call(orderMap, leftId) ? orderMap[leftId] : Number.MAX_SAFE_INTEGER;
			var rightIndex = Object.prototype.hasOwnProperty.call(orderMap, rightId) ? orderMap[rightId] : Number.MAX_SAFE_INTEGER;

			if (leftIndex !== rightIndex) {
				return leftIndex - rightIndex;
			}

			return String(left.name || '').localeCompare(String(right.name || ''));
		});
	}

	function initializeProductListSorting() {
		var tableBody = document.querySelector('.product-list-table tbody');
		if (!tableBody) {
			return;
		}

		var draggedRow = null;

		function getRows() {
			return Array.prototype.slice.call(tableBody.querySelectorAll('.product-list-row'));
		}

		function getRowFromNode(node) {
			if (!node || !node.closest) {
				return null;
			}
			return node.closest('.product-list-row');
		}

		function updateRowNumbers() {
			getRows().forEach(function (row, index) {
				var numberCell = row.querySelector('.product-list-row-number');
				if (numberCell) {
					numberCell.textContent = index + 1;
				}
			});
		}

		function saveOrder() {
			var currentOrder = getRows().map(function (row) {
				return String(row.getAttribute('data-product-id') || '');
			}).filter(function (id) {
				return id !== '';
			});
			localStorage.setItem(productListOrderStorageKey, JSON.stringify(currentOrder));
		}

		function loadSavedOrder() {
			var savedOrder = getSavedProductOrder();
			if (!savedOrder.length) {
				updateRowNumbers();
				return;
			}

			var rowMap = {};
			getRows().forEach(function (row) {
				rowMap[String(row.getAttribute('data-product-id') || '')] = row;
			});

			savedOrder.forEach(function (id) {
				if (rowMap[id]) {
					tableBody.appendChild(rowMap[id]);
					delete rowMap[id];
				}
			});

			Object.keys(rowMap).forEach(function (id) {
				tableBody.appendChild(rowMap[id]);
			});

			updateRowNumbers();
		}

		function clearDragState() {
			getRows().forEach(function (row) {
				row.classList.remove('is-dragging');
				row.classList.remove('drag-target');
			});
		}

		tableBody.querySelectorAll('.product-list-drag-cell').forEach(function (handle) {
			handle.addEventListener('dragstart', function (event) {
				draggedRow = getRowFromNode(handle);
				if (!draggedRow) {
					return;
				}
				event.stopPropagation();
				draggedRow.classList.add('is-dragging');
				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData('text/plain', draggedRow.getAttribute('data-product-id') || '');
				}
			});

			handle.addEventListener('dragend', function () {
				draggedRow = null;
				clearDragState();
				updateRowNumbers();
				saveOrder();
			});
		});

		getRows().forEach(function (row) {
			row.addEventListener('dragover', function (event) {
				event.preventDefault();
				if (!draggedRow || draggedRow === row) {
					return;
				}

				clearDragState();
				row.classList.add('drag-target');

				var rowBounds = row.getBoundingClientRect();
				var insertBefore = event.clientY < rowBounds.top + (rowBounds.height / 2);

				if (insertBefore) {
					tableBody.insertBefore(draggedRow, row);
				} else {
					tableBody.insertBefore(draggedRow, row.nextSibling);
				}
			});

			row.addEventListener('drop', function (event) {
				event.preventDefault();
				clearDragState();
				updateRowNumbers();
				saveOrder();
			});
		});

		loadSavedOrder();
	}

	function chunkPriceListProducts(products, itemsPerChunk) {
		var chunks = [];
		for (var index = 0; index < products.length; index += itemsPerChunk) {
			chunks.push(products.slice(index, index + itemsPerChunk));
		}
		return chunks;
	}

	function wrapPosterText(ctx, text, maxWidth, maxLines) {
		var words = String(text || '').split(/\s+/).filter(function (word) {
			return word !== '';
		});
		var lines = [];
		var currentLine = '';

		if (words.length === 0) {
			return [''];
		}

		words.forEach(function (word) {
			var testLine = currentLine ? currentLine + ' ' + word : word;
			if (ctx.measureText(testLine).width <= maxWidth || currentLine === '') {
				currentLine = testLine;
				return;
			}

			lines.push(currentLine);
			currentLine = word;
		});

		if (currentLine) {
			lines.push(currentLine);
		}

		if (typeof maxLines === 'number' && maxLines > 0 && lines.length > maxLines) {
			lines = lines.slice(0, maxLines);
			var lastLine = lines[maxLines - 1];
			while (lastLine.length > 0 && ctx.measureText(lastLine + '...').width > maxWidth) {
				lastLine = lastLine.slice(0, -1).trim();
			}
			lines[maxLines - 1] = (lastLine || lines[maxLines - 1]) + '...';
		}

		return lines;
	}

	function formatPosterDownloadDate(dateValue) {
		var date = dateValue instanceof Date ? dateValue : new Date(dateValue);
		if (isNaN(date.getTime())) {
			return productPriceListConfig.generatedDate || '';
		}

		var monthNames = [
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
		];

		return monthNames[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
	}

	function drawStyledPosterPage(ctx, pageProducts, pageIndex, totalPages, downloadedDateLabel) {
		var width = 1080;
		var height = 1080;
		var sidePadding = 70;
		var topPadding = 74;
		var cardStartY = 286;
		var cardHeight = 136;
		var cardGap = 18;

		var gradient = ctx.createLinearGradient(0, 0, 0, height);
		gradient.addColorStop(0, '#fdfbf8');
		gradient.addColorStop(1, '#f5f1ea');
		ctx.fillStyle = gradient;
		ctx.fillRect(0, 0, width, height);

		ctx.fillStyle = 'rgba(0, 0, 0, 0.035)';
		ctx.beginPath();
		ctx.arc(width - 110, 118, 165, 0, Math.PI * 2);
		ctx.fill();

		ctx.beginPath();
		ctx.arc(126, height - 120, 140, 0, Math.PI * 2);
		ctx.fill();

		ctx.textBaseline = 'top';
		ctx.textAlign = 'center';
		ctx.fillStyle = '#2a2928';
		ctx.font = '700 88px Georgia';
		ctx.fillText(String(productPriceListConfig.catalogTitle || 'PRICE LIST').toUpperCase(), width / 2, topPadding);

		ctx.font = '600 30px Arial';
		ctx.fillStyle = '#4b5563';
		ctx.fillText(productPriceListConfig.companyName || 'Rice Trading Price List', width / 2, 182);

		ctx.font = '500 20px Arial';
		ctx.fillStyle = '#6b7280';
		ctx.fillText('Updated Pricelist as of ' + downloadedDateLabel, width / 2, 224);

		ctx.fillStyle = '#242424';
		drawRoundedRectangle(ctx, width - 184, 64, 112, 44, 22);
		ctx.fill();
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillStyle = '#ffffff';
		ctx.font = '700 22px Arial';
		ctx.fillText((pageIndex + 1) + ' / ' + totalPages, width - 128, 86);

		pageProducts.forEach(function (product, itemIndex) {
			var cardY = cardStartY + (itemIndex * (cardHeight + cardGap));
			var nameWidth = width - (sidePadding * 2) - 290;
			var unitDetail = String(product.unit_detail || ('1 ' + (product.unit_label || 'Pcs'))).trim();

			ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
			drawRoundedRectangle(ctx, sidePadding, cardY, width - (sidePadding * 2), cardHeight, 28);
			ctx.fill();

			ctx.fillStyle = '#e5ddd0';
			drawRoundedRectangle(ctx, sidePadding + 24, cardY + 18, 180, 30, 15);
			ctx.fill();
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillStyle = '#4b5563';
			ctx.font = '700 16px Arial';
			ctx.fillText(String(product.type || 'Uncategorized').toUpperCase(), sidePadding + 114, cardY + 33);

			ctx.textAlign = 'left';
			ctx.textBaseline = 'top';
			ctx.fillStyle = '#111827';
			ctx.font = '600 42px Arial';
			var nameLines = wrapPosterText(ctx, product.name, nameWidth, 2);
			nameLines.forEach(function (line, lineIndex) {
				ctx.fillText(line, sidePadding + 24, cardY + 54 + (lineIndex * 36));
			});

			ctx.font = '600 22px Arial';
			ctx.fillStyle = '#6b7280';
			ctx.fillText(unitDetail, sidePadding + 24, cardY + 98);

			ctx.textAlign = 'right';
			ctx.textBaseline = 'top';
			ctx.fillStyle = '#111827';
			ctx.font = '700 54px Arial';
			ctx.fillText(formatProductCurrency(product.price), width - sidePadding - 24, cardY + 44);

			ctx.font = '600 20px Arial';
			ctx.fillStyle = '#6b7280';
			ctx.fillText('per ' + String(product.unit_label || 'Pcs'), width - sidePadding - 24, cardY + 102);
		});

	}

	function downloadStyledProductPriceListJpg() {
		var products = getActivePriceListProducts();
		if (products.length === 0) {
			alert('No active products are available to export.');
			return;
		}

		var pages = chunkPriceListProducts(products, 5);
		var downloadedDateLabel = formatPosterDownloadDate(new Date());

		pages.forEach(function (pageProducts, pageIndex) {
			var canvas = document.createElement('canvas');
			canvas.width = 1080;
			canvas.height = 1080;

			var ctx = canvas.getContext('2d');
			if (!ctx) {
				return;
			}

			drawStyledPosterPage(ctx, pageProducts, pageIndex, pages.length, downloadedDateLabel);

			var downloadLink = document.createElement('a');
			downloadLink.href = canvas.toDataURL('image/jpeg', 0.94);
			downloadLink.download = slugifyFileName(productPriceListConfig.companyName) + '-' + slugifyFileName(productPriceListConfig.fileSlug || 'price-list') + '-' + (pageIndex + 1) + '.jpg';
			document.body.appendChild(downloadLink);
			setTimeout(function () {
				downloadLink.click();
				document.body.removeChild(downloadLink);
			}, pageIndex * 180);
		});
	}
</script>

<div class="modal fade" id="editProductDetails" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form class="form new-form" method="POST" action="index.php">
				<div class="modal-header">
					<h4 class="modal-title">Edit Product</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="mainmenu" value="product_list" />
					<input type="hidden" name="edit_product" value="edit_product" />
					<input type="hidden" name="productID" value="" />
					<?php if ($searchProduct !== ''): ?>
						<input type="hidden" name="searchProduct" value="<?php echo htmlspecialchars($searchProduct, ENT_QUOTES); ?>" />
					<?php endif; ?>

					<label for="edit_product_name" class="form-label">Product Name</label>
					<input type="text" class="form-control mb-1" id="edit_product_name" name="product_name" value="" required />
					<small class="text-muted d-block mb-3">Product ID stays the same when you rename. Past receipts keep the name recorded at the time of sale.</small>

					<label for="edit_product_type" class="form-label">Type / Category</label>
					<input type="text" class="form-control mb-3" id="edit_product_type" name="product_type" list="product_type_suggestions" placeholder="Rice, LPG, Frozen Foods, Eggs" required />

					<label for="edit_product_base_unit" class="form-label">Base Unit</label>
					<select class="form-select mb-3" id="edit_product_base_unit" name="product_base_unit" required>
						<option value="pc">Pcs</option>
						<option value="kg">KG</option>
						<option value="sack">Sack</option>
						<option value="tray">Tray</option>
						<option value="pack">Pack</option>
						<option value="tank">Tank</option>
					</select>

					<div class="product-conversion-wrap mb-3">
						<input type="hidden" name="can_convert_to_kg" value="0" />
						<div class="form-check mb-2">
							<input class="form-check-input" type="checkbox" value="1" id="edit_can_convert_to_kg" name="can_convert_to_kg" />
							<label class="form-check-label" for="edit_can_convert_to_kg">Enable alternate sale unit</label>
						</div>
						<div class="product-alternate-unit-wrap mb-2" style="display: none;">
							<label for="edit_alternate_sale_unit" class="form-label">Convert To Unit</label>
							<select class="form-select" id="edit_alternate_sale_unit" name="alternate_sale_unit">
								<option value="">Select unit</option>
								<?php foreach ($baseUnitOptions as $unitOption): ?>
									<option value="<?php echo htmlspecialchars($unitOption); ?>"><?php echo htmlspecialchars(junkshop_unit_label($unitOption)); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="product-conversion-qty-wrap" style="display: none;">
							<label for="edit_kg_equivalent_qty" class="form-label product-conversion-label">Conversion Qty</label>
							<input type="number" step="0.01" min="0" class="form-control" id="edit_kg_equivalent_qty" name="kg_equivalent_qty" value="0" />
						</div>
						<small class="text-muted d-block mt-2 product-conversion-hint"></small>
					</div>

					<label for="edit_selling_price" class="form-label product-base-price-label">Selling Price</label>
					<div class="product-standard-pricing-wrap">
						<input type="number" step="0.01" min="0" class="form-control mb-3" id="edit_selling_price" name="selling_price" value="" required />
					</div>

					<div class="product-lpg-pricing-wrap mb-3" style="display: none;">
						<label for="edit_lpg_refill_price" class="form-label">Refill / Swap Out Price</label>
						<input type="number" step="0.01" min="0" class="form-control mb-3" id="edit_lpg_refill_price" name="lpg_refill_price" value="" />
						<label for="edit_lpg_new_tank_price" class="form-label">New Tank with LPG Price</label>
						<input type="number" step="0.01" min="0" class="form-control" id="edit_lpg_new_tank_price" name="lpg_new_tank_price" value="" />
					</div>

					<div class="product-alternate-price-wrap mb-3" style="display: none;">
						<label for="edit_alternate_selling_price" class="form-label product-alternate-price-label">Alternate Unit Selling Price</label>
						<input type="number" step="0.01" min="0" class="form-control" id="edit_alternate_selling_price" name="alternate_selling_price" value="" />
					</div>

					<label for="edit_stock_limit" class="form-label">Stock Alert Limit (Base Unit)</label>
					<input type="number" step="0.01" min="0" class="form-control mb-3" id="edit_stock_limit" name="stock_limit" value="0" />

				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" name="save_changes" value="Save">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="addNewProduct" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form class="form new-form" method="POST" action="index.php">
				<div class="modal-header">
					<h4 class="modal-title">Add Product</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="mainmenu" value="product_list" />
					<input type="hidden" name="add_new_product" value="" />

					<label for="new_product_name" class="form-label">Product Name</label>
					<input type="text" class="form-control mb-3" id="new_product_name" name="product_name" value="" required />

					<label for="new_product_type" class="form-label">Type / Category</label>
					<input type="text" class="form-control mb-3" id="new_product_type" name="product_type" list="product_type_suggestions" placeholder="Rice, LPG, Frozen Foods, Eggs" required />
					<datalist id="product_type_suggestions">
						<?php foreach ($productTypeSuggestions as $productType): ?>
							<option value="<?php echo htmlspecialchars($productType); ?>"></option>
						<?php endforeach; ?>
					</datalist>

					<label for="new_product_base_unit" class="form-label">Base Unit</label>
					<select class="form-select mb-3" id="new_product_base_unit" name="product_base_unit" required>
						<option value="pc" selected>Pcs</option>
						<option value="kg">KG</option>
						<option value="sack">Sack</option>
						<option value="tray">Tray</option>
						<option value="pack">Pack</option>
						<option value="tank">Tank</option>
					</select>

					<div class="product-conversion-wrap mb-3">
						<input type="hidden" name="can_convert_to_kg" value="0" />
						<div class="form-check mb-2">
							<input class="form-check-input" type="checkbox" value="1" id="new_can_convert_to_kg" name="can_convert_to_kg" />
							<label class="form-check-label" for="new_can_convert_to_kg">Enable alternate sale unit</label>
						</div>
						<div class="product-alternate-unit-wrap mb-2" style="display: none;">
							<label for="new_alternate_sale_unit" class="form-label">Convert To Unit</label>
							<select class="form-select" id="new_alternate_sale_unit" name="alternate_sale_unit">
								<option value="">Select unit</option>
								<?php foreach ($baseUnitOptions as $unitOption): ?>
									<option value="<?php echo htmlspecialchars($unitOption); ?>"><?php echo htmlspecialchars(junkshop_unit_label($unitOption)); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="product-conversion-qty-wrap" style="display: none;">
							<label for="new_kg_equivalent_qty" class="form-label product-conversion-label">Conversion Qty</label>
							<input type="number" step="0.01" min="0" class="form-control" id="new_kg_equivalent_qty" name="kg_equivalent_qty" value="0" />
						</div>
						<small class="text-muted d-block mt-2 product-conversion-hint"></small>
					</div>

					<label for="new_selling_price" class="form-label product-base-price-label">Selling Price</label>
					<div class="product-standard-pricing-wrap">
						<input type="number" step="0.01" min="0" class="form-control mb-3" id="new_selling_price" name="selling_price" value="" required />
					</div>

					<div class="product-lpg-pricing-wrap mb-3" style="display: none;">
						<label for="new_lpg_refill_price" class="form-label">Refill / Swap Out Price</label>
						<input type="number" step="0.01" min="0" class="form-control mb-3" id="new_lpg_refill_price" name="lpg_refill_price" value="" />
						<label for="new_lpg_new_tank_price" class="form-label">New Tank with LPG Price</label>
						<input type="number" step="0.01" min="0" class="form-control" id="new_lpg_new_tank_price" name="lpg_new_tank_price" value="" />
					</div>

					<div class="product-alternate-price-wrap mb-3" style="display: none;">
						<label for="new_alternate_selling_price" class="form-label product-alternate-price-label">Alternate Unit Selling Price</label>
						<input type="number" step="0.01" min="0" class="form-control" id="new_alternate_selling_price" name="alternate_selling_price" value="" />
					</div>

					<label for="new_stock_limit" class="form-label">Stock Alert Limit (Base Unit)</label>
					<input type="number" step="0.01" min="0" class="form-control mb-3" id="new_stock_limit" name="stock_limit" value="0" />

				</div>
				<div class="modal-footer">
					<input type="submit" class="btn btn-success" name="save_changes" value="Save">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>
