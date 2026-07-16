<?php
	$profileId = (int) ($_POST['profile_id'] ?? 1);
	$companyNameValue = trim((string) ($_POST['company_name'] ?? ''));
	$addressLine1Value = trim((string) ($_POST['address_line_1'] ?? ''));
	$addressLine2Value = trim((string) ($_POST['address_line_2'] ?? ''));
	$contactNumberValue = trim((string) ($_POST['contact_number'] ?? ''));
	$tinNumberValue = trim((string) ($_POST['tin_number'] ?? ''));

	if ($companyNameValue === '') {
		$companyNameValue = "UNO CGT Rice Trading";
	}

	$companyNameValue = $connectDB->real_escape_string($companyNameValue);
	$addressLine1Value = $connectDB->real_escape_string($addressLine1Value);
	$addressLine2Value = $connectDB->real_escape_string($addressLine2Value);
	$contactNumberValue = $connectDB->real_escape_string($contactNumberValue);
	$tinNumberValue = $connectDB->real_escape_string($tinNumberValue);

	$existingProfileCheck = $connectDB->query("SELECT ID FROM company_profile WHERE ID = '$profileId' LIMIT 1");
	if ($existingProfileCheck && $existingProfileCheck->num_rows > 0) {
		$connectDB->query("
			UPDATE company_profile
			SET
				CompanyName = '$companyNameValue',
				AddressLine1 = '$addressLine1Value',
				AddressLine2 = '$addressLine2Value',
				ContactNumber = '$contactNumberValue',
				TinNumber = '$tinNumberValue'
			WHERE ID = '$profileId'
		");
	} else {
		$connectDB->query("
			INSERT INTO company_profile (ID, CompanyName, AddressLine1, AddressLine2, ContactNumber, TinNumber)
			VALUES ('$profileId', '$companyNameValue', '$addressLine1Value', '$addressLine2Value', '$contactNumberValue', '$tinNumberValue')
		");
	}

	header("Location: " . $server . "?mainmenu=company_profile&saved=1");
	exit;
?>
