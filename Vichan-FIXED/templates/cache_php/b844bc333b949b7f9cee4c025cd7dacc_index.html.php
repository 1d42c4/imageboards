<?php
/* compiled: index.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<!doctype html>
<html>
<head>
	<meta charset="utf-8">

        <script type="text/javascript">
	  var
          '; ?><?php if (!(view_get($__ctx, 'no_post_form'))): ?><?php echo '
              active_page = "index"
            , board_name = "'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '";
	  '; ?><?php else: ?><?php echo '
              active_page = "ukko";
          '; ?><?php endif; ?><?php echo '
        </script>

	'; ?><?php echo Element('header.html', $__ctx); ?><?php echo '

	'; ?><?php ob_start(); ?><?php if ((view_get($__ctx, 'config.thread_subject_in_title')) && (view_get($__ctx, 'thread.subject'))): ?><?php echo view_filter(view_get($__ctx, 'thread.subject'), 'e', []); ?><?php else: ?><?php echo view_filter(view_get($__ctx, 'thread.body_nomarkup'), 'e', []); ?><?php endif; ?><?php $meta_subject = ob_get_clean(); $__ctx['meta_subject'] = $meta_subject; ?><?php echo '

	<meta name="description" content="'; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '" />
	<meta name="twitter:card" value="summary">
	<meta name="twitter:title" content="'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo '" />
	<meta name="twitter:description" content="'; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '" />
	<meta name="twitter:image" content="'; ?><?php echo view_get($__ctx, 'config.domain'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'config.logo'); ?><?php echo '" />
	<meta property="og:title" content="'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo '" />
	<meta property="og:type" content="article" />
	<meta property="og:image" content="'; ?><?php echo view_get($__ctx, 'config.domain'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'config.logo'); ?><?php echo '" />
	<meta property="og:description" content="'; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '" />
	<title>'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo '</title>
</head>
<body class="8chan vichan '; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo 'is-moderator'; ?><?php else: ?><?php echo 'is-not-moderator'; ?><?php endif; ?><?php echo ' active-'; ?><?php if (!(view_get($__ctx, 'no_post_form'))): ?><?php echo 'index'; ?><?php else: ?><?php echo 'ukko'; ?><?php endif; ?><?php echo '" data-stylesheet="'; ?><?php if (view_get($__ctx, 'config.default_stylesheet.1') != ''): ?><?php echo view_get($__ctx, 'config.default_stylesheet.1'); ?><?php else: ?><?php echo 'default'; ?><?php endif; ?><?php echo '">
	'; ?><?php echo view_get($__ctx, 'boardlist.top'); ?><?php echo '

	'; ?><?php if (view_get($__ctx, 'config.url_banner')): ?><?php echo '<img class="board_image" src="'; ?><?php echo view_get($__ctx, 'config.url_banner'); ?><?php echo '" '; ?><?php if ((view_get($__ctx, 'config.banner_width')) || (view_get($__ctx, 'config.banner_height'))): ?><?php echo 'style="'; ?><?php if (view_get($__ctx, 'config.banner_width')): ?><?php echo 'width:'; ?><?php echo view_get($__ctx, 'config.banner_width'); ?><?php echo 'px'; ?><?php endif; ?><?php echo ';'; ?><?php if (view_get($__ctx, 'config.banner_width')): ?><?php echo 'height:'; ?><?php echo view_get($__ctx, 'config.banner_height'); ?><?php echo 'px'; ?><?php endif; ?><?php echo '" '; ?><?php endif; ?><?php echo 'alt="" />'; ?><?php endif; ?><?php echo '

	<header>
		<h1>'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo '</h1>
		<div class="subtitle">
			'; ?><?php if (view_get($__ctx, 'board.subtitle')): ?><?php echo '
				'; ?><?php if (view_get($__ctx, 'config.allow_subtitle_html')): ?><?php echo '
					'; ?><?php echo view_get($__ctx, 'board.subtitle'); ?><?php echo '
				'; ?><?php else: ?><?php echo '
					'; ?><?php echo view_filter(view_get($__ctx, 'board.subtitle'), 'e', []); ?><?php echo '
				'; ?><?php endif; ?><?php echo '
			'; ?><?php endif; ?><?php echo '
			'; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo '<p><a href="?/">'; ?><?php echo _('Return to dashboard'); ?><?php echo '</a></p>'; ?><?php endif; ?><?php echo '
		</div>
	</header>

	'; ?><?php echo view_get($__ctx, 'config.ad.top'); ?><?php echo '

	'; ?><?php if (!(view_get($__ctx, 'no_post_form'))): ?><?php echo '
		'; ?><?php echo Element('post_form.html', $__ctx); ?><?php echo '
	'; ?><?php else: ?><?php echo '
		'; ?><?php echo Element('boardlist.html', $__ctx); ?><?php echo '
	'; ?><?php endif; ?><?php echo '

	'; ?><?php if (view_get($__ctx, 'config.page_nav_top')): ?><?php echo '
		<div class="pages top">
			'; ?><?php $__iter = view_get($__ctx, 'pages'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $page): $__ctx['page'] = $page; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
			 [<a '; ?><?php if (view_get($__ctx, 'page.selected')): ?><?php echo 'class="selected"'; ?><?php endif; ?><?php if (!(view_get($__ctx, 'page.selected'))): ?><?php echo 'href="'; ?><?php echo view_get($__ctx, 'page.link'); ?><?php echo '"'; ?><?php endif; ?><?php echo '>'; ?><?php echo view_get($__ctx, 'page.num'); ?><?php echo '</a>]'; ?><?php if (view_get($__ctx, 'loop.last')): ?><?php echo ' '; ?><?php endif; ?><?php echo '
			'; ?><?php endforeach; ?><?php echo '
			'; ?><?php echo view_get($__ctx, 'btn.next'); ?><?php echo '
		</div>
	'; ?><?php endif; ?><?php echo '

	'; ?><?php if (view_get($__ctx, 'config.global_message')): ?><?php echo '<hr /><div class="blotter">'; ?><?php echo view_get($__ctx, 'config.global_message'); ?><?php echo '</div>'; ?><?php endif; ?><?php echo '
	<hr />
	<form name="postcontrols" action="'; ?><?php echo view_get($__ctx, 'config.post_url'); ?><?php echo '" method="post">
	<input type="hidden" name="board" value="'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '" />
	'; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo '<input type="hidden" name="mod" value="1" />'; ?><?php endif; ?><?php echo '
	'; ?><?php echo view_get($__ctx, 'body'); ?><?php echo '
	'; ?><?php echo Element('report_delete.html', $__ctx); ?><?php echo '
	</form>

	<div class="pages">
		'; ?><?php echo view_get($__ctx, 'btn.prev'); ?><?php echo ' '; ?><?php $__iter = view_get($__ctx, 'pages'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $page): $__ctx['page'] = $page; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
		 [<a '; ?><?php if (view_get($__ctx, 'page.selected')): ?><?php echo 'class="selected"'; ?><?php endif; ?><?php if (!(view_get($__ctx, 'page.selected'))): ?><?php echo 'href="'; ?><?php echo view_get($__ctx, 'page.link'); ?><?php echo '"'; ?><?php endif; ?><?php echo '>'; ?><?php echo view_get($__ctx, 'page.num'); ?><?php echo '</a>]'; ?><?php if (view_get($__ctx, 'loop.last')): ?><?php echo ' '; ?><?php endif; ?><?php echo '
		'; ?><?php endforeach; ?><?php echo ' '; ?><?php echo view_get($__ctx, 'btn.next'); ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'config.catalog_link')): ?><?php echo '
			 | <a href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php if (view_get($__ctx, 'mod')): ?><?php echo view_get($__ctx, 'config.file_mod'); ?><?php echo '?/'; ?><?php endif; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo view_get($__ctx, 'config.catalog_link'); ?><?php echo '">'; ?><?php echo _('Catalog'); ?><?php echo '</a>
		'; ?><?php endif; ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'config.archive.enabled')): ?><?php echo '
			 | <a href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo view_get($__ctx, 'config.archive.dir'); ?><?php echo view_get($__ctx, 'config.archive.file_index'); ?><?php echo '">'; ?><?php echo _('Archive'); ?><?php echo '</a>
		'; ?><?php endif; ?><?php echo '
	</div>

	'; ?><?php echo view_get($__ctx, 'boardlist.bottom'); ?><?php echo '

	'; ?><?php echo view_get($__ctx, 'config.ad.bottom'); ?><?php echo '

	'; ?><?php echo Element('footer.html', $__ctx); ?><?php echo '
	<script type="text/javascript">'; ?><?php echo '
		ready();
	'; ?><?php /* unknown tag: endverbatim */ ?><?php echo '</script>

</body>
</html>
'; ?>