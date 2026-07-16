<?php
	$profileSaved = isset($_GET['saved']) && $_GET['saved'] == '1';
?>

<?php if ($profileSaved): ?>
	<div class="alert alert-success mb-4" role="alert">
		Company profile updated successfully.
	</div>
<?php endif; ?>

<div class="row g-4">
	<div class="col-lg-7">
		<div class="entry-card">
			<div class="section-head">
				<div>
					<p class="section-kicker">Business settings</p>
					<h3>Edit company profile</h3>
				</div>
			</div>
			<form method="POST" action="?mainmenu=save_company_profile" class="row g-3">
				<input type="hidden" name="profile_id" value="<?php echo $companyProfileId; ?>">

				<div class="col-12">
					<label class="form-label" for="company_name">Company Name</label>
					<input class="form-control" type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($companyName); ?>" required>
				</div>

				<div class="col-12">
					<label class="form-label" for="address_line_1">Address Line 1</label>
					<input class="form-control" type="text" id="address_line_1" name="address_line_1" value="<?php echo htmlspecialchars($companyAddressLine1); ?>">
				</div>

				<div class="col-12">
					<label class="form-label" for="address_line_2">Address Line 2</label>
					<input class="form-control" type="text" id="address_line_2" name="address_line_2" value="<?php echo htmlspecialchars($companyAddressLine2); ?>">
				</div>

				<div class="col-md-6">
					<label class="form-label" for="contact_number">Contact Number</label>
					<input class="form-control" type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($companyContactNumber); ?>">
				</div>

				<div class="col-md-6">
					<label class="form-label" for="tin_number">TIN Number</label>
					<input class="form-control" type="text" id="tin_number" name="tin_number" value="<?php echo htmlspecialchars($companyTinNumber); ?>">
				</div>

				<div class="col-12 d-flex gap-2 flex-wrap">
					<button class="btn btn-primary" type="submit">Save Company Profile</button>
					<a class="btn btn-outline-secondary" href="?mainmenu=company_profile">Reset</a>
				</div>
			</form>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="dashboard-card h-100">
			<div class="section-head">
				<div>
					<p class="section-kicker">Live preview</p>
					<h3>Business identity</h3>
				</div>
			</div>
			<div class="company-profile-preview">
				<div class="company-profile-mark"><?php echo htmlspecialchars($companyInitials); ?></div>
				<h4><?php echo htmlspecialchars($companyName); ?></h4>
				<?php if ($companyAddressLine1 !== ''): ?>
					<p><?php echo htmlspecialchars($companyAddressLine1); ?></p>
				<?php endif; ?>
				<?php if ($companyAddressLine2 !== ''): ?>
					<p><?php echo htmlspecialchars($companyAddressLine2); ?></p>
				<?php endif; ?>
				<?php if ($companyContactNumber !== ''): ?>
					<p><strong>Contact:</strong> <?php echo htmlspecialchars($companyContactNumber); ?></p>
				<?php endif; ?>
				<?php if ($companyTinNumber !== ''): ?>
					<p><strong>TIN:</strong> <?php echo htmlspecialchars($companyTinNumber); ?></p>
				<?php endif; ?>
			</div>
			<p class="summary-note mb-0">Changes here are used by the app shell, purchase receipt, and delivery invoice automatically.</p>
		</div>
	</div>
</div>
