<?php
	$action = trim((string) ($_POST['user_action'] ?? $_GET['user_action'] ?? ''));
	$username = trim((string) ($_POST['username'] ?? ''));
	$fullName = trim((string) ($_POST['full_name'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
	$userId = (int) ($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
	$targetActive = isset($_POST['target_active']) ? (int) $_POST['target_active'] : (isset($_GET['target_active']) ? (int) $_GET['target_active'] : 1);
	$loggedInUserId = junkshop_current_user_id();

	if ($action === 'add_user') {
		if ($username === '') {
			echo "<div class='alert alert-danger' role='alert'>Username is required.</div>";
			return;
		}
		if (strlen($username) < 3) {
			echo "<div class='alert alert-danger' role='alert'>Username must be at least 3 characters.</div>";
			return;
		}
		if ($password === '' || $confirmPassword === '') {
			echo "<div class='alert alert-danger' role='alert'>Password and confirm password are required.</div>";
			return;
		}
		if ($password !== $confirmPassword) {
			echo "<div class='alert alert-danger' role='alert'>Passwords do not match.</div>";
			return;
		}
		if (strlen($password) < 5) {
			echo "<div class='alert alert-danger' role='alert'>Password must be at least 5 characters.</div>";
			return;
		}

		$safeUsername = mysqli_real_escape_string($connectDB, $username);
		$duplicateCheck = $connectDB->query("SELECT ID FROM users WHERE Username = '$safeUsername' LIMIT 1");
		if ($duplicateCheck && $duplicateCheck->num_rows > 0) {
			echo "<div class='alert alert-danger' role='alert'>That username is already in use.</div>";
			return;
		}

		$safeFullName = mysqli_real_escape_string($connectDB, $fullName);
		$safePasswordHash = mysqli_real_escape_string($connectDB, password_hash($password, PASSWORD_DEFAULT));
		if ($connectDB->query("
			INSERT INTO users (Username, PasswordHash, FullName, IsActive)
			VALUES ('$safeUsername', '$safePasswordHash', '$safeFullName', 1)
		")) {
			echo "<div class='alert alert-success' role='alert'>User " . htmlspecialchars($username) . " has been added.</div>";
		} else {
			echo "<div class='alert alert-danger' role='alert'>Unable to add user.</div>";
		}
		return;
	}

	if ($action === 'toggle_user_status' && $userId > 0) {
		$targetActive = $targetActive === 1 ? 1 : 0;
		if ($userId === $loggedInUserId && $targetActive === 0) {
			echo "<div class='alert alert-danger' role='alert'>You cannot deactivate the account that is currently signed in.</div>";
			return;
		}

		if ($targetActive === 0) {
			$activeCountResult = $connectDB->query("SELECT COUNT(*) AS total FROM users WHERE IsActive = 1");
			$activeCount = $activeCountResult ? (int) ($activeCountResult->fetch_assoc()['total'] ?? 0) : 0;
			if ($activeCount <= 1) {
				echo "<div class='alert alert-danger' role='alert'>Keep at least one active account so someone can still sign in.</div>";
				return;
			}
		}

		$safeUserId = mysqli_real_escape_string($connectDB, (string) $userId);
		if ($connectDB->query("UPDATE users SET IsActive = '$targetActive' WHERE ID = '$safeUserId'")) {
			$statusLabel = $targetActive === 1 ? 'active' : 'inactive';
			echo "<div class='alert alert-success' role='alert'>User status updated to " . htmlspecialchars($statusLabel) . ".</div>";
		} else {
			echo "<div class='alert alert-danger' role='alert'>Unable to update user status.</div>";
		}
	}
?>
