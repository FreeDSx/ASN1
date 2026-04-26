<?php

declare(strict_types=1);

/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Performance\FreeDSx\Asn1\Bench;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Performance\FreeDSx\Asn1\Support\Cast;
use Throwable;

/**
 * Symfony Console entry point for `composer bench`.
 */
final class BenchCommand extends Command
{
    protected static $defaultName = 'bench';

    protected static $defaultDescription = 'Run encoder benchmarks against representative payloads.';

    protected function configure(): void
    {
        $this
            ->addOption('encoder', null, InputOption::VALUE_REQUIRED, 'Encoder under test: ber | der', 'ber')
            ->addOption('workload', null, InputOption::VALUE_REQUIRED, 'Run a single workload only (default: all)')
            ->addOption('warmup', null, InputOption::VALUE_REQUIRED, 'Warmup iterations discarded', '2')
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, 'Timed samples taken', '7')
            ->addOption('revs', null, InputOption::VALUE_REQUIRED, 'Inner repetitions per sample', '3')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON to stdout (machine-readable)')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Write JSON report to PATH');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $opts = new BenchOptions(
            encoder: Cast::toString($input->getOption('encoder'), 'ber'),
            workload: Cast::toStringOrNull($input->getOption('workload')),
            warmup: Cast::toInt($input->getOption('warmup'), 2),
            samples: Cast::toInt($input->getOption('samples'), 7),
            revs: Cast::toInt($input->getOption('revs'), 3),
        );

        $emitJson = Cast::toBool($input->getOption('json'));
        $outPath = Cast::toStringOrNull($input->getOption('out'));

        $progress = $emitJson
            ? null
            : static function (string $line) use ($output): void {
                $output->writeln($line);
            };

        try {
            $report = (new Benchmarker())->run($opts, $progress);
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        if ($outPath !== null) {
            $report->writeTo($outPath);
            if (!$emitJson) {
                $output->writeln(sprintf('wrote %s', $outPath));
            }
        }
        if ($emitJson) {
            $output->write($report->toJson());
        }

        return Command::SUCCESS;
    }
}
