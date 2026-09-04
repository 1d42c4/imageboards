<?php
/* compiled: post_reply.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '

<div class="post reply" id="reply_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '">
<p class="intro">
	
	'; ?><?php if (!(view_get($__ctx, 'index'))): ?><?php echo '<a id="'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" class="post_anchor"></a>'; ?><?php endif; ?><?php echo '
	<input type="checkbox" class="delete" name="delete_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" id="delete_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" />
	<label for="delete_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '">
		'; ?><?php echo Element('post/subject.html', $__ctx); ?><?php echo '
		'; ?><?php echo Element('post/name.html', $__ctx); ?><?php echo '
		'; ?><?php echo Element('post/ip.html', $__ctx); ?><?php echo '
		'; ?><?php echo Element('post/flag.html', $__ctx); ?><?php echo '
		'; ?><?php echo Element('post/time.html', $__ctx); ?><?php echo '
	</label>
	
	'; ?><?php echo Element('post/poster_id.html', $__ctx); ?><?php echo '&nbsp;
	<a class="post_no" id="post_no_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" onclick="highlightReply('; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo ')" href="'; ?><?php echo view_get($__ctx, 'post.link'); ?><?php echo '">No.</a>
	<a class="post_no" onclick="citeReply('; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo ')" href="'; ?><?php echo view_get($__ctx, 'post.link(\'q\')'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '</a>
	</p>
	
    '; ?><?php echo Element('post/fileinfo.html', $__ctx); ?><?php echo ' 
    '; ?><?php echo Element('post/post_controls.html', $__ctx); ?><?php echo '
	
	<div class="body" '; ?><?php if (view_filter(view_get($__ctx, 'post.files'), 'length', []) > 1): ?><?php echo 'style="clear:both"'; ?><?php endif; ?><?php echo '>
		'; ?><?php if (view_get($__ctx, 'index')): ?><?php echo view_filter(view_get($__ctx, 'post.body'), 'truncate_body', [view_get($__ctx, 'post.link')]); ?><?php else: ?><?php echo view_get($__ctx, 'post.body'); ?><?php endif; ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'post.modifiers[\'ban message\']')): ?><?php echo '
			'; ?><?php echo view_filter(view_get($__ctx, 'config.mod.ban_message'), 'sprintf', [view_get($__ctx, 'post.modifiers[\'ban message\']')]); ?><?php echo '
		'; ?><?php endif; ?><?php echo '
	</div>
</div>
<br/>

'; ?>