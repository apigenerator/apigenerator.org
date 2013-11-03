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

class Phpdoc extends AbstractGenerator
{
	const PARAM_SOURCE_FILE = 'source-file';

	const PARAM_DOCS_FILE = 'docs-file';

	const PARAM_STRING = 'string';

	const PARAM_BOOL = 'bool';

	protected function generateDocs(Repository $repository, Repository $docsRepository, GitSource $source)
	{
		$this->logger->debug('Generate docs');

		$args = array(dirname(dirname(__DIR__)) . '/vendor/bin/phpdoc.php');
		foreach (
			array(
				'config'             => static::PARAM_SOURCE_FILE,
				'extensions'         => static::PARAM_STRING,
				'ignore'             => static::PARAM_STRING,
				'ignore-tags'        => static::PARAM_STRING,
				'encoding'           => static::PARAM_STRING,
				'title'              => static::PARAM_STRING,
				'defaultpackagename' => static::PARAM_STRING,
				'template'           => static::PARAM_STRING,
				'hidden'             => static::PARAM_BOOL,
				'ignore-symlinks'    => static::PARAM_BOOL,
				'visibility'         => static::PARAM_STRING,
				'sourcecode'         => static::PARAM_BOOL,
				'parseprivate'       => static::PARAM_BOOL,
			) as $parameter => $type
		) {
			if (array_key_exists($parameter, $this->settings)) {
				$value = $this->settings[$parameter];
				switch ($type) {
					case static::PARAM_SOURCE_FILE:
						$value = $repository->getSourcesPath() . '/' . ltrim($value, '/');
						break;
					case static::PARAM_DOCS_FILE:
						$value = $repository->getDocsPath() . '/' . ltrim($value, '/');
						break;
					case static::PARAM_STRING:
						// do nothing
						break;
					case static::PARAM_BOOL:
						if ($value) {
							$args[] = '--' . $parameter;
						}
						continue 2;
					default:
						$this->logger->warning(sprintf('Parameter %s has an illegal type %s', $parameter, $type));
						// skip
						continue 2;
				}

				$args[] = '--' . $parameter . '=' . $value;
			}
		}
		$args[] = '--directory';
		$args[] = $repository->getSourcesPath() . (array_key_exists('src-path', $this->settings) ? '/' . ltrim($this->settings['src-path'], '/') : '');
		$args[] = '--target';
		$args[] = $docsRepository->getDocsPath() . (array_key_exists('docs-path', $this->settings) ? '/' . ltrim($this->settings['docs-path'], '/') : '');
		$args[] = '--force';
		$args[] = '--no-interaction';

		$process = ProcessBuilder::create($args)->getProcess();
		$this->logger->debug('exec ' . $process->getCommandLine());
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput() ?: $process->getOutput());
		}
	}
}
