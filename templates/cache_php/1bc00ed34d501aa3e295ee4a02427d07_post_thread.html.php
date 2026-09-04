<?php
/* compiled: post_thread.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '


<div class="thread" id="thread_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" data-board="'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '">
'; ?><?php if (!(view_get($__ctx, 'index'))): ?><?php echo '<a id="'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" class="post_anchor"></a>'; ?><?php endif; ?><?php echo '


'; ?><?php echo Element('post/fileinfo.html', $__ctx); ?><?php echo '
<div class="post op" id="op_'; ?><?php echo view_get($__ctx, 'post.id'); ?><?php echo '" '; ?><?php if (view_get($__ctx, 'post.num_files') > 1): ?><?php echo 'style=\'clear:both\''; ?><?php endif; ?><?php echo '><p class="intro">
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
	'; ?><?php if (view_get($__ctx, 'post.sticky')): ?><?php echo '
		<img class="icon" title="Sticky" src="'; ?><?php echo view_get($__ctx, 'config.image_sticky'); ?><?php echo '" alt="Sticky" />
	'; ?><?php endif; ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'post.locked')): ?><?php echo '
		<img class="icon" title="Locked" src="'; ?><?php echo view_get($__ctx, 'config.image_locked'); ?><?php echo '" alt="Locked" />
	'; ?><?php endif; ?><?php echo '
	'; ?><?php if ((view_get($__ctx, 'post.sage')) && ((view_get($__ctx, 'config.mod.view_bumplock') < 0) || ((view_get($__ctx, 'post.mod')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.view_bumplock'), view_get($__ctx, 'board.uri')]))))): ?><?php echo '
		<img class="icon" title="Bumplocked" src="'; ?><?php echo view_get($__ctx, 'config.image_bumplocked'); ?><?php echo '" alt="Bumplocked" />
	'; ?><?php endif; ?><?php echo '
	'; ?><?php if (view_get($__ctx, 'index')): ?><?php echo '
		<a href="'; ?><?php echo view_get($__ctx, 'post.root'); ?><?php echo view_get($__ctx, 'board.dir'); ?><?php echo view_get($__ctx, 'config.dir.res'); ?><?php echo link_for(view_get($__ctx, 'post')); ?><?php echo '">['; ?><?php echo _('Reply'); ?><?php echo ']</a>
	'; ?><?php endif; ?><?php echo '
	'; ?><?php echo Element('post/post_controls.html', $__ctx); ?><?php echo '
	</p>
	<div class="body">
		'; ?><?php if (view_get($__ctx, 'index')): ?><?php echo view_filter(view_get($__ctx, 'post.body'), 'truncate_body', [view_get($__ctx, 'post.link')]); ?><?php else: ?><?php echo view_get($__ctx, 'post.body'); ?><?php endif; ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'post.modifiers[\'ban message\']')): ?><?php echo '
			'; ?><?php echo view_filter(view_get($__ctx, 'config.mod.ban_message'), 'sprintf', [view_get($__ctx, 'post.modifiers[\'ban message\']')]); ?><?php echo '
		'; ?><?php endif; ?><?php echo '
	</div>
	'; ?><?php if ((view_get($__ctx, 'post.omitted')) || (view_get($__ctx, 'post.omitted_images'))): ?><?php echo '
		<span class="omitted">
			'; ?><?php if (view_get($__ctx, 'post.omitted')): ?><?php echo '
				'; ?><?php echo _('1 post
				{% plural post.omitted %}
					{{ count }} posts'); ?><?php echo '
				'; ?><?php if (view_get($__ctx, 'post.omitted_images')): ?><?php echo '
					 '; ?><?php echo _('and'); ?><?php echo ' 
				'; ?><?php endif; ?><?php echo '
			'; ?><?php endif; ?><?php echo '
			'; ?><?php if (view_get($__ctx, 'post.omitted_images')): ?><?php echo '
				'; ?><?php echo _('1 image reply
				{% plural post.omitted_images %}
					{{ count }} image replies'); ?><?php echo '
			'; ?><?php endif; ?><?php echo ' '; ?><?php echo _('omitted. Click reply to view.'); ?><?php echo '
		</span>
	'; ?><?php endif; ?><?php echo '
'; ?><?php if (!(view_get($__ctx, 'index'))): ?><?php echo '
'; ?><?php endif; ?><?php echo '
</div>
'; ?><?php $hr = view_get($__ctx, 'post.hr'); $__ctx['hr'] = $hr; ?><?php echo '
'; ?><?php $__iter = view_get($__ctx, 'post.posts'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $post): $__ctx['post'] = $post; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
	'; ?><?php echo Element('post_reply.html', $__ctx); ?><?php echo '
'; ?><?php endforeach; ?><?php echo '
<br class="clear"/>'; ?><?php if (view_get($__ctx, 'hr')): ?><?php echo '<hr/>'; ?><?php endif; ?><?php echo '
</div>
'; ?>