<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($appThemeMode); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo htmlspecialchars($companyName); ?></title>
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
	<?php
	if (in_array($mainMenu, ['purchase_invoice', 'sale_invoice', 'print_sale_receipt', 'print_purchase_invoice'], true)) {
		echo '<link href="' . $server . 'css/receipt.css" type="text/css" rel="stylesheet" />';
	}
	if ($mainMenu === 'print_purchase_invoice') {
		echo '<link href="' . $server . 'css/purchase-invoice-print.css" type="text/css" rel="stylesheet" />';
	}
	?>
	<link href="<?php echo $server; ?>css/font.css?v=<?php echo filemtime(__DIR__ . '/../css/font.css'); ?>" type="text/css" rel="stylesheet" />
	<script src="<?php echo $server; ?>css/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</head>
<body<?php echo in_array($mainMenu, ['sale_invoice', 'print_sale_receipt', 'print_purchase_invoice'], true) ? ' class="body-receipt"' : ''; ?>>
