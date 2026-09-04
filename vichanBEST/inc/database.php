<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 *  PostgreSQL-only database layer (PHP 8.1+)
 *
 *  Application SQL still uses MySQL-style ``table`` / `column` quoting;
 *  db_rewrite_sql() translates that to PostgreSQL.
 */

defined('TINYBOARD') or exit;

function db_quote_ident(string $name): string {
	return '"' . str_replace('"', '""', $name) . '"';
}

/**
 * Rewrite vichan MySQL-style SQL for PostgreSQL.
 *
 *  - ``table``  → "prefix_table"
 *  - `column`   → "column"
 */
function db_rewrite_sql(string $query): string {
	global $config;

	$prefix = $config['db']['prefix'] ?? '';
	$board_regex = $config['board_regex'] ?? '[0-9a-zA-Z$_\x{0080}-\x{FFFF}]+';

	// ``table`` → "prefix_table"
	$query = preg_replace_callback(
		'/``(' . $board_regex . ')``/u',
		static function (array $m) use ($prefix): string {
			return db_quote_ident($prefix . $m[1]);
		},
		$query
	);

	// Remaining `identifier` → "identifier"
	$query = preg_replace_callback(
		'/`([^`]+)`/',
		static function (array $m): string {
			return db_quote_ident($m[1]);
		},
		$query
	);

	// INSERT … VALUES (NULL, …) → DEFAULT for SERIAL first columns
	$query = preg_replace(
		'/\bINSERT\s+INTO\s+((?:"[^"]+"|\w+(?:\.\w+)?))\s+VALUES\s*\(\s*NULL\s*,/i',
		'INSERT INTO $1 VALUES (DEFAULT,',
		$query
	);

	// LIMIT offset,count → LIMIT count OFFSET offset
	$query = preg_replace(
		'/\bLIMIT\s+(:[a-zA-Z_][a-zA-Z0-9_]*|\$\d+|\d+)\s*,\s*(:[a-zA-Z_][a-zA-Z0-9_]*|\$\d+|\d+)/i',
		'LIMIT $2 OFFSET $1',
		$query
	);

	$query = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $query);
	$query = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $query);
	$query = preg_replace('/\bUTC_TIMESTAMP\s*\(\s*\)/i', 'NOW() AT TIME ZONE \'UTC\'', $query);
	$query = preg_replace('/\bBINARY\s+/i', '', $query);
	$query = preg_replace('/\b(FORCE|USE|IGNORE)\s+INDEX\s*\([^)]*\)/i', '', $query);

	return $query;
}

/** Posts-table schema template (Twig). */
function db_schema_template(string $base): string {
	return $base . '.pgsql.sql';
}

function db_install_sql_path(): string {
	return 'install.pgsql.sql';
}

class PreparedQueryDebug {
	protected $query;
	protected $explain_query = false;

	public function __construct($query) {
		global $pdo, $config;
		$query = preg_replace("/[\n\t]+/", ' ', $query);

		$this->query = $pdo->prepare($query);
		if ($config['debug'] && $config['debug_explain'] && preg_match('/^(SELECT|INSERT|UPDATE|DELETE) /i', $query)) {
			try {
				$this->explain_query = $pdo->prepare("EXPLAIN $query");
			} catch (Throwable $e) {
				$this->explain_query = false;
			}
		}
	}

	public function __call($function, $args) {
		global $config, $debug;

		if ($config['debug'] && $function == 'execute') {
			if ($this->explain_query) {
				try {
					$this->explain_query->execute() or error(db_error($this->explain_query));
				} catch (Throwable $e) {
					// best-effort
				}
			}
			$start = microtime(true);
		}

		if ($this->explain_query && $function == 'bindValue') {
			call_user_func_array([$this->explain_query, $function], $args);
		}

		$return = call_user_func_array([$this->query, $function], $args);

		if ($config['debug'] && $function == 'execute') {
			$time = microtime(true) - $start;
			$debug['sql'][] = [
				'query' => $this->query->queryString,
				'rows' => $this->query->rowCount(),
				'explain' => $this->explain_query ? $this->explain_query->fetchAll(PDO::FETCH_ASSOC) : null,
				'time' => '~' . round($time * 1000, 2) . 'ms',
			];
			$debug['time']['db_queries'] += $time;
		}

		return $return;
	}
}

