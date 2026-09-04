<?php
/* compiled: post/subject.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php if (view_filter(view_get($__ctx, 'post.subject'), 'length', []) > 0): ?><?php echo '
	
	<span class="subject">'; ?><?php echo view_filter(view_get($__ctx, 'post.subject'), 'bidi_cleanup', []); ?><?php echo '</span> 
'; ?><?php endif; ?><?php echo '

'; ?>