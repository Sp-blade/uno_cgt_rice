<?php
    $salesMode = 'COMBINED';
    $isLpgSale = false;
    $productWhere = ''; // One product picker now serves regular and LPG sales.
    
    $saleError = isset($_GET['sale_error']) ? trim((string) $_GET['sale_error']) : '';

    $products = [];
    $productResult = $connectDB->query("
        SELECT
            p.Product_ID AS id,
            p.ProductName AS name,
            COALESCE(NULLIF(p.SellingPrice, 0), p.ProductPrice) AS price,
            COALESCE(p.AlternateSellingPrice, 0) AS alternate_price,
            COALESCE(NULLIF(p.LpgRefillPrice, 0), NULLIF(p.SellingPrice, 0), p.ProductPrice) AS lpg_refill_price,
            COALESCE(NULLIF(p.LpgNewTankPrice, 0), NULLIF(p.SellingPrice, 0), p.ProductPrice) AS lpg_new_tank_price,
            COALESCE(p.ProductBaseUnit, 'pc') AS unit,
            COALESCE(p.CanConvertToKg, 0) AS can_convert,
            COALESCE(p.KgEquivalentQty, 0) AS equiv_qty,
            COALESCE(p.AlternateSaleUnit, '') AS alternate_unit,
            COALESCE(p.ProductType, '') AS type,
            COALESCE(SUM(b.QuantityRemaining), 0) AS stock,
            COALESCE(p.OpenedAlternateQty, 0) AS opened_qty,
            COALESCE((
                SELECT ib.UnitCost
                FROM inventory_batches ib
                WHERE ib.Product_ID = p.Product_ID
                    AND ib.QuantityRemaining > 0
                ORDER BY ib.BatchDate ASC, ib.ID ASC
                LIMIT 1
            ), (
                SELECT pu.ProductPrice
                FROM purchases pu
                WHERE pu.Product_ID = p.Product_ID
                ORDER BY pu.PurchaseDate DESC, pu.ID DESC
                LIMIT 1
            ), 0) AS purchase_price,
            COALESCE((
                SELECT ib.UnitCost
                FROM products tank
                INNER JOIN inventory_batches ib ON ib.Product_ID = tank.Product_ID
                WHERE tank.ParentProduct_ID = p.Product_ID
                    AND COALESCE(tank.IsSubProduct, 0) = 1
                    AND ib.QuantityRemaining > 0
                ORDER BY ib.BatchDate ASC, ib.ID ASC
                LIMIT 1
            ), (
                SELECT pu.ProductPrice
                FROM products tank
                INNER JOIN purchases pu ON pu.Product_ID = tank.Product_ID
                WHERE tank.ParentProduct_ID = p.Product_ID
                    AND COALESCE(tank.IsSubProduct, 0) = 1
                ORDER BY pu.PurchaseDate DESC, pu.ID DESC
                LIMIT 1
            ), 0) AS lpg_tank_purchase_price
        FROM products p
        LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
        WHERE p.IsActive = 1 AND COALESCE(p.IsSubProduct, 0) = 0 $productWhere
        GROUP BY p.Product_ID, p.ProductName, p.SellingPrice, p.AlternateSellingPrice, p.LpgRefillPrice, p.LpgNewTankPrice, p.ProductPrice, p.ProductBaseUnit, p.CanConvertToKg, p.KgEquivalentQty, p.AlternateSaleUnit, p.OpenedAlternateQty, p.ProductType
        ORDER BY p.ProductName ASC
    ");
    if ($productResult && $productResult->num_rows > 0) {
        while ($row = $productResult->fetch_assoc()) {
            $products[] = $row;
        }
    }

    $customers = [];
    $customerResult = $connectDB->query("SELECT CustomerName, Address, GoogleMap FROM customers WHERE CustomerName <> '" . mysqli_real_escape_string($connectDB, junkshop_walk_in_customer_name()) . "' ORDER BY CustomerName ASC");
    if ($customerResult && $customerResult->num_rows > 0) {
        while ($row = $customerResult->fetch_assoc()) {
            $customers[] = $row;
        }
    }
?>

<script>
    const saleProducts = <?php echo json_encode($products); ?>;
    const saleCustomers = <?php echo json_encode($customers); ?>;
    const isLpgSale = false;
</script>

<form class="form new-form" method="POST" action="?mainmenu=sale_invoice" id="saleForm">
    <input type="hidden" name="sales_type" value="OTHERS" />
    <input type="hidden" name="previousmenu" value="sell_product_others" />
    
    <!-- Hidden fields for backend processing -->
    
    <!-- Customer address/map submitted via hidden fields -->
    <input type="hidden" name="customer_address" id="customer_address" value="" />
    <input type="hidden" name="customer_google_map" id="customer_google_map" value="" />
    
    <div class="container-fluid">
        <div class="dashboard-card">
            <div class="dashboard-list-head mb-4">
                <div>
                    <p class="section-kicker">Sales workflow</p>
                    <h3>Sell Product</h3>
                </div>
            </div>

            <?php if ($saleError === 'stock'): ?>
                <div class="alert alert-danger" role="alert">Not enough stock for one or more selected products.</div>
            <?php elseif ($saleError === 'empty'): ?>
                <div class="alert alert-danger" role="alert">Add at least one valid product before saving a sale.</div>
            <?php elseif ($saleError !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($saleError); ?></div>
            <?php endif; ?>

            <datalist id="customer_suggestions">
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo htmlspecialchars($customer['CustomerName']); ?>" data-address="<?php echo htmlspecialchars($customer['Address']); ?>" data-map="<?php echo htmlspecialchars($customer['GoogleMap']); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <!-- Checkout Details -->
            <div class="row custom-row mb-4 pb-2 sale-checkout-section align-items-end">
                <div class="col-md-2">
                    <label for="customer_type">Customer Type</label>
                    <select class="form-select" id="customer_type" name="customer_type" onchange="toggleCustomerType()">
                        <option value="walk_in">Walk-in</option>
                        <option value="registered">Customer</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="customer_name">Customer Name</label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" value="Walk-in Customer" list="customer_suggestions" onblur="applyCustomerSelection()" required autocomplete="off" />
                </div>
                <div class="col-md-3">
                    <label for="sale_date">Date &amp; Time</label>
                    <input type="datetime-local" class="form-control" id="sale_date" name="sale_date" value="<?php echo junkshop_datetime_input_value(date('Y-m-d H:i:s')); ?>" required />
                </div>
            </div>

            <div class="row custom-row mb-4 pb-2 registered-only customer-detail-fields align-items-start" style="display: none;">
                <div class="col-md-5">
                    <label for="customer_address_visible">Address</label>
                    <input type="text" class="form-control" id="customer_address_visible" placeholder="Enter customer address" autocomplete="off" />
                    <small class="text-muted d-block mt-1">Add address details for this new customer.</small>
                </div>
                <div class="col-md-5">
                    <label for="customer_google_map_visible">Google Map Link</label>
                    <input type="url" class="form-control" id="customer_google_map_visible" placeholder="https://maps.google.com/..." autocomplete="off" />
                </div>
            </div>

            <datalist id="sale_product_suggestions">
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo htmlspecialchars($product['name']); ?>" data-id="<?php echo (int) $product['id']; ?>" data-price="<?php echo htmlspecialchars((string) $product['price']); ?>" data-alternate-price="<?php echo htmlspecialchars((string) $product['alternate_price']); ?>" data-unit="<?php echo htmlspecialchars($product['unit']); ?>" data-can-convert="<?php echo (int) $product['can_convert']; ?>" data-equiv-qty="<?php echo htmlspecialchars((string) $product['equiv_qty']); ?>" data-alternate-unit="<?php echo htmlspecialchars((string) $product['alternate_unit']); ?>" data-type="<?php echo htmlspecialchars($product['type']); ?>" data-stock="<?php echo htmlspecialchars((string) $product['stock']); ?>" data-opened-qty="<?php echo htmlspecialchars((string) ($product['opened_qty'] ?? 0)); ?>" data-purchase-price="<?php echo htmlspecialchars((string) $product['purchase_price']); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <!-- Product Rows -->
            <div id="dynamicSaleFields">
                <div class="transaction-entry-row sale-row">
                    <div class="row custom-row align-items-end g-3 sale-item-line">
                        <div class="col-sm-4 product-name-cell">
                            <label>Product Name</label>
                            <input type="text" class="form-control" name="product_name[]" list="sale_product_suggestions" onblur="applyProductSelection(this)" required />
                            <input type="hidden" name="product_id[]" value="" />
                            <input type="hidden" name="product_type[]" value="" />
                            <input type="hidden" name="kg_conversion_qty[]" value="0" />
                            <input type="hidden" name="lpg_tank_payment[]" value="0" />
                        </div>
                        <div class="col-sm-2">
                            <label class="sale-qty-label">Qty</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="product_quantity[]" required oninput="calculateSaleTotal()" />
                        </div>
                        <div class="col-sm-2 sale-unit-cell">
                            <label>Sale Unit</label>
                            <select class="form-select sale-unit-select" name="sale_unit[]" onchange="handleSaleUnitChange(this)">
                                <option value="pc">Pcs</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label>Price</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="product_price[]" required oninput="calculateSaleTotal()" />
                        </div>
                        <div class="col-sm-1">
                            <label>Total Price</label>
                            <input type="text" class="form-control" name="total_price[]" readonly />
                        </div>
                        <div class="col-sm-1 transaction-action-cell sale-action-cell">
                            <label class="visually-hidden">Delete</label>
                            <div class="icon-action-group">
                                <button type="button" class="icon-action-btn icon-action-btn-delete" onclick="deleteSaleRow(this)" aria-label="Delete sale row" title="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="row custom-row g-3 lpg-options mt-2" style="display: none;">
                        <div class="col-md-4">
                            <label>LPG Transaction</label>
                            <select class="form-select" name="lpg_transaction_type[]" onchange="toggleLpgCondition(this)">
                                <option value="SWAPPED" selected>Regular Sale (Swap Out)</option>
                                <option value="SOLD">New Tank with LPG</option>
                                <option value="LENT" class="lpg-lend-option">Lend Tank</option>
                            </select>
                        </div>
                        <div class="col-md-4 lpg-condition-wrap">
                            <label class="lpg-condition-label">Empty Tank Condition</label>
                            <select class="form-select" name="lpg_tank_condition[]">
                                <option value="">Select condition</option>
                                <option value="NEW">New</option>
                                <option value="OLD">Old</option>
                            </select>
                        </div>
                    </div>
                    <div class="row custom-row g-3 registered-only row-payment-options mt-2" style="display: none;">
                        <div class="col-md-3">
                            <label>Payment Method</label>
                            <select class="form-select" name="payment_status[]" onchange="toggleRowPaymentStatus(this)">
                                <option value="PAID">Full Payment</option>
                                <option value="PARTIAL">Partial Payment</option>
                                <option value="UNPAID">Loan / Unpaid</option>
                            </select>
                        </div>
                        <div class="col-md-2 row-partial-only" style="display: none;">
                            <label>Amount Paid</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="amount_paid[]" value="0" oninput="calculateSaleTotal()" />
                        </div>
                        <div class="col-md-2 row-loan-partial-only" style="display: none;">
                            <label>Due Date</label>
                            <input type="date" class="form-control sale-due-date-input" name="due_date[]" />
                        </div>
                    </div>
                    <div class="row custom-row sale-loss-warning-row mt-2" style="display: none;">
                        <div class="col-12">
                            <div class="alert alert-warning py-2 mb-0 sale-loss-warning" role="alert"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row custom-row fixed-footer">
        <div class="col-auto footer-action"><button type="button" class="btn btn-info" id="addSaleRowButton">+ Add</button></div>
        <div class="col-auto footer-action">
            <input type="hidden" id="saleGrandTotalInput" name="grandTotal" value="0.00" />
            <input type="hidden" id="saleAmountDueInput" name="amount_due" value="0.00" />
            <input type="submit" class="btn btn-success" value="Continue">
        </div>
        <div class="col-auto footer-metric"><p class="metric-label">Count</p><div class="totalprice"><span id="saleItemCount">1</span></div></div>
        <div class="col-auto footer-metric"><p class="metric-label">Grand Total</p><div class="totalprice">&#8369;<span id="saleGrandTotal">0.00</span></div></div>
        <div class="col-auto footer-metric"><p class="metric-label">Amount Due</p><div class="totalprice text-warning">&#8369;<span id="saleAmountDue">0.00</span></div></div>
        <div class="col-auto footer-metric footer-field">
            <p class="metric-label">Cash</p>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm footer-input border-success" id="cash_given" name="cash_given" oninput="calculateSaleTotal()" />
        </div>
        <div class="col-auto footer-metric footer-field">
            <p class="metric-label">Change</p>
            <input type="text" class="form-control form-control-sm footer-input border-primary bg-light" id="change_amount" readonly />
        </div>
    </div>
</form>

<script>
    const unitLabels = { pc: 'Pcs', kg: 'KG', sack: 'Sack', tray: 'Tray', pack: 'Pack', tank: 'Tank' };

    function normalizeUnit(unit) {
        const value = String(unit || 'pc').toLowerCase();
        return ['pc', 'kg', 'sack', 'tray', 'pack', 'tank'].includes(value) ? value : 'pc';
    }

    function getAlternateUnit(baseUnit, alternateSaleUnit) {
        const base = normalizeUnit(baseUnit);
        const alternate = normalizeUnit(alternateSaleUnit);
        if (!alternate || alternate === base) {
            return null;
        }
        return alternate;
    }

    function usesLegacyPcKgConversion(baseUnit, alternateUnit) {
        return normalizeUnit(baseUnit) === 'pc' && normalizeUnit(alternateUnit) === 'kg';
    }

    function canConvertUnits(baseUnit, canConvert, equivQty, alternateSaleUnit) {
        return Number(canConvert) === 1 && Number(equivQty) > 0 && getAlternateUnit(baseUnit, alternateSaleUnit) !== null;
    }

    function saleQtyToBase(quantity, saleUnit, baseUnit, equivQty, alternateSaleUnit) {
        quantity = Number(quantity) || 0;
        baseUnit = normalizeUnit(baseUnit);
        saleUnit = normalizeUnit(saleUnit);
        equivQty = Number(equivQty) || 0;
        const alternateUnit = getAlternateUnit(baseUnit, alternateSaleUnit);
        if (saleUnit === baseUnit || equivQty <= 0 || !alternateUnit) return quantity;
        if (saleUnit !== alternateUnit) return quantity;
        if (usesLegacyPcKgConversion(baseUnit, alternateUnit)) return quantity * equivQty;
        return quantity / equivQty;
    }

    function salePriceToBase(sellPrice, saleUnit, baseUnit, equivQty, alternateSaleUnit) {
        sellPrice = Number(sellPrice) || 0;
        if (sellPrice <= 0) return 0;
        const baseQtyPerSaleUnit = saleQtyToBase(1, saleUnit, baseUnit, equivQty, alternateSaleUnit);
        if (baseQtyPerSaleUnit <= 0) return sellPrice;
        return sellPrice / baseQtyPerSaleUnit;
    }

    function formatMoney(value) {
        return '₱' + (Number(value) || 0).toFixed(2);
    }

    function getRowPurchasePricePerBase(row) {
        const lpgPanel = row.querySelector('.lpg-options');
        const isLpgVisible = lpgPanel && lpgPanel.style.display !== 'none';
        const tankSelect = row.querySelector('select[name="lpg_transaction_type[]"]');
        const refillPurchase = parseFloat(row.dataset.purchasePrice || '0') || 0;
        if (isLpgVisible && tankSelect && tankSelect.value === 'SOLD') {
            const tankPurchase = parseFloat(row.dataset.lpgTankPurchasePrice || '0') || 0;
            return refillPurchase + tankPurchase;
        }
        return refillPurchase;
    }

    function updateRowLossWarning(row) {
        const warningRow = row.querySelector('.sale-loss-warning-row');
        const warningBox = row.querySelector('.sale-loss-warning');
        if (!warningRow || !warningBox) return false;

        const productName = row.querySelector('input[name="product_name[]"]')?.value.trim() || '';
        const sellPrice = parseFloat(row.querySelector('input[name="product_price[]"]')?.value) || 0;
        const purchasePricePerBase = getRowPurchasePricePerBase(row);
        const baseUnit = normalizeUnit(row.dataset.baseUnit || 'pc');
        const saleUnit = normalizeUnit(row.querySelector('.sale-unit-select')?.value || baseUnit);
        const equivQty = row.dataset.equivQty || '0';
        const alternateSaleUnit = row.dataset.alternateUnit || '';

        if (productName === '' || sellPrice <= 0 || purchasePricePerBase <= 0) {
            warningRow.style.display = 'none';
            warningBox.textContent = '';
            return false;
        }

        const sellPricePerBase = salePriceToBase(sellPrice, saleUnit, baseUnit, equivQty, alternateSaleUnit);
        if (sellPricePerBase > purchasePricePerBase) {
            warningRow.style.display = 'none';
            warningBox.textContent = '';
            return false;
        }

        const baseLabel = unitLabels[baseUnit] || baseUnit.toUpperCase();
        const saleLabel = unitLabels[saleUnit] || saleUnit.toUpperCase();
        let message = 'Warning: Sell price (' + formatMoney(sellPrice) + '/' + saleLabel;

        if (saleUnit !== baseUnit) {
            message += ', equivalent to ' + formatMoney(sellPricePerBase) + '/' + baseLabel;
        }

        message += ') is equal to or lower than the current purchase price (' + formatMoney(purchasePricePerBase) + '/' + baseLabel + '). This may result in a loss.';
        warningBox.textContent = message;
        warningRow.style.display = 'flex';
        return true;
    }

    function baseQtyToAlternate(quantity, baseUnit, equivQty, alternateSaleUnit) {
        quantity = Number(quantity) || 0;
        baseUnit = normalizeUnit(baseUnit);
        equivQty = Number(equivQty) || 0;
        const alternateUnit = getAlternateUnit(baseUnit, alternateSaleUnit);
        if (equivQty <= 0 || !alternateUnit) return null;
        if (usesLegacyPcKgConversion(baseUnit, alternateUnit)) return quantity / equivQty;
        return quantity * equivQty;
    }

    function syncSaleUnitControls(row, preferredUnit) {
        const baseUnit = normalizeUnit(row.dataset.baseUnit || 'pc');
        const canConvert = Number(row.dataset.canConvert || 0) === 1;
        const equivQty = Number(row.dataset.equivQty || 0);
        const alternateSaleUnit = row.dataset.alternateUnit || '';
        const select = row.querySelector('.sale-unit-select');
        const conversionInput = row.querySelector('input[name="kg_conversion_qty[]"]');
        const qtyLabel = row.querySelector('.sale-qty-label');
        if (!select || !conversionInput) return;

        const alternateUnit = getAlternateUnit(baseUnit, alternateSaleUnit);
        const supportsConversion = canConvertUnits(baseUnit, canConvert ? 1 : 0, equivQty, alternateSaleUnit);
        select.innerHTML = '';
        const baseOption = document.createElement('option');
        baseOption.value = baseUnit;
        baseOption.textContent = unitLabels[baseUnit] || baseUnit.toUpperCase();
        select.appendChild(baseOption);

        if (supportsConversion && alternateUnit) {
            const altOption = document.createElement('option');
            altOption.value = alternateUnit;
            altOption.textContent = unitLabels[alternateUnit] || alternateUnit.toUpperCase();
            select.appendChild(altOption);
        }

        const selectedUnit = normalizeUnit(preferredUnit || row.dataset.selectedUnit || baseUnit);
        select.value = supportsConversion && selectedUnit === alternateUnit ? alternateUnit : baseUnit;
        row.dataset.selectedUnit = select.value;
        conversionInput.value = select.value !== baseUnit ? String(equivQty) : '0';
        if (qtyLabel) {
            qtyLabel.textContent = 'Qty (' + (unitLabels[select.value] || select.value.toUpperCase()) + ')';
        }
        applySaleUnitPrice(row);
    }

    function getSaleUnitPrice(row) {
        const lpgPanel = row.querySelector('.lpg-options');
        const isLpgVisible = lpgPanel && lpgPanel.style.display !== 'none';
        const tankSelect = row.querySelector('select[name="lpg_transaction_type[]"]');
        if (isLpgVisible && tankSelect) {
            const refillPrice = Number(row.dataset.lpgRefillPrice || row.dataset.basePrice || 0);
            const newTankPrice = Number(row.dataset.lpgNewTankPrice || 0);
            if (tankSelect.value === 'SOLD') {
                const combinedPrice = (refillPrice > 0 ? refillPrice : 0) + (newTankPrice > 0 ? newTankPrice : 0);
                return combinedPrice > 0 ? combinedPrice : Number(row.dataset.basePrice || 0);
            }
            return refillPrice > 0 ? refillPrice : Number(row.dataset.basePrice || 0);
        }
        const baseUnit = normalizeUnit(row.dataset.baseUnit || 'pc');
        const selectedUnit = normalizeUnit(row.querySelector('.sale-unit-select')?.value || baseUnit);
        const basePrice = Number(row.dataset.basePrice || 0);
        const alternatePrice = Number(row.dataset.alternatePrice || 0);
        return selectedUnit !== baseUnit && alternatePrice > 0 ? alternatePrice : basePrice;
    }

    function applySaleUnitPrice(row) {
        const priceInput = row.querySelector('input[name="product_price[]"]');
        if (!priceInput) return;
        const price = getSaleUnitPrice(row);
        if (price > 0) {
            priceInput.value = price.toFixed(2);
        }
    }

    function handleSaleUnitChange(select) {
        const row = select.closest('.sale-row');
        if (!row) return;
        row.dataset.selectedUnit = normalizeUnit(select.value);
        syncSaleUnitControls(row, select.value);
        calculateSaleTotal();
    }

    function updateLpgTransactionOptions() {
        const isWalkIn = document.getElementById('customer_type').value === 'walk_in';
        document.querySelectorAll('select[name="lpg_transaction_type[]"]').forEach(function (select) {
            const lentOption = select.querySelector('option[value="LENT"]');
            if (lentOption) {
                lentOption.hidden = isWalkIn;
                lentOption.disabled = isWalkIn;
            }
            if (isWalkIn && select.value === 'LENT') {
                select.value = 'SWAPPED';
                toggleLpgCondition(select);
            }
        });
    }

    function toggleCustomerType() {
        const type = document.getElementById('customer_type').value;
        const nameInput = document.getElementById('customer_name');
        const rowPaymentSections = document.querySelectorAll('.registered-only.row-payment-options');
        const customerDetailFields = document.querySelector('.registered-only.customer-detail-fields');
        
        if (type === 'walk_in') {
            nameInput.value = 'Walk-in Customer';
            rowPaymentSections.forEach(el => el.style.display = 'none');
            if (customerDetailFields) customerDetailFields.style.display = 'none';
            document.getElementById('customer_address').value = '';
            document.getElementById('customer_google_map').value = '';
            document.getElementById('customer_address_visible').value = '';
            document.getElementById('customer_google_map_visible').value = '';
        } else {
            if (nameInput.value === 'Walk-in Customer') nameInput.value = '';
            rowPaymentSections.forEach(el => el.style.display = 'flex');
        }

        updateLpgTransactionOptions();
        document.querySelectorAll('.sale-row').forEach(row => {
            const statusSelect = row.querySelector('select[name="payment_status[]"]');
            if (statusSelect) toggleRowPaymentStatus(statusSelect);
        });
        updateCustomerAddressFields();
        calculateSaleTotal();
    }

    function syncVisibleCustomerFieldsToHidden() {
        document.getElementById('customer_address').value = document.getElementById('customer_address_visible').value.trim();
        document.getElementById('customer_google_map').value = document.getElementById('customer_google_map_visible').value.trim();
    }

    function updateCustomerAddressFields() {
        const type = document.getElementById('customer_type').value;
        const nameInput = document.getElementById('customer_name');
        const addressHidden = document.getElementById('customer_address');
        const mapHidden = document.getElementById('customer_google_map');
        const addressVisible = document.getElementById('customer_address_visible');
        const mapVisible = document.getElementById('customer_google_map_visible');
        const customerDetailFields = document.querySelector('.registered-only.customer-detail-fields');

        if (!addressHidden || !mapHidden || !addressVisible || !mapVisible || type !== 'registered') {
            if (customerDetailFields) customerDetailFields.style.display = 'none';
            if (addressHidden) addressHidden.value = '';
            if (mapHidden) mapHidden.value = '';
            return;
        }

        const customerName = nameInput.value.trim();
        const customer = saleCustomers.find(item => item.CustomerName === customerName);
        const isNewCustomer = customerName !== '' && !customer;

        if (isNewCustomer) {
            if (customerDetailFields) customerDetailFields.style.display = 'flex';
            syncVisibleCustomerFieldsToHidden();
            return;
        }

        if (customerDetailFields) customerDetailFields.style.display = 'none';
        addressVisible.value = '';
        mapVisible.value = '';

        if (customer) {
            addressHidden.value = customer.Address || '';
            mapHidden.value = customer.GoogleMap || '';
        } else {
            addressHidden.value = '';
            mapHidden.value = '';
        }
    }

    function toggleRowPaymentStatus(select) {
        const row = select.closest('.sale-row');
        const status = select.value;
        const partialField = row.querySelector('.row-partial-only');
        const dueDateField = row.querySelector('.row-loan-partial-only');
        const dueDateInput = row.querySelector('.sale-due-date-input');

        if (partialField) partialField.style.display = status === 'PARTIAL' ? 'block' : 'none';
        if (dueDateField) dueDateField.style.display = (status === 'PARTIAL' || status === 'UNPAID') ? 'block' : 'none';
        if (dueDateInput) {
            dueDateInput.required = status === 'UNPAID';
            if (status !== 'PARTIAL' && status !== 'UNPAID') {
                dueDateInput.value = '';
            }
        }

        calculateSaleTotal();
    }

    function getRowAmountDue(row, isRegistered) {
        const rowTotal = parseFloat(row.querySelector('input[name="total_price[]"]').value) || 0;
        if (!isRegistered) return rowTotal;

        const status = row.querySelector('select[name="payment_status[]"]')?.value || 'PAID';
        if (status === 'PAID') return rowTotal;
        if (status === 'PARTIAL') return parseFloat(row.querySelector('input[name="amount_paid[]"]')?.value) || 0;
        return 0;
    }

    function syncRowPaymentValues(row, isRegistered, rowTotal) {
        const statusSelect = row.querySelector('select[name="payment_status[]"]');
        const amountPaidInput = row.querySelector('input[name="amount_paid[]"]');
        if (!statusSelect || !amountPaidInput) return;

        if (!isRegistered) {
            statusSelect.value = 'PAID';
            amountPaidInput.value = rowTotal.toFixed(2);
            return;
        }

        const status = statusSelect.value;
        if (status === 'PAID') {
            amountPaidInput.value = rowTotal.toFixed(2);
        } else if (status === 'UNPAID') {
            amountPaidInput.value = '0.00';
        }
    }

    function findProductByName(name) { return saleProducts.find(product => product.name === name); }

    function productIsLpg(product) {
        if (!product) return false;
        const type = String(product.type || '').toUpperCase();
        const name = String(product.name || '').toLowerCase();
        return type === 'LPG' || name.includes('lpg') || name.includes('gasul');
    }

    function configureLpgRow(row, product) {
        const panel = row.querySelector('.lpg-options');
        const typeInput = row.querySelector('input[name="product_type[]"]');
        const tankSelect = row.querySelector('select[name="lpg_transaction_type[]"]');
        const condition = row.querySelector('select[name="lpg_tank_condition[]"]');
        const isLpg = productIsLpg(product);
        
        typeInput.value = isLpg ? 'LPG' : String(product?.type || '');
        
        panel.style.display = isLpg ? 'flex' : 'none'; 
        
        if (!isLpg) {
            tankSelect.value = 'NONE';
            if (condition) condition.value = '';
        } else {
            tankSelect.value = 'SWAPPED';
        }
        toggleLpgCondition(tankSelect);
        applySaleUnitPrice(row);
    }

    function toggleLpgCondition(select) {
        const row = select.closest('.sale-row');
        const wrap = row.querySelector('.lpg-condition-wrap');
        const condition = row.querySelector('select[name="lpg_tank_condition[]"]');
        const conditionLabel = wrap ? wrap.querySelector('.lpg-condition-label') : null;
        const swapped = select.value === 'SWAPPED';
        const lent = select.value === 'LENT';
        const needsCondition = swapped || lent;
        if (wrap) wrap.style.display = needsCondition ? 'block' : 'none';
        if (conditionLabel) {
            conditionLabel.textContent = lent ? 'Lent Tank Condition' : 'Empty Tank Condition';
        }
        if (condition) {
            condition.required = needsCondition;
            if (!needsCondition) condition.value = '';
        }
        applySaleUnitPrice(row);
        calculateSaleTotal();
    }

    function applyCustomerSelection() {
        const nameInput = document.getElementById('customer_name');
        const customerName = nameInput.value.trim();
        if (customerName === '' || customerName === 'Walk-in Customer') {
            return;
        }

        document.getElementById('customer_type').value = 'registered';
        toggleCustomerType();
        updateCustomerAddressFields();
    }

    function applyProductSelection(input) {
        const row = input.closest('.sale-row');
        const product = findProductByName(input.value);
        if (!product) {
            if (input.value.trim() !== '') alert('Please select a valid product from the suggestions.');
            input.value = '';
            row.querySelector('input[name="product_id[]"]').value = '';
            row.querySelector('input[name="product_type[]"]').value = '';
            configureLpgRow(row, null);
            row.querySelector('input[name="product_price[]"]').value = '';
            row.dataset.basePrice = '0';
            row.dataset.alternatePrice = '0';
            row.dataset.alternateUnit = '';
            row.dataset.stock = '0';
            row.dataset.openedQty = '0';
            row.dataset.purchasePrice = '0';
            row.dataset.lpgTankPurchasePrice = '0';
            calculateSaleTotal();
            return;
        }
        row.querySelector('input[name="product_id[]"]').value = product.id;
        row.dataset.baseUnit = normalizeUnit(product.unit || 'pc');
        row.dataset.canConvert = String(product.can_convert || 0);
        row.dataset.equivQty = String(product.equiv_qty || 0);
        row.dataset.alternateUnit = normalizeUnit(product.alternate_unit || '');
        row.dataset.basePrice = String(product.price || 0);
        row.dataset.alternatePrice = String(product.alternate_price || 0);
        row.dataset.lpgRefillPrice = String(product.lpg_refill_price || product.price || 0);
        row.dataset.lpgNewTankPrice = String(product.lpg_new_tank_price || 0);
        row.dataset.lpgTankPurchasePrice = String(product.lpg_tank_purchase_price || 0);
        row.dataset.stock = String(product.stock || 0);
        row.dataset.openedQty = String(product.opened_qty || 0);
        row.dataset.purchasePrice = String(product.purchase_price || 0);
        syncSaleUnitControls(row, row.dataset.baseUnit);
        configureLpgRow(row, product);
        calculateSaleTotal();
    }

    function calculateSaleTotal() {
        let grandTotal = 0;
        let amountDue = 0;
        const type = document.getElementById('customer_type').value;
        const isRegistered = type === 'registered';

        document.querySelectorAll('.sale-row').forEach(row => {
            const qty = parseFloat(row.querySelector('input[name="product_quantity[]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name="product_price[]"]').value) || 0;
            
            const baseTotal = qty * price;
            row.querySelector('input[name="total_price[]"]').value = baseTotal.toFixed(2);
            grandTotal += baseTotal;

            syncRowPaymentValues(row, isRegistered, baseTotal);
            amountDue += getRowAmountDue(row, isRegistered);
            updateRowLossWarning(row);
        });
        
        document.getElementById('saleGrandTotal').textContent = grandTotal.toFixed(2);
        document.getElementById('saleGrandTotalInput').value = grandTotal.toFixed(2);
        document.getElementById('saleAmountDue').textContent = amountDue.toFixed(2);
        document.getElementById('saleAmountDueInput').value = amountDue.toFixed(2);

        const cashGiven = parseFloat(document.getElementById('cash_given').value) || 0;
        if (cashGiven > 0) {
            const change = cashGiven - amountDue;
            document.getElementById('change_amount').value = change >= 0 ? change.toFixed(2) : 'Insufficient';
        } else {
            document.getElementById('change_amount').value = '';
        }
        
        updateSaleItemCount();
    }

    function updateSaleItemCount() {
        document.getElementById('saleItemCount').textContent = document.querySelectorAll('.sale-row').length;
    }

    function deleteSaleRow(button) {
        if (document.querySelectorAll('.sale-row').length <= 1) return;
        button.closest('.sale-row').remove();
        calculateSaleTotal();
    }

    document.getElementById('addSaleRowButton').addEventListener('click', function() {
        const firstRow = document.querySelector('.sale-row');
        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('input').forEach(input => {
            if (input.type === 'hidden' && input.name === 'kg_conversion_qty[]') input.value = '0';
            else if (input.name === 'lpg_tank_payment[]') input.value = '0';
            else input.value = '';
        });
        clone.dataset.baseUnit = 'pc';
        clone.dataset.canConvert = '0';
        clone.dataset.equivQty = '0';
        clone.dataset.alternateUnit = '';
        clone.dataset.basePrice = '0';
        clone.dataset.alternatePrice = '0';
        clone.dataset.selectedUnit = 'pc';
        clone.dataset.purchasePrice = '0';
        clone.dataset.lpgTankPurchasePrice = '0';
        clone.dataset.stock = '0';
        clone.dataset.openedQty = '0';
        syncSaleUnitControls(clone, 'pc');
        clone.querySelectorAll('select[name="lpg_transaction_type[]"]').forEach(select => select.value = 'SWAPPED');
        clone.querySelectorAll('select[name="payment_status[]"]').forEach(select => select.value = 'PAID');
        clone.querySelectorAll('.row-partial-only, .row-loan-partial-only').forEach(el => el.style.display = 'none');

        configureLpgRow(clone, null);
        updateLpgTransactionOptions();
        const cloneWarningRow = clone.querySelector('.sale-loss-warning-row');
        const cloneWarningBox = clone.querySelector('.sale-loss-warning');
        if (cloneWarningRow) cloneWarningRow.style.display = 'none';
        if (cloneWarningBox) cloneWarningBox.textContent = '';
        
        document.getElementById('dynamicSaleFields').appendChild(clone);
        const customerType = document.getElementById('customer_type').value;
        const paymentSection = clone.querySelector('.registered-only.row-payment-options');
        
        // FIX: Use 'flex' instead of 'block'
        if (paymentSection) paymentSection.style.display = customerType === 'registered' ? 'flex' : 'none';
        
        updateSaleItemCount();
        window.scrollTo(0, document.body.scrollHeight);
        clone.querySelector('input[name="product_name[]"]').focus();
    });

    document.getElementById('saleForm').addEventListener('submit', function(event) {
        syncVisibleCustomerFieldsToHidden();
        calculateSaleTotal();
        const requestedByProduct = {};
        let hasProduct = false;
        document.querySelectorAll('.sale-row').forEach(row => {
            const productId = row.querySelector('input[name="product_id[]"]').value;
            const productName = row.querySelector('input[name="product_name[]"]').value.trim();
            const qty = parseFloat(row.querySelector('input[name="product_quantity[]"]').value) || 0;
            const saleUnit = row.querySelector('.sale-unit-select')?.value || row.dataset.baseUnit || 'pc';
            const baseUnit = row.dataset.baseUnit || 'pc';
            const equivQty = row.dataset.equivQty || '0';
            const alternateSaleUnit = row.dataset.alternateUnit || '';
            if (productName !== '' && productId !== '' && qty > 0) {
                hasProduct = true;
                const key = productId + '|' + productName;
                const baseQty = saleQtyToBase(qty, saleUnit, baseUnit, equivQty, alternateSaleUnit);
                requestedByProduct[key] = (requestedByProduct[key] || 0) + baseQty;
            }
        });
        if (!hasProduct) {
            event.preventDefault();
            alert('Add at least one valid product before continuing.');
            return;
        }

        const isRegistered = document.getElementById('customer_type').value === 'registered';
        if (isRegistered) {
            let missingLoanDueDate = false;
            document.querySelectorAll('.sale-row').forEach(function (row) {
                const status = row.querySelector('select[name="payment_status[]"]')?.value || 'PAID';
                if (status === 'UNPAID') {
                    const dueDate = row.querySelector('.sale-due-date-input')?.value.trim() || '';
                    if (dueDate === '') {
                        missingLoanDueDate = true;
                    }
                }
            });
            if (missingLoanDueDate) {
                event.preventDefault();
                alert('Due date is required for loan / unpaid items.');
                return;
            }
        }

        const lossWarnings = [];
        document.querySelectorAll('.sale-row').forEach(row => {
            if (updateRowLossWarning(row)) {
                const productName = row.querySelector('input[name="product_name[]"]')?.value.trim() || 'Product';
                lossWarnings.push(productName);
            }
        });

        if (lossWarnings.length > 0) {
            const uniqueNames = [...new Set(lossWarnings)];
            const proceed = window.confirm(
                'One or more products may be sold at a loss:\n\n- ' +
                uniqueNames.join('\n- ') +
                '\n\nContinue anyway?'
            );
            if (!proceed) {
                event.preventDefault();
            }
        }
    });

    calculateSaleTotal();
    updateLpgTransactionOptions();
    document.querySelectorAll('.sale-row').forEach(row => syncSaleUnitControls(row));
    document.getElementById('customer_name').addEventListener('input', updateCustomerAddressFields);
    document.getElementById('customer_name').addEventListener('change', applyCustomerSelection);
    document.getElementById('customer_address_visible').addEventListener('input', syncVisibleCustomerFieldsToHidden);
    document.getElementById('customer_google_map_visible').addEventListener('input', syncVisibleCustomerFieldsToHidden);
</script>
