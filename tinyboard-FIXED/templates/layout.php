<?php
/** @var callable $e */
/** @var callable $url */
/** @var string $title */
/** @var string $content */
/** @var \Newboard\Config $config */
/** @var string $stylesheet */
/** @var string $stylesheet_name */
/** @var array<string,string> $stylesheets */
/** @var list<array<string,mixed>>|null $boardlist */
/** @var string|null $active_page */
/** @var mixed $mod */
$site = $config->string('name', 'Newboard');
$active_page = $active_page ?? 'page';
$isMod = !empty($mod);
$bodyClass = '8chan vichan '
    . ($isMod ? 'is-moderator' : 'is-not-moderator')
    . ' active-' . preg_replace('/[^a-z0-9_-]/i', '', $active_page);
$stylesheets = is_array($stylesheets ?? null) ? $stylesheets : [];
$stylesheet = $stylesheet ?? 'yotsuba_b.css';
$ssUrl = $url('/stylesheets/' . $stylesheet);
$baseCss = $url('/stylesheets/style.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">
	<meta name="referrer" content="no-referrer">
	<title><?= $e($title ?? $site) ?></title>
	<link rel="stylesheet" media="screen" href="<?= $e($baseCss) ?>">
	<link rel="stylesheet" type="text/css" id="stylesheet" href="<?= $e($ssUrl) ?>">
	<script>
	function setStylesheet(name, file) {
		document.cookie = 'newboard_style=' + encodeURIComponent(name) + ';path=/;max-age=31536000;SameSite=Lax';
		var el = document.getElementById('stylesheet');
		if (el) el.href = <?= json_encode($url('/stylesheets/'), JSON_UNESCAPED_SLASHES) ?> + file;
	}
	</script>
	<style>
	/* small newboard extras on top of vichan skins */
	.mod-controls { font-size: 9pt; margin-left: 0.4em; }
	.mod-controls button { font-size: 8pt; cursor: pointer; }
	form.inline { display: inline; }
	.archive-list { list-style: none; padding: 0; margin: 1rem 0; }
	.archive-list li {
		display: flex; gap: 0.75rem; align-items: flex-start;
		border-bottom: 1px solid #ccc; padding: 0.6rem 0;
	}
	.archive-list .athumb img { max-width: 100px; max-height: 100px; }
	.archive-list .ameta { font-size: 0.9em; opacity: 0.85; }
	.archive-list .asnippet { margin-top: 0.25rem; }
	.archive-empty { margin: 2rem 0; opacity: 0.8; }
	.style-select { float: right; font-size: 9pt; margin: 2px 4px; }
	</style>
</head>
<body class="<?= $e($bodyClass) ?>" data-stylesheet="<?= $e($stylesheet) ?>">
	<div class="boardlist">
		<?php
		$links = [];
		$nav = $config->get('nav', []);
		if (is_array($nav)) {
		    foreach ($nav as $label => $href) {
		        if (is_string($href)) {
		            $links[$label] = $href;
		        }
		    }
		}
		if (!empty($boardlist) && is_array($boardlist)) {
		    foreach ($boardlist as $b) {
		        $links[(string) $b['uri']] = '/' . $b['uri'];
		    }
		}
		$cur = isset($board['uri']) ? (string) $board['uri'] : null;
		if ($cur !== null && $cur !== '') {
		    $links['Catalog'] = '/' . $cur . '/catalog';
		    $links['Archive'] = '/' . $cur . '/archive';
		}
		$links['Mod'] = '/mod';
		$first = true;
		foreach ($links as $label => $href):
		    if (!$first) {
		        echo ' ';
		    }
		    $first = false;
		    $hrefOut = str_starts_with((string) $href, 'http') ? $href : $url((string) $href);
		?>
			<a href="<?= $e($hrefOut) ?>"><?= $e((string) $label) ?></a>
		<?php endforeach; ?>
		<span class="style-select">
			Style:
			<select onchange="var o=this.options[this.selectedIndex]; setStylesheet(o.value, o.getAttribute('data-file'));">
				<?php foreach ($stylesheets as $name => $file): ?>
					<option value="<?= $e($name) ?>" data-file="<?= $e($file) ?>" <?= ($stylesheet_name ?? '') === $name ? 'selected' : '' ?>><?= $e($name) ?></option>
				<?php endforeach; ?>
			</select>
		</span>
	</div>

	<?= $content ?>

	<div class="boardlist bottom">
		<?php $first = true; foreach ($links as $label => $href):
		    if (!$first) {
		        echo ' ';
		    }
		    $first = false;
		    $hrefOut = str_starts_with((string) $href, 'http') ? $href : $url((string) $href);
		?>
			<a href="<?= $e($hrefOut) ?>"><?= $e((string) $label) ?></a>
		<?php endforeach; ?>
	</div>
	<footer>
		<p class="unimportant" style="text-align:center">
			<?= $e($site) ?> — no IP collection · PHP <?= $e(PHP_VERSION) ?> · SQLite
			· skins from vichanBEST1
		</p>
	</footer>
</body>
</html>
