<?php
/* compiled: header.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<link rel="stylesheet" media="screen" href="'; ?><?php echo view_get($__ctx, 'config.url_stylesheet'); ?><?php echo '?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '">
'; ?><?php if (view_get($__ctx, 'config.url_favicon')): ?><?php echo '<link rel="shortcut icon" href="'; ?><?php echo view_get($__ctx, 'config.url_favicon'); ?><?php echo '">'; ?><?php endif; ?><?php echo '
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">
'; ?><?php if (view_get($__ctx, 'config.meta_keywords')): ?><?php echo '<meta name="keywords" content="'; ?><?php echo view_get($__ctx, 'config.meta_keywords'); ?><?php echo '">'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'config.default_stylesheet.1') != ''): ?><?php echo '<link rel="stylesheet" type="text/css" id="stylesheet" href="'; ?><?php echo view_get($__ctx, 'config.uri_stylesheets'); ?><?php echo view_get($__ctx, 'config.default_stylesheet.1'); ?><?php echo '?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '">'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'config.font_awesome')): ?><?php echo '<link rel="stylesheet" href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'config.font_awesome_css'); ?><?php echo '?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '">'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'config.country_flags_condensed')): ?><?php echo '<link rel="stylesheet" href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'config.country_flags_condensed_css'); ?><?php echo '?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '">'; ?><?php endif; ?><?php echo '
<script type="text/javascript">
	var configRoot="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo '";
	var inMod = '; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo ' true '; ?><?php else: ?><?php echo ' false '; ?><?php endif; ?><?php echo ';
	var modRoot = "'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo '" + (inMod ? "mod.php?/" : "");
</script>
'; ?><?php if (!(view_get($__ctx, 'nojavascript'))): ?><?php echo '
	<script type="text/javascript" src="'; ?><?php echo view_get($__ctx, 'config.url_javascript'); ?><?php echo '?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '" data-resource-version="'; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '"></script>
	'; ?><?php if (!(view_get($__ctx, 'config.additional_javascript_compile'))): ?><?php echo '
	'; ?><?php $__iter = view_get($__ctx, 'config.additional_javascript'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $javascript): $__ctx['javascript'] = $javascript; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '<script type="text/javascript" src="'; ?><?php echo view_get($__ctx, 'config.additional_javascript_url'); ?><?php echo view_get($__ctx, 'javascript'); ?><?php echo '?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '"></script>'; ?><?php endforeach; ?><?php echo '
	'; ?><?php endif; ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo '
	<script type="text/javascript" src="/js/mod/mod_snippets.js?v='; ?><?php echo view_get($__ctx, 'config.resource_version'); ?><?php echo '"></script>
	'; ?><?php endif; ?><?php echo '
'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'config.captcha.provider') == 'recaptcha'): ?><?php echo '<script src="//www.recaptcha.net/recaptcha/api.js"></script>
<style type="text/css">'; ?><?php echo '
	#recaptcha_area {
		float: none !important;
		padding: 0 !important;
	}
	#recaptcha_logo, #recaptcha_privacy {
		display: none;
	}
	#recaptcha_table {
		border: none !important;
	}
	#recaptcha_table tr:first-child {
		height: auto;
	}
	.recaptchatable img {
		float: none !important;
	}
	#recaptcha_response_field {
		font-size: 10pt !important;
		border: 1px solid #a9a9a9 !important;
		padding: 1px !important;
	}
	td.recaptcha_image_cell {
		background: transparent !important;
	}
	.recaptchatable, #recaptcha_area tr, #recaptcha_area td, #recaptcha_area th {
		padding: 0 !important;
	}
'; ?><?php /* unknown tag: endverbatim */ ?><?php echo '</style>'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'config.captcha.provider.hcaptcha')): ?><?php echo '
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
'; ?><?php endif; ?><?php echo '
'; ?>