<?php
/* compiled: post/file_controls.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php if ((view_get($__ctx, 'post.files')) && (view_get($__ctx, 'mod'))): ?><?php echo '
<span class="controls">
'; ?><?php if ((view_get($__ctx, 'file.file') != 'deleted') && (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.deletefile'), view_get($__ctx, 'board.uri')]))): ?><?php echo '
'; ?><?php echo twig_secure_link_confirm(view_get($__ctx, 'config.mod.link_deletefile'), view_filter('Delete file', 'trans', []), view_filter('Are you sure you want to delete this file?', 'trans', []), (((string)(view_get($__ctx, 'board.dir'))) . ((string)('deletefile/')) . ((string)(view_get($__ctx, 'post.id'))) . ((string)('/')) . ((string)(view_get($__ctx, 'loop.index0'))))); ?><?php echo '&nbsp;
'; ?><?php endif; ?><?php echo '
'; ?><?php if ((view_get($__ctx, 'file.file')) && (view_get($__ctx, 'file.file') != 'deleted') && (view_get($__ctx, 'file.thumb') != 'spoiler') && (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.spoilerimage'), view_get($__ctx, 'board.uri')]))): ?><?php echo '
'; ?><?php echo twig_secure_link_confirm(view_get($__ctx, 'config.mod.link_spoilerimage'), view_filter('Spoiler file', 'trans', []), view_filter('Are you sure you want to spoiler this file?', 'trans', []), (((string)(view_get($__ctx, 'board.dir'))) . ((string)('spoiler/')) . ((string)(view_get($__ctx, 'post.id'))) . ((string)('/')) . ((string)(view_get($__ctx, 'loop.index0'))))); ?><?php echo '
'; ?><?php endif; ?><?php echo '
</span>
'; ?><?php endif; ?><?php echo '
'; ?>