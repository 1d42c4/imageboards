<?php
/* compiled: archive_index.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<script type="text/javascript">
		var active_page = "archive"
		  , board_name = "'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '";
	</script>
	'; ?><?php echo Element('header.html', $__ctx); ?><?php echo '
	<title>'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo _('Archive'); ?><?php echo '</title>
	<style>
		.archive-list { list-style: none; padding: 0; margin: 1rem 0; }
		.archive-list li {
			display: flex; gap: 0.75rem; align-items: flex-start;
			border-bottom: 1px solid #ccc; padding: 0.6rem 0;
		}
		.archive-list .athumb img { max-width: 100px; max-height: 100px; }
		.archive-list .ameta { font-size: 0.9em; opacity: 0.85; }
		.archive-list .asnippet { margin-top: 0.25rem; }
		.archive-banner { font-weight: bold; }
		.archive-empty { margin: 2rem 0; opacity: 0.8; }
	</style>
</head>
<body class="8chan vichan is-not-moderator active-archive" data-stylesheet="'; ?><?php if (view_get($__ctx, 'config.default_stylesheet.1') != ''): ?><?php echo view_get($__ctx, 'config.default_stylesheet.1'); ?><?php else: ?><?php echo 'default'; ?><?php endif; ?><?php echo '">
	'; ?><?php echo view_get($__ctx, 'boardlist.top'); ?><?php echo '

	<header>
		<h1>'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo _('Archive'); ?><?php echo '</h1>
		<div class="subtitle">
			'; ?><?php echo _('Read-only threads that have left the board'); ?><?php echo '
			— <a href="'; ?><?php echo view_get($__ctx, 'return'); ?><?php echo '">['; ?><?php echo _('Back to board'); ?><?php echo ']</a>
			'; ?><?php if (view_get($__ctx, 'config.catalog_link')): ?><?php echo '
				<a href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo view_get($__ctx, 'config.catalog_link'); ?><?php echo '">['; ?><?php echo _('Catalog'); ?><?php echo ']</a>
			'; ?><?php endif; ?><?php echo '
		</div>
	</header>

	<div class="banner archive-banner">
		'; ?><?php echo _('Archive'); ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'total')): ?><?php echo ' — '; ?><?php echo view_get($__ctx, 'total'); ?><?php echo ' '; ?><?php if (view_get($__ctx, 'total') == 1): ?><?php echo _('thread'); ?><?php else: ?><?php echo _('threads'); ?><?php endif; ?><?php endif; ?><?php echo '
	</div>

	'; ?><?php if (view_get($__ctx, 'threads')): ?><?php echo '
	<ul class="archive-list">
		'; ?><?php $__iter = view_get($__ctx, 'threads'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $t): $__ctx['t'] = $t; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
		<li>
			'; ?><?php if (view_get($__ctx, 't.thumb')): ?><?php echo '
			<a class="athumb" href="'; ?><?php echo view_get($__ctx, 't.url'); ?><?php echo '"><img src="'; ?><?php echo view_get($__ctx, 'config.uri_thumb'); ?><?php echo view_get($__ctx, 't.thumb'); ?><?php echo '" alt="" /></a>
			'; ?><?php endif; ?><?php echo '
			<div>
				<a href="'; ?><?php echo view_get($__ctx, 't.url'); ?><?php echo '"><strong>'; ?><?php if (view_get($__ctx, 't.subject')): ?><?php echo view_filter(view_get($__ctx, 't.subject'), 'e', []); ?><?php else: ?><?php echo 'No.'; ?><?php echo view_get($__ctx, 't.id'); ?><?php endif; ?><?php echo '</strong></a>
				<span class="ameta">
					— '; ?><?php echo view_filter(view_get($__ctx, 't.name'), 'e', []); ?><?php echo '
					— No.'; ?><?php echo view_get($__ctx, 't.id'); ?><?php echo '
					— '; ?><?php echo view_get($__ctx, 't.reply_count'); ?><?php echo ' '; ?><?php if (view_get($__ctx, 't.reply_count') == 1): ?><?php echo _('reply'); ?><?php else: ?><?php echo _('replies'); ?><?php endif; ?><?php echo '
					— '; ?><?php echo _('posted'); ?><?php echo ' <time datetime="'; ?><?php echo view_filter(view_get($__ctx, 't.time'), 'date', ['c']); ?><?php echo '">'; ?><?php echo view_filter(view_get($__ctx, 't.time'), 'date', [view_get($__ctx, 'config.post_date')]); ?><?php echo '</time>
					— '; ?><?php echo _('archived'); ?><?php echo ' <time datetime="'; ?><?php echo view_filter(view_get($__ctx, 't.archived_at'), 'date', ['c']); ?><?php echo '">'; ?><?php echo view_filter(view_get($__ctx, 't.archived_at'), 'date', [view_get($__ctx, 'config.post_date')]); ?><?php echo '</time>
				</span>
				<div class="asnippet">'; ?><?php echo view_filter(view_get($__ctx, 't.snippet'), 'e', []); ?><?php echo '</div>
			</div>
		</li>
		'; ?><?php endforeach; ?><?php echo '
	</ul>
	'; ?><?php else: ?><?php echo '
	<p class="archive-empty">'; ?><?php echo _('No archived threads yet. When threads fall off the board they will appear here, read-only.'); ?><?php echo '</p>
	'; ?><?php endif; ?><?php echo '

	'; ?><?php if (view_filter(view_get($__ctx, 'pages'), 'length', []) > 1): ?><?php echo '
	<div class="pages">
		'; ?><?php $__iter = view_get($__ctx, 'pages'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $page): $__ctx['page'] = $page; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
		 [<a '; ?><?php if (view_get($__ctx, 'page.selected')): ?><?php echo 'class="selected"'; ?><?php endif; ?><?php if (!(view_get($__ctx, 'page.selected'))): ?><?php echo 'href="'; ?><?php echo view_get($__ctx, 'page.link'); ?><?php echo '"'; ?><?php endif; ?><?php echo '>'; ?><?php echo view_get($__ctx, 'page.num'); ?><?php echo '</a>]
		'; ?><?php endforeach; ?><?php echo '
	</div>
	'; ?><?php endif; ?><?php echo '

	'; ?><?php echo view_get($__ctx, 'boardlist.bottom'); ?><?php echo '
	'; ?><?php echo Element('footer.html', $__ctx); ?><?php echo '
	<script type="text/javascript">'; ?><?php echo '
		ready();
	'; ?><?php /* unknown tag: endverbatim */ ?><?php echo '</script>
</body>
</html>
'; ?>