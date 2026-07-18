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
            COALESCE(p.ProductBaseUnit, 'pc') AS unit,
            COALESCE(p.CanConvertToKg, 0) AS can_convert,
            COALESCE(p.KgEquivalentQty, 0) AS equiv_qty,
            COALESCE(p.ProductType, '') AS type,
            COALESCE(SUM(b.QuantityRemaining), 0) AS stock
        FROM products p
        LEFT JOIN inventory_batches b ON b.Product_ID = p.Product_ID
        WHERE p.IsActive = 1 $productWhere
        GROUP BY p.Product_ID, p.ProductName, p.SellingPrice, p.AlternateSellingPrice, p.ProductPrice, p.ProductBaseUnit, p.CanConvertToKg, p.KgEquivalentQty, p.ProductType
        ORDER BY p.ProductName ASC
    ");
    if ($productResult && $productResult->num_rows > 0) {
        while ($row = $productResult->fetch_assoc()) {
            $products[] = $row;
        }
    }

    $customers = [];
    $customerResult = $connectDB->query("SELECT CustomerName, Address, GoogleMap FROM customers ORDER BY CustomerName ASC");
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
                <div class="col-md-2">
                    <label for="sale_date">Date &amp; Time</label>
                    <input type="datetime-local" class="form-control" id="sale_date" name="sale_date" value="<?php echo junkshop_datetime_input_value(date('Y-m-d H:i:s')); ?>" required />
                </div>
            </div>

            <datalist id="sale_product_suggestions">
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo htmlspecialchars($product['name']); ?>" data-id="<?php echo (int) $product['id']; ?>" data-price="<?php echo htmlspecialchars((string) $product['price']); ?>" data-alternate-price="<?php echo htmlspecialchars((string) $product['alternate_price']); ?>" data-unit="<?php echo htmlspecialchars($product['unit']); ?>" data-can-convert="<?php echo (int) $product['can_convert']; ?>" data-equiv-qty="<?php echo htmlspecialchars((string) $product['equiv_qty']); ?>" data-type="<?php echo htmlspecialchars($product['type']); ?>" data-stock="<?php echo htmlspecialchars((string) $product['stock']); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <!-- Product Rows -->
            <div id="dynamicSaleFields">
                <div class="transaction-entry-row sale-row">
                    <div class="row custom-row align-items-end g-3 sale-item-line">
                        <div class="col-sm-5 product-name-cell">
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
                        <div class="col-md-3">
                            <label>LPG Tank</label>
                            <select class="form-select" name="lpg_transaction_type[]" onchange="toggleLpgCondition(this)">
                                <option value="NONE">No Tank Swap</option>
                                <option value="SWAPPED">Swapped Tank</option>
                            </select>
                        </div>
                        <div class="col-md-6 lpg-condition-wrap" style="display: none;">
                            <label>Tank Condition / Note</label>
                            <input type="text" class="form-control" name="lpg_tank_condition[]" placeholder="Example: dented, rusty, good condition" />
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
                            <input type="date" class="form-control" name="due_date[]" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row custom-row fixed-footer">
        <div class="col-md-2 col-12 footer-action"><button type="button" class="btn btn-info w-100" id="addSaleRowButton">+ Add</button></div>
        <div class="col-md-2 col-12 footer-action">
            <input type="hidden" id="saleGrandTotalInput" name="grandTotal" value="0.00" />
            <input type="hidden" id="saleAmountDueInput" name="amount_due" value="0.00" />
            <input type="submit" class="btn btn-success w-100" value="Continue">
        </div>
        <div class="col-md-1 col-6 footer-metric"><p class="metric-label">Item Count</p><div class="totalprice"><span id="saleItemCount">1</span></div></div>
        <div class="col-md-2 col-6 footer-metric"><p class="metric-label">Grand Total</p><div class="totalprice">&#8369;<span id="saleGrandTotal">0.00</span></div></div>
        <div class="col-md-2 col-6 footer-metric"><p class="metric-label">Amount Due</p><div class="totalprice text-warning">&#8369;<span id="saleAmountDue">0.00</span></div></div>
        <div class="col-md-1 col-6 footer-metric">
            <p class="metric-label">Cash Given</p>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm border-success" id="cash_given" name="cash_given" oninput="calculateSaleTotal()" />
        </div>
        <div class="col-md-2 col-6 footer-metric">
            <p class="metric-label">Change</p>
            <input type="text" class="form-control form-control-sm border-primary bg-light" id="change_amount" readonly />
        </div>
    </div>
