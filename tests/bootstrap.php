<?php

require __DIR__ . '/../vendor/autoload.php';

// Load .env file for integration tests (DB2 connection vars)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
