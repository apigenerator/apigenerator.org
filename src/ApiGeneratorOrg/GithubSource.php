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

class GithubSource implements GitSource
{
	public function getRepositoryUrl(Repository $repository)
	{
		return 'git@github.com:' . $repository->getRepository() . '.git';
	}

	public function getPagesUrl(Repository $repository)
	{
		return sprintf(
			'http://%s.github.io/%s/',
			$repository->getOwnerName(),
			$repository->getRepositoryName()
		);
	}
}
