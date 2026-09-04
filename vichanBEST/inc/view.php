<?php
/**
 * Lightweight template engine (Twig-subset) — no Composer/Twig dependency.
 * Compiles templates/*.html (and .xml / main.js) to cached PHP under templates/cache_php/.
 */

defined('TINYBOARD') or exit;

/**
 * Resolve dotted path against context (arrays, objects, no-arg methods).
 */
function view_get(array $ctx, string $path) {
	if ($path === '') {
		return null;
	}
	// Ternary and literals handled by expression compiler; paths only here
	$parts = explode('.', $path);
	$cur = $ctx;
	foreach ($parts as $p) {
		if (is_array($cur)) {
			if (array_key_exists($p, $cur)) {
				$cur = $cur[$p];
				continue;
			}
			if (ctype_digit($p) && array_key_exists((int)$p, $cur)) {
				$cur = $cur[(int)$p];
				continue;
			}
			return null;
		}
		if (is_object($cur)) {
			if ($p === 'length' || $p === 'count') {
				if ($cur instanceof Countable) {
					$cur = count($cur);
					continue;
				}
				if (is_array($cur) || $cur instanceof Traversable) {
					$cur = iterator_count($cur);
					continue;
				}
			}
			if (isset($cur->$p)) {
				$cur = $cur->$p;
				continue;
			}
			// ArrayAccess / numeric
			if ((is_int($p) || ctype_digit($p)) && isset($cur[(int)$p])) {
				$cur = $cur[(int)$p];
				continue;
			}
			if (method_exists($cur, $p)) {
				try {
					$cur = $cur->$p();
					continue;
				} catch (ArgumentCountError $e) {
					return null;
				}
			}
			if (method_exists($cur, 'get' . ucfirst($p))) {
				$m = 'get' . ucfirst($p);
				$cur = $cur->$m();
				continue;
			}
			return null;
		}
		return null;
	}
	return $cur;
}

