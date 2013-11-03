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

class Repository
{
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

	static public function createFromGithubPayload($payload)
	{
		$repository = new self();
		$repository->setOwnerName($payload->repository->owner->name);
		$repository->setRepositoryName($payload->repository->name);
		$repository->setMasterBranch($payload->repository->master_branch);
		$repository->setCommitBranch(str_replace('refs/heads/', '', $payload->ref));
		$repository->setCommitMessage($payload->head_commit->message);
		return $repository;
	}

	/**
	 * @param string $ownerName
	 */
	public function setOwnerName($ownerName)
	{
		$this->ownerName = $ownerName;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getOwnerName()
	{
		return $this->ownerName;
	}

	/**
	 * @param string $repositoryName
	 */
	public function setRepositoryName($repositoryName)
	{
		$this->repositoryName = $repositoryName;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRepositoryName()
	{
		return $this->repositoryName;
	}

	/**
	 * @param string $masterBranch
	 */
	public function setMasterBranch($masterBranch)
	{
		$this->masterBranch = $masterBranch;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMasterBranch()
	{
		return $this->masterBranch;
	}

	/**
	 * @param string $commitBranch
	 */
	public function setCommitBranch($commitBranch)
	{
		$this->commitBranch = $commitBranch;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCommitBranch()
	{
		return $this->commitBranch;
	}

	/**
	 * @param string $commitMessage
	 */
	public function setCommitMessage($commitMessage)
	{
		$this->commitMessage = $commitMessage;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCommitMessage()
	{
		return $this->commitMessage;
	}

	/**
	 * @return string
	 */
	public function getRepository()
	{
		return $this->ownerName . '/' . $this->repositoryName;
	}

	public function getSourcesPath()
	{
		return sprintf(
			'%s/sources/%s/%s/',
			dirname(dirname(__DIR__)),
			$this->ownerName,
			$this->repositoryName
		);
	}

	public function getDocsPath()
	{
		return sprintf(
			'%s/docs/%s/%s/',
			dirname(dirname(__DIR__)),
			$this->ownerName,
			$this->repositoryName
		);
	}
}
