<?php
	$settingsId = (int) ($_POST['settings_id'] ?? 1);
	$themeMode = strtolower(trim((string) ($_POST['theme_mode'] ?? 'default')));

	if (!in_array($themeMode, ['default', 'dark', 'custom'], true)) {
		$themeMode = 'default';
	}

	$customPrimary = junkshop_normalize_hex_color($_POST['custom_primary'] ?? '#0f766e', '#0f766e');
	$customAccent = junkshop_normalize_hex_color($_POST['custom_accent'] ?? '#f59e0b', '#f59e0b');
	$customBackground = junkshop_normalize_hex_color($_POST['custom_background'] ?? '#eff4f8', '#eff4f8');

	$themeModeValue = $connectDB->real_escape_string($themeMode);
	$customPrimaryValue = $connectDB->real_escape_string($customPrimary);
	$customAccentValue = $connectDB->real_escape_string($customAccent);
	$customBackgroundValue = $connectDB->real_escape_string($customBackground);

	$existingSettingsCheck = $connectDB->query("SELECT ID FROM app_settings WHERE ID = '$settingsId' LIMIT 1");
	if ($existingSettingsCheck && $existingSettingsCheck->num_rows > 0) {
		$connectDB->query("
			UPDATE app_settings
			SET
				ThemeMode = '$themeModeValue',
				CustomPrimary = '$customPrimaryValue',
				CustomAccent = '$customAccentValue',
				CustomBackground = '$customBackgroundValue'
			WHERE ID = '$settingsId'
		");
	} else {
		$connectDB->query("
			INSERT INTO app_settings (ID, ThemeMode, CustomPrimary, CustomAccent, CustomBackground)
			VALUES ('$settingsId', '$themeModeValue', '$customPrimaryValue', '$customAccentValue', '$customBackgroundValue')
		");
	}

	header("Location: " . $server . "?mainmenu=app_appearance&saved=1");
	exit;
?>
