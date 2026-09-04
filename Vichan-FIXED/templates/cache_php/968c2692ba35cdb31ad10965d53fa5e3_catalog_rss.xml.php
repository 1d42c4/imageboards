<?php
/* compiled: catalog_rss.xml */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
<channel>
	<title>/'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/ - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo '</title>
	<link>'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/</link>
	<description>Live feed of new threads on the board /'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/ - '; ?><?php echo view_filter(view_get($__ctx, 'board.title'), 'e', []); ?><?php echo '.</description>
	<atom:link href="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/index.rss" rel="self" type="application/rss+xml"/>
	'; ?><?php $__iter = view_get($__ctx, 'recent_posts'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $post): $__ctx['post'] = $post; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
	<item>
		<title>'; ?><?php if (view_get($__ctx, 'post.subject')): ?><?php echo view_filter(view_get($__ctx, 'post.subject'), 'e', []); ?><?php else: ?><?php echo view_filter(view_filter(mb_substr((string)(view_get($__ctx, 'post.body_nomarkup')), 0, 256), 'remove_modifiers', []), 'e', []); ?><?php endif; ?><?php echo '</title>
		<link>'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/res/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '.html</link>
		<guid>'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/res/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '.html</guid>
		<comments>'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/res/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '.html</comments>
		<pubDate>'; ?><?php echo view_get($__ctx, 'post.pubdate'); ?><?php echo '</pubDate>
		<description><![CDATA[ <a href=\''; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '/res/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '.html\' target=_blank><img style=\'float:left;margin:8px\' border=0 src=\''; ?><?php if (!(view_get($__ctx, 'config.uri_thumb'))): ?><?php echo view_get($__ctx, 'config.root'); ?><?php endif; ?><?php echo view_get($__ctx, 'post.file'); ?><?php echo '\'></a> '; ?><?php echo view_get($__ctx, 'post.body'); ?><?php echo ' ]]></description>
	</item>
	'; ?><?php endforeach; ?><?php echo '
</channel>
</rss>
'; ?>