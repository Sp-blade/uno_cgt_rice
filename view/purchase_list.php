<?php 

//pagination start
$perPage = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagebtn = isset($_GET['pagebtn']) ? (int)$_GET['pagebtn'] : 0;

if ($page < 1) {
    $page = 1;
}

$page = $page + $pagebtn;

$startAt = $perPage * ($page - 1);

// Search functionality for invoice number or product name
$searchInvoice = isset($_GET['searchInvoice']) ? mysqli_real_escape_string($connectDB, $_GET['searchInvoice']) : '';

// SQL PurchaseList History with optional invoice number filter
$allPurchases = "SELECT MIN(PurchaseDate) AS PurchaseDate, InvoiceNo, COUNT(Quantity) as Product_Count, SUM(TotalPurchasePrice) as totalPurchasePrice_sum,
                        GROUP_CONCAT(DISTINCT ProductName ORDER BY ID SEPARATOR ', ') AS stock_summary
                 FROM purchases
                 WHERE InvoiceNo LIKE '%$searchInvoice%' OR ProductName LIKE '%$searchInvoice%'
                 GROUP BY InvoiceNo
                 ORDER BY MAX(ID) DESC";

// Counting PurchaseList History
$purchaseList = $connectDB -> query($allPurchases);
$totalProduct = 0;
while($countProduct = $purchaseList->fetch_assoc()){
    $totalProduct = $totalProduct + 1;
}

$totalPages = ceil($totalProduct / $perPage);

// SQL PurchaseList Pagination with filter
$allPurchases = "SELECT MIN(PurchaseDate) AS PurchaseDate, InvoiceNo, COUNT(Quantity) as Product_Count, SUM(TotalPurchasePrice) as totalPurchasePrice_sum,
                        GROUP_CONCAT(DISTINCT ProductName ORDER BY ID SEPARATOR ', ') AS stock_summary
                 FROM purchases
                 WHERE InvoiceNo LIKE '%$searchInvoice%' OR ProductName LIKE '%$searchInvoice%'
                 GROUP BY InvoiceNo
                 ORDER BY MAX(ID) DESC
                 LIMIT ".$startAt.",".$perPage."";
$purchaseList = $connectDB -> query($allPurchases);

//pagination end

?>

<div class="dashboard-card">
    <div class="section-head">
        <div>
            <p class="section-kicker">History</p>
            <h3>Purchase history</h3>
        </div>
    </div>

    <form method="GET" class="list-toolbar">
        <input type="hidden" name="mainmenu" value="purchase_list" />
        <input type="text" class="searchbox" name="searchInvoice" placeholder="Search Invoice Number / Product" value="<?php echo htmlspecialchars($searchInvoice); ?>" />
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div class="table-card margin-top">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col" class="text-right-align">Date & Time</th>
                        <th scope="col" class="text-right-align">Invoice Number</th>
                        <th scope="col" class="text-right-align">Summary</th>
                        <th scope="col" class="text-right-align">Items</th>
                        <th scope="col" class="text-right-align">Total Purchase Price</th>
                        <th scope="col" class="text-right-align">Action</th>
                    </tr>
                </thead>
                <tbody>

<?php

while($Product = $purchaseList->fetch_assoc()){
    echo "<tr>";
    echo "<td>" . junkshop_format_datetime($Product['PurchaseDate']) . "</td>";
    echo "<td>" . $Product['InvoiceNo'] . "</td>";
    $summaryText = trim((string) ($Product['stock_summary'] ?? ''));
    if ($summaryText === '') {
        $summaryText = 'No summary available';
    }
    if (strlen($summaryText) > 70) {
        $summaryText = substr($summaryText, 0, 67) . '...';
    }
    echo "<td>" . htmlspecialchars($summaryText) . "</td>";
    echo "<td>" . $Product['Product_Count'] . "</td>";
    echo "<td>&#8369;" . number_format($Product['totalPurchasePrice_sum'],2) . "</td>";

    echo "<td class='text-center'>";
        echo "<div class='icon-action-group justify-content-center'>";
        echo "<a class='icon-action-btn icon-action-btn-view' href='?mainmenu=view_invoice&page=" . $page . "&searchInvoice=" . urlencode($searchInvoice) . "&invoiceNo=" . $Product['InvoiceNo'] . "&return_to=" . urlencode($_SERVER['REQUEST_URI']) . "' aria-label='View invoice " . $Product['InvoiceNo'] . "' title='View'><i class='bi bi-eye' aria-hidden='true'></i></a>";
        echo "<a class='icon-action-btn icon-action-btn-delete js-history-delete' href='?mainmenu=purchase_list&delete_product=1&page=" . $page . "&searchInvoice=" . urlencode($searchInvoice) . "&invoiceNo=" . $Product['InvoiceNo'] . "' data-record-label='Invoice No. " . $Product['InvoiceNo'] . "' aria-label='Delete invoice " . $Product['InvoiceNo'] . "' title='Delete'><i class='bi bi-trash3' aria-hidden='true'></i></a>";
        echo "</div>";
    echo "</td>";
    echo "</tr>";
}

?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="paging_con">
        <form class="pagebtn" id="pagingForm" action="" method="GET">
            <input type="hidden" name="mainmenu" value="purchase_list" />
            <input type="hidden" name="searchInvoice" value="<?php echo htmlspecialchars($searchInvoice); ?>" />
            <button type="submit" class="btn btn-primary" name="pagebtn" value="-1" <?php echo ($page < 2) ? "disabled" : ""; ?>>&lt;</button>
            <span>Page</span>
            <input type="text" class="pagging text-center" name="page" value="<?php echo $page; ?>" onblur="autoSubmit()" />
            <span>/ <?php echo $totalPages; ?></span>
            <button type="submit" class="btn btn-primary" name="pagebtn" value="+1" <?php echo ($page >= $totalPages) ? "disabled" : ""; ?>>&gt;</button>
        </form>
    </div>
</div>

<script>
    function autoSubmit() {
        const form = document.getElementById('pagingForm');
        form.submit(); // Submit the form when the input loses focus
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-history-delete').forEach(function (button) {
            button.addEventListener('click', function (event) {
                const recordLabel = button.getAttribute('data-record-label') || 'this record';
                const confirmation = window.prompt('Type DELETE to permanently remove ' + recordLabel + '.');
                if (confirmation !== 'DELETE') {
                    event.preventDefault();
                }
            });
        });
    });
</script>
