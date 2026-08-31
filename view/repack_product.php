<?php
	$repackStatus = trim((string) ($_GET['repack_status'] ?? ''));
	$repackError = trim((string) ($_GET['repack_error'] ?? ''));
	$successRepackId = (int) ($_GET['repack_id'] ?? 0);
	$successSourceQty = (float) ($_GET['source_qty'] ?? 0);
	$successTargetQty = (float) ($_GET['target_qty'] ?? 0);
	$successSourceUnit = junkshop_normalize_base_unit($_GET['source_unit'] ?? 'pc');
	$successTargetUnit = junkshop_normalize_base_unit($_GET['target_unit'] ?? 'pc');

	$repackProducts = [];
	$productResult = $connectDB->query("
		SELECT
			p.Product_ID,
			p.ProductName,
			COALESCE(p.ProductType, '') AS ProductType,
			COALESCE(p.ProductBaseUnit, 'pc') AS ProductBaseUnit,
			COALESCE(p.CanConvertToKg, 0) AS CanConvertToKg,
			COALESCE(p.KgEquivalentQty, 0) AS KgEquivalentQty,
			COALESCE(p.AlternateSaleUnit, '') AS AlternateSaleUnit,
			COALESCE(p.OpenedAlternateQty, 0) AS OpenedAlternateQty,
			COALESCE(p.OpenedAlternateCost, 0) AS OpenedAlternateCost,
			COALESCE(p.IsSubProduct, 0) AS IsSubProduct,
			COALESCE(SUM(b.QuantityRemaining), 0) AS WholeStock
		FROM products p
		LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
		WHERE p.IsActive = 1
			AND UPPER(COALESCE(p.ProductType, '')) <> 'LPG TANK'
			AND COALESCE(p.IsSubProduct, 0) = 0
		GROUP BY p.Product_ID, p.ProductName, p.ProductType, p.ProductBaseUnit, p.CanConvertToKg, p.KgEquivalentQty, p.AlternateSaleUnit, p.OpenedAlternateQty, p.OpenedAlternateCost, p.IsSubProduct
		ORDER BY p.ProductName ASC
	");

	if ($productResult && $productResult->num_rows > 0) {
		while ($row = $productResult->fetch_assoc()) {
			$baseUnit = junkshop_normalize_base_unit($row['ProductBaseUnit'] ?? 'pc');
			$canConvert = (int) ($row['CanConvertToKg'] ?? 0);
			$equivQty = (float) ($row['KgEquivalentQty'] ?? 0);
			$alternateSaleUnit = trim((string) ($row['AlternateSaleUnit'] ?? ''));
			$alternateUnit = junkshop_get_alternate_sale_unit($baseUnit, $alternateSaleUnit);
			$factor = junkshop_product_repack_unit_factor($baseUnit, $canConvert, $equivQty, $alternateSaleUnit);
			$contentKg = junkshop_product_repack_content_kg($row);
			$contentKgLabel = junkshop_product_repack_content_kg_label($row);
			$configuredUnits = junkshop_product_repack_units($row);
			$wholeStock = round((float) ($row['WholeStock'] ?? 0), 2);
			$openedStock = round((float) ($row['OpenedAlternateQty'] ?? 0), 2);
			$availableUnits = [$baseUnit => $wholeStock];
			if ($alternateUnit !== null && junkshop_can_convert_units($baseUnit, $canConvert, $equivQty, $alternateSaleUnit)) {
				$wholeAsAlternate = junkshop_base_qty_to_alternate($wholeStock, $baseUnit, $equivQty, $alternateSaleUnit);
				$availableUnits[$alternateUnit] = round(
					(float) ($wholeAsAlternate ?? 0)
					+ (junkshop_supports_opened_alternate_stock($baseUnit, $canConvert, $equivQty, $alternateSaleUnit) ? $openedStock : 0),
					2
				);
			}
			$hasSourceStock = count(array_filter($availableUnits, function ($qty) {
				return (float) $qty > 0.009;
			})) > 0;

			$repackProducts[] = [
				'id' => (int) ($row['Product_ID'] ?? 0),
				'name' => trim((string) ($row['ProductName'] ?? '')),
				'category' => trim((string) ($row['ProductType'] ?? '')) !== '' ? trim((string) $row['ProductType']) : 'Products',
				'base_unit' => $baseUnit,
				'base_unit_label' => junkshop_unit_label($baseUnit),
				'can_convert' => $canConvert,
				'equiv_qty' => $equivQty,
				'alternate_unit' => $alternateUnit ?? '',
				'conversion_label' => junkshop_conversion_label($baseUnit, $equivQty, $alternateSaleUnit),
				'factor' => $factor,
				'factor_label' => $contentKgLabel !== '' ? $contentKgLabel : junkshop_product_repack_factor_label($baseUnit, $canConvert, $equivQty, $alternateSaleUnit),
				'content_kg' => $contentKg,
				'content_kg_label' => $contentKgLabel,
				'units' => $configuredUnits,
				'units_label' => implode(' / ', array_map('junkshop_unit_label', $configuredUnits)),
				'whole_stock' => $wholeStock,
				'opened_stock' => $openedStock,
				'available_units' => $availableUnits,
				'has_source_stock' => $hasSourceStock,
			];
		}
	}

	$recentRepacks = [];
	$recentRepackResult = $connectDB->query("
		SELECT
			r.ID,
			r.RepackDate,
			r.SourceProductName,
			r.TargetProductName,
			r.SourceQty,
			COALESCE(NULLIF(r.SourceUnit, ''), src.ProductBaseUnit, 'pc') AS SourceUnit,
			r.TargetQty,
			COALESCE(NULLIF(r.TargetUnit, ''), tgt.ProductBaseUnit, 'pc') AS TargetUnit,
			r.TotalCost,
			r.Notes
		FROM product_repacks r
		LEFT JOIN products src ON src.Product_ID = r.SourceProduct_ID
		LEFT JOIN products tgt ON tgt.Product_ID = r.TargetProduct_ID
		ORDER BY r.RepackDate DESC, r.ID DESC
		LIMIT 20
	");
	if ($recentRepackResult && $recentRepackResult->num_rows > 0) {
		while ($row = $recentRepackResult->fetch_assoc()) {
			$row['SourceUnit'] = junkshop_normalize_base_unit($row['SourceUnit'] ?? 'pc');
			$row['TargetUnit'] = junkshop_normalize_base_unit($row['TargetUnit'] ?? 'pc');
			$row['SourceUnitLabel'] = junkshop_unit_label($row['SourceUnit']);
			$row['TargetUnitLabel'] = junkshop_unit_label($row['TargetUnit']);
			$recentRepacks[] = $row;
		}
	}
?>

<div class="dashboard-card">
	<div class="section-head inventory-section-head">
		<div>
			<p class="section-kicker">Inventory tools</p>
			<h3>Repack / Convert Product</h3>
			<p class="text-muted mb-0">Target products are matched using both the source product's base unit and conversion unit. Example: a Sack convertible to KG can target products configured as Sack or KG.</p>
		</div>
		<div class="inventory-header-actions d-flex gap-2 flex-wrap">
			<a class="btn btn-outline-secondary" href="?mainmenu=inventory">Back to Inventory</a>
		</div>
	</div>

	<?php if ($repackStatus === 'success'): ?>
		<div class="alert alert-success margin-top" role="alert">
			Repack completed successfully.
			<?php if ($successSourceQty > 0 && $successTargetQty > 0): ?>
				Converted <?php echo number_format($successSourceQty, 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($successSourceUnit)); ?> into <?php echo number_format($successTargetQty, 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($successTargetUnit)); ?>.
			<?php endif; ?>
		</div>
	<?php elseif ($repackStatus === 'error' && $repackError !== ''): ?>
		<div class="alert alert-danger margin-top" role="alert"><?php echo htmlspecialchars($repackError); ?></div>
	<?php endif; ?>

	<form method="POST" action="?mainmenu=save_repack" class="form new-form margin-top" id="repackForm">
		<div class="row g-4">
			<div class="col-lg-7">
				<div class="panel-card p-4">
					<div class="mb-4">
						<label for="repack_date" class="form-label">Repack Date &amp; Time</label>
						<input type="datetime-local" class="form-control" id="repack_date" name="repack_date" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>" required />
					</div>

					<div class="mb-4">
						<label for="source_product_id" class="form-label">Source Product</label>
						<select class="form-select" id="source_product_id" name="source_product_id" required>
							<option value="">Select source product</option>
							<?php foreach ($repackProducts as $product): ?>
								<?php if (!$product['has_source_stock']) continue; ?>
								<option value="<?php echo (int) $product['id']; ?>">
									<?php echo htmlspecialchars($product['name']); ?>
									(<?php echo number_format($product['whole_stock'], 2); ?> <?php echo htmlspecialchars($product['base_unit_label']); ?>
									<?php if ($product['opened_stock'] > 0.009 && $product['alternate_unit'] !== ''): ?>
										+ <?php echo number_format($product['opened_stock'], 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($product['alternate_unit'])); ?>
									<?php endif; ?> available)
								</option>
							<?php endforeach; ?>
						</select>
						<div class="form-text" id="sourceProductMeta">Choose the product you want to repack from.</div>
					</div>

					<div class="mb-4">
						<label for="source_unit" class="form-label">Source Unit to Convert</label>
						<select class="form-select" id="source_unit" name="source_unit" required disabled>
							<option value="">Select source product first</option>
						</select>
						<div class="form-text">Choose the product's base unit or enabled conversion unit.</div>
					</div>

					<div class="mb-4">
						<label for="source_qty" class="form-label">Quantity to Repack</label>
						<div class="input-group">
							<input type="number" class="form-control" id="source_qty" name="source_qty" min="0.01" step="0.01" placeholder="0.00" required />
							<span class="input-group-text" id="sourceQtyUnit">units</span>
						</div>
						<div class="form-text" id="sourceQtyHelp">Quantity uses base-unit stock when available; otherwise it uses available conversion-unit stock.</div>
					</div>

					<div class="mb-4">
						<label for="target_product_id" class="form-label">Target Product</label>
						<select class="form-select" id="target_product_id" name="target_product_id" required disabled>
							<option value="">Select source product first</option>
						</select>
						<div class="form-text" id="targetProductMeta">Lists products whose base or conversion unit matches the source product.</div>
					</div>

					<div class="mb-4">
						<label class="form-label">Converted Quantity</label>
						<div class="input-group">
							<input type="text" class="form-control" id="target_qty_preview" value="0.00" readonly />
							<span class="input-group-text" id="targetQtyUnit">units</span>
						</div>
					</div>

					<div class="mb-4">
						<label for="repack_notes" class="form-label">Notes</label>
						<input type="text" class="form-control" id="repack_notes" name="repack_notes" maxlength="500" placeholder="Optional notes" />
					</div>

					<button type="submit" class="btn btn-primary" id="repackSubmitBtn">Save Repack</button>
				</div>
			</div>

			<div class="col-lg-5">
				<div class="panel-card p-4 mb-4">
					<h4 class="h5 mb-3">Conversion Preview</h4>
					<p class="text-muted" id="conversionSummary">Select source and target products to preview the conversion.</p>
					<ul class="mb-0" id="conversionDetails"></ul>
				</div>

				<div class="panel-card p-4">
					<h4 class="h5 mb-3">Rules</h4>
					<ul class="mb-0 text-muted">
						<li>A target must share at least one configured unit with the source.</li>
						<li>Both base units and enabled conversion units are considered.</li>
						<li>Example: Source Sack / KG shows targets configured with Sack or KG.</li>
						<li>The shared conversion unit determines the quantity ratio when available.</li>
						<li>Inventory cost moves from source FIFO batches to the new target batch.</li>
					</ul>
				</div>
			</div>
		</div>
	</form>
</div>

<?php if (!empty($recentRepacks)): ?>
	<div class="table-card margin-top">
		<div class="section-head px-3 pt-3">
			<div>
				<p class="section-kicker">History</p>
				<h3>Recent Repacks</h3>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<thead>
					<tr>
						<th>Date</th>
						<th>From</th>
						<th>To</th>
						<th class="text-end">Source Qty</th>
						<th class="text-end">Target Qty</th>
						<th>Notes</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($recentRepacks as $repack): ?>
						<tr>
							<td><?php echo htmlspecialchars(junkshop_format_datetime($repack['RepackDate'] ?? '')); ?></td>
							<td><?php echo htmlspecialchars($repack['SourceProductName'] ?? ''); ?></td>
							<td><?php echo htmlspecialchars($repack['TargetProductName'] ?? ''); ?></td>
							<td class="text-end">
								<?php echo number_format((float) ($repack['SourceQty'] ?? 0), 2); ?>
								<?php echo htmlspecialchars($repack['SourceUnitLabel'] ?? junkshop_unit_label($repack['SourceUnit'] ?? 'pc')); ?>
							</td>
							<td class="text-end">
								<?php echo number_format((float) ($repack['TargetQty'] ?? 0), 2); ?>
								<?php echo htmlspecialchars($repack['TargetUnitLabel'] ?? junkshop_unit_label($repack['TargetUnit'] ?? 'pc')); ?>
							</td>
							<td><?php echo htmlspecialchars($repack['Notes'] ?? ''); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>

<script>
	(function () {
		const repackProducts = <?php echo json_encode($repackProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const productMap = {};
		repackProducts.forEach(function (product) {
			productMap[String(product.id)] = product;
		});

		const sourceSelect = document.getElementById('source_product_id');
		const targetSelect = document.getElementById('target_product_id');
		const sourceQtyInput = document.getElementById('source_qty');
		const sourceUnitInput = document.getElementById('source_unit');
		const targetQtyPreview = document.getElementById('target_qty_preview');
		const sourceQtyUnit = document.getElementById('sourceQtyUnit');
		const targetQtyUnit = document.getElementById('targetQtyUnit');
		const sourceProductMeta = document.getElementById('sourceProductMeta');
		const targetProductMeta = document.getElementById('targetProductMeta');
		const conversionSummary = document.getElementById('conversionSummary');
		const conversionDetails = document.getElementById('conversionDetails');
		const submitBtn = document.getElementById('repackSubmitBtn');

		function productsCanRepack(source, target) {
			if (!source || !target || String(source.id) === String(target.id)) {
				return false;
			}

			return getCommonUnit(source, target) !== '';
		}

		function getProductUnits(product) {
			return product && Array.isArray(product.units) ? product.units : [];
		}

		function getCommonUnit(source, target) {
			const sourceUnits = getProductUnits(source);
			const targetUnits = getProductUnits(target);
			const commonUnits = sourceUnits.filter(function (unit) {
				return targetUnits.indexOf(unit) !== -1;
			});
			if (!commonUnits.length) {
				return '';
			}
			if (source.alternate_unit && commonUnits.indexOf(source.alternate_unit) !== -1) {
				return source.alternate_unit;
			}
			if (target.alternate_unit && commonUnits.indexOf(target.alternate_unit) !== -1) {
				return target.alternate_unit;
			}
			return commonUnits[0];
		}

		function getFactorForUnit(product, unit) {
			if (!product || !unit) {
				return 0;
			}
			if (product.base_unit === unit) {
				return 1;
			}
			if (
				Number(product.can_convert) !== 1
				|| product.alternate_unit !== unit
				|| Number(product.equiv_qty) <= 0
			) {
				return 0;
			}
			if (product.base_unit === 'pc' && unit === 'kg') {
				return 1 / Number(product.equiv_qty);
			}
			return Number(product.equiv_qty);
		}

		function calculateTargetQty(sourceQty, source, target) {
			sourceQty = Number(sourceQty);
			if (!sourceQty || sourceQty <= 0) {
				return 0;
			}

			const commonUnit = getCommonUnit(source, target);
			const sourceInputUnit = sourceUnitInput.value || source.base_unit;
			const sourceInputFactor = getFactorForUnit(source, sourceInputUnit);
			const sourceFactor = getFactorForUnit(source, commonUnit);
			const targetFactor = getFactorForUnit(target, commonUnit);
			if (!sourceInputFactor || !sourceFactor || !targetFactor) {
				return 0;
			}
			const sourceBaseQty = sourceQty / sourceInputFactor;
			return Math.round((sourceBaseQty * (sourceFactor / targetFactor)) * 100) / 100;
		}

		function formatQty(value) {
			return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		}

		function getUnitLabel(unit) {
			const labels = { pc: 'Pcs', kg: 'KG', sack: 'Sack', tray: 'Tray', pack: 'Pack', tank: 'Tank' };
			return labels[String(unit || '').toLowerCase()] || String(unit || '').toUpperCase();
		}

		function populateTargetOptions() {
			const source = productMap[sourceSelect.value] || null;
			targetSelect.innerHTML = '';
			targetSelect.disabled = true;

			if (!source) {
				targetSelect.innerHTML = '<option value="">Select source product first</option>';
				return;
			}

			const compatibleProducts = repackProducts.filter(function (product) {
				return productsCanRepack(source, product);
			});

			if (compatibleProducts.length === 0) {
				targetSelect.innerHTML = '<option value="">No compatible target products</option>';
				targetProductMeta.textContent = 'No product has a base or conversion unit matching the selected source.';
				return;
			}

			targetSelect.disabled = false;
			targetSelect.innerHTML = '<option value="">Select target product</option>';
			compatibleProducts.forEach(function (product) {
				const option = document.createElement('option');
				option.value = String(product.id);
				option.textContent = product.name + ' (' + product.units_label + ')';
				targetSelect.appendChild(option);
			});
		}

		function populateSourceUnitOptions() {
			const source = productMap[sourceSelect.value] || null;
			sourceUnitInput.innerHTML = '';
			sourceUnitInput.disabled = true;
			if (!source) {
				sourceUnitInput.innerHTML = '<option value="">Select source product first</option>';
				return;
			}

			const availableUnits = source.available_units || {};
			const unitsWithStock = getProductUnits(source).filter(function (unit) {
				return Number(availableUnits[unit] || 0) > 0.009;
			});
			if (!unitsWithStock.length) {
				sourceUnitInput.innerHTML = '<option value="">No available source stock</option>';
				return;
			}

			sourceUnitInput.disabled = false;
			sourceUnitInput.innerHTML = '<option value="">Select source unit</option>';
			unitsWithStock.forEach(function (unit) {
				const option = document.createElement('option');
				option.value = unit;
				option.textContent = getUnitLabel(unit) + ' (' + formatQty(availableUnits[unit]) + ' available)';
				sourceUnitInput.appendChild(option);
			});
			sourceUnitInput.value = unitsWithStock[0];
		}

		function updatePreview() {
			const source = productMap[sourceSelect.value] || null;
			const target = productMap[targetSelect.value] || null;
			const sourceQty = Number(sourceQtyInput.value || 0);
			conversionDetails.innerHTML = '';

			if (!source) {
				sourceProductMeta.textContent = 'Choose the product you want to repack from.';
				targetProductMeta.textContent = 'Lists products whose base or conversion unit matches the source product.';
				conversionSummary.textContent = 'Select source and target products to preview the conversion.';
				targetQtyPreview.value = '0.00';
				sourceQtyUnit.textContent = 'units';
				targetQtyUnit.textContent = 'units';
				submitBtn.disabled = true;
				return;
			}

			const sourceUnit = sourceUnitInput.value;
			const availableSourceQty = Number((source.available_units || {})[sourceUnit] || 0);
			sourceQtyUnit.textContent = getUnitLabel(sourceUnit);
			sourceProductMeta.textContent = source.name
				+ ' • ' + formatQty(source.whole_stock) + ' ' + source.base_unit_label
				+ (source.alternate_unit ? ' + ' + formatQty(source.opened_stock) + ' ' + getUnitLabel(source.alternate_unit) : '')
				+ ' opened stock • converting in ' + (sourceUnit ? getUnitLabel(sourceUnit) : 'selected unit');

			if (!sourceUnit || !target) {
				targetProductMeta.textContent = 'Select a target matching one of the source units: ' + source.units_label + '.';
				conversionSummary.textContent = !sourceUnit ? 'Select the source unit to convert.' : 'Select a compatible target product.';
				targetQtyPreview.value = '0.00';
				targetQtyUnit.textContent = source.base_unit_label;
				submitBtn.disabled = true;
				return;
			}

			targetQtyUnit.textContent = target.base_unit_label;
			targetProductMeta.textContent = target.name + ' • ' + target.factor_label;

			const perSourceUnit = calculateTargetQty(1, source, target);
			const targetQty = calculateTargetQty(sourceQty, source, target);
			targetQtyPreview.value = formatQty(targetQty);
			const commonUnit = getCommonUnit(source, target);

			conversionSummary.textContent = '1 ' + source.base_unit_label + ' of ' + source.name + ' becomes ' + formatQty(perSourceUnit) + ' ' + target.base_unit_label + ' of ' + target.name + '.';

			const detailItems = [
				'Source units: ' + source.units_label,
				'Target units: ' + target.units_label,
				'Shared unit used: ' + getUnitLabel(commonUnit),
				'Ratio: ' + formatQty(getFactorForUnit(source, commonUnit)) + ' / ' + formatQty(getFactorForUnit(target, commonUnit))
			];
			detailItems.forEach(function (text) {
				const item = document.createElement('li');
				item.textContent = text;
				conversionDetails.appendChild(item);
			});

			const hasStock = sourceQty > 0 && sourceQty <= availableSourceQty;
			const hasTargetQty = targetQty > 0;
			submitBtn.disabled = !(hasStock && hasTargetQty);
			if (sourceQty > availableSourceQty) {
				sourceProductMeta.textContent = 'Not enough stock. Available: ' + formatQty(availableSourceQty) + ' ' + getUnitLabel(sourceUnit) + '.';
			}
		}

		sourceSelect.addEventListener('change', function () {
			populateSourceUnitOptions();
			populateTargetOptions();
			updatePreview();
		});
		sourceUnitInput.addEventListener('change', updatePreview);
		targetSelect.addEventListener('change', updatePreview);
		sourceQtyInput.addEventListener('input', updatePreview);

		populateSourceUnitOptions();
		populateTargetOptions();
		updatePreview();
	})();
</script>
