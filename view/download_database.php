<?php
	$restoreStatus = isset($_GET['restore_status']) ? $_GET['restore_status'] : '';
	$restoreMessage = isset($_GET['restore_message']) ? $_GET['restore_message'] : '';
	$expectedBackupName = 'unocgtricedb_backup.sql';
	$expectedDatabaseName = 'unocgtricedb';
?>

<div class="container-fluid has-fixed-footer">
	<div class="dashboard-card">
		<div class="section-head">
			<div>
				<p class="section-kicker">Database tools</p>
				<h3>Backup and restore database</h3>
			</div>
		</div>

		<?php if ($restoreMessage !== ''): ?>
			<div class="alert <?php echo $restoreStatus === 'success' ? 'alert-success' : 'alert-danger'; ?>" role="alert">
				<?php echo htmlspecialchars($restoreMessage); ?>
			</div>
		<?php endif; ?>

		<div class="row g-4">
			<div class="col-lg-6">
				<div class="table-card h-100">
					<div class="section-head">
						<div>
							<p class="section-kicker">Download backup</p>
							<h3>Export current database</h3>
						</div>
					</div>
					<p class="text-muted mb-3">Download a full SQL backup of the live database before making major changes.</p>
					<p class="mb-3"><strong>Expected backup file name:</strong> <?php echo htmlspecialchars($expectedBackupName); ?></p>
					<a class="btn btn-primary" href="?mainmenu=download_database_file">Download Backup</a>
				</div>
			</div>

			<div class="col-lg-6">
				<div class="table-card h-100">
					<div class="section-head">
						<div>
							<p class="section-kicker">Restore backup</p>
							<h3>Upload and replace live database</h3>
						</div>
					</div>
					<div class="alert alert-warning" role="alert">
						<strong>Warning:</strong> Restoring a backup will update the existing database and replace current data in <strong><?php echo htmlspecialchars($expectedDatabaseName); ?></strong>.
					</div>
					<form method="POST" action="?mainmenu=restore_database" enctype="multipart/form-data">
						<div class="mb-3">
							<label for="database_backup" class="form-label">Backup file</label>
							<input type="file" class="form-control" id="database_backup" name="database_backup" accept=".sql" required />
							<small class="text-muted">Upload only the backup for <?php echo htmlspecialchars($expectedDatabaseName); ?>. Example: <?php echo htmlspecialchars($expectedBackupName); ?></small>
						</div>
						<div class="form-check mb-3">
							<input class="form-check-input" type="checkbox" value="yes" id="confirm_restore" name="confirm_restore" required />
							<label class="form-check-label" for="confirm_restore">
								I understand this will update the existing database and may overwrite current data.
							</label>
						</div>
						<button type="submit" class="btn btn-danger">Upload And Restore Database</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
