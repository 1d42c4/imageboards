<?php

use Vichan\Functions\Format;
use Lifo\IP\CIDR;

class Bans {
	static private function shouldDelete(array $ban, bool $require_ban_view) {
		return $ban['expires'] && ($ban['seen'] || !$require_ban_view) && $ban['expires'] < time();
	}

	static private function deleteBans(array $ban_ids) {
		$len = count($ban_ids);
		if ($len < 1) {
			return;
		}
		if ($len === 1) {
			$query = prepare('DELETE FROM ``bans`` WHERE `id` = :id');
			$query->bindValue(':id', $ban_ids[0], PDO::PARAM_INT);
			$query->execute() or error(db_error());
		} else {
			$placeholders = [];
			for ($i = 0; $i < $len; $i++) {
				$placeholders[] = ":id{$i}";
			}
			$query = prepare('DELETE FROM ``bans`` WHERE `id` IN (' . implode(',', $placeholders) . ')');
			for ($i = 0; $i < $len; $i++) {
				$query->bindValue(":id{$i}", (int)$ban_ids[$i], PDO::PARAM_INT);
			}
			$query->execute() or error(db_error());
		}
		Vichan\Functions\Theme\rebuild_themes('bans');
	}

	/** Normalize IP binary fields after a DB fetch. */
	static private function hydrateBan(array $ban): array {
		$ban['ipstart'] = db_normalize_binary($ban['ipstart'] ?? null);
		$ban['ipend'] = db_normalize_binary($ban['ipend'] ?? null);
		if (!empty($ban['post']) && is_string($ban['post'])) {
			$decoded = json_decode($ban['post'], true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$ban['post'] = $decoded;
			}
		}
		$ban['mask'] = self::range_to_string([$ban['ipstart'], $ban['ipend']]);
		return $ban;
	}

	static private function findAutoGc(?string $ip, $board, bool $get_mod_info, bool $require_ban_view, ?int $ban_id): array {
		$query = prepare('SELECT ``bans``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``bans``
		' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
		WHERE
			(' . ($board !== false ? '(`board` IS NULL OR `board` = :board) AND' : '') . '
			(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)) OR (``bans``.id = :id))
		ORDER BY `expires` IS NULL, `expires` DESC');

		if ($board !== false) {
			$query->bindValue(':board', $board, PDO::PARAM_STR);
		}

		$query->bindValue(':id', $ban_id);
		db_bind_binary($query, ':ip', inet_pton($ip));
		$query->execute() or error(db_error($query));

		$ban_list = [];
		$to_delete_list = [];

		while ($ban = $query->fetch(PDO::FETCH_ASSOC)) {
			$ban = self::hydrateBan($ban);
			if (self::shouldDelete($ban, $require_ban_view)) {
				$to_delete_list[] = $ban['id'];
			} else {
				$ban_list[] = $ban;
			}
		}

		self::deleteBans($to_delete_list);

		return $ban_list;
	}

	static private function findNoGc(?string $ip, string $board, bool $get_mod_info, ?int $ban_id): array {
		$query = prepare('SELECT ``bans``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``bans``
		' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
		WHERE
			(' . ($board !== false ? '(`board` IS NULL OR `board` = :board) AND' : '') . '
			(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)) OR (``bans``.id = :id))
			AND (`expires` IS NULL OR `expires` >= :curr_time)
		ORDER BY `expires` IS NULL, `expires` DESC');

		if ($board !== false) {
			$query->bindValue(':board', $board, PDO::PARAM_STR);
		}

		$query->bindValue(':id', $ban_id);
		db_bind_binary($query, ':ip', inet_pton($ip));
		$query->bindValue(':curr_time', time());
		$query->execute() or error(db_error($query));

		$ban_list = $query->fetchAll(PDO::FETCH_ASSOC);
		return array_map([self::class, 'hydrateBan'], $ban_list);
	}

