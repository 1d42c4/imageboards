<?php
/* compiled: post/fileinfo.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '	'; ?><?php if (view_get($__ctx, 'post.embed')): ?><?php echo '
		'; ?><?php echo view_get($__ctx, 'post.embed'); ?><?php echo '
	'; ?><?php else: ?><?php echo '
	<div class="files '; ?><?php if (view_get($__ctx, 'post.num_files') > 1): ?><?php echo ' multifile'; ?><?php endif; ?><?php echo '">
	'; ?><?php $__iter = view_get($__ctx, 'post.files'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $file): $__ctx['file'] = $file; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
		<div class="file'; ?><?php if (view_get($__ctx, 'post.num_files') > 1): ?><?php echo ' multifile" style="width:'; ?><?php echo view_get($__ctx, 'file.thumbwidth + 40'); ?><?php echo 'px"'; ?><?php else: ?><?php echo '"'; ?><?php endif; ?><?php echo '>
	'; ?><?php if (view_get($__ctx, 'file.file') == 'deleted'): ?><?php echo '
		<img class="post-image deleted" src="'; ?><?php echo view_get($__ctx, 'config.root'); ?><?php echo view_get($__ctx, 'config.image_deleted'); ?><?php echo '" alt="" />
	'; ?><?php else: ?><?php echo '
		<p class="fileinfo"><span>File: <a href="'; ?><?php echo view_get($__ctx, 'config.uri_img'); ?><?php echo view_get($__ctx, 'file.file'); ?><?php echo '">'; ?><?php echo view_get($__ctx, 'file.file'); ?><?php echo '</a></span><span class="unimportant">
		(
			'; ?><?php if (view_get($__ctx, 'file.thumb') == 'spoiler'): ?><?php echo '
				'; ?><?php echo _('Spoiler Image'); ?><?php echo ',
			'; ?><?php endif; ?><?php echo '
			'; ?><?php echo view_filter(view_get($__ctx, 'file.size'), 'filesize', []); ?><?php echo '
			'; ?><?php if ((view_get($__ctx, 'file.width')) && (view_get($__ctx, 'file.height'))): ?><?php echo '
				, '; ?><?php echo view_get($__ctx, 'file.width'); ?><?php echo 'x'; ?><?php echo view_get($__ctx, 'file.height'); ?><?php echo '
				'; ?><?php if (view_get($__ctx, 'config.show_ratio')): ?><?php echo '
					, '; ?><?php echo twig_ratio_function(view_get($__ctx, 'file.width'), view_get($__ctx, 'file.height')); ?><?php echo '
				'; ?><?php endif; ?><?php echo '
			'; ?><?php endif; ?><?php echo '
			'; ?><?php if ((view_get($__ctx, 'config.show_filename')) && (view_get($__ctx, 'file.filename'))): ?><?php echo '
				, 
				'; ?><?php if (view_get($__ctx, 'file.thumb') == 'spoiler'): ?><?php echo '
					<a href="'; ?><?php echo view_get($__ctx, 'config.uri_img'); ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.file'), 'e', []), 'bidi_cleanup', []); ?><?php echo '" download="'; ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.filename'), 'e', []), 'bidi_cleanup', []); ?><?php echo '" title="'; ?><?php echo _('Spoiler Image'); ?><?php echo '">'; ?><?php echo _('Spoiler Image'); ?><?php echo '</a>
				'; ?><?php elseif (view_filter(view_get($__ctx, 'file.filename'), 'length', []) > view_get($__ctx, 'config.max_filename_display')): ?><?php echo '
					<a href="'; ?><?php echo view_get($__ctx, 'config.uri_img'); ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.file'), 'e', []), 'bidi_cleanup', []); ?><?php echo '" download="'; ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.filename'), 'e', []), 'bidi_cleanup', []); ?><?php echo '" title="Save as original filename: '; ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.filename'), 'e', []), 'bidi_cleanup', []); ?><?php echo '">'; ?><?php echo view_filter(view_filter(view_filter(view_get($__ctx, 'file.filename'), 'truncate_filename', [view_get($__ctx, 'config.max_filename_display')]), 'e', []), 'bidi_cleanup', []); ?><?php echo '</a>
				'; ?><?php else: ?><?php echo '
					<a href="'; ?><?php echo view_get($__ctx, 'config.uri_img'); ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.file'), 'e', []), 'bidi_cleanup', []); ?><?php echo '" download="'; ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.filename'), 'e', []), 'bidi_cleanup', []); ?><?php echo '" title="Save as original filename">'; ?><?php echo view_filter(view_filter(view_get($__ctx, 'file.filename'), 'e', []), 'bidi_cleanup', []); ?><?php echo '</a>
				'; ?><?php endif; ?><?php echo '
			'; ?><?php endif; ?><?php echo '
		)
		</span>
		'; ?><?php echo Element('post/file_controls.html', $__ctx); ?><?php echo '
		</p>
	'; ?><?php $__inc_ctx = $__ctx; $__inc_ctx['post'] = view_get($__ctx, 'file'); echo Element('post/image.html', $__inc_ctx); ?><?php echo '
	'; ?><?php endif; ?><?php echo '
</div>
	'; ?><?php endforeach; ?><?php echo '
</div>
	'; ?><?php endif; ?><?php echo '
'; ?>