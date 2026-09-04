<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

use Vichan\Context;
use Vichan\Functions\Net;

defined('TINYBOARD') or exit;

// create a hash/salt pair for validate logins (session cookie material)
function mkhash(string $username, $password = null, $salt = false) {
	global $config;

	$generated_salt = false;
	if (!$salt) {
		$salt = substr(base64_encode(random_bytes(16) . $config['cookies']['salt']), 0, 15);
		$generated_salt = true;
	}

	// HMAC-based session token (no client address — privacy fork)
	$hash = substr(base64_encode(hash_hmac(
		'sha256',
		$username . '|' . $password . '|' . $salt . '|' . $config['password_crypt_version'],
		$config['cookies']['salt'],
		true
	)), 0, 20);

	return $generated_salt ? [$hash, $salt] : $hash;
}

/**
 * Hash a moderator password for storage.
 * version column stores the schema version; password column stores the hash.
 *
 * v0: sha256(salt . sha1(pass)) with salt in version column (legacy Tinyboard)
 * v1: crypt() SHA512 ($6$)
 * v2: password_hash() PASSWORD_DEFAULT (bcrypt/argon2 depending on PHP)
 */
function crypt_password(string $password): array {
	global $config;
	$version = (int)($config['password_crypt_version'] ?? 2);
	if ($version >= 2) {
		return [2, password_hash($password, PASSWORD_DEFAULT)];
	}
	// v1 fallback
	$new_salt = generate_salt();
	$hash = crypt($password, $config['password_crypt'] . $new_salt . '$');
	return [1, $hash];
}

function test_password(string $password, string $salt, string $test): array {
	// Modern password_hash formats
	if (is_string($password) && preg_match('/^\$2[ayb]\$|^\$argon2(id|i|d)\$/', $password)) {
		return [2, password_verify($test, $password)];
	}

	// crypt() SHA512 / other crypt hashes
	if (is_string($password) && str_starts_with($password, '$') && strlen($password) > 20) {
		$comp = crypt($test, $password);
		return [1, is_string($comp) && hash_equals($password, $comp)];
	}

	// Legacy v0: version column held the salt string
	$version = (strlen((string)$salt) <= 8) ? (int)$salt : 0;
	if ($version === 0) {
		$comp = hash('sha256', $salt . sha1($test));
		return [0, hash_equals($password, $comp)];
	}

	$comp = crypt($test, $password);
	return [(int)$version, is_string($comp) && hash_equals($password, $comp)];
}

function generate_salt(): string {
	return strtr(base64_encode(random_bytes(16)), '+', '.');
}

function calc_cookie_name(bool $is_https, bool $is_path_jailed, string $base_name): string {
	if ($is_https) {
		if ($is_path_jailed) {
			return "__Host-$base_name";
		} else {
			return "__Secure-$base_name";
		}
	} else {
		return $base_name;
	}
}