	static public function range_to_string($mask) {
		list($ipstart, $ipend) = $mask;
		$ipstart = db_normalize_binary($ipstart);
		$ipend = db_normalize_binary($ipend);

		if ($ipstart === null || $ipstart === '') {
			return '???';
		}

		if (!isset($ipend) || $ipend === false || $ipend === null || $ipend === '') {
			// Not a range. Single IP address.
			$ipstr = @inet_ntop($ipstart);
			return $ipstr !== false ? $ipstr : '???';
		}

		if (strlen($ipstart) != strlen($ipend)) {
			return '???';
		}

		$range = CIDR::range_to_cidr(inet_ntop($ipstart), inet_ntop($ipend));
		if ($range !== false) {
			return $range;
		}

		return '???';
	}

	private static function calc_cidr($mask) {
		$cidr = new CIDR($mask);
		$range = $cidr->getRange();

		return [ inet_pton($range[0]), inet_pton($range[1]) ];
	}

	public static function parse_time($str) {
		if (empty($str))
			return false;

		if (($time = @strtotime($str)) !== false)
			return $time;

		if (!preg_match('/^((\d+)\s?ye?a?r?s?)?\s?+((\d+)\s?mon?t?h?s?)?\s?+((\d+)\s?we?e?k?s?)?\s?+((\d+)\s?da?y?s?)?((\d+)\s?ho?u?r?s?)?\s?+((\d+)\s?mi?n?u?t?e?s?)?\s?+((\d+)\s?se?c?o?n?d?s?)?$/', $str, $matches))
			return false;

		$expire = 0;

		if (isset($matches[2])) {
			// Years
			$expire += (int)$matches[2]*60*60*24*365;
		}
		if (isset($matches[4])) {
			// Months
			$expire += (int)$matches[4]*60*60*24*30;
		}
		if (isset($matches[6])) {
			// Weeks
			$expire += (int)$matches[6]*60*60*24*7;
		}
		if (isset($matches[8])) {
			// Days
			$expire += (int)$matches[8]*60*60*24;
		}
		if (isset($matches[10])) {
			// Hours
			$expire += (int)$matches[10]*60*60;
		}
		if (isset($matches[12])) {
			// Minutes
			$expire += (int)$matches[12]*60;
		}
		if (isset($matches[14])) {
			// Seconds
			$expire += (int)$matches[14];
		}

		return time() + $expire;
	}

