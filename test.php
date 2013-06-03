<?php

require_once 'vendor/autoload.php';

$foo = \Symfony\Component\Yaml\Yaml::parse('~');
var_dump($foo);
