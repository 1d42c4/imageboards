<?php
/* compiled: post/ip.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php if ((view_get($__ctx, 'config.privacy.store_ip')) && (view_get($__ctx, 'post.mod')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.show_ip'), view_get($__ctx, 'board.uri')])) && (view_get($__ctx, 'post.ip'))): ?><?php echo '
	 [<span class="ip-link" style="margin:0;" title="'; ?><?php echo _('Stored post IP (tools are limited when IPs are optional)'); ?><?php echo '">'; ?><?php echo view_filter(view_get($__ctx, 'post.ip'), 'cloak_ip', []); ?><?php echo '</span>]
'; ?><?php endif; ?><?php echo '
'; ?>