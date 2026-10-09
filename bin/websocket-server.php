#!/usr/bin/env php
<?php

use App\Kernel;
use App\Service\RealtimeChatHandler;
use Symfony\Component\Dotenv\Dotenv;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

require_once dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

TcpConnection::$defaultMaxPackageSize = 8192;
$worker = new Worker($_SERVER['WEBSOCKET_ADDRESS'] ?? 'websocket://0.0.0.0:8082');
$worker->count = 1;
$kernel->getContainer()->get(RealtimeChatHandler::class)->register($worker);

Worker::runAll();