function view_e($value): string {
	if ($value === null || $value === false) {
		return '';
	}
	if (is_array($value) || is_object($value)) {
		return '';
	}
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Apply a named filter (Twig-compatible names used in our templates).
 */
function view_filter($value, string $name, array $args = []) {
	global $config;
	switch ($name) {
		case 'e':
		case 'escape':
			return view_e($value);
		case 'trans':
			return is_string($value) ? _($value) : $value;
		case 'split':
			return explode((string)($args[0] ?? ','), (string)$value);
		case 'push':
			if (!is_array($value)) {
				$value = [];
			}
			$value[] = $args[0] ?? null;
			return $value;
		case 'sort':
			if (is_array($value)) {
				sort($value);
			}
			return $value;
		case 'first':
			if (is_array($value)) {
				return $value[array_key_first($value)] ?? null;
			}
			return $value;
		case 'last':
			if (is_array($value)) {
				return $value[array_key_last($value)] ?? null;
			}
			return $value;
		case 'upper':
			return mb_strtoupper((string)$value);
		case 'lower':
			return mb_strtolower((string)$value);
		case 'raw':
			return $value;
		case 'length':
		case 'count':
			if (is_countable($value)) {
				return count($value);
			}
			if (is_string($value)) {
				return mb_strlen($value);
			}
			return 0;
		case 'date':
			$fmt = $args[0] ?? 'Y-m-d H:i';
			return twig_date_filter($value, $fmt);
		case 'truncate':
			return twig_truncate_filter($value, (int)($args[0] ?? 30), !empty($args[1]), $args[2] ?? '…');
		case 'truncate_body':
			return truncate($value, $args[0] ?? false);
		case 'truncate_filename':
			return twig_filename_truncate_filter($value, (int)($args[0] ?? 30));
		case 'remove_modifiers':
			return remove_modifiers($value);
		case 'filesize':
			return format_bytes($value);
		case 'capcode':
			return capcode($value);
		case 'hasPermission':
			// value is $mod, args: permission, board?
			return twig_hasPermission_filter($value, $args[0] ?? null, $args[1] ?? null);
		case 'ago':
			return \Vichan\Functions\Format\ago($value);
		case 'until':
			return \Vichan\Functions\Format\until($value);
		case 'addslashes':
			return addslashes((string)$value);
		case 'sprintf':
			return sprintf((string)$value, ...$args);
		case 'cloak_ip':
			return cloak_ip($value);
		case 'cloak_mask':
			return cloak_mask($value);
		case 'extension':
			return twig_extension_filter($value, $args[0] ?? true);
		case 'bidi_cleanup':
			return bidi_cleanup($value);
		case 'poster_id':
			return poster_id($value, $args[0] ?? 0, $args[1] ?? '');
		case 'join':
			return is_array($value) ? implode($args[0] ?? '', $value) : (string)$value;
		case 'json_encode':
			return json_encode($value);
		case 'lower':
			return mb_strtolower((string)$value);
		case 'upper':
			return mb_strtoupper((string)$value);
		case 'default':
			return ($value === null || $value === '' || $value === false) ? ($args[0] ?? '') : $value;
		default:
			return $value;
	}
}

/**
 * Compile a Twig-like source string to PHP.
 */
function view_compile(string $source, string $name = 'template'): string {
	$body = view_compile_body($source, $name);
	$php = "<?php\n/* compiled: " . addslashes($name) . " */\n";
	$php .= "if (!isset(\$__ctx)) { \$__ctx = get_defined_vars(); }\n?>";
	return $php . $body;
}

/** Compile template body only (no PHP header). */
function view_compile_body(string $source, string $name = 'template'): string {
	// Remove comments {# #}
	$source = preg_replace('/\{#.*?#\}/s', '', $source);

	// apply spaceless — no-op markers
	$source = preg_replace('/\{%\s*apply\s+spaceless\s*%\}/', '', $source);
	$source = preg_replace('/\{%\s*endapply\s*%\}/', '', $source);

	$php = '';
	$offset = 0;
	$len = strlen($source);
	while ($offset < $len) {
		// Verbatim block (emit as raw text, no nested compile)
		if (preg_match('/\G\{%\s*verbatim\s*%\}/A', $source, $vm, 0, $offset)) {
			$start = $offset + strlen($vm[0]);
			if (!preg_match('/\{%\s*endverbatim\s*%\}/', $source, $em, PREG_OFFSET_CAPTURE, $start)) {
				$php .= view_compile_raw(substr($source, $offset));
				break;
			}
			$endPos = $em[0][1];
			$php .= view_compile_raw(substr($source, $start, $endPos - $start));
			$offset = $endPos + strlen($em[0][0]);
			continue;
		}

		// Find next tag
		$pos_var = strpos($source, '{{', $offset);
		$pos_tag = strpos($source, '{%', $offset);
		$next = false;
		$type = null;
		if ($pos_var === false && $pos_tag === false) {
			$php .= view_compile_raw(substr($source, $offset));
			break;
		}
		if ($pos_var === false) {
			$next = $pos_tag;
			$type = 'tag';
		} elseif ($pos_tag === false) {
			$next = $pos_var;
			$type = 'var';
		} else {
			if ($pos_tag < $pos_var) {
				$next = $pos_tag;
				$type = 'tag';
			} else {
				$next = $pos_var;
				$type = 'var';
			}
		}
		if ($next > $offset) {
			$php .= view_compile_raw(substr($source, $offset, $next - $offset));
		}
		if ($type === 'var') {
			$end = strpos($source, '}}', $next);
			if ($end === false) {
				$php .= view_compile_raw(substr($source, $next));
				break;
			}
			$expr = trim(substr($source, $next + 2, $end - $next - 2));
			$php .= '<?php echo ' . view_compile_print_expr($expr) . '; ?>';
			$offset = $end + 2;
		} else {
			$end = strpos($source, '%}', $next);
			if ($end === false) {
				$php .= view_compile_raw(substr($source, $next));
				break;
			}
			$tag = trim(substr($source, $next + 2, $end - $next - 2));
			// {% trans 'string' %} (inline)
			if (preg_match('/^trans\s+[\'"](.*)[\'"]\s*$/s', $tag, $tm)) {
				$php .= '<?php echo _(' . var_export($tm[1], true) . '); ?>';
				$offset = $end + 2;
				continue;
			}
			// {% trans %}...{% endtrans %} — capture at compile time (no nested output buffers)
			if (preg_match('/^trans\b/', $tag)) {
				$after = $end + 2;
				if (preg_match('/\{%\s*endtrans\s*%\}/', $source, $em, PREG_OFFSET_CAPTURE, $after)) {
					$inner = substr($source, $after, $em[0][1] - $after);
					// Inner may still contain {{ }} — compile recursively as value
					$inner = trim($inner);
					if ($inner === '') {
						$php .= '';
					} elseif (strpos($inner, '{{') === false && strpos($inner, '{%') === false) {
						$php .= '<?php echo _(' . var_export($inner, true) . '); ?>';
					} else {
						// Rare: trans with nested tags — fall back to identity of stripped text
						$php .= '<?php echo _(' . var_export(trim(strip_tags($inner)), true) . '); ?>';
					}
					$offset = $em[0][1] + strlen($em[0][0]);
					continue;
				}
			}
			// {% set name %}...{% endset %}
			if (preg_match('/^set\s+(\w+)$/', $tag, $sm)) {
				$after = $end + 2;
				if (preg_match('/\{%\s*endset\s*%\}/', $source, $em, PREG_OFFSET_CAPTURE, $after)) {
					$inner = substr($source, $after, $em[0][1] - $after);
					$php .= '<?php ob_start(); ?>'
						. view_compile_body($inner, $name . '#set')
						. '<?php $' . $sm[1] . ' = ob_get_clean(); $__ctx[' . var_export($sm[1], true) . '] = $' . $sm[1] . '; ?>';
					$offset = $em[0][1] + strlen($em[0][0]);
					continue;
				}
			}
			if (preg_match('/^verbatim/i', $tag)) {
				$offset = $end + 2;
				continue;
			}
			$php .= view_compile_tag($tag);
			$offset = $end + 2;
		}
	}

	return $php;
}

// (view_compile / view_compile_body end)

function view_compile_raw(string $s): string {
	if ($s === '') {
		return '';
	}
	return '<?php echo ' . var_export($s, true) . '; ?>';
}

/**
 * Compile {% tag %} contents to PHP.
 */
function view_compile_tag(string $tag): string {
	// if / elseif / else / endif
	if (preg_match('/^if\s+(.+)$/s', $tag, $m)) {
		return '<?php if (' . view_compile_condition(trim($m[1])) . '): ?>';
	}
	if (preg_match('/^elseif\s+(.+)$/s', $tag, $m)) {
		return '<?php elseif (' . view_compile_condition(trim($m[1])) . '): ?>';
	}
	if ($tag === 'else') {
		return '<?php else: ?>';
	}
	if ($tag === 'endif') {
		return '<?php endif; ?>';
	}
	// for k, v in y
	if (preg_match('/^for\s+(\w+)\s*,\s*(\w+)\s+in\s+(.+)$/s', $tag, $m)) {
		$k = $m[1];
		$v = $m[2];
		$iter = trim($m[3]);
		return '<?php $__iter = ' . view_compile_value_expr($iter) . '; '
			. 'if ($__iter === null) { $__iter = []; } '
			. 'if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } '
			. 'if (!is_array($__iter)) { $__iter = []; } '
			. '$__i = 0; $__count = count($__iter); '
			. 'foreach ($__iter as $' . $k . ' => $' . $v . '): '
			. '$__ctx[' . var_export($k, true) . '] = $' . $k . '; '
			. '$__ctx[' . var_export($v, true) . '] = $' . $v . '; '
			. '$__ctx[\'loop\'] = [\'index\' => $__i + 1, \'index0\' => $__i, \'first\' => $__i === 0, \'last\' => $__i === $__count - 1, \'length\' => $__count]; '
			. '$__i++; ?>';
	}
	// for x in y
	if (preg_match('/^for\s+(\w+)\s+in\s+(.+)$/s', $tag, $m)) {
		$var = $m[1];
		$iter = trim($m[2]);
		return '<?php $__iter = ' . view_compile_value_expr($iter) . '; '
			. 'if ($__iter === null) { $__iter = []; } '
			. 'if ($__iter instanceof Traversable && !is_array($__iter)) { $__iter = iterator_to_array($__iter); } '
			. 'if (!is_array($__iter) && !($__iter instanceof Traversable)) { $__iter = []; } '
			. '$__i = 0; $__count = is_countable($__iter) ? count($__iter) : 0; '
			. 'foreach ($__iter as $' . $var . '): '
			. '$__ctx[' . var_export($var, true) . '] = $' . $var . '; '
			. '$__ctx[\'loop\'] = [\'index\' => $__i + 1, \'index0\' => $__i, \'first\' => $__i === 0, \'last\' => $__i === $__count - 1, \'length\' => $__count]; '
			. '$__i++; ?>';
	}
	if ($tag === 'endfor') {
		return '<?php endforeach; ?>';
	}
	// include 'file' [with { key: expr, ... }]
	if (preg_match('/^include\s+[\'"]([^\'"]+)[\'"](?:\s+with\s+(\{.*\}))?\s*$/s', $tag, $m)) {
		$file = $m[1];
		if (empty($m[2])) {
			return '<?php echo Element(' . var_export($file, true) . ', $__ctx); ?>';
		}
		// Build a shallow copy of context, then apply with-map overrides
		$php = '<?php $__inc_ctx = $__ctx; ';
		$inner = trim($m[2]);
		if ($inner[0] === '{') {
			$inner = substr($inner, 1, -1);
		}
		// split top-level commas
		$parts = view_split_top_level($inner, ',');
		foreach ($parts as $pair) {
			$pair = trim($pair);
			if ($pair === '') {
				continue;
			}
			if (!preg_match('/^[\'"]?(\w+)[\'"]?\s*:\s*(.+)$/s', $pair, $pm)) {
				continue;
			}
			$key = $pm[1];
			$val = view_compile_value_expr(trim($pm[2]));
			$php .= '$__inc_ctx[' . var_export($key, true) . '] = ' . $val . '; ';
		}
		$php .= 'echo Element(' . var_export($file, true) . ', $__inc_ctx); ?>';
		return $php;
	}
	// set x = y
	if (preg_match('/^set\s+(\w+)\s*=\s*(.+)$/s', $tag, $m)) {
		return '<?php $' . $m[1] . ' = ' . view_compile_value_expr(trim($m[2])) . '; $__ctx[' . var_export($m[1], true) . '] = $' . $m[1] . '; ?>';
	}
	// set x %} ... {% endset — handled as block: set name
	if (preg_match('/^set\s+(\w+)$/', $tag, $m)) {
		return '<?php ob_start(); $__set_name = ' . var_export($m[1], true) . '; ?>';
	}
	if ($tag === 'endset') {
		return '<?php $__set_val = ob_get_clean(); ${$__set_name} = $__set_val; $__ctx[$__set_name] = $__set_val; ?>';
	}
	// trans/endtrans handled in main compile loop (compile-time capture)
	if (preg_match('/^trans\b/', $tag) || $tag === 'endtrans') {
		return '<?php /* trans marker should be handled by compiler */ ?>';
	}
	// ignore unknown tags
	return '<?php /* unknown tag: ' . addslashes($tag) . ' */ ?>';
}

function view_compile_condition(string $expr): string {
	$expr = trim($expr);
	// Strip wrapping parens if balanced
	while ($expr !== '' && $expr[0] === '(' && view_balanced_parens($expr)) {
		$expr = trim(substr($expr, 1, -1));
	}
	// not X
	if (preg_match('/^not\s+(.+)$/s', $expr, $m)) {
		return '!(' . view_compile_condition(trim($m[1])) . ')';
	}
	// Split and/or at top level only
	$or = view_split_logic($expr, 'or');
	if (count($or) > 1) {
		return '(' . implode(') || (', array_map('view_compile_condition', $or)) . ')';
	}
	$and = view_split_logic($expr, 'and');
	if (count($and) > 1) {
		return '(' . implode(') && (', array_map('view_compile_condition', $and)) . ')';
	}
	// comparisons at top level
	if (preg_match('/^(.+?)\s*(===|!==|==|!=|<=|>=|<|>)\s*(.+)$/s', $expr, $m) && view_top_level_op($expr, $m[2])) {
		return view_compile_value_expr(trim($m[1])) . ' ' . $m[2] . ' ' . view_compile_value_expr(trim($m[3]));
	}
	return view_compile_value_expr($expr);
}

function view_balanced_parens(string $expr): bool {
	if ($expr === '' || $expr[0] !== '(') {
		return false;
	}
	$depth = 0;
	$len = strlen($expr);
	for ($i = 0; $i < $len; $i++) {
		if ($expr[$i] === '(') {
			$depth++;
		} elseif ($expr[$i] === ')') {
			$depth--;
			if ($depth === 0) {
				return $i === $len - 1;
			}
		}
	}
	return false;
}

/** Split on and/or keywords only when paren depth is 0. */
function view_split_logic(string $expr, string $op): array {
	$parts = [];
	$depth = 0;
	$cur = '';
	$len = strlen($expr);
	$opLen = strlen($op);
	for ($i = 0; $i < $len; $i++) {
		$c = $expr[$i];
		if ($c === '(') {
			$depth++;
			$cur .= $c;
			continue;
		}
		if ($c === ')') {
			$depth--;
			$cur .= $c;
			continue;
		}
		if ($depth === 0 && preg_match('/\G\s+' . $op . '\s+/A', $expr, $m, 0, $i)) {
			$parts[] = trim($cur);
			$cur = '';
			$i += strlen($m[0]) - 1;
			continue;
		}
		$cur .= $c;
	}
	$parts[] = trim($cur);
	return $parts;
}

function view_top_level_op(string $expr, string $op): bool {
	$pos = strpos($expr, $op);
	if ($pos === false) {
		return false;
	}
	$depth = 0;
	$len = strlen($expr);
	for ($i = 0; $i < $len; $i++) {
		if ($expr[$i] === '(') {
			$depth++;
		} elseif ($expr[$i] === ')') {
			$depth--;
		} elseif ($depth === 0 && substr($expr, $i, strlen($op)) === $op) {
			return true;
		}
	}
	return false;
}

/**
 * Expression that returns a value (for if/for/set).
 */
function view_compile_value_expr(string $expr): string {
	$expr = trim($expr);
	// Twig string concat: a ~ b ~ c
	$concat = view_split_concat($expr);
	if (count($concat) > 1) {
		$parts = [];
		foreach ($concat as $piece) {
			$parts[] = '((string)(' . view_compile_value_expr(trim($piece)) . '))';
		}
		return '(' . implode(' . ', $parts) . ')';
	}
	// string literals
	if (preg_match('/^([\'"])(.*)\1$/s', $expr, $m)) {
		return var_export(stripcslashes($m[2]), true);
	}
	// numbers
	if (is_numeric($expr)) {
		return $expr;
	}
	// true/false/null
	if (in_array(strtolower($expr), ['true', 'false', 'null', 'none'], true)) {
		$map = ['true' => 'true', 'false' => 'false', 'null' => 'null', 'none' => 'null'];
		return $map[strtolower($expr)];
	}
	// function call: foo(a, b) or link_for(...)
	if (preg_match('/^(\w+)\((.*)\)$/s', $expr, $m)) {
		$fn = $m[1];
		$args_s = trim($m[2]);
		$args = [];
		if ($args_s !== '') {
			foreach (view_split_args($args_s) as $a) {
				$args[] = view_compile_value_expr(trim($a));
			}
		}
		// Map Twig functions
		$allowed = [
			'time' => 'time', 'floor' => 'floor', 'count' => 'count',
			'hiddenInputs' => 'hiddenInputs', 'hiddenInputsHash' => 'hiddenInputsHash',
			'ratio' => 'twig_ratio_function', 'secure_link_confirm' => 'twig_secure_link_confirm',
			'secure_link' => 'twig_secure_link', 'link_for' => 'link_for',
			'check_container' => 'twig_check_container', 'range' => 'range',
		];
		if (isset($allowed[$fn])) {
			return $allowed[$fn] . '(' . implode(', ', $args) . ')';
		}
		// method-like: treat as filter-less path? fall through
	}
	// Filters and slice: path|filter(args)|filter2  and path[:256]
	return view_compile_print_expr($expr, false);
}

/**
 * Expression for echo (or raw value if $as_print false returns php expr).
 */
function view_compile_print_expr(string $expr, bool $wrap_echo_parts = true): string {
	$expr = trim($expr);
	// Split filters by | not inside parens
	$parts = view_split_filters($expr);
	$base = array_shift($parts);

	// slice syntax name[:n] or name[n:m]
	$slice = null;
	if (preg_match('/^(.+)\[:(\d+)\]$/', $base, $m)) {
		$base = $m[1];
		$slice = [0, (int)$m[2]];
	}

	$code = view_compile_path_or_literal($base);
	if ($slice) {
		$code = 'mb_substr((string)(' . $code . '), ' . $slice[0] . ', ' . $slice[1] . ')';
	}

	foreach ($parts as $f) {
		$f = trim($f);
		if ($f === '') {
			continue;
		}
		// filter(args)
		if (preg_match('/^(\w+)(?:\((.*)\))?$/s', $f, $m)) {
			$fname = $m[1];
			$fargs = [];
			if (isset($m[2]) && trim($m[2]) !== '') {
				foreach (view_split_args($m[2]) as $a) {
					$fargs[] = view_compile_value_expr(trim($a));
				}
			}
			$code = 'view_filter(' . $code . ', ' . var_export($fname, true) . ', [' . implode(', ', $fargs) . '])';
		}
	}
	return $code;
}

function view_compile_path_or_literal(string $base): string {
	$base = trim($base);
	if (preg_match('/^([\'"])(.*)\1$/s', $base, $m)) {
		return var_export(stripcslashes($m[2]), true);
	}
	if (is_numeric($base)) {
		return $base;
	}
	if (in_array(strtolower($base), ['true', 'false', 'null', 'none'], true)) {
		return strtolower($base) === 'none' ? 'null' : strtolower($base);
	}
	// function
	if (preg_match('/^(\w+)\((.*)\)$/s', $base, $m)) {
		return view_compile_value_expr($base);
	}
	// path: board.uri / config.dir.thumb
	if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $base)) {
		return 'view_get($__ctx, ' . var_export($base, true) . ')';
	}
	// ternary: a ? b : c (simple)
	if (preg_match('/^(.+?)\s*\?\s*(.+?)\s*:\s*(.+)$/s', $base, $m)) {
		return '((' . view_compile_condition(trim($m[1])) . ') ? (' . view_compile_value_expr(trim($m[2])) . ') : (' . view_compile_value_expr(trim($m[3])) . '))';
	}
	// fallback: treat as path
	return 'view_get($__ctx, ' . var_export($base, true) . ')';
}

