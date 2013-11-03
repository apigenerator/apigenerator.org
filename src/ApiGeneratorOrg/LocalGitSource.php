<?php

/**
 * ApiGenerator.org
 * Copyright (C) 2013 Tristan Lins
 *
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    apigenerator.org
 * @license    LGPL-3.0+
 * @filesource
 */

namespace ApiGeneratorOrg;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class LocalGitSource implements GitSource
{
	protected $path;

	function __construct($path)
	{
		$this->path = $path;
	}

	protected function getPath(Repository $repository)
	{
		return str_replace(
			array(
				'{owner}',
				'{repo}',
				'{master-branch}',
				'{commit-branch}'
			),
			array(
				$repository->getOwnerName(),
				$repository->getRepositoryName(),
				$repository->getMasterBranch(),
				$repository->getCommitBranch(),
			),
			$this->path
		);
	}

	public function getRepositoryUrl(Repository $repository)
	{
		return $this->getPath($repository);
	}

	public function getPagesUrl(Repository $repository)
	{
		return $this->getPath($repository);
	}
}
