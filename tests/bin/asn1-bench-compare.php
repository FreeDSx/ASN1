<?php

declare(strict_types=1);

/**
 * Diff two single-pair encoder bench reports.
 *
 * Run with --help to see options.
 */

use Symfony\Component\Console\Application;
use Tests\Performance\FreeDSx\Asn1\Compare\CompareCommand;

require __DIR__ . '/../../vendor/autoload.php';

$command = new CompareCommand();
$application = new Application('FreeDSx ASN1 bench-compare');
$application->add($command);
$application->setDefaultCommand(
    (string) $command->getName(),
    true,
);
$application->run();