	static public function parse_range($mask) {
		$ipstart = false;
		$ipend = false;

		if (preg_match('@^(\d{1,3}\.){1,3}([\d*]{1,3})?$@', $mask) && substr_count($mask, '*') == 1) {
			// IPv4 wildcard mask
			$parts = explode('.', $mask);
			$ipv4 = '';
			foreach ($parts as $part) {
				if ($part == '*') {
					$ipstart = inet_pton($ipv4 . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
					$ipend = inet_pton($ipv4 . '255' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
					break;
				} elseif(($wc = strpos($part, '*')) !== false) {
					$ipstart = inet_pton($ipv4 . substr($part, 0, $wc) . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
					$ipend = inet_pton($ipv4 . substr($part, 0, $wc) . '9' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
					break;
				}
				$ipv4 .= "$part.";
			}
		} elseif (preg_match('@^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d+$@', $mask)) {
			list($ipv4, $bits) = explode('/', $mask);
			if ($bits > 32)
				return false;

			list($ipstart, $ipend) = self::calc_cidr($mask);
		} elseif (preg_match('@^[:a-z\d]+/\d+$@i', $mask)) {
			list($ipv6, $bits) = explode('/', $mask);
			if ($bits > 128)
				return false;

			list($ipstart, $ipend) = self::calc_cidr($mask);
		} else {
			if (($ipstart = @inet_pton($mask)) === false)
				return false;
		}

		return [$ipstart, $ipend];
	}

	static public function find(?string $ip, $board = false, bool $get_mod_info = false, ?int $ban_id = null, bool $auto_gc = true) {
		global $config;

		if ($auto_gc) {
			return self::findAutoGc($ip, $board, $get_mod_info, $config['require_ban_view'], $ban_id);
		} else {
			return self::findNoGc($ip, $board, $get_mod_info, $ban_id);
		}
	}

	static public function stream_json($out = false, $filter_ips = false, $filter_staff = false, $board_access = false) {
		$query = query("SELECT ``bans``.*, `username` FROM ``bans``
			LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`
 			ORDER BY `created` DESC") or error(db_error());
                $bans = $query->fetchAll(PDO::FETCH_ASSOC);

		if ($board_access && $board_access[0] == '*') $board_access = false;

		$out ? fputs($out, "[") : print("[");

		$end = end($bans);

		foreach ($bans as &$ban) {
			$ban = self::hydrateBan($ban);

			if (is_array($ban['post'] ?? null)) {
				$ban['message'] = $ban['post']['body'] ?? 0;
			} elseif (!empty($ban['post'])) {
				$post = json_decode((string)$ban['post']);
				$ban['message'] = isset($post->body) ? $post->body : 0;
			}
			unset($ban['ipstart'], $ban['ipend'], $ban['post'], $ban['creator']);

			if ($board_access === false || in_array ($ban['board'], $board_access)) {
				$ban['access'] = true;
			}

			if (filter_var($uncloaked_mask, FILTER_VALIDATE_IP) !== false) {
				$ban['single_addr'] = true;
			}
			if ($filter_staff || ($board_access !== false && !in_array($ban['board'], $board_access))) {
				$ban['username'] = '?';
			}
			if ($filter_ips || ($board_access !== false && !in_array($ban['board'], $board_access))) {
				@list($ban['mask'], $subnet) = explode("/", $ban['mask']);
				$ban['mask'] = preg_split("/[\.:]/", $ban['mask']);
				$ban['mask'] = array_slice($ban['mask'], 0, 2);
				$ban['mask'] = implode(".", $ban['mask']);
				$ban['mask'] .= ".x.x";
				if (isset ($subnet)) {
					$ban['mask'] .= "/$subnet";
				}
				$ban['masked'] = true;
			}

			$json = json_encode($ban);
			$out ? fputs($out, $json) : print($json);

			if ($ban['id'] != $end['id']) {
				$out ? fputs($out, ",") : print(",");
			}
		}

		$out ? fputs($out, "]") : print("]");

	}

	static public function seen($ban_id) {
		$query = query("UPDATE ``bans`` SET `seen` = 1 WHERE `id` = " . (int)$ban_id) or error(db_error());
                Vichan\Functions\Theme\rebuild_themes('bans');
	}

	static public function purge($require_seen, $moratorium) {
		if ($require_seen) {
			$query = prepare("DELETE FROM ``bans`` WHERE `expires` IS NOT NULL AND `expires` + :moratorium < :curr_time AND `seen` = 1");
		} else {
			$query = prepare("DELETE FROM ``bans`` WHERE `expires` IS NOT NULL AND `expires` + :moratorium < :curr_time");
		}
		$query->bindValue(':moratorium', $moratorium);
		$query->bindValue(':curr_time', time());
		$query->execute() or error(db_error($query));

		$affected = $query->rowCount();
		if ($affected > 0) {
			Vichan\Functions\Theme\rebuild_themes('bans');
		}
		return $affected;
	}

	static public function delete($ban_id, $modlog = false, $boards = false, $dont_rebuild = false) {
		global $config;

		if ($boards && $boards[0] == '*') $boards = false;

		if ($modlog) {
			$query = query("SELECT `ipstart`, `ipend`, `board` FROM ``bans`` WHERE `id` = " . (int)$ban_id) or error(db_error());
			if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
				// Ban doesn't exist
				return false;
			}

			$ban = self::hydrateBan($ban);

			if ($boards !== false && !in_array($ban['board'], $boards)) {
				error($config['error']['noaccess']);
			}

			$mask = $ban['mask'];
			$cloaked_mask = cloak_mask($mask);

			modLog("Removed ban #{$ban_id} for " .
				(filter_var($mask, FILTER_VALIDATE_IP) !== false ? "<a href=\"?/IP/$cloaked_mask\">$cloaked_mask</a>" : $cloaked_mask));
		}

		query("DELETE FROM ``bans`` WHERE `id` = " . (int)$ban_id) or error(db_error());

		if (!$dont_rebuild) Vichan\Functions\Theme\rebuild_themes('bans');

		return true;
	}

	static public function new_ban($cloaked_mask, $reason, $length = false, $ban_board = false, $mod_id = false, $post = false) {
		global $mod, $pdo, $board, $config;

		if (!privacy_store_ip()) {
			error(_('IP bans are disabled: this board does not store client IP addresses.'));
		}

		$mask = uncloak_mask($cloaked_mask);

		if ($mod_id === false) {
			$mod_id = isset($mod['id']) ? $mod['id'] : -1;
		}

		$range = self::parse_range($mask);
		$mask = self::range_to_string($range);
		$cloaked_mask = cloak_mask($mask);

		$query = prepare("INSERT INTO ``bans`` VALUES (NULL, :ipstart, :ipend, :time, :expires, :board, :mod, :reason, 0, :post)");

		db_bind_binary($query, ':ipstart', $range[0]);
		if ($range[1] !== false && $range[1] != $range[0]) {
			db_bind_binary($query, ':ipend', $range[1]);
		} else {
			$query->bindValue(':ipend', null, PDO::PARAM_NULL);
		}

		$query->bindValue(':mod', $mod_id, PDO::PARAM_INT);
		$query->bindValue(':time', time(), PDO::PARAM_INT);

		if ($reason !== '') {
			$reason = escape_markup_modifiers($reason);
			markup($reason);
			$query->bindValue(':reason', $reason);
		} else {
			$query->bindValue(':reason', null, PDO::PARAM_NULL);
		}

		if ($length) {
			if (is_int($length) || ctype_digit((string)$length)) {
				$length = time() + (int)$length;
			} else {
				$length = self::parse_time($length);
			}
			$query->bindValue(':expires', $length, PDO::PARAM_INT);
		} else {
			$query->bindValue(':expires', null, PDO::PARAM_NULL);
		}

		if ($ban_board) {
			$query->bindValue(':board', $ban_board);
		} else {
			$query->bindValue(':board', null, PDO::PARAM_NULL);
		}

		if ($post) {
			if (!isset($board['uri'])) {
				openBoard($post['board']);
			}

			$post['board'] = $board['uri'];
			// Cap serialized post snapshot size
			if (isset($post['body'])) {
				$post['body'] = mb_strcut((string)$post['body'], 0, 32768);
			}

			$query->bindValue(':post', json_encode($post, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
		} else {
			$query->bindValue(':post', null, PDO::PARAM_NULL);
		}

		$query->execute() or error(db_error($query));

		$ban_len = $length > 0 ? preg_replace('/^(\d+) (\w+?)s?$/', '$1-$2', Format\until($length)) : 'permanent';
		$ban_board = $ban_board ? "/$ban_board/" : 'all boards';
		$ban_ip = filter_var($mask, FILTER_VALIDATE_IP) !== false ? "<a href=\"?/IP/$cloaked_mask\">$cloaked_mask</a>" : $cloaked_mask;
		$ban_id = db_last_insert_id('bans');
		$ban_reason = $reason ? 'reason: ' . utf8tohtml($reason) : 'no reason';

		modLog("Created a new $ban_len ban on $ban_board for $ban_ip (<small># $ban_id </small>) with $ban_reason");

		Vichan\Functions\Theme\rebuild_themes('bans');

		return $ban_id;
	}
}
