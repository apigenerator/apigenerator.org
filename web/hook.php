<?php

/**
 * GenApiDoc Hook
 * Copyright (C) 2013 Tristan Lins
 *
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    genapidoc
 * @license    LGPL-3.0+
 * @filesource
 */

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

$hook = new \GhApiGen\Hook();

if (!array_key_exists('payload', $_POST)) {
	header("HTTP/1.1 400 Bad Request");
	echo '400 Bad Request';
	exit(1);
}

$payload = $_POST['payload'];
$payload = json_decode($payload);

$hook->run(
	$payload->repository->owner->name,
	$payload->repository->name,
	$payload->repository->master_branch,
	str_replace('refs/heads/', '', $payload->ref),
	$payload->head_commit->message
);

while (count(ob_list_handlers())) ob_end_flush();