</form>

<script>
    const unitLabels = { pc: 'Pcs', kg: 'KG', sack: 'Sack', tray: 'Tray' };

    function normalizeUnit(unit) {
        const value = String(unit || 'pc').toLowerCase();
        return ['pc', 'kg', 'sack', 'tray'].includes(value) ? value : 'pc';
    }

    function getAlternateUnit(baseUnit) {
        switch (normalizeUnit(baseUnit)) {
            case 'sack': return 'kg';
            case 'tray': return 'pc';
            case 'pc': return 'kg';
            default: return null;
        }
    }

    function canConvertUnits(baseUnit, canConvert, equivQty) {
        return Number(canConvert) === 1 && Number(equivQty) > 0 && getAlternateUnit(baseUnit) !== null;
    }

    function saleQtyToBase(quantity, saleUnit, baseUnit, equivQty) {
        quantity = Number(quantity) || 0;
        baseUnit = normalizeUnit(baseUnit);
        saleUnit = normalizeUnit(saleUnit);
        equivQty = Number(equivQty) || 0;
        if (saleUnit === baseUnit || equivQty <= 0) return quantity;
        const alternateUnit = getAlternateUnit(baseUnit);
        if (alternateUnit === null || saleUnit !== alternateUnit) return quantity;
        if (baseUnit === 'pc' && saleUnit === 'kg') return quantity * equivQty;
        return quantity / equivQty;
    }

    function baseQtyToAlternate(quantity, baseUnit, equivQty) {
        quantity = Number(quantity) || 0;
        baseUnit = normalizeUnit(baseUnit);
        equivQty = Number(equivQty) || 0;
        if (equivQty <= 0) return null;
        if (baseUnit === 'pc') return quantity / equivQty;
        return quantity * equivQty;
    }

    function syncSaleUnitControls(row, preferredUnit) {
        const baseUnit = normalizeUnit(row.dataset.baseUnit || 'pc');
        const canConvert = Number(row.dataset.canConvert || 0) === 1;
        const equivQty = Number(row.dataset.equivQty || 0);
        const select = row.querySelector('.sale-unit-select');
        const conversionInput = row.querySelector('input[name="kg_conversion_qty[]"]');
        const qtyLabel = row.querySelector('.sale-qty-label');
        if (!select || !conversionInput) return;

        const alternateUnit = getAlternateUnit(baseUnit);
        const supportsConversion = canConvertUnits(baseUnit, canConvert ? 1 : 0, equivQty);
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

    function toggleCustomerType() {
        const type = document.getElementById('customer_type').value;
        const nameInput = document.getElementById('customer_name');
        const rowPaymentSections = document.querySelectorAll('.registered-only.row-payment-options');
        
        if (type === 'walk_in') {
            nameInput.value = 'Walk-in Customer';
            rowPaymentSections.forEach(el => el.style.display = 'none');
        } else {
            if (nameInput.value === 'Walk-in Customer') nameInput.value = '';
            rowPaymentSections.forEach(el => el.style.display = 'flex');
            
            // REMOVED: nameInput.focus(); 
            // This was stealing your cursor and preventing clicks!
        }

        document.querySelectorAll('.sale-row').forEach(row => {
            const statusSelect = row.querySelector('select[name="payment_status[]"]');
            if (statusSelect) toggleRowPaymentStatus(statusSelect);
        });
        calculateSaleTotal();
    }

    function toggleRowPaymentStatus(select) {
        const row = select.closest('.sale-row');
        const status = select.value;
        const partialField = row.querySelector('.row-partial-only');
        const dueDateField = row.querySelector('.row-loan-partial-only');

        if (partialField) partialField.style.display = status === 'PARTIAL' ? 'block' : 'none';
        if (dueDateField) dueDateField.style.display = (status === 'PARTIAL' || status === 'UNPAID') ? 'block' : 'none';

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
        const condition = row.querySelector('input[name="lpg_tank_condition[]"]');
        const isLpg = productIsLpg(product);
        
        typeInput.value = isLpg ? 'LPG' : String(product?.type || '');
        
        // FIX: Use 'flex' instead of 'block'
        panel.style.display = isLpg ? 'flex' : 'none'; 
        
        if (!isLpg) {
            tankSelect.value = 'NONE';
            condition.value = '';
        }
        toggleLpgCondition(tankSelect);
    }       

    function toggleLpgCondition(select) {
        const row = select.closest('.sale-row');
        const wrap = row.querySelector('.lpg-condition-wrap');
        const condition = row.querySelector('input[name="lpg_tank_condition[]"]');
        const swapped = select.value === 'SWAPPED';
        wrap.style.display = swapped ? 'block' : 'none';
        condition.required = swapped;
        if (!swapped) condition.value = '';
        calculateSaleTotal();
    }

    function applyCustomerSelection() {
        const nameInput = document.getElementById('customer_name');
        const customer = saleCustomers.find(item => item.CustomerName === nameInput.value);
        if (!customer) return;
        document.getElementById('customer_type').value = 'registered';
        toggleCustomerType();
        if (customer.Address) document.getElementById('customer_address').value = customer.Address;
        if (customer.GoogleMap) document.getElementById('customer_google_map').value = customer.GoogleMap;
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
            row.dataset.stock = '0';
            calculateSaleTotal();
            return;
        }
        row.querySelector('input[name="product_id[]"]').value = product.id;
        row.dataset.baseUnit = normalizeUnit(product.unit || 'pc');
        row.dataset.canConvert = String(product.can_convert || 0);
        row.dataset.equivQty = String(product.equiv_qty || 0);
        row.dataset.basePrice = String(product.price || 0);
        row.dataset.alternatePrice = String(product.alternate_price || 0);
        row.dataset.stock = String(product.stock || 0);
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
            const stock = parseFloat(row.dataset.stock || '0') || 0;
            
            // DELETED the stockNote variables and if-statements that turned the text red
            
            const baseTotal = qty * price;
            row.querySelector('input[name="total_price[]"]').value = baseTotal.toFixed(2);
            grandTotal += baseTotal;

            syncRowPaymentValues(row, isRegistered, baseTotal);
            amountDue += getRowAmountDue(row, isRegistered);
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
        clone.dataset.basePrice = '0';
        clone.dataset.alternatePrice = '0';
        clone.dataset.selectedUnit = 'pc';
        syncSaleUnitControls(clone, 'pc');
        clone.querySelectorAll('select[name="lpg_transaction_type[]"]').forEach(select => select.value = 'NONE');
        clone.querySelectorAll('select[name="payment_status[]"]').forEach(select => select.value = 'PAID');
        clone.querySelectorAll('.row-partial-only, .row-loan-partial-only').forEach(el => el.style.display = 'none');
        
        configureLpgRow(clone, null);
        clone.dataset.stock = '0';
        
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
            if (productName !== '' && productId !== '' && qty > 0) {
                hasProduct = true;
                const key = productId + '|' + productName;
                const baseQty = saleQtyToBase(qty, saleUnit, baseUnit, equivQty);
                requestedByProduct[key] = (requestedByProduct[key] || 0) + baseQty;
            }
        });
        if (!hasProduct) {
            event.preventDefault();
            alert('Add at least one valid product before continuing.');
            return;
        }
    });

    calculateSaleTotal();
    document.querySelectorAll('.sale-row').forEach(row => syncSaleUnitControls(row));
</script>