/** Split on ~ outside strings/parens */
function view_split_concat(string $expr): array {
	$parts = [];
	$depth = 0;
	$cur = '';
	$in_str = false;
	$q = '';
	$len = strlen($expr);
	for ($i = 0; $i < $len; $i++) {
		$c = $expr[$i];
		if ($in_str) {
			$cur .= $c;
			if ($c === $q && ($i === 0 || $expr[$i - 1] !== '\\')) {
				$in_str = false;
			}
			continue;
		}
		if ($c === '"' || $c === "'") {
			$in_str = true;
			$q = $c;
			$cur .= $c;
			continue;
		}
		if ($c === '(') {
			$depth++;
			$cur .= $c;
			continue;
		}
		if ($c === ')') {
			$depth--;
			$cur .= $c;
			continue;
		}
		if ($c === '~' && $depth === 0) {
			$parts[] = $cur;
			$cur = '';
			continue;
		}
		$cur .= $c;
	}
	$parts[] = $cur;
	return $parts;
}

function view_split_filters(string $expr): array {
	$parts = [];
	$depth = 0;
	$cur = '';
	$len = strlen($expr);
	for ($i = 0; $i < $len; $i++) {
		$c = $expr[$i];
		if ($c === '(') {
			$depth++;
			$cur .= $c;
		} elseif ($c === ')') {
			$depth--;
			$cur .= $c;
		} elseif ($c === '|' && $depth === 0) {
			$parts[] = $cur;
			$cur = '';
		} else {
			$cur .= $c;
		}
	}
	$parts[] = $cur;
	return $parts;
}

