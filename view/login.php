<?php
	$loginError = trim((string) ($loginError ?? ''));
	$loginUsername = trim((string) ($loginUsername ?? ''));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($appThemeMode ?? 'default'); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Sign in | <?php echo htmlspecialchars($companyName ?? 'UNO CGT Rice Trading'); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
	<link href="<?php echo $server; ?>css/main.css?v=<?php echo filemtime(__DIR__ . '/../css/main.css'); ?>" type="text/css" rel="stylesheet" />
	<?php if (!empty($appThemeCssVars)): ?>
	<style id="app-theme-vars">
		:root {
			<?php foreach ($appThemeCssVars as $themeVar => $themeValue): ?>
			<?php echo htmlspecialchars($themeVar); ?>: <?php echo htmlspecialchars($themeValue); ?>;
			<?php endforeach; ?>
		}
	</style>
	<?php endif; ?>
</head>
<body class="body-login">
	<div class="login-shell">
		<div class="login-card">
			<div class="login-brand">
				<div class="sidebar-brand-mark"><?php echo htmlspecialchars($companyInitials ?? 'UC'); ?></div>
				<div>
					<p class="sidebar-kicker">Operations Suite</p>
					<h1><?php echo htmlspecialchars($companyName ?? 'UNO CGT Rice Trading'); ?></h1>
				</div>
			</div>
			<p class="login-subtitle">Sign in to continue. Accounts can only be created from Settings after you are signed in.</p>

			<?php if ($loginError !== ''): ?>
				<div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($loginError); ?></div>
			<?php endif; ?>

			<form method="POST" action="<?php echo htmlspecialchars($server); ?>" class="login-form">
				<input type="hidden" name="mainmenu" value="login" />
				<label class="form-label" for="login_username">Username</label>
				<input class="form-control mb-3" type="text" id="login_username" name="username" value="<?php echo htmlspecialchars($loginUsername); ?>" autocomplete="username" required autofocus />

				<label class="form-label" for="login_password">Password</label>
				<input class="form-control mb-4" type="password" id="login_password" name="password" autocomplete="current-password" required />

				<button class="btn btn-primary w-100" type="submit">Sign in</button>
			</form>
		</div>
	</div>
</body>
</html>
