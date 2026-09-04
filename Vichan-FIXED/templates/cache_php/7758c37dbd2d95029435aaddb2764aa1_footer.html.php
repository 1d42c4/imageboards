<?php
/* compiled: footer.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<footer>
'; ?><?php $__iter = view_get($__ctx, 'config.footer'); if ($__iter === null) { $__iter = []; } if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } $__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; foreach ($__iter as $footer): $__ctx['footer'] = $footer; $__ctx['loop'] = ['index' => $__i + 1, 'index0' => $__i, 'first' => $__i === 0, 'last' => $__i === $__count - 1, 'length' => $__count]; $__i++; ?><?php echo '
	<p class="unimportant" style="margin-top:20px;text-align:center;">'; ?><?php echo view_get($__ctx, 'footer'); ?><?php echo '</p>
'; ?><?php endforeach; ?><?php echo '
</footer>
'; ?>