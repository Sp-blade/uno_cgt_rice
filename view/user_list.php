<?php
	$users = [];
	$userResult = $connectDB->query("SELECT ID, Username, FullName, IsActive, CreatedAt FROM users ORDER BY Username ASC");
	if ($userResult && $userResult->num_rows > 0) {
		while ($row = $userResult->fetch_assoc()) {
			$users[] = $row;
		}
	}
	$loggedInUserId = junkshop_current_user_id();
?>

<div class="dashboard-card">
	<div class="dashboard-list-head">
		<div>
			<p class="section-kicker">Settings</p>
			<h3>User accounts</h3>
		</div>
		<button data-bs-toggle="modal" data-bs-target="#addUserModal" class="btn btn-info">Add User</button>
	</div>
	<p class="text-muted mb-0">New accounts can only be created here. Inactive users cannot sign in.</p>

	<div class="table-card margin-top">
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th>No</th>
						<th>Username</th>
						<th>Full Name</th>
						<th>Created</th>
						<th class="text-center">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if (count($users) === 0): ?>
						<tr>
							<td colspan="5" class="empty-state">No user accounts found.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($users as $index => $user): ?>
							<?php
								$userId = (int) ($user['ID'] ?? 0);
								$isActive = (int) ($user['IsActive'] ?? 0) === 1;
								$isCurrent = $userId === $loggedInUserId;
							?>
							<tr>
								<td><?php echo $index + 1; ?></td>
								<td>
									<?php echo htmlspecialchars($user['Username'] ?? ''); ?>
									<?php if ($isCurrent): ?>
										<span class="badge text-bg-light ms-1">Signed in</span>
									<?php endif; ?>
								</td>
								<td><?php echo htmlspecialchars(trim((string) ($user['FullName'] ?? '')) !== '' ? $user['FullName'] : '—'); ?></td>
								<td><?php echo htmlspecialchars(junkshop_format_datetime($user['CreatedAt'] ?? '')); ?></td>
								<td class="text-center">
									<form method="POST" class="d-inline-flex align-items-center gap-2">
										<input type="hidden" name="mainmenu" value="user_list" />
										<input type="hidden" name="user_action" value="toggle_user_status" />
										<input type="hidden" name="user_id" value="<?php echo $userId; ?>" />
										<input type="hidden" name="target_active" value="<?php echo $isActive ? '0' : '1'; ?>" />
										<div class="form-check form-switch m-0 d-inline-flex align-items-center">
											<input
												class="form-check-input"
												type="checkbox"
												role="switch"
												<?php echo $isActive ? 'checked' : ''; ?>
												<?php echo $isCurrent ? 'disabled' : ''; ?>
												onchange="this.form.querySelector('input[name=&quot;target_active&quot;]').value = this.checked ? '1' : '0'; this.form.submit();"
											/>
										</div>
										<span class="badge <?php echo $isActive ? 'text-bg-success' : 'text-bg-secondary'; ?>">
											<?php echo $isActive ? 'Active' : 'Inactive'; ?>
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

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form class="form new-form" method="POST">
				<div class="modal-header">
					<h4 class="modal-title">Add User</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="mainmenu" value="user_list" />
					<input type="hidden" name="user_action" value="add_user" />

					<label for="new_username" class="form-label">Username</label>
					<input type="text" class="form-control mb-3" id="new_username" name="username" minlength="3" required />

					<label for="new_full_name" class="form-label">Full Name</label>
					<input type="text" class="form-control mb-3" id="new_full_name" name="full_name" />

					<label for="new_password" class="form-label">Password</label>
					<input type="password" class="form-control mb-3" id="new_password" name="password" minlength="5" autocomplete="new-password" required />

					<label for="new_confirm_password" class="form-label">Confirm Password</label>
					<input type="password" class="form-control" id="new_confirm_password" name="confirm_password" minlength="5" autocomplete="new-password" required />
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save</button>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>
