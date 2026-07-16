<?php
	$searchProduct = isset($_GET['searchProduct']) ? trim((string) $_GET['searchProduct']) : '';
	$safeSearchProduct = mysqli_real_escape_string($connectDB, $searchProduct);

	$allProductSql = "
		SELECT
			p.*,
			COALESCE(parent.ProductName, '') AS ParentProductName
		FROM products p
		LEFT JOIN products parent ON parent.Product_ID = p.ParentProduct_ID
	";
	if ($safeSearchProduct !== '') {
		$allProductSql .= " WHERE p.ProductName LIKE '%$safeSearchProduct%' OR p.ProductType LIKE '%$safeSearchProduct%' OR p.ProductBaseUnit LIKE '%$safeSearchProduct%' OR parent.ProductName LIKE '%$safeSearchProduct%'";
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
			$row['ProductBaseUnit'] = strtolower(trim((string) ($row['ProductBaseUnit'] ?? 'pc'))) === 'kg' ? 'kg' : 'pc';
			$row['ProductPrice'] = (float) ($row['ProductPrice'] ?? 0);
			$row['SellingPrice'] = (float) ($row['SellingPrice'] ?? 0);
			$row['StockLimit'] = (float) ($row['StockLimit'] ?? 0);
			$row['CanConvertToKg'] = (int) ($row['CanConvertToKg'] ?? 0);
			$row['KgEquivalentQty'] = (float) ($row['KgEquivalentQty'] ?? 0);
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
				'price' => (float) ($row['ProductPrice'] ?? 0),
			];
		}
	}

	$productTypeSuggestions = ['Rice', 'LPG', 'Frozen Foods', 'Eggs'];
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
	$activeExportResult = $connectDB->query("SELECT * FROM products WHERE IsActive = 1 AND COALESCE(IsSubProduct, 0) = 0 ORDER BY ProductType ASC, ProductName ASC");
	if ($activeExportResult && $activeExportResult->num_rows > 0) {
		while ($row = $activeExportResult->fetch_assoc()) {
			$productName = trim((string) ($row['ProductName'] ?? ''));
			if ($productName === '') {
				continue;
			}

			$productType = trim((string) ($row['ProductType'] ?? ''));
			$productUnit = strtolower(trim((string) ($row['ProductBaseUnit'] ?? 'pc'))) === 'kg' ? 'kg' : 'pc';
			$productPurchasePrice = (float) ($row['ProductPrice'] ?? 0);

			$activeExportProducts[] = [
				'id' => (int) ($row['Product_ID'] ?? 0),
				'name' => $productName,
				'type' => $productType !== '' ? $productType : 'Products',
				'unit' => $productUnit,
				'price' => round($productPurchasePrice, 2),
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
	}
</style>

<div class="dashboard-card">
	<div class="dashboard-list-head">
		<div>
			<p class="section-kicker">Inventory management</p>
			<h3>Product List</h3>
		</div>
		<div class="d-flex flex-wrap justify-content-end gap-2">
			<button type="button" class="btn btn-outline-secondary" onclick="printPlainProductPriceList()">
				<i class="bi bi-printer"></i>
				<span>Print Plain List</span>
			</button>
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
						<th>Purchase Price</th>
						<th>Selling Price</th>
						<th>Stock Limit</th>
						<th>KG Conversion</th>
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
							<tr
								class="product-list-row"
								draggable="true"
								data-product-id="<?php echo (int) $Product['Product_ID']; ?>"
								data-product-name="<?php echo htmlspecialchars($Product['ProductName'], ENT_QUOTES); ?>"
								data-product-type="<?php echo htmlspecialchars($Product['ProductType'] !== '' ? $Product['ProductType'] : 'Products', ENT_QUOTES); ?>"
								data-product-unit="<?php echo htmlspecialchars($Product['ProductBaseUnit'], ENT_QUOTES); ?>"
								data-product-price="<?php echo htmlspecialchars((string) round($Product['ProductPrice'], 2), ENT_QUOTES); ?>"
								data-stock-limit="<?php echo htmlspecialchars((string) round($Product['StockLimit'], 2), ENT_QUOTES); ?>"
								data-product-active="<?php echo (int) $Product['IsActive']; ?>"
								data-product-exportable="<?php echo ($Product['IsSubProduct'] === 0) ? '1' : '0'; ?>"
							>
								<td class="product-list-drag-cell">::</td>
								<td class="product-list-row-number"><?php echo $index + 1; ?></td>
								<td>
									<div class="d-flex flex-column gap-1">
										<span><?php echo htmlspecialchars($Product['ProductName']); ?></span>
									</div>
								</td>
								<td><?php echo htmlspecialchars($Product['ProductType'] !== '' ? $Product['ProductType'] : 'Uncategorized'); ?></td>
								<td>
									<span class="badge <?php echo ($Product['ProductBaseUnit'] === 'kg') ? 'text-bg-success' : 'text-bg-secondary'; ?>">
										<?php echo ($Product['ProductBaseUnit'] === 'kg') ? 'KG' : 'Pcs'; ?>
									</span>
								</td>
								<td>&#8369;<?php echo number_format($Product['ProductPrice'], 2); ?></td>
								<td>&#8369;<?php echo number_format($Product['SellingPrice'], 2); ?></td>
								<td><?php echo number_format($Product['StockLimit'], 2); ?></td>
								<td>
									<?php if ($Product['ProductBaseUnit'] === 'pc' && $Product['CanConvertToKg'] === 1 && $Product['KgEquivalentQty'] > 0): ?>
										<span class="badge text-bg-info">1 KG = <?php echo number_format($Product['KgEquivalentQty'], 2); ?> pcs</span>
									<?php else: ?>
										<span class="text-muted">Not set</span>
									<?php endif; ?>
								</td>
								<td class="text-center">
									<div class="icon-action-group justify-content-center">
										<button
											data-bs-toggle="modal"
											data-bs-target="#editProductDetails"
											type="button"
											class="icon-action-btn icon-action-btn-edit"
											data-id="<?php echo $Product['Product_ID']; ?>"
											data-name="<?php echo htmlspecialchars($Product['ProductName']); ?>"
											data-type="<?php echo htmlspecialchars($Product['ProductType']); ?>"
											data-base-unit="<?php echo htmlspecialchars((string) $Product['ProductBaseUnit']); ?>"
											data-price="<?php echo $Product['ProductPrice']; ?>"
											data-selling-price="<?php echo $Product['SellingPrice']; ?>"
											data-stock-limit="<?php echo $Product['StockLimit']; ?>"
											data-can-convert="<?php echo $Product['CanConvertToKg']; ?>"
											data-kg-equivalent="<?php echo $Product['KgEquivalentQty']; ?>"
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
		$('#editProductDetails').on('show.bs.modal', function (event) {
			var button = $(event.relatedTarget);
			var productId = button.data('id');
			var productName = button.data('name');
			var productType = button.data('type');
			var productBaseUnit = button.attr('data-base-unit') || 'pc';
			var productPrice = button.data('price');
			var sellingPrice = button.attr('data-selling-price') || 0;
			var stockLimit = button.attr('data-stock-limit') || 0;

			var modal = $(this);
			modal.find('input[name="productID"]').val(productId);
			modal.find('input[name="product_name"]').val(productName);
			modal.find('input[name="product_type"]').val(productType);
			modal.find('select[name="product_base_unit"]').val(productBaseUnit);
			modal.find('input[name="product_price"]').val(productPrice);
			modal.find('input[name="selling_price"]').val(sellingPrice);
			modal.find('input[name="stock_limit"]').val(stockLimit);
		});

		$('#addNewProduct').on('show.bs.modal', function () {
			var modal = $(this);
			modal.find('input[name="product_price"]').val('');
			modal.find('input[name="selling_price"]').val('');
			modal.find('input[name="stock_limit"]').val('0');
			modal.find('select[name="product_base_unit"]').val('pc');
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
		return String(unit || '').toLowerCase() === 'kg' ? 'Per KG' : 'Per Pcs';
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
				unit: String(dataset.productUnit || 'pc').toLowerCase() === 'kg' ? 'kg' : 'pc',
				price: Number(dataset.productPrice || 0),
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
		var tableBody = document.querySelector('.table tbody');
		if (!tableBody) {
			return;
		}

		var draggedRow = null;

		function getRows() {
			return Array.prototype.slice.call(tableBody.querySelectorAll('.product-list-row'));
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

		getRows().forEach(function (row) {
			row.addEventListener('dragstart', function () {
				draggedRow = row;
				row.classList.add('is-dragging');
			});

			row.addEventListener('dragend', function () {
				draggedRow = null;
				clearDragState();
				updateRowNumbers();
				saveOrder();
			});

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

	function printPlainProductPriceList() {
		var products = getActivePriceListProducts();
		if (products.length === 0) {
			alert('No active products are available to print.');
			return;
		}

		var printWindow = window.open('', '_blank', 'width=1280,height=900');
		if (!printWindow) {
			alert('Please allow pop-ups so the plain price list can be printed.');
			return;
		}

		var pages = chunkPriceListProducts(products, 4);
		var pagesHtml = pages.map(function (pageProducts) {
			var rowsHtml = pageProducts.map(function (product) {
				return `
					<div class="plain-price-list-row">
						<div class="plain-price-list-name">${escapeHtml(product.name)}</div>
						<div class="plain-price-list-price">${escapeHtml(formatProductCurrency(product.price))}</div>
					</div>
				`;
			}).join('');

			return `
				<section class="plain-price-list-page">
					${rowsHtml}
				</section>
			`;
		}).join('');

		printWindow.document.open();
		printWindow.document.write(`
			<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="utf-8" />
				<title>${escapeHtml(productPriceListConfig.companyName)} ${escapeHtml(productPriceListConfig.catalogTitle || 'Price List')}</title>
				<style>
					@page {
						size: letter landscape;
						margin: 0.5in;
					}

					* {
						box-sizing: border-box;
						-webkit-print-color-adjust: exact !important;
						print-color-adjust: exact !important;
					}

					body {
						margin: 0;
						font-family: Arial, Helvetica, sans-serif;
						color: #111111;
						background: #ffffff;
					}

					.plain-price-list-page {
						min-height: 7.8in;
						display: flex;
						flex-direction: column;
						justify-content: space-evenly;
					}

					.plain-price-list-page + .plain-price-list-page {
						page-break-before: always;
					}

					.plain-price-list-row {
						display: flex;
						align-items: flex-start;
						justify-content: space-between;
						gap: 28px;
						padding: 20px 0;
						border-bottom: 2px solid #111111;
					}

					.plain-price-list-name,
					.plain-price-list-price {
						font-size: 60px;
						line-height: 1.05;
						font-weight: 700;
					}

					.plain-price-list-name {
						flex: 1 1 auto;
						min-width: 0;
						padding-right: 20px;
						word-break: break-word;
					}

					.plain-price-list-price {
						flex: 0 0 auto;
						white-space: nowrap;
						text-align: right;
					}
				</style>
			</head>
			<body>
				${pagesHtml}
			</body>
			</html>
		`);
		printWindow.document.close();

		printWindow.onload = function () {
			setTimeout(function () {
				printWindow.focus();
				printWindow.print();
			}, 300);
		};
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
			var nameWidth = width - (sidePadding * 2) - 260;

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
			ctx.font = '600 48px Arial';
			var nameLines = wrapPosterText(ctx, product.name, nameWidth, 2);
			nameLines.forEach(function (line, lineIndex) {
				ctx.fillText(line, sidePadding + 24, cardY + 58 + (lineIndex * 42));
			});

			ctx.textAlign = 'right';
			ctx.fillStyle = '#111827';
			ctx.font = '700 54px Arial';
			ctx.fillText(formatProductCurrency(product.price), width - sidePadding - 24, cardY + 44);
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
			<form class="form new-form" method="GET">
				<div class="modal-header">
					<h4 class="modal-title">Edit Product</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="mainmenu" value="product_list" />
					<input type="hidden" name="edit_product" value="edit_product" />
					<input type="hidden" name="productID" value="" />

					<label for="edit_product_name" class="form-label">Product Name</label>
					<input type="text" class="form-control mb-3" id="edit_product_name" name="product_name" value="" readonly />

					<label for="edit_product_type" class="form-label">Type / Category</label>
					<input type="text" class="form-control mb-3" id="edit_product_type" name="product_type" list="product_type_suggestions" placeholder="Rice, LPG, Frozen Foods, Eggs" required />

					<label for="edit_product_base_unit" class="form-label">Base Unit</label>
					<select class="form-select mb-3" id="edit_product_base_unit" name="product_base_unit" required>
						<option value="pc">Pcs</option>
						<option value="kg">KG</option>
					</select>

					<label for="edit_product_price" class="form-label">Purchase Price</label>
					<input type="number" step="0.01" min="0" class="form-control" id="edit_product_price" name="product_price" value="" />
					<small class="text-muted d-block mb-3 purchase-price-hint">Used for purchases and purchase history.</small>

					<label for="edit_selling_price" class="form-label">Selling Price</label>
					<input type="number" step="0.01" min="0" class="form-control mb-3" id="edit_selling_price" name="selling_price" value="" required />

					<label for="edit_stock_limit" class="form-label">Stock Alert Limit</label>
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
			<form class="form new-form" method="GET">
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
					</select>

					<label for="new_product_price" class="form-label">Purchase Price</label>
					<input type="number" step="0.01" min="0" class="form-control" id="new_product_price" name="product_price" value="" />
					<small class="text-muted d-block mb-3 purchase-price-hint">Used for purchases and purchase history.</small>

					<label for="new_selling_price" class="form-label">Selling Price</label>
					<input type="number" step="0.01" min="0" class="form-control mb-3" id="new_selling_price" name="selling_price" value="" required />

					<label for="new_stock_limit" class="form-label">Stock Alert Limit</label>
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
