<?php
/* compiled: post/post_controls.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php if (view_get($__ctx, 'mod')): ?><?php echo '
<span class="controls '; ?><?php if (!(view_get($__ctx, 'post.thread'))): ?><?php echo 'op'; ?><?php endif; ?><?php echo '">
'; ?><?php if (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.delete'), view_get($__ctx, 'board.uri')])): ?><?php echo '
	<a title="'; ?><?php echo _('Delete'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'delete/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'delete_token'); ?><?php echo '" onclick="return confirm(\''; ?><?php echo _('Are you sure you want to delete this?'); ?><?php echo '\');">'; ?><?php echo view_get($__ctx, 'config.mod.link_delete'); ?><?php echo '</a>&nbsp;
'; ?><?php endif; ?><?php echo '
'; ?><?php if ((view_get($__ctx, 'config.privacy.store_ip')) && (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.deletebyip'), view_get($__ctx, 'board.uri')]))): ?><?php echo '
	<a title="'; ?><?php echo _('Delete all posts by IP'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'deletebyip/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'deletebyip_token'); ?><?php echo '" onclick="return confirm(\''; ?><?php echo _('Are you sure you want to delete all posts by this IP address?'); ?><?php echo '\');">'; ?><?php echo view_get($__ctx, 'config.mod.link_deletebyip'); ?><?php echo '</a>&nbsp;
'; ?><?php endif; ?><?php echo '
'; ?><?php if ((view_get($__ctx, 'config.privacy.store_ip')) && (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.ban'), view_get($__ctx, 'board.uri')]))): ?><?php echo '
	<a title="'; ?><?php echo _('Ban'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'ban/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_ban'); ?><?php echo '</a>&nbsp;
'; ?><?php endif; ?><?php echo '
'; ?><?php if ((view_get($__ctx, 'config.privacy.store_ip')) && (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.bandelete'), view_get($__ctx, 'board.uri')]))): ?><?php echo '
	<a title="'; ?><?php echo _('Ban &amp; Delete'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'ban&amp;delete/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_bandelete'); ?><?php echo '</a>&nbsp;
'; ?><?php endif; ?><?php echo '

'; ?><?php if (!(view_get($__ctx, 'post.thread'))): ?><?php echo '
'; ?><?php if (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.sticky'), view_get($__ctx, 'board.uri')])): ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'post.sticky')): ?><?php echo '
	<a title="'; ?><?php echo _('Make thread not sticky'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'unsticky/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'unsticky_token'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_desticky'); ?><?php echo '</a>&nbsp;
	'; ?><?php else: ?><?php echo '
	<a title="'; ?><?php echo _('Make thread sticky'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'sticky/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'sticky_token'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_sticky'); ?><?php echo '</a>&nbsp;
	'; ?><?php endif; ?><?php echo '
'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.bumplock'), view_get($__ctx, 'board.uri')])): ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'post.sage')): ?><?php echo '
	<a title="'; ?><?php echo _('Allow thread to be bumped'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'bumpunlock/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'bumpunlock_token'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_bumpunlock'); ?><?php echo '</a>&nbsp;
	'; ?><?php else: ?><?php echo '
	<a title="'; ?><?php echo _('Prevent thread from being bumped'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'bumplock/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'bumplock_token'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_bumplock'); ?><?php echo '</a>&nbsp;
	'; ?><?php endif; ?><?php echo '
'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.lock'), view_get($__ctx, 'board.uri')])): ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'post.locked')): ?><?php echo '
	<a title="'; ?><?php echo _('Unlock thread'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'unlock/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'unlock_token'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_unlock'); ?><?php echo '</a>&nbsp;
	'; ?><?php else: ?><?php echo '
	<a title="'; ?><?php echo _('Lock thread'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'lock/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'lock_token'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_lock'); ?><?php echo '</a>&nbsp;
	'; ?><?php endif; ?><?php echo '
'; ?><?php endif; ?><?php echo '
'; ?><?php if ((view_get($__ctx, 'config.archive.enabled')) && (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.archive'), view_get($__ctx, 'board.uri')]))): ?><?php echo '
	<a title="'; ?><?php echo _('Move thread to read-only archive'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'archive/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '/'; ?><?php echo view_get($__ctx, 'archive_token'); ?><?php echo '" onclick="return confirm(\''; ?><?php echo _('Archive this thread? It will leave the board and become read-only.'); ?><?php echo '\');">'; ?><?php echo view_get($__ctx, 'config.mod.link_archive'); ?><?php echo '</a>&nbsp;
'; ?><?php endif; ?><?php echo '
'; ?><?php endif; ?><?php echo '

'; ?><?php if (view_filter(view_get($__ctx, 'mod'), 'hasPermission', [view_get($__ctx, 'config.mod.editpost'), view_get($__ctx, 'board.uri')])): ?><?php echo '
	<a title="'; ?><?php echo _('Edit post'); ?><?php echo '" href="?/'; ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo 'edit/'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'config.mod.link_editpost'); ?><?php echo '</a>&nbsp;
'; ?><?php endif; ?><?php echo '
</span>
'; ?><?php endif; ?><?php echo '
'; ?>