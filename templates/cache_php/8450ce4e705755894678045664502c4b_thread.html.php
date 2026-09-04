<?php
/* compiled: thread.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<!doctype html>
<html>
<head>
	<meta charset="utf-8">

        <script type="text/javascript">
          var active_page = "thread"
	    , board_name = "'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '"
	    , thread_id = "'; ?><?php echo view_get($__ctx, 'thread.id'); ?><?php echo '";
	</script>

	'; ?><?php echo Element('header.html', $__ctx); ?><?php echo '

	'; ?><?php ob_start(); ?><?php if ((view_get($__ctx, 'config.thread_subject_in_title')) && (view_get($__ctx, 'thread.subject'))): ?><?php echo view_filter(view_get($__ctx, 'thread.subject'), 'e', []); ?><?php else: ?><?php echo view_filter(view_get($__ctx, 'thread.body_nomarkup'), 'e', []); ?><?php endif; ?><?php $meta_subject = ob_get_clean(); $__ctx['meta_subject'] = $meta_subject; ?><?php echo '

	<meta name="description" content="'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo ' - '; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '" />
	<meta name="twitter:card" value="summary">
	<meta name="twitter:title" content="'; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '" />
	<meta name="twitter:description" content="'; ?><?php echo view_filter(view_filter(view_get($__ctx, 'thread.body_nomarkup'), 'remove_modifiers', []), 'e', []); ?><?php echo '" />
	'; ?><?php if (view_get($__ctx, 'thread.files.0.thumb')): ?><?php echo '<meta name="twitter:image" content="'; ?><?php echo view_get($__ctx, 'config.domain'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'config.dir.thumb'); ?><?php echo view_get($__ctx, 'thread.files.0.thumb'); ?><?php echo '" />'; ?><?php endif; ?><?php echo '
	<meta property="og:title" content="'; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '" />
	<meta property="og:type" content="article" />
	<meta property="og:url" content="'; ?><?php echo view_get($__ctx, 'config.domain'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'config.dir.res'); ?><?php echo view_get($__ctx, 'thread.id'); ?><?php echo '.html" />
	'; ?><?php if (view_get($__ctx, 'thread.files.0.thumb')): ?><?php echo '<meta property="og:image" content="'; ?><?php echo view_get($__ctx, 'config.domain'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'config.dir.thumb'); ?><?php echo view_get($__ctx, 'thread.files.0.thumb'); ?><?php echo '" />'; ?><?php endif; ?><?php echo '
	<meta property="og:description" content="'; ?><?php echo view_filter(view_filter(view_get($__ctx, 'thread.body_nomarkup'), 'remove_modifiers', []), 'e', []); ?><?php echo '" />

	<title>'; ?><?php echo view_get($__ctx, 'board.url'); ?><?php echo ' - '; ?><?php echo view_get($__ctx, 'meta_subject'); ?><?php echo '</title>
</head>
<body class="8chan vichan '; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo 'is-moderator'; ?><?php else: ?><?php echo 'is-not-moderator'; ?><?php endif; ?><?php echo ' active-thread" data-stylesheet="'; ?><?php if (view_get($__ctx, 'config.default_stylesheet.1') != ''): ?><?php echo view_get($__ctx, 'config.default_stylesheet.1'); ?><?php else: ?><?php echo 'default'; ?><?php endif; ?><?php echo '">
	'; ?><?php echo view_get($__ctx, 'boardlist.top'); ?><?php echo '
	<a name="top"></a>
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


	<div class="banner">'; ?><?php echo _('Posting mode: Reply'); ?><?php echo ' <a class="unimportant" href="'; ?><?php echo view_get($__ctx, 'return'); ?><?php echo '">['; ?><?php echo _('Return'); ?><?php echo ']</a> <a class="unimportant" href="#bottom">['; ?><?php echo _('Go to bottom'); ?><?php echo ']</a></div>

	'; ?><?php echo view_get($__ctx, 'config.ad.top'); ?><?php echo '

	'; ?><?php echo Element('post_form.html', $__ctx); ?><?php echo '

	'; ?><?php if (view_get($__ctx, 'config.global_message')): ?><?php echo '<hr /><div class="blotter">'; ?><?php echo view_get($__ctx, 'config.global_message'); ?><?php echo '</div>'; ?><?php endif; ?><?php echo '
	<hr />
	<form name="postcontrols" action="'; ?><?php echo view_get($__ctx, 'config.post_url'); ?><?php echo '" method="post">
		<input type="hidden" name="board" value="'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '" />
		'; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo '<input type="hidden" name="mod" value="1" />'; ?><?php endif; ?><?php echo '
		
		'; ?><?php echo view_get($__ctx, 'body'); ?><?php echo '
		
		<div id="thread-interactions">
			<span id="thread-links">
				<a id="thread-return" href="'; ?><?php echo view_get($__ctx, 'return'); ?><?php echo '">['; ?><?php echo _('Return'); ?><?php echo ']</a>
				<a id="thread-top" href="#top">['; ?><?php echo _('Go to top'); ?><?php echo ']</a>
                		'; ?><?php if (view_get($__ctx, 'config.catalog_link')): ?><?php echo '
					<a id="thread-catalog" href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php if (view_get($__ctx, 'mod')): ?><?php echo view_get($__ctx, 'config.file_mod'); ?><?php echo '?/'; ?><?php endif; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo view_get($__ctx, 'config.catalog_link'); ?><?php echo '">'; ?><?php echo _('Catalog'); ?><?php echo '</a>
		                '; ?><?php endif; ?><?php echo '
			</span>
			
			<span id="thread-quick-reply">
				<a id="link-quick-reply" href="#">['; ?><?php echo _('Post a Reply'); ?><?php echo ']</a>
			</span>
			
			'; ?><?php echo Element('report_delete.html', $__ctx); ?><?php echo '
		</div>
		
		<div class="clearfix"></div>
	</form>
	
	<a name="bottom"></a>
	'; ?><?php echo view_get($__ctx, 'boardlist.bottom'); ?><?php echo '

	'; ?><?php echo view_get($__ctx, 'config.ad.bottom'); ?><?php echo '

	'; ?><?php echo Element('footer.html', $__ctx); ?><?php echo '
	
	<script type="text/javascript">'; ?><?php echo '
		ready();
	'; ?><?php /* unknown tag: endverbatim */ ?><?php echo '</script>
</body>
</html>
'; ?>