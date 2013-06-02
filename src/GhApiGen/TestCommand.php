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

use Guzzle\Http\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('test')
			->setDescription('Local test the hook')
			->addArgument('owner', InputArgument::REQUIRED, 'Name of the owner')
			->addArgument('repository', InputArgument::REQUIRED, 'Name of the repository')
			->addArgument('branch', InputArgument::OPTIONAL, 'Branch to use', 'master');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$ownerName = $input->getArgument('owner');
		$repositoryName = $input->getArgument('repository');
		$branch = $input->getArgument('branch');

		$url = sprintf('https://api.github.com/repos/%s/%s/commits/%s', $ownerName, $repositoryName, $branch);

		$client = new Client();
		$request = $client->get($url);
		$response = $request->send();
		$json = $response->json();

		$hook = new Hook();
		$hook->run(
			$ownerName,
			$repositoryName,
			$branch,
			$branch,
			$json['commit']['message']
		);
	}
}
