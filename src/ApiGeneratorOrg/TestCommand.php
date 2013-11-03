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

use Guzzle\Http\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class TestCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('test')
			->setDescription('Local test the hook')
			->addOption('github', null, InputOption::VALUE_NONE, 'Use github as source')
			->addOption('local', null, InputOption::VALUE_REQUIRED, 'Use local path as source')
			->addArgument('owner', InputArgument::REQUIRED, 'Name of the owner')
			->addArgument('repository', InputArgument::REQUIRED, 'Name of the repository')
			->addArgument('branch', InputArgument::OPTIONAL, 'Branch to use', 'master');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$ownerName = $input->getArgument('owner');
		$repositoryName = $input->getArgument('repository');
		$branch = $input->getArgument('branch');

		$github = $input->getOption('github');
		$local  = $input->getOption('local');

		$repository = new Repository();
		$repository->setOwnerName($ownerName);
		$repository->setRepositoryName($repositoryName);
		$repository->setMasterBranch($branch);
		$repository->setCommitBranch($branch);

		if ($github) {
			$url = sprintf('https://api.github.com/repos/%s/%s/commits/%s', $ownerName, $repositoryName, $branch);

			$client = new Client();
			$request = $client->get($url);
			$response = $request->send();
			$json = $response->json();

			$repository->setCommitMessage($json['commit']['message']);

			$source = new GithubSource();
		}
		else if ($local) {
			$source = new LocalGitSource($local);

			$git = ProcessBuilder::create()
				->add('git')
				->add('show')
				->add('--format=%s')
				->add('-s')
				->setWorkingDirectory($source->getRepositoryUrl($repository))
				->getProcess();

			$git->run();
			if (!$git->isSuccessful()) {
				throw new \RuntimeException($git->getErrorOutput());
			}

			$repository->setCommitMessage(trim($git->getOutput()));
		}
		else {
			throw new \InvalidArgumentException('You must specify at least one source');
		}

		$hook = new Hook();
		$hook->run($repository, $source);
	}
}
