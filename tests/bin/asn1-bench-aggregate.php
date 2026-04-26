<?php

declare(strict_types=1);

/**
 * Aggregate N paired baseline/head encoder bench reports into a single comparison
 * with median Δ%, per-pair range, and sign-consistency.
 *
 * Run with --help to see options.
 */

use Symfony\Component\Console\Application;
use Tests\Performance\FreeDSx\Asn1\Aggregate\AggregateCommand;

require __DIR__ . '/../../vendor/autoload.php';

$command = new AggregateCommand();
$application = new Application('FreeDSx ASN1 bench-aggregate');
$application->add($command);
$application->setDefaultCommand(
    (string) $command->getName(),
    true,
);
$application->run();
