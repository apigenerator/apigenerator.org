<?php

/**
 * Github ApiGen Hook
 * Copyright (C) 2013 Tristan Lins
 *
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    gh-apigen-hook
 * @license    LGPL-3.0+
 * @filesource
 */

namespace GhApiGen;

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
	 * @var string
	 */
	protected $root;

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
	 * @var string
	 */
	protected $ownerName;

	/**
	 * @var string
	 */
	protected $repositoryName;

	/**
	 * @var string
	 */
	protected $masterBranch;

	/**
	 * @var string
	 */
	protected $commitBranch;

	/**
	 * @var string
	 */
	protected $commitMessage;

	/**
	 * @var array
	 */
	protected $repositories;

	/**
	 * @var array
	 */
	protected $defaultSettings;

	/**
	 * @var stdClass
	 */
	protected $payload;

	/**
	 * @var string
	 */
	protected $repository;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var string
	 */
	protected $sourcesPath;

	/**
	 * @var string
	 */
	protected $docsPath;

	function __construct()
	{
		set_error_handler(array($this, 'handleError'));
		set_exception_handler(array($this, 'handleException'));

		$this->root = dirname(dirname(__DIR__));

		$this->handler = new RotatingFileHandler($this->root . '/log/hook.log', 7);
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

	public function run($ownerName, $repositoryName, $masterBranch, $commitBranch, $commitMessage)
	{
		$this->logger = new Logger($ownerName . '/' . $repositoryName, array($this->handler));

		$this->logger->info(
			sprintf(
				'Run apigen for %s/%s, branch %s: %s',
				$ownerName,
				$repositoryName,
				$commitBranch,
				$commitMessage
			)
		);

		$this->ownerName      = $ownerName;
		$this->repositoryName = $repositoryName;
		$this->masterBranch   = $masterBranch;
		$this->commitBranch   = $commitBranch;
		$this->commitMessage  = $commitMessage;

		$this->repository = $this->ownerName . '/' . $this->repositoryName;

		$this->checkApiGenInstalled();
		$this->buildDefaultSettings();
		$this->initSourcePath();
		$this->initDocsPath();
		$this->checkoutSource();
		$this->buildSettings();
		$this->checkBranch();
		$this->prepareDocs();
		$this->generateDocs();
		$this->pushDocs();
	}

	protected function checkApiGenInstalled()
	{
		if (!file_exists($this->root . '/apigen/apigen.php')) {
			throw new \RuntimeException('apigen is not installed');
		}
	}

	protected function buildDefaultSettings()
	{
		if (file_exists($this->root . '/config/defaults.yml')) {
			$this->defaultSettings = Yaml::parse($this->root . '/config/defaults.yml');
		}
		else {
			$this->defaultSettings = array();
		}

		# build default base url
		if (!array_key_exists('base-url', $this->defaultSettings)) {
			$this->defaultSettings['base-url'] = sprintf(
				'http://%s.github.io/%s/',
				$this->ownerName,
				$this->repositoryName
			);
		}

		# set default title
		if (!array_key_exists('title', $this->defaultSettings)) {
			$this->defaultSettings['title'] = $this->repository;
		}
	}

	protected function initSourcePath()
	{
		# create sources path
		$this->sourcesPath = sprintf(
			$this->root . '/sources/%s/%s/',
			$this->ownerName,
			$this->repositoryName
		);

		$this->logger->debug(sprintf('Init sources directory %s', $this->sourcesPath));

		if (!$this->fs->exists($this->sourcesPath)) {
			$this->fs->mkdir($this->sourcesPath);
		}
	}

	protected function initDocsPath()
	{
		# create docs path
		$this->docsPath = sprintf(
			$this->root . '/docs/%s/%s/',
			$this->ownerName,
			$this->repositoryName
		);

		$this->logger->debug(sprintf('Init docs directory %s', $this->docsPath));

		if (!$this->fs->exists($this->docsPath)) {
			$this->fs->mkdir($this->docsPath);
		}
	}

	protected function checkoutSource()
	{
		$url = escapeshellarg('git://github.com/' . $this->repository . '.git');

		if ($this->fs->exists($this->sourcesPath . '.git')) {
			$this->logger->debug(sprintf('Update sources %s', $this->sourcesPath));

			$process = new Process('git remote set-url origin ' . $url, $this->sourcesPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = new Process('git fetch origin', $this->sourcesPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = new Process('git reset --hard ' . escapeshellarg(
				'origin/' . $this->commitBranch
			), $this->sourcesPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else {
			$this->logger->debug(sprintf('Checkout source %s', $url));

			$process = new Process('git clone -b ' . escapeshellarg(
				$this->commitBranch
			) . ' ' . $url . ' ' . escapeshellarg($this->sourcesPath));
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
	}

	protected function buildSettings()
	{
		if (!file_exists($this->sourcesPath . '/apigen.yml')) {
			$this->logger->warning('apigen.yml is missing, skip');
			exit;
		}

		$this->settings = Yaml::parse($this->sourcesPath . '/apigen.yml');

		if ($this->settings === null) {
			$this->settings = array();
		}

		// use the github master branch
		if (empty($this->settings['branch'])) {
			$this->settings['branch'] = $this->masterBranch;
		}

		# merge with defaults
		$this->settings = array_merge(
			$this->defaultSettings,
			$this->settings
		);

		$this->logger->debug(
			sprintf('Build settings for %s/%s', $this->ownerName, $this->repositoryName),
			$this->settings
		);
	}

	protected function checkBranch()
	{
		if ($this->settings['branch'] != $this->commitBranch) {
			$this->logger->warning(
				'Skip branch ' . $this->commitBranch . ', expect branch ' . $this->settings['branch']
			);
			exit;
		}
	}

	protected function prepareDocs()
	{
		$url = escapeshellarg('git@github.com:' . $this->repository . '.git');

		if ($this->fs->exists($this->docsPath . '.git')) {
			$process = new Process('git remote set-url origin ' . $url, $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else {
			$process = new Process('git init ' . escapeshellarg($this->docsPath));
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = new Process('git remote add origin ' . $url, $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}

		$process = new Process('git fetch origin', $this->docsPath);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}

		$process = new Process('git branch -a', $this->docsPath);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}
		$branches = explode("\n", $process->getOutput());
		$branches = array_map(
			function ($branch) {
				return ltrim($branch, '*');
			},
			$branches
		);
		$branches = array_map('trim', $branches);

		if (in_array('remotes/origin/gh-pages', $branches)) {
			$this->logger->debug(sprintf('Update docs %s', $url));

			$process = new Process('git checkout -B gh-pages', $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = new Process('git reset --hard origin/gh-pages', $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else if (!in_array('gh-pages', $branches)) {
			$this->logger->debug(sprintf('Initialise empty docs %s', $url));

			$process = new Process('git checkout --orphan gh-pages', $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else {
			$this->logger->debug(sprintf('Reuse local docs branch %s', $url));

			$process = new Process('git checkout -B gh-pages', $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
	}

	protected function generateDocs()
	{
		$this->logger->debug('Generate docs');

		$args = array($this->root . '/apigen/apigen.php');
		foreach (
			array(
				'config'           => Hook::PARAM_SOURCE_FILE,
				'extensions'       => Hook::PARAM_STRING,
				'exclude'          => Hook::PARAM_STRING,
				'skip-doc-path'    => Hook::PARAM_STRING,
				'skip-doc-prefix'  => Hook::PARAM_STRING,
				'charset'          => Hook::PARAM_STRING,
				'main'             => Hook::PARAM_STRING,
				'title'            => Hook::PARAM_STRING,
				'base-url'         => Hook::PARAM_STRING,
				'google-cse-id'    => Hook::PARAM_STRING,
				'google-cse-label' => Hook::PARAM_STRING,
				'google-analytics' => Hook::PARAM_STRING,
				'template-config'  => Hook::PARAM_SOURCE_FILE,
				'allowed-html'     => Hook::PARAM_STRING,
				'groups'           => Hook::PARAM_STRING,
				'autocomplete'     => Hook::PARAM_STRING,
				'access-levels'    => Hook::PARAM_STRING,
				'internal'         => Hook::PARAM_BOOL,
				'php'              => Hook::PARAM_BOOL,
				'tree'             => Hook::PARAM_BOOL,
				'deprecated'       => Hook::PARAM_BOOL,
				'todo'             => Hook::PARAM_BOOL,
				'source-code'      => Hook::PARAM_BOOL,
				'download'         => Hook::PARAM_BOOL,
				'report'           => Hook::PARAM_DOCS_FILE,
				'wipeout'          => Hook::PARAM_BOOL,
			) as $parameter => $type
		) {
			if (array_key_exists($parameter, $this->settings)) {
				$value = $this->settings[$parameter];
				switch ($type) {
					case Hook::PARAM_SOURCE_FILE:
						$value = $this->sourcesPath . '/' . ltrim($value, '/');
						break;
					case Hook::PARAM_DOCS_FILE:
						$value = $this->docsPath . '/' . ltrim($value, '/');
						break;
					case Hook::PARAM_STRING:
						// do nothing
						break;
					case Hook::PARAM_BOOL:
						$value = $value ? 'yes' : 'no';
						break;
					default:
						$this->logger->warning(sprintf('Parameter %s has an illegal type %s', $parameter, $type));
						// skip
						continue;
				}

				$args[] = '--' . $parameter;
				$args[] = $value;
			}
		}
		$args[] = '--source';
		$args[] = $this->sourcesPath . (array_key_exists('src-path', $this->settings) ? '/' . ltrim($this->settings['src-path'], '/') : '');
		$args[] = '--destination';
		$args[] = $this->docsPath . (array_key_exists('docs-path', $this->settings) ? '/' . ltrim($this->settings['docs-path'], '/') : '');
		$args   = array_map('escapeshellarg', $args);

		$cmd = 'php ' . implode(' ', $args);

		$process = new Process($cmd);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}
	}

	protected function pushDocs()
	{
		$this->logger->debug('Push docs');

		$process = new Process('git status -s', $this->docsPath);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}

		if ($process->getOutput()) {
			$process = new Process('git add .', $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = new Process('git commit -m ' . escapeshellarg($this->commitMessage), $this->docsPath);
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}

		$process = new Process('git push origin gh-pages', $this->docsPath);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}
	}
}
