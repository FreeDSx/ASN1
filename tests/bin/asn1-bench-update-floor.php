<?php

declare(strict_types=1);

/**
 * Captures current bench numbers + margin and writes a new perf floor JSON.
 *
 * Run this on the same hardware/runner the CI floor job uses, then commit the
 * resulting tests/performance/baseline-floor.json after reviewing the diff.
 *
 * Run with --help to see options.
 */

use Symfony\Component\Console\Application;
use Tests\Performance\FreeDSx\Asn1\Floor\UpdateFloorCommand;

require __DIR__ . '/../../vendor/autoload.php';

$command = new UpdateFloorCommand();
$application = new Application('FreeDSx ASN1 bench-update-floor');
$application->add($command);
$application->setDefaultCommand(
    (string) $command->getName(),
    true,
);
$application->run();
