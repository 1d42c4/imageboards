<?php
/* compiled: catalog.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<script type="text/javascript">
		var active_page = "catalog"
		  , board_name = "'; ?><?php echo view_get($__ctx, 'board'); ?><?php echo '";
	</script>
	'; ?><?php echo Element('header.html', $__ctx); ?><?php echo '
	<title>'; ?><?php echo view_get($__ctx, 'board'); ?><?php echo ' - Catalog</title>
	<style>
		.theme-catalog .threads { margin: 1rem 0; }
		.theme-catalog #Grid { display: flex; flex-wrap: wrap; gap: 0.75rem; }
		.theme-catalog .mix { width: auto; }
		.theme-catalog .grid-size-vsmall .thread-image { max-width: 80px; max-height: 80px; }
		.theme-catalog .grid-size-small .thread-image { max-width: 150px; max-height: 150px; }
		.theme-catalog .grid-size-large .thread-image { max-width: 300px; max-height: 300px; }
		.theme-catalog .thread { border: 1px solid #aaa; padding: 0.4rem; max-width: 320px; }
		.theme-catalog .replies { font-size: 0.9em; max-height: 8em; overflow: hidden; }
	</style>
</head>
<body class="8chan vichan '; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo 'is-moderator'; ?><?php else: ?><?php echo 'is-not-moderator'; ?><?php endif; ?><?php echo ' theme-catalog active-catalog" data-stylesheet="'; ?><?php if (view_get($__ctx, 'config.default_stylesheet.1') != ''): ?><?php echo view_get($__ctx, 'config.default_stylesheet.1'); ?><?php else: ?><?php echo 'default'; ?><?php endif; ?><?php echo '">
	'; ?><?php echo view_get($__ctx, 'boardlist.top'); ?><?php echo '
	<header>
		<h1>'; ?><?php echo view_get($__ctx, 'settings.title'); ?><?php echo ' (<a href="'; ?><?php echo view_get($__ctx, 'link'); ?><?php echo '">/'; ?><?php echo view_get($__ctx, 'board'); ?><?php echo '/</a>)</h1>
		<div class="subtitle">'; ?><?php echo view_get($__ctx, 'settings.subtitle'); ?><?php echo '
			'; ?><?php if (view_get($__ctx, 'config.archive.enabled')): ?><?php echo '
				— <a href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'config.archive.dir'); ?><?php echo view_get($__ctx, 'config.archive.file_index'); ?><?php echo '">'; ?><?php echo _('Archive'); ?><?php echo '</a>
			'; ?><?php endif; ?><?php echo '
			'; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo '<p><a href="?/">'; ?><?php echo _('Return to dashboard'); ?><?php echo '</a></p>'; ?><?php endif; ?><?php echo '
		</div>
	</header>

	<span>'; ?><?php echo _('Sort by'); ?><?php echo ': </span>
	<select id="sort_by" style="display: inline-block">
		<option selected value="bump:desc">'; ?><?php echo _('Bump order'); ?><?php echo '</option>
		<option value="time:desc">'; ?><?php echo _('Creation date'); ?><?php echo '</option>
		<option value="reply:desc">'; ?><?php echo _('Reply count'); ?><?php echo '</option>
		<option value="random:desc">'; ?><?php echo _('Random'); ?><?php echo '</option>
	</select>

	<span>'; ?><?php echo _('Image size'); ?><?php echo ': </span>
	<select id="image_size" style="display: inline-block">
		<option value="vsmall">'; ?><?php echo _('Very small'); ?><?php echo '</option>
		<option selected value="small">'; ?><?php echo _('Small'); ?><?php echo '</option>
		<option value="large">'; ?><?php echo _('Large'); ?><?php echo '</option>
	</select>
	<div class="threads">
		<div id="Grid">
		'; ?><?php $__iter = view_get($__ctx, 'recent_posts'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $post): $__ctx['post'] = $post; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
			<div class="mix"
				data-reply="'; ?><?php echo view_get($__ctx, 'post.reply_count'); ?><?php echo '"
				 data-bump="'; ?><?php echo view_get($__ctx, 'post.bump'); ?><?php echo '"
				 data-time="'; ?><?php echo view_get($__ctx, 'post.time'); ?><?php echo '"
				 data-id="'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '"
				 data-sticky="'; ?><?php if (view_get($__ctx, 'post.sticky')): ?><?php echo 'true'; ?><?php else: ?><?php echo 'false'; ?><?php endif; ?><?php echo '"
				 data-locked="'; ?><?php if (view_get($__ctx, 'post.locked')): ?><?php echo 'true'; ?><?php else: ?><?php echo 'false'; ?><?php endif; ?><?php echo '"
			>
				<div class="thread grid-li grid-size-small">
					<a href="'; ?><?php echo view_get($__ctx, 'post.link'); ?><?php echo '">
						'; ?><?php if (view_get($__ctx, 'post.youtube')): ?><?php echo '
							<img src="https://img.youtube.com/vi/'; ?><?php echo view_get($__ctx, 'post.youtube'); ?><?php echo '/0.jpg"
						'; ?><?php else: ?><?php echo '
							<img src="'; ?><?php echo view_get($__ctx, 'post.file'); ?><?php echo '"
						'; ?><?php endif; ?><?php echo '
						id="img-'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" data-subject="'; ?><?php if (view_get($__ctx, 'post.subject')): ?><?php echo view_filter(view_get($__ctx, 'post.subject'), 'e', []); ?><?php endif; ?><?php echo '" data-name="'; ?><?php echo view_filter(view_get($__ctx, 'post.name'), 'e', []); ?><?php echo '" class="'; ?><?php echo view_get($__ctx, 'post.board'); ?><?php echo ' thread-image" title="'; ?><?php echo view_get($__ctx, 'post.pubdate'); ?><?php echo '"'; ?><?php if (view_get($__ctx, 'config.content_lazy_loading')): ?><?php echo ' loading="lazy"'; ?><?php endif; ?><?php echo '>
					</a>
						<div class="replies">
							<strong>R: '; ?><?php echo view_get($__ctx, 'post.reply_count'); ?><?php echo ' / I: '; ?><?php echo view_get($__ctx, 'post.image_count'); ?><?php if (view_get($__ctx, 'post.sticky')): ?><?php echo ' (sticky)'; ?><?php endif; ?><?php echo '</strong>
							'; ?><?php if (view_get($__ctx, 'post.subject')): ?><?php echo '
								<p class="intro">
									<span class="subject">
										'; ?><?php echo view_filter(view_get($__ctx, 'post.subject'), 'e', []); ?><?php echo '
									</span>
								</p>
							'; ?><?php else: ?><?php echo '
								<br />
							'; ?><?php endif; ?><?php echo '
								'; ?><?php echo view_get($__ctx, 'post.body'); ?><?php echo '
						</div>
				</div>
			</div>
		'; ?><?php endforeach; ?><?php echo '
		</div>
	</div>

	<hr/>
	'; ?><?php echo Element('footer.html', $__ctx); ?><?php echo '
	<script type="text/javascript">
		onReady(init);
		ready();
	</script>
</body>
</html>
'; ?>