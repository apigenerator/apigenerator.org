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
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractGenerator
{
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
	protected $defaultSettings;

	/**
	 * @var array
	 */
	protected $settings;

	function __construct()
	{
		$this->handler = new RotatingFileHandler(dirname(dirname(__DIR__)) . '/log/hook.log', 7);
		$this->logger  = new Logger('*/*', array($this->handler));

		$this->fs = new Filesystem();
	}


	public function run(Repository $repository, GitSource $source)
	{
		$this->logger = new Logger($repository->getRepository(), array($this->handler));

		try {
			$docsRepository = null;

			$this->initSourcePath($repository, $source);
			$this->checkoutSource($repository, $source);
			$this->buildDefaultSettings($repository, $source);
			$this->buildSettings($repository, $docsRepository, $source);

			$this->checkBranch($repository, $docsRepository, $source);

			$this->initDocsPath($repository, $docsRepository, $source);
			$this->prepareDocs($repository, $docsRepository, $source);
			$this->generateDocs($repository, $docsRepository, $source);
			$this->pushDocs($repository, $docsRepository, $source);

			$this->updateHistory($repository, $docsRepository, $source);
		}
		catch (\Exception $exception) {
			$this->logger->addCritical($exception->getMessage() . "\n" . $exception->getTraceAsString());
		}
	}

	protected function initSourcePath(Repository $repository, GitSource $source)
	{
		$this->logger->debug(sprintf('Init sources directory %s', $repository->getSourcesPath()));

		if (!$this->fs->exists($repository->getSourcesPath())) {
			$this->fs->mkdir($repository->getSourcesPath());
		}
	}

	protected function checkoutSource(Repository $repository, GitSource $source)
	{
		$url = $source->getRepositoryUrl($repository);

		if ($this->fs->exists($repository->getSourcesPath() . '.git')) {
			$this->logger->debug(sprintf('Update sources %s', $repository->getSourcesPath()));

			$process = ProcessBuilder::create(array('git', 'remote', 'set-url', 'origin', $url))
				->setWorkingDirectory($repository->getSourcesPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = ProcessBuilder::create(array('git', 'fetch', 'origin'))
				->setWorkingDirectory($repository->getSourcesPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = ProcessBuilder::create(array('git', 'reset', '--hard', 'origin/' . $repository->getCommitBranch()))
				->setWorkingDirectory($repository->getSourcesPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else {
			$this->logger->debug(sprintf('Checkout source %s', $url));

			$process = ProcessBuilder::create(array('git', 'clone', '-b', $repository->getCommitBranch(), $url, $repository->getSourcesPath()))
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
	}

	protected function buildDefaultSettings(Repository $repository, GitSource $source)
	{
		$class = new \ReflectionClass($this);
		$className = strtolower($class->getShortName());
		$filename = $className . '.yml';

		if (file_exists(dirname(dirname(__DIR__)) . '/config/' . $filename)) {
			$this->defaultSettings = Yaml::parse(dirname(dirname(__DIR__)) . '/config/' . $filename);
		}
		else {
			$this->defaultSettings = array();
		}

		$this->defaultSettings['docs-branch'] = 'gh-pages';
	}

	protected function buildSettings(Repository $repository, Repository &$docsRepository = null, GitSource $source)
	{
		$class = new \ReflectionClass($this);
		$className = strtolower($class->getShortName());
		$filename = $className . '.yml';

		if (!file_exists($repository->getSourcesPath() . '/' . $filename)) {
			throw new \RuntimeException($filename . ' is missing, skip');
		}

		$this->settings = Yaml::parse($repository->getSourcesPath() . '/' . $filename);

		if ($this->settings === null) {
			$this->settings = array();
		}

		// use the master branch
		if (!empty($this->settings['branch'])) {
			$this->settings['src-branch'] = $this->settings['branch'];
		}
		else if (empty($this->settings['branch'])) {
			$this->settings['src-branch'] = $repository->getMasterBranch();
		}

		# merge with defaults
		$this->settings = array_merge(
			$this->defaultSettings,
			$this->settings
		);

		if (isset($this->settings['docs-repository'])) {
			list($ownerName, $repositoryName) = explode('/', $this->settings['docs-repository']);
			$docsRepository = clone $repository;
			$docsRepository->setOwnerName($ownerName);
			$docsRepository->setRepositoryName($repositoryName);
		}
		else {
			$docsRepository = $repository;
		}

		# build default base url
		if (!array_key_exists('base-url', $this->defaultSettings)) {
			$this->settings['base-url'] = $source->getPagesUrl($docsRepository);
		}

		# set default title
		if (!array_key_exists('title', $this->defaultSettings)) {
			$this->settings['title'] = $repository->getRepository();
		}

		$this->logger->debug(
			sprintf('Build settings for %s/%s', $repository->getOwnerName(), $repository->getRepositoryName()),
			$this->settings
		);
	}

	protected function checkBranch(Repository $repository, Repository $docsRepository, GitSource $source)
	{
		if ($this->settings['src-branch'] != $repository->getCommitBranch()) {
			throw new \RuntimeException(
				'Skip branch ' . $repository->getCommitBranch() . ', expect branch ' . $this->settings['src-branch']
			);
		}
	}

	protected function initDocsPath(Repository $repository, Repository $docsRepository, GitSource $source)
	{
		$this->logger->debug(sprintf('Init docs directory %s', $docsRepository->getDocsPath()));

		if (!$this->fs->exists($docsRepository->getDocsPath())) {
			$this->fs->mkdir($docsRepository->getDocsPath());
		}
	}

	protected function prepareDocs(Repository $repository, Repository $docsRepository, GitSource $source)
	{
		$url = $source->getRepositoryUrl($docsRepository);

		if ($this->fs->exists($docsRepository->getDocsPath() . '.git')) {
			$process = ProcessBuilder::create(array('git', 'remote', 'set-url', 'origin', $url))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else {
			$process = ProcessBuilder::create(array('git', 'init', $docsRepository->getDocsPath()))
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = ProcessBuilder::create(array('git', 'remote', 'add', 'origin', $url))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}

		$process = ProcessBuilder::create(array('git', 'fetch', 'origin'))
			->setWorkingDirectory($docsRepository->getDocsPath())
			->getProcess();
		$this->logger->debug('exec ' . $process->getCommandLine());
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}

		$process = ProcessBuilder::create(array('git', 'branch', '-a'))
			->setWorkingDirectory($docsRepository->getDocsPath())
			->getProcess();
		$this->logger->debug('exec ' . $process->getCommandLine());
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

		if (in_array('remotes/origin/' . $this->settings['docs-branch'], $branches)) {
			$this->logger->debug(sprintf('Update docs %s', $url));

			$process = ProcessBuilder::create(array('git', 'checkout', '-f', '-B', $this->settings['docs-branch']))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = ProcessBuilder::create(array('git', 'reset', '--hard', 'origin/' . $this->settings['docs-branch']))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else if (!in_array($this->settings['docs-branch'], $branches)) {
			$this->logger->debug(sprintf('Initialise empty docs %s', $url));

			$process = ProcessBuilder::create(array('git', 'checkout', '--orphan', $this->settings['docs-branch']))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
		else {
			$this->logger->debug(sprintf('Reuse local docs branch %s', $url));

			$process = ProcessBuilder::create(array('git', 'checkout', '-Bf', $this->settings['docs-branch']))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}
	}

	abstract protected function generateDocs(Repository $repository, Repository $docsRepository, GitSource $source);

	protected function pushDocs(Repository $repository, Repository $docsRepository, GitSource $source)
	{
		$this->logger->debug('Push docs');

		$process = ProcessBuilder::create(array('git', 'status', '-s'))
			->setWorkingDirectory($docsRepository->getDocsPath())
			->getProcess();
		$this->logger->debug('exec ' . $process->getCommandLine());
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}

		if ($process->getOutput()) {
			$process = ProcessBuilder::create(array('git', 'add', '.'))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}

			$process = ProcessBuilder::create(array('git', 'commit', '-m', $repository->getCommitMessage()))
				->setWorkingDirectory($docsRepository->getDocsPath())
				->getProcess();
			$this->logger->debug('exec ' . $process->getCommandLine());
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
			}
		}

		$process = ProcessBuilder::create(array('git', 'push', 'origin', $this->settings['docs-branch']))
			->setWorkingDirectory($docsRepository->getDocsPath())
			->getProcess();
		$this->logger->debug('exec ' . $process->getCommandLine());
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput());
		}
	}

	protected function updateHistory(Repository $repository, Repository $docsRepository, GitSource $source)
	{
		if (array_key_exists('promoted', $this->settings) && $this->settings['promoted'] === false) {
			return;
		}

		$pathname = dirname(dirname(__DIR__)) . '/web/history.html';
		$file = fopen($pathname, file_exists($pathname) ? 'r+' : 'w');
		flock($file, LOCK_EX);

		$history = stream_get_contents($file);
		$lines = explode("\n", $history);
		$lines = array_map('trim', $lines);
		$lines = array_filter($lines);

		$class = new \ReflectionClass($this);
		$className = strtolower($class->getShortName());

		array_unshift(
			$lines,
			sprintf(
				'<tr><td>%s</td><td>%s</td><td><a href="%s" target="_blank">%s</a></td></tr>',
				date('Y-m-d H:i:s'),
				$className,
				$source->getPagesUrl($docsRepository),
				$repository->getRepository()
			)
		);

		while (count($lines) > 15) {
			array_pop($lines);
		}

		$history = implode("\n", $lines);
		ftruncate($file, 0);
		fwrite($file, $history);

		fflush($file);
		flock($file, LOCK_UN);
		fclose($file);
	}
}