/** Split on top-level commas; respects quotes, (), {}, []. */
function view_split_top_level(string $args, string $sep = ','): array {
	$parts = [];
	$depth = 0;
	$cur = '';
	$in_str = false;
	$q = '';
	$len = strlen($args);
	for ($i = 0; $i < $len; $i++) {
		$c = $args[$i];
		if ($in_str) {
			$cur .= $c;
			if ($c === $q && ($i === 0 || $args[$i - 1] !== '\\')) {
				$in_str = false;
			}
			continue;
		}
		if ($c === '"' || $c === "'") {
			$in_str = true;
			$q = $c;
			$cur .= $c;
			continue;
		}
		if ($c === '(' || $c === '{' || $c === '[') {
			$depth++;
			$cur .= $c;
			continue;
		}
		if ($c === ')' || $c === '}' || $c === ']') {
			$depth--;
			$cur .= $c;
			continue;
		}
		if ($c === $sep && $depth === 0) {
			$parts[] = $cur;
			$cur = '';
			continue;
		}
		$cur .= $c;
	}
	if (trim($cur) !== '') {
		$parts[] = $cur;
	}
	return $parts;
}

function view_split_args(string $args): array {
	return view_split_top_level($args, ',');
}

/**
 * Load and render a template by name (relative to templates/).
 */