function sql_open() {
	global $pdo, $config, $debug;
	if ($pdo) {
		return true;
	}

	if ($config['debug']) {
		$start = microtime(true);
	}

	$type = $config['db']['type'] ?? 'pgsql';
	if ($type !== 'pgsql') {
		error(_('This build requires PostgreSQL. Set $config[\'db\'][\'type\'] = \'pgsql\'.'));
	}

	if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
		error(_('PHP PDO PostgreSQL driver (pdo_pgsql) is not installed.'));
	}

	$server = $config['db']['server'] ?? '127.0.0.1';
	$database = $config['db']['database'] ?? '';
	$port = $config['db']['port'] ?? '5432';

	if (isset($server[0]) && $server[0] === ':') {
		$unix_socket = substr($server, 1);
		$dsn = 'pgsql:host=' . $unix_socket . ';dbname=' . $database;
	} else {
		$host = $server;
		$p = $port;
		if (strpos($server, ':') !== false && !filter_var($server, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			[$host, $maybe_port] = explode(':', $server, 2);
			if (ctype_digit($maybe_port)) {
				$p = $maybe_port;
			}
		}
		$dsn = 'pgsql:host=' . $host . ';port=' . (int)($p ?: 5432) . ';dbname=' . $database;
	}

	if (!empty($config['db']['dsn'])) {
		$dsn .= ';' . $config['db']['dsn'];
	}

	try {
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::ATTR_TIMEOUT => $config['db']['timeout'] ?? 30,
		];

		if (!empty($config['db']['persistent'])) {
			$options[PDO::ATTR_PERSISTENT] = true;
		}

		$pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], $options);

		if ($config['debug']) {
			$debug['time']['db_connect'] = '~' . round((microtime(true) - $start) * 1000, 2) . 'ms';
		}

		$pdo->exec("SET client_encoding TO 'UTF8'");
		$pdo->exec("SET datestyle TO 'ISO, YMD'");
		$pdo->exec("SET bytea_output TO 'hex'");

		return $pdo;
	} catch (PDOException $e) {
		$message = $e->getMessage();
		$message = str_replace($config['db']['user'], '<em>hidden</em>', $message);
		$message = str_replace($config['db']['password'], '<em>hidden</em>', $message);
		error(_('Database error: ') . $message);
	}
}

/** Bind a binary string for bytea columns (hex form). */
function db_bind_binary(PDOStatement $stmt, string $param, $value): void {
	if ($value === null || $value === false) {
		$stmt->bindValue($param, null, PDO::PARAM_NULL);
		return;
	}
	$stmt->bindValue($param, '\\x' . bin2hex($value), PDO::PARAM_STR);
}

/** Normalize a bytea value from PostgreSQL into raw binary. */
function db_normalize_binary($value): ?string {
	if ($value === null || $value === false || $value === '') {
		return $value === '' ? '' : null;
	}
	if (is_resource($value)) {
		$value = stream_get_contents($value);
		if ($value === false) {
			return null;
		}
	}
	if (!is_string($value)) {
		return null;
	}
	if (str_starts_with($value, '\\x') || str_starts_with($value, '\x')) {
		$hex = substr($value, 2);
		$bin = @hex2bin($hex);
		return $bin === false ? $value : $bin;
	}
	if (ctype_xdigit($value) && (strlen($value) % 2) === 0 && strlen($value) >= 8) {
		$bin = @hex2bin($value);
		if ($bin !== false && (strlen($bin) === 4 || strlen($bin) === 16)) {
			return $bin;
		}
	}
	return $value;
}

function prepare($query) {
	global $pdo, $debug, $config;

	$query = db_rewrite_sql($query);
	sql_open();

	if ($config['debug']) {
		return new PreparedQueryDebug($query);
	}

	return $pdo->prepare($query);
}

function query($query) {
	global $pdo, $debug, $config;

	$query = db_rewrite_sql($query);
	sql_open();

	if ($config['debug']) {
		if ($config['debug_explain'] && preg_match('/^(SELECT|INSERT|UPDATE|DELETE) /i', $query)) {
			try {
				$explain = $pdo->query('EXPLAIN ' . $query);
			} catch (Throwable $e) {
				$explain = null;
			}
		}
		$start = microtime(true);
		try {
			$query = $pdo->query($query);
		} catch (PDOException $e) {
			return false;
		}
		if (!$query) {
			return false;
		}
		$time = microtime(true) - $start;
		$debug['sql'][] = [
			'query' => $query->queryString,
			'rows' => $query->rowCount(),
			'explain' => isset($explain) && $explain ? $explain->fetchAll(PDO::FETCH_ASSOC) : null,
			'time' => '~' . round($time * 1000, 2) . 'ms',
		];
		$debug['time']['db_queries'] += $time;
		return $query;
	}

	try {
		return $pdo->query($query);
	} catch (PDOException $e) {
		return false;
	}
}

function db_error($PDOStatement = null) {
	global $pdo, $db_error;

	if (isset($PDOStatement) && $PDOStatement instanceof PDOStatement) {
		$db_error = $PDOStatement->errorInfo();
		return $db_error[2] ?? 'Unknown statement error';
	}

	if (!$pdo) {
		return 'No database connection';
	}

	$db_error = $pdo->errorInfo();
	return $db_error[2] ?? 'Unknown database error';
}

function db_last_insert_id(?string $table = null, string $column = 'id'): string {
	global $pdo;
	sql_open();

	if ($table !== null) {
		$seq = $table . '_' . $column . '_seq';
		try {
			return (string)$pdo->lastInsertId($seq);
		} catch (Throwable $e) {
			// fall through
		}
	}

	return (string)$pdo->lastInsertId();
}

function db_split_sql_statements(string $sql): array {
	$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
	$cleaned = '';
	foreach (preg_split("/\r\n|\n|\r/", $sql) as $line) {
		if (preg_match('/^\s*--/', $line)) {
			continue;
		}
		$cleaned .= preg_replace('/\s+--.*$/', '', $line) . "\n";
	}
	$out = [];
	foreach (explode(';', $cleaned) as $chunk) {
		$chunk = trim($chunk);
		if ($chunk !== '') {
			$out[] = $chunk;
		}
	}
	return $out;
}

