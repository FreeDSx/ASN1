<?php

declare(strict_types=1);

/**
 * Bench encoder throughput against representative payloads.
 *
 * Run with --help to see the full option list.
 */

use Symfony\Component\Console\Application;
use Tests\Performance\FreeDSx\Asn1\Bench\BenchCommand;

require __DIR__ . '/../../vendor/autoload.php';

$command = new BenchCommand();
$application = new Application('FreeDSx ASN1 bench');
$application->add($command);
$application->setDefaultCommand(
    (string) $command->getName(),
    true,
);
$application->run();
