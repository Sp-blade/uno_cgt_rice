<?php
	$appearanceSaved = isset($_GET['saved']) && $_GET['saved'] == '1';
?>

<?php if ($appearanceSaved): ?>
	<div class="alert alert-success mb-4" role="alert">
		Application colors updated successfully.
	</div>
<?php endif; ?>

<div class="row g-4">
	<div class="col-lg-7">
		<div class="entry-card">
			<div class="section-head">
				<div>
					<p class="section-kicker">Appearance</p>
					<h3>Application colors</h3>
				</div>
			</div>

			<form method="POST" action="?mainmenu=save_app_appearance" id="appAppearanceForm" class="row g-4">
				<input type="hidden" name="settings_id" value="<?php echo $appSettingsId; ?>">

				<div class="col-12">
					<label class="form-label d-block mb-3">Theme</label>
					<div class="theme-option-grid">
						<label class="theme-option-card">
							<input type="radio" name="theme_mode" value="default" <?php echo $appThemeMode === 'default' ? 'checked' : ''; ?>>
							<span class="theme-option-body">
								<span class="theme-option-title">Default</span>
								<span class="theme-option-desc">Light teal theme</span>
								<span class="theme-option-preview theme-preview-default" aria-hidden="true">
									<span></span><span></span><span></span>
								</span>
							</span>
						</label>

						<label class="theme-option-card">
							<input type="radio" name="theme_mode" value="dark" <?php echo $appThemeMode === 'dark' ? 'checked' : ''; ?>>
							<span class="theme-option-body">
								<span class="theme-option-title">Dark</span>
								<span class="theme-option-desc">Dark slate theme</span>
								<span class="theme-option-preview theme-preview-dark" aria-hidden="true">
									<span></span><span></span><span></span>
								</span>
							</span>
						</label>

						<label class="theme-option-card">
							<input type="radio" name="theme_mode" value="custom" <?php echo $appThemeMode === 'custom' ? 'checked' : ''; ?>>
							<span class="theme-option-body">
								<span class="theme-option-title">Customize</span>
								<span class="theme-option-desc">Pick your own colors</span>
								<span class="theme-option-preview theme-preview-custom" aria-hidden="true">
									<span style="background: <?php echo htmlspecialchars($appThemeCustomPrimary); ?>;"></span>
									<span style="background: <?php echo htmlspecialchars($appThemeCustomAccent); ?>;"></span>
									<span style="background: <?php echo htmlspecialchars($appThemeCustomBackground); ?>;"></span>
								</span>
							</span>
						</label>
					</div>
				</div>

				<div class="col-12" id="customThemeFields" <?php echo $appThemeMode === 'custom' ? '' : 'hidden'; ?>>
					<div class="custom-theme-fields">
						<div>
							<label class="form-label" for="custom_primary">Primary color</label>
							<div class="color-input-row">
								<input class="form-control form-control-color" type="color" id="custom_primary" name="custom_primary" value="<?php echo htmlspecialchars($appThemeCustomPrimary); ?>">
								<input class="form-control" type="text" id="custom_primary_text" value="<?php echo htmlspecialchars($appThemeCustomPrimary); ?>" maxlength="7" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})">
							</div>
						</div>

						<div>
							<label class="form-label" for="custom_accent">Accent color</label>
							<div class="color-input-row">
								<input class="form-control form-control-color" type="color" id="custom_accent" name="custom_accent" value="<?php echo htmlspecialchars($appThemeCustomAccent); ?>">
								<input class="form-control" type="text" id="custom_accent_text" value="<?php echo htmlspecialchars($appThemeCustomAccent); ?>" maxlength="7" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})">
							</div>
						</div>

						<div>
							<label class="form-label" for="custom_background">Background color</label>
							<div class="color-input-row">
								<input class="form-control form-control-color" type="color" id="custom_background" name="custom_background" value="<?php echo htmlspecialchars($appThemeCustomBackground); ?>">
								<input class="form-control" type="text" id="custom_background_text" value="<?php echo htmlspecialchars($appThemeCustomBackground); ?>" maxlength="7" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})">
							</div>
						</div>
					</div>
				</div>

				<div class="col-12 d-flex gap-2 flex-wrap">
					<button class="btn btn-primary" type="submit">Save Colors</button>
					<a class="btn btn-outline-secondary" href="?mainmenu=app_appearance">Reset</a>
				</div>
			</form>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="dashboard-card h-100">
			<div class="section-head">
				<div>
					<p class="section-kicker">Live preview</p>
					<h3>Theme preview</h3>
				</div>
			</div>
			<div class="theme-live-preview" id="themeLivePreview">
				<div class="theme-live-preview-sidebar">
					<div class="theme-live-preview-mark"></div>
					<div class="theme-live-preview-line"></div>
					<div class="theme-live-preview-line short"></div>
				</div>
				<div class="theme-live-preview-main">
					<div class="theme-live-preview-card">
						<div class="theme-live-preview-line"></div>
						<div class="theme-live-preview-line short"></div>
						<button type="button" class="theme-live-preview-button">Primary button</button>
					</div>
				</div>
			</div>
			<p class="summary-note mb-0">Saved colors apply across the dashboard, forms, tables, and navigation for all users on this device database.</p>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('appAppearanceForm');
	const customFields = document.getElementById('customThemeFields');
	const preview = document.getElementById('themeLivePreview');
	const themeRadios = form.querySelectorAll('input[name="theme_mode"]');

	const defaultTheme = {
		background: '#eff4f8',
		surface: 'rgba(255, 255, 255, 0.9)',
		text: '#0f172a',
		primary: '#0f766e',
		accent: '#f59e0b',
		primaryContrast: '#ffffff'
	};

	const darkTheme = {
		background: '#0f172a',
		surface: 'rgba(30, 41, 59, 0.88)',
		text: '#f1f5f9',
		primary: '#2dd4bf',
		accent: '#fbbf24',
		primaryContrast: '#0f172a'
	};

	const colorPairs = [
		['custom_primary', 'custom_primary_text'],
		['custom_accent', 'custom_accent_text'],
		['custom_background', 'custom_background_text']
	];

	function normalizeHex(value) {
		const trimmed = (value || '').trim();
		if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(trimmed)) {
			if (trimmed.length === 4) {
				return ('#' + trimmed[1] + trimmed[1] + trimmed[2] + trimmed[2] + trimmed[3] + trimmed[3]).toLowerCase();
			}
			return trimmed.toLowerCase();
		}
		return null;
	}

	function hexToRgb(hex) {
		const normalized = normalizeHex(hex);
		if (!normalized) {
			return { r: 0, g: 0, b: 0 };
		}

		return {
			r: parseInt(normalized.slice(1, 3), 16),
			g: parseInt(normalized.slice(3, 5), 16),
			b: parseInt(normalized.slice(5, 7), 16)
		};
	}

	function relativeLuminance(hex) {
		const rgb = hexToRgb(hex);
		const channels = [rgb.r, rgb.g, rgb.b].map(function (channel) {
			const value = channel / 255;
			return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
		});
		return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
	}

	function contrastRatio(first, second) {
		const firstLuminance = relativeLuminance(first);
		const secondLuminance = relativeLuminance(second);
		return (Math.max(firstLuminance, secondLuminance) + 0.05)
			/ (Math.min(firstLuminance, secondLuminance) + 0.05);
	}

	function contrastText(background) {
		return contrastRatio('#0f172a', background) >= contrastRatio('#ffffff', background)
			? '#0f172a'
			: '#ffffff';
	}

	function bindColorPair(colorId, textId) {
		const colorInput = document.getElementById(colorId);
		const textInput = document.getElementById(textId);
		if (!colorInput || !textInput) {
			return;
		}

		colorInput.addEventListener('input', function () {
			textInput.value = colorInput.value;
			updatePreview();
		});

		textInput.addEventListener('input', function () {
			const normalized = normalizeHex(textInput.value);
			if (normalized) {
				colorInput.value = normalized;
				textInput.value = normalized;
				updatePreview();
			}
		});
	}

	colorPairs.forEach(function (pair) {
		bindColorPair(pair[0], pair[1]);
	});

	function getSelectedThemeMode() {
		const selected = form.querySelector('input[name="theme_mode"]:checked');
		return selected ? selected.value : 'default';
	}

	function toggleCustomFields() {
		const isCustom = getSelectedThemeMode() === 'custom';
		customFields.hidden = !isCustom;
		updatePreview();
	}

	function updatePreview() {
		const mode = getSelectedThemeMode();
		let theme = defaultTheme;

		if (mode === 'dark') {
			theme = darkTheme;
		} else if (mode === 'custom') {
			const customBackground = document.getElementById('custom_background').value;
			const customPrimary = document.getElementById('custom_primary').value;
			const isLight = relativeLuminance(customBackground) >= 0.32;
			theme = {
				background: customBackground,
				surface: isLight ? 'rgba(255, 255, 255, 0.9)' : 'rgba(30, 41, 59, 0.88)',
				text: isLight ? '#0f172a' : '#f1f5f9',
				primary: customPrimary,
				accent: document.getElementById('custom_accent').value,
				primaryContrast: contrastText(customPrimary)
			};
		}

		preview.style.background = theme.background;
		preview.style.color = theme.text;
		preview.style.setProperty('--preview-surface', theme.surface);
		preview.style.setProperty('--preview-primary', theme.primary);
		preview.style.setProperty('--preview-accent', theme.accent);
		preview.style.setProperty('--preview-primary-contrast', theme.primaryContrast);
	}

	themeRadios.forEach(function (radio) {
		radio.addEventListener('change', toggleCustomFields);
	});

	updatePreview();
});
</script>