function login(string $username, string $password) {
	global $mod, $config;

	$query = prepare("SELECT `id`, `type`, `boards`, `password`, `version` FROM ``mods`` WHERE BINARY `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));

	if ($user = $query->fetch(PDO::FETCH_ASSOC)) {
		list($version, $ok) = test_password($user['password'], $user['version'], $password);

		if ($ok) {
			$target_version = (int)($config['password_crypt_version'] ?? 2);
			// Rehash if schema is outdated or password_hash needs rehash
			$needs_upgrade = $target_version > $version
				|| ($version >= 2 && password_needs_rehash($user['password'], PASSWORD_DEFAULT));

			if ($needs_upgrade) {
				list($user['version'], $user['password']) = crypt_password($password);
				$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
				$query->bindValue(':password', $user['password']);
				$query->bindValue(':version', (string)$user['version']);
				$query->bindValue(':id', $user['id'], PDO::PARAM_INT);
				$query->execute() or error(db_error($query));
			}

			return $mod = [
				'id' => $user['id'],
				'type' => $user['type'],
				'username' => $username,
				'hash' => mkhash($username, $user['password']),
				'boards' => explode(',', $user['boards']),
			];
		}
	}

	return false;
}

function setCookies(): void {
	global $mod, $config;
	if (!$mod) {
		error('setCookies() was called for a non-moderator!');
	}

	$is_https = Net\is_connection_secure($config['cookies']['secure_login_only'] === 1);
	$is_path_jailed = $config['cookies']['jail'];
	$name = calc_cookie_name($is_https, $is_path_jailed, $config['cookies']['mod']);

	// <username>:<password>:<salt>
	$value = "{$mod['username']}:{$mod['hash'][0]}:{$mod['hash'][1]}";

	$options = [
		'expires' => time() + $config['cookies']['expire'],
		'path' => $is_path_jailed ? $config['cookies']['path'] : '/',
		'secure' => $is_https,
		'httponly' => $config['cookies']['httponly'],
		'samesite' => 'Strict'
	];

	setcookie($name, $value, $options);
}

function destroyCookies(): void {
	global $config;
	$base_name = $config['cookies']['mod'];
	$del_time = time() - 60 * 60 * 24 * 365; // 1 year.
	$jailed_path = $config['cookies']['jail'] ? $config['cookies']['path'] : '/';
	$http_only = $config['cookies']['httponly'];

	$options_multi = [
		$base_name => [
			'expires' => $del_time,
			'path' => $jailed_path ,
			'secure' => false,
			'httponly' => $http_only,
			'samesite' => 'Strict'
		],
		"__Host-$base_name" => [
			'expires' => $del_time,
			'path' => $jailed_path,
			'secure' => true,
			'httponly' => $http_only,
			'samesite' => 'Strict'
		],
		"__Secure-$base_name" => [
			'expires' => $del_time,
			'path' => '/',
			'secure' => true,
			'httponly' => $http_only,
			'samesite' => 'Strict'
		]
	];

	foreach ($options_multi as $name => $options) {
		if (isset($_COOKIE[$name])) {
			setcookie($name, 'deleted', $options);
			unset($_COOKIE[$name]);
		}
	}
}

function modLog(string $action, ?string $_board = null): void {
	global $mod, $board, $config;
	$query = prepare("INSERT INTO ``modlogs`` VALUES (:id, :board, :time, :text)");
	$query->bindValue(':id', (isset($mod['id']) ? $mod['id'] : -1), PDO::PARAM_INT);
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':text', $action);
	if (isset($_board))
		$query->bindValue(':board', $_board);
	elseif (isset($board))
		$query->bindValue(':board', $board['uri']);
	else
		$query->bindValue(':board', null, PDO::PARAM_NULL);
	$query->execute() or error(db_error($query));

	if ($config['syslog']) {
		_syslog(LOG_INFO, '[mod/' . $mod['username'] . ']: ' . $action);
	}
}

function make_secure_link_token(string $uri): string {
	global $mod, $config;
	return substr(sha1($config['cookies']['salt'] . '-' . $uri . '-' . $mod['id']), 0, 8);
}

function check_login(Context $ctx, bool $prompt = false): void {
	global $config, $mod;

	$is_https = Net\is_connection_secure($config['cookies']['secure_login_only'] === 1);
	$is_path_jailed = $config['cookies']['jail'];
	$expected_cookie_name = calc_cookie_name($is_https, $is_path_jailed, $config['cookies']['mod']);

	// Validate session
	if (isset($_COOKIE[$expected_cookie_name])) {
		// Should be username:hash:salt
		$cookie = explode(':', $_COOKIE[$expected_cookie_name]);
		if (count($cookie) != 3) {
			// Malformed cookies
			destroyCookies();
			if ($prompt) {
				mod_login($ctx);
			}
			exit;
		}

		$query = prepare("SELECT `id`, `type`, `boards`, `password` FROM ``mods`` WHERE `username` = :username");
		$query->bindValue(':username', $cookie[0]);
		$query->execute() or error(db_error($query));
		$user = $query->fetch(PDO::FETCH_ASSOC);

		// Deleted user or bad token — clear session instead of fatal on missing row
		if (!$user || $cookie[1] !== mkhash($cookie[0], $user['password'], $cookie[2])) {
			destroyCookies();
			if ($prompt) {
				mod_login($ctx);
			}
			exit;
		}

		$mod = array(
			'id' => (int)$user['id'],
			'type' => (int)$user['type'],
			'username' => $cookie[0],
			'boards' => explode(',', $user['boards'])
		);
	}
}
