<?php
/* compiled: post/flag.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php if ((view_get($__ctx, 'config.display_flags')) && (view_get($__ctx, 'post.modifiers.flag'))): ?><?php echo '
	 <img
		'; ?><?php if (view_get($__ctx, 'config.country_flags_condensed')): ?><?php echo '
			 class="flag flag-'; ?><?php echo view_get($__ctx, 'post.modifiers.flag'); ?><?php echo '" src="'; ?><?php echo view_get($__ctx, 'config.image_blank'); ?><?php echo '"
		'; ?><?php else: ?><?php echo '
			 class="flag" src="'; ?><?php echo view_filter(view_get($__ctx, 'config.uri_flags'), 'sprintf', [view_get($__ctx, 'post.modifiers.flag')]); ?><?php echo '"
		'; ?><?php endif; ?><?php echo '
		 style="'; ?><?php if (view_get($__ctx, 'post.modifiers[\'flag style\']')): ?><?php echo '
				'; ?><?php echo view_get($__ctx, 'post.modifiers[\'flag style\']'); ?><?php echo '
			'; ?><?php else: ?><?php echo '
				'; ?><?php echo view_get($__ctx, 'config.flag_style'); ?><?php echo '
			'; ?><?php endif; ?><?php echo '"

		'; ?><?php if (view_get($__ctx, 'post.modifiers[\'flag alt\']')): ?><?php echo ' alt="'; ?><?php echo view_filter(view_get($__ctx, 'post.modifiers[\'flag alt\']'), 'e', ['html_attr']); ?><?php echo '"
			 title="'; ?><?php echo view_filter(view_get($__ctx, 'post.modifiers[\'flag alt\']'), 'e', ['html_attr']); ?><?php echo '"
		'; ?><?php endif; ?><?php echo '
	>
'; ?><?php endif; ?><?php echo '
'; ?>