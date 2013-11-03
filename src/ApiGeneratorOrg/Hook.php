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

class Hook
{
	const PARAM_SOURCE_FILE = 'source-file';

	const PARAM_DOCS_FILE = 'docs-file';

	const PARAM_STRING = 'string';

	const PARAM_BOOL = 'bool';

	/**
	 * @var HandlerInterface
	 */
	protected $handler;

	/**
	 * @var Logger
	 */
	protected $logger;

	/**
	 * @var Filesystem
	 */
	protected $fs;

	/**
	 * @var array
	 */
	protected $repositories;

	function __construct()
	{
		set_error_handler(array($this, 'handleError'));
		set_exception_handler(array($this, 'handleException'));

		$this->handler = new RotatingFileHandler(dirname(dirname(__DIR__)) . '/log/hook.log', 7);
		$this->logger  = new Logger('*/*', array($this->handler));

		$this->fs = new Filesystem();
	}

	/**
	 * @param $errno
	 * @param $errstr
	 * @param $errfile
	 * @param $errline
	 *
	 * @throws ErrorException
	 */
	public function handleError($errno, $errstr, $errfile, $errline)
	{
		throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	}

	/**
	 * @param Exception $exception
	 */
	public function handleException($exception)
	{
		$message = '';
		$e       = $exception;
		do {
			$message .= sprintf(
				"%s:%d: [%s] (%d) %s\n",
				$e->getFile(),
				$e->getLine(),
				get_class($e),
				$e->getCode(),
				$e->getMessage()
			);
			$e = $e->getPrevious();
		} while ($e);

		$this->logger->error(rtrim($message));

		while (count(ob_list_handlers())) {
			ob_end_clean();
		}
		header("HTTP/1.1 500 Internal Server Error");
		echo '500 Internal Server Error';
		exit(1);
	}

	public function run(Repository $repository, GitSource $source)
	{
		$this->logger = new Logger($repository->getRepository(), array($this->handler));

		$this->logger->info(
			sprintf(
				'Generate api docs for %s/%s, branch %s: %s',
				$repository->getOwnerName(),
				$repository->getRepositoryName(),
				$repository->getCommitBranch(),
				$repository->getCommitMessage()
			)
		);

		$this->generateDocs($repository, $source);
	}

	protected function generateDocs(Repository $repository, GitSource $source)
	{
		$apigen = new Apigen();
		$apigen->run($repository, $source);

		$phpdoc = new Phpdoc();
		$phpdoc->run($repository, $source);
	}
}
