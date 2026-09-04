<?php
/* compiled: post/poster_id.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php if (view_get($__ctx, 'config.poster_ids')): ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'post.thread')): ?><?php echo '
		 <span class="poster_id">'; ?><?php echo view_filter(view_get($__ctx, 'post.ip'), 'poster_id', [view_get($__ctx, 'post.thread')]); ?><?php echo '</span>
	'; ?><?php else: ?><?php echo '
		 <span class="poster_id">'; ?><?php echo view_filter(view_get($__ctx, 'post.ip'), 'poster_id', [view_get($__ctx, 'post.id')]); ?><?php echo '</span>
	'; ?><?php endif; ?><?php echo '
'; ?><?php endif; ?><?php echo '
'; ?>