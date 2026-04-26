<?php

declare(strict_types=1);

/**
 * Runs the encoder bench and fails if any workload exceeds its budgeted floor
 * recorded in tests/performance/baseline-floor.json.
 *
 * Run with --help to see options.
 */

use Symfony\Component\Console\Application;
use Tests\Performance\FreeDSx\Asn1\Floor\FloorCommand;

require __DIR__ . '/../../vendor/autoload.php';

$command = new FloorCommand();
$application = new Application('FreeDSx ASN1 bench-vs-floor');
$application->add($command);
$application->setDefaultCommand(
    (string) $command->getName(),
    true,
);
$application->run();
