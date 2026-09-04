<?php
/* compiled: post/name.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php $capcode = view_filter(view_get($__ctx, 'post.capcode'), 'capcode', []); $__ctx['capcode'] = $capcode; ?><?php echo '
'; ?><?php if ((view_filter(view_get($__ctx, 'post.email'), 'length', []) > 0) && ((view_get($__ctx, 'config.hide_sage') != true) || (view_get($__ctx, 'post.email') != 'sage')) && (view_get($__ctx, 'config.hide_email') != true)): ?><?php echo '
	
	<a class="email" href="mailto:'; ?><?php echo view_get($__ctx, 'post.email'); ?><?php echo '">
'; ?><?php endif; ?><?php echo '
	<span '; ?><?php if (view_get($__ctx, 'capcode.name')): ?><?php echo 'style="'; ?><?php echo view_get($__ctx, 'capcode.name'); ?><?php echo '" '; ?><?php endif; ?><?php echo ' class="name">'; ?><?php echo view_filter(view_get($__ctx, 'post.name'), 'bidi_cleanup', []); ?><?php echo '</span>
	'; ?><?php if (view_filter(view_get($__ctx, 'post.trip'), 'length', []) > 0): ?><?php echo '
		<span '; ?><?php if (view_get($__ctx, 'capcode.trip')): ?><?php echo 'style="'; ?><?php echo view_get($__ctx, 'capcode.trip'); ?><?php echo '" '; ?><?php endif; ?><?php echo ' class="trip">'; ?><?php echo view_get($__ctx, 'post.trip'); ?><?php echo '</span>
	'; ?><?php endif; ?><?php echo '
'; ?><?php if ((view_filter(view_get($__ctx, 'post.email'), 'length', []) > 0) && ((view_get($__ctx, 'config.hide_sage') != true) || (view_get($__ctx, 'post.email') != 'sage')) && (view_get($__ctx, 'config.hide_email') != true)): ?><?php echo '
	
	</a>
'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'capcode')): ?><?php echo '
        '; ?><?php echo view_get($__ctx, 'capcode.cap'); ?><?php echo '
'; ?><?php endif; ?><?php echo '
'; ?>