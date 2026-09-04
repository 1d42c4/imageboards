<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

/**
 * Fire a named event. Optional plugins may register handlers via event_handler().
 * @return mixed false if none/ok, or first non-empty handler result (treated as error/skip)
 */
function event(string $event, mixed ...$args): mixed {
	global $events;

	if (!isset($events[$event])) {
		return false;
	}

	foreach ($events[$event] as $callback) {
		if (!is_callable($callback)) {
			error('Event handler for ' . $event . ' is not callable!');
		}
		if ($error = $callback(...$args)) {
			return $error;
		}
	}

	return false;
}

function event_handler(string $event, callable $callback): void {
	global $events;
	$events[$event][] = $callback;
}

function reset_events(): void {
	global $events;
	$events = [];
}

