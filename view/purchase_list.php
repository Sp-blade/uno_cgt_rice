<?php
	$searchInvoice = isset($_GET['searchInvoice']) ? mysqli_real_escape_string($connectDB, $_GET['searchInvoice']) : '';

	$allPurchases = "SELECT MIN(PurchaseDate) AS PurchaseDate, InvoiceNo, COUNT(Quantity) as Product_Count, SUM(TotalPurchasePrice) as totalPurchasePrice_sum,
                        GROUP_CONCAT(DISTINCT ProductName ORDER BY ID SEPARATOR ', ') AS stock_summary
                 FROM purchases
                 WHERE InvoiceNo LIKE '%$searchInvoice%' OR ProductName LIKE '%$searchInvoice%'
                 GROUP BY InvoiceNo
                 ORDER BY MAX(ID) DESC";
	$purchaseList = $connectDB->query($allPurchases);
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
        <input type="text" class="searchbox" name="searchInvoice" placeholder="Search PO Number / Product" value="<?php echo htmlspecialchars($searchInvoice); ?>" />
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div class="table-card margin-top">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col" class="text-right-align">Date & Time</th>
                        <th scope="col" class="text-right-align">PO Number</th>
                        <th scope="col" class="text-right-align">Summary</th>
                        <th scope="col" class="text-right-align">Items</th>
                        <th scope="col" class="text-right-align">Total Purchase Price</th>
                        <th scope="col" class="text-right-align">Action</th>
                    </tr>
                </thead>
                <tbody>

<?php
if ($purchaseList && $purchaseList->num_rows > 0) {
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
			echo "<a class='icon-action-btn icon-action-btn-view' href='?mainmenu=view_invoice&searchInvoice=" . urlencode($searchInvoice) . "&invoiceNo=" . $Product['InvoiceNo'] . "&return_to=" . urlencode($_SERVER['REQUEST_URI']) . "' aria-label='View PO " . $Product['InvoiceNo'] . "' title='View'><i class='bi bi-eye' aria-hidden='true'></i></a>";
			echo "<a class='icon-action-btn icon-action-btn-delete js-history-delete' href='?mainmenu=purchase_list&delete_product=1&searchInvoice=" . urlencode($searchInvoice) . "&invoiceNo=" . $Product['InvoiceNo'] . "' data-record-label='PO Number " . $Product['InvoiceNo'] . "' aria-label='Delete PO " . $Product['InvoiceNo'] . "' title='Delete'><i class='bi bi-trash3' aria-hidden='true'></i></a>";
			echo "</div>";
		echo "</td>";
		echo "</tr>";
	}
} else {
	echo "<tr><td colspan='6' class='empty-state'>No purchase records found.</td></tr>";
}
?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
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
