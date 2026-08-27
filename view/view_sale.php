<?php
    $deliveryNo = (int) ($_GET['deliveryNo'] ?? 0);
    $returnTo = isset($_GET['return_to']) ? $_GET['return_to'] : '?mainmenu=sales_list';
    $encodedReturnTo = htmlspecialchars($returnTo, ENT_QUOTES);
    
    $saleDetails = $connectDB->query("SELECT * FROM sales WHERE DeliveryNo = '$deliveryNo' ORDER BY ID ASC");
    
    $saleItems = [];
    $saleTotal = 0;
    $customerName = '';
    $saleDate = '';
    
    if ($saleDetails && $saleDetails->num_rows > 0) {
        while ($row = $saleDetails->fetch_assoc()) {
            $saleItems[] = $row;
            $customerName = $row['CustomerName'];
            $saleDate = $row['SaleDate'];
            $saleTotal += (float) $row['TotalSalePrice'];
        }
    }

    // Fetch Customer Details, Balance, and Latest Due Date based on the CustomerName attached to this sale
    $customerData = null;
    if (!empty($customerName)) {
        $safeCustomer = mysqli_real_escape_string($connectDB, $customerName);
        $custQuery = $connectDB->query("
            SELECT 
                c.CustomerName,
                c.Address,
                c.GoogleMap,
                COALESCE(SUM(a.Balance), 0) AS CurrentBalance,
                MAX(a.DueDate) AS LatestDueDate 
            FROM customers c 
            LEFT JOIN customer_accounts a ON a.CustomerName = c.CustomerName 
            WHERE c.CustomerName = '$safeCustomer' 
            GROUP BY c.CustomerName, c.Address, c.GoogleMap
            LIMIT 1
        ");
        if ($custQuery && $custQuery->num_rows > 0) {
            $customerData = $custQuery->fetch_assoc();
        }
    }

    $saleAccountData = null;
    if ($deliveryNo > 0) {
        $saleAccountQuery = $connectDB->query("
            SELECT Balance, DueDate, AmountPaid, TotalAmount, PaymentStatus
            FROM customer_accounts
            WHERE DeliveryNo = '$deliveryNo'
            LIMIT 1
        ");
        if ($saleAccountQuery && $saleAccountQuery->num_rows > 0) {
            $saleAccountData = $saleAccountQuery->fetch_assoc();
        }
    }

    $customerAddress = trim((string) ($customerData['Address'] ?? ''));
    $customerCurrentBalance = (float) ($customerData['CurrentBalance'] ?? 0);
    $customerLatestDueDate = trim((string) ($customerData['LatestDueDate'] ?? ''));
    $customerGoogleMap = trim((string) ($customerData['GoogleMap'] ?? ''));
    $invoiceBalance = (float) ($saleAccountData['Balance'] ?? 0);
    $invoiceDueDate = trim((string) ($saleAccountData['DueDate'] ?? ''));
?>

<div class="container-fluid has-fixed-footer">
    <div class="dashboard-card">
        <div class="dashboard-list-head">
            <div>
                <p class="section-kicker">Sales</p>
                <h3>Sales Invoice <?php echo $deliveryNo; ?></h3>
            </div>
            <div class="detail-head-meta">
                <div class="detail-head-meta-panel detail-head-meta-columns">
                    <div class="detail-head-meta-item">
                        <p class="page-kicker">Customer Name</p>
                        <h3><?php echo htmlspecialchars($customerName !== '' ? $customerName : 'Walk-in Customer'); ?></h3>
                    </div>
                    <div class="detail-head-meta-item">
                        <p class="page-kicker">Date &amp; Time</p>
                        <h3><?php echo $saleDate !== '' ? junkshop_format_datetime($saleDate) : 'Not available'; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Product</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-end">Total</th>
                        <th>Notes</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($saleItems)): ?>
                        <?php $count = 1; ?>
                        <?php foreach ($saleItems as $sale): ?>
                            <tr>
                                <td><?php echo $count; ?></td>
                                <td><?php echo htmlspecialchars($sale['ProductName']); ?></td>
                                <td class="text-end"><?php echo number_format($sale['Quantity'], 2); ?> <?php echo htmlspecialchars(junkshop_unit_label($sale['SaleUnit'] ?? 'pc')); ?></td>
                                <td class="text-end">&#8369;<?php echo number_format($sale['UnitPrice'], 2); ?></td>
                                <td class="text-end">&#8369;<?php echo number_format($sale['TotalSalePrice'], 2); ?></td>
                                <td><?php echo htmlspecialchars($sale['Notes']); ?></td>
                                <td class="text-center">
                                    <div class="icon-action-group justify-content-center">
                                    <a
                                        href="<?php echo $server; ?>?mainmenu=view_sale&deliveryNo=<?php echo $deliveryNo; ?>&return_to=<?php echo urlencode($returnTo); ?>&delete_product=1&ID=<?php echo $sale['ID']; ?>"
                                        class="icon-action-btn icon-action-btn-delete"
                                        onclick='return confirm("Do you want to delete this sold record?")'
                                        aria-label="Delete sale item <?php echo $count; ?>"
                                        title="Delete"
                                    >
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                    </a>
                                    </div>
                                </td>
                            </tr>
                            <?php $count++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                            <tr><td colspan="7" class="empty-state">No sale details found.</td></tr>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REPLACED: Customer Details Section (Horizontal Design) -->
    <div class="dashboard-card margin-top">
        <div class="section-head">
            <div>
                <p class="section-kicker">Contact Information</p>
                <h3>Customer Details</h3>
            </div>
        </div>
        <div class="table-card">
            <div class="row g-0 align-items-center p-3">
                <div class="col-md-3">
                    <p class="text-muted mb-1"><small>Full Name</small></p>
                    <h5 class="mb-0 text-truncate"><?php echo htmlspecialchars($customerName ?: 'Walk-in Customer'); ?></h5>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1"><small>Address</small></p>
                    <p class="mb-0 text-truncate" title="<?php echo htmlspecialchars($customerAddress); ?>">
                        <?php echo htmlspecialchars($customerAddress !== '' ? $customerAddress : 'Not provided'); ?>
                    </p>
                </div>
                <div class="col-md-2">
                    <p class="text-muted mb-1"><small>Balance</small></p>
                    <h5 class="mb-0 fw-semibold text-primary">&#8369;<?php echo number_format($customerCurrentBalance, 2); ?></h5>
                </div>
                <div class="col-md-2">
                    <p class="text-muted mb-1"><small>Due Date</small></p>
                    <h5 class="mb-0 text-dark">
                        <?php 
                            $dueDate = $customerLatestDueDate;
                            echo $dueDate !== '' ? date('M-d-Y', strtotime($dueDate)) : 'No due date'; 
                        ?>
                    </h5>
                </div>
                <div class="col-md-2 text-end">
                    <?php if ($customerGoogleMap !== ''): ?>
                        <a href="<?php echo htmlspecialchars($customerGoogleMap); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-geo-alt"></i> View on Maps
                        </a>
                    <?php else: ?>
                        <span class="text-muted small">Not provided</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row custom-row fixed-footer footer-layout-single">
    <div class="col-md-2 col-12 footer-action">
        <a class="btn btn-info w-100" href="<?php echo $encodedReturnTo; ?>">Back</a>
    </div>
    <div class="col-md-2 col-12 footer-action">
        <?php if (!empty($saleItems)): ?>
			<a class="btn btn-outline-secondary w-100" href="<?php echo $server; ?>?mainmenu=print_sale_receipt&amp;deliveryNo=<?php echo (int) $deliveryNo; ?>&amp;return_to=<?php echo urlencode('?mainmenu=view_sale&deliveryNo=' . (int) $deliveryNo . '&return_to=' . $returnTo); ?>">Print Receipt</a>
        <?php endif; ?>
    </div>
    <div class="col-md-2 col-6 footer-metric">
        <p class="metric-label">Sale Total</p>
        <div class="totalprice">&#8369;<?php echo number_format($saleTotal, 2); ?></div>
    </div>
</div>
