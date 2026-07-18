<?php
	$searchDelivery = isset($_GET['searchDelivery']) ? mysqli_real_escape_string($connectDB, $_GET['searchDelivery']) : '';
	$sql = "SELECT SaleDate, DeliveryNo, CustomerName, COUNT(ID) AS item_count, SUM(TotalSalePrice) AS total_amount,
			GROUP_CONCAT(DISTINCT ProductName ORDER BY ID SEPARATOR ', ') AS sold_summary,
			GROUP_CONCAT(DISTINCT CASE WHEN Notes IS NOT NULL AND TRIM(Notes) <> '' THEN TRIM(Notes) END ORDER BY ID SEPARATOR ', ') AS notes_summary
		FROM sales
		WHERE DeliveryNo LIKE '%$searchDelivery%' OR CustomerName LIKE '%$searchDelivery%' OR ProductName LIKE '%$searchDelivery%' OR Notes LIKE '%$searchDelivery%'
		GROUP BY SaleDate, DeliveryNo, CustomerName
		ORDER BY SaleDate DESC, DeliveryNo DESC";
	$salesList = $connectDB->query($sql);
?>

<div class="dashboard-card">
	<?php $currentRequestUri = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); ?>
	<div class="section-head">
		<div>
			<p class="section-kicker">History</p>
			<h3>Sold / delivery history</h3>
		</div>
	</div>

	<form method="GET" class="list-toolbar">
		<input type="hidden" name="mainmenu" value="sales_list" />
		<input type="text" class="searchbox" name="searchDelivery" placeholder="Search Sale No / Customer / Product / Notes" value="<?php echo htmlspecialchars($searchDelivery); ?>" />
		<button type="submit" class="btn btn-primary">Search</button>
	</form>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>Date</th>
						<th>Sale No</th>
						<th>Customer</th>
						<th>Summary</th>
						<th>Note</th>
						<th>Items</th>
						<th>Total Sold</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($salesList && $salesList->num_rows > 0): ?>
						<?php while ($sale = $salesList->fetch_assoc()): ?>
							<tr>
								<td><?php echo junkshop_format_datetime($sale['SaleDate']); ?></td>
								<td><?php echo $sale['DeliveryNo']; ?></td>
								<td><?php echo htmlspecialchars($sale['CustomerName']); ?></td>
								<td>
									<?php
										$summaryText = trim((string) ($sale['sold_summary'] ?? ''));
										if ($summaryText === '') {
											$summaryText = 'No summary available';
										}
										if (strlen($summaryText) > 70) {
											$summaryText = substr($summaryText, 0, 67) . '...';
										}
										echo htmlspecialchars($summaryText);
									?>
								</td>
								<td>
									<?php
										$notesText = trim((string) ($sale['notes_summary'] ?? ''));
										if ($notesText === '') {
											$notesText = 'No note';
										}
										if (strlen($notesText) > 70) {
											$notesText = substr($notesText, 0, 67) . '...';
										}
										echo htmlspecialchars($notesText);
									?>
								</td>
								<td><?php echo $sale['item_count']; ?></td>
								<td>&#8369;<?php echo number_format($sale['total_amount'], 2); ?></td>
								<td class="text-center">
									<div class="icon-action-group justify-content-center">
										<a
											class="icon-action-btn icon-action-btn-view"
											href="?mainmenu=view_sale&deliveryNo=<?php echo $sale['DeliveryNo']; ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
											aria-label="View sale <?php echo $sale['DeliveryNo']; ?>"
											title="View"
										>
											<i class="bi bi-eye" aria-hidden="true"></i>
										</a>
										<a
											class="icon-action-btn icon-action-btn-delete js-history-delete"
											href="?mainmenu=sales_list&delete_product=1&deliveryNo=<?php echo $sale['DeliveryNo']; ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
											data-record-label="Sale No. <?php echo $sale['DeliveryNo']; ?>"
											aria-label="Delete sale <?php echo $sale['DeliveryNo']; ?>"
											title="Delete"
										>
											<i class="bi bi-trash3" aria-hidden="true"></i>
										</a>
									</div>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else: ?>
						<tr><td colspan="8" class="empty-state">No sold/delivery history found.</td></tr>
					<?php endif; ?>
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
