<?php
/* compiled: post/time.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo ' <time datetime="'; ?><?php echo view_filter(view_get($__ctx, 'post.time'), 'date', ['Y-m-d\\TH:i:sZ']); ?><?php echo '">'; ?><?php echo view_filter(view_get($__ctx, 'post.time'), 'date', [view_get($__ctx, 'config.post_date')]); ?><?php echo '</time>
'; ?>