function view_render(string $templateFile, array $options): string {
	global $config;

	$base = $config['dir']['template'] ?? 'templates';
	$path = $base . '/' . $templateFile;
	if (!is_readable($path)) {
		// allow .php native templates
		$pathPhp = preg_replace('/\.(html|xml|js|sql)$/', '.php', $path);
		if ($pathPhp && is_readable($pathPhp)) {
			return view_render_php($pathPhp, $options);
		}
		throw new Exception("Template file '{$templateFile}' does not exist in '{$base}'!");
	}

	// Native PHP template if extension is .php
	if (str_ends_with($templateFile, '.php')) {
		return view_render_php($path, $options);
	}

	$cacheDir = $base . '/cache_php';
	if (!is_dir($cacheDir)) {
		@mkdir($cacheDir, 0775, true);
	}
	$cacheKey = md5($templateFile . '|' . (string)@filemtime($path));
	$cacheFile = $cacheDir . '/' . $cacheKey . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $templateFile) . '.php';

	if (!is_readable($cacheFile) || filemtime($cacheFile) < filemtime($path) || !empty($config['twig_auto_reload']) || !empty($config['debug'])) {
		$source = file_get_contents($path);
		$compiled = view_compile($source, $templateFile);
		file_put_contents($cacheFile, $compiled);
	}

	$__ctx = $options;
	// Also extract for rare raw PHP in templates
	extract($options, EXTR_SKIP);
	ob_start();
	include $cacheFile;
	$body = ob_get_clean();

	if (!empty($config['minify_html']) && preg_match('/\.html$/i', $templateFile)) {
		$body = trim(preg_replace("/[\t\r\n]+/", '', $body));
	}
	return $body;
}

function view_render_php(string $path, array $options): string {
	extract($options, EXTR_SKIP);
	$__ctx = $options;
	ob_start();
	include $path;
	return ob_get_clean();
}
