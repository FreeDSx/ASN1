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

namespace Tests\Performance\FreeDSx\Asn1\Floor;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Performance\FreeDSx\Asn1\Bench\Benchmarker;
use Tests\Performance\FreeDSx\Asn1\Bench\BenchOptions;
use Tests\Performance\FreeDSx\Asn1\Support\Cast;
use Throwable;

/**
 * `composer bench-update-floor` — runs the bench, applies a margin to each
 * measurement, and writes a new tests/performance/baseline-floor.json.
 *
 * Run this on the same kind of machine the CI floor job uses (ubuntu-latest)
 * after intentional perf changes; review the diff and commit.
 */
final class UpdateFloorCommand extends Command
{
    /**
     * Workloads to omit from the floor (synthetic / too-noisy to budget).
     *
     * @var list<string>
     */
    private const EXCLUDED_WORKLOADS = ['primitives_baseline'];

    protected static $defaultName = 'bench-update-floor';

    protected static $defaultDescription = 'Capture current bench numbers + margin as the new perf floor.';

    protected function configure(): void
    {
        $this
            ->addOption(
                'out',
                null,
                InputOption::VALUE_REQUIRED,
                'Output JSON path',
                __DIR__ . '/../baseline-floor.json',
            )
            ->addOption(
                'margin',
                null,
                InputOption::VALUE_REQUIRED,
                'Headroom percent above measured (default 20)',
                '20',
            )
            ->addOption(
                'samples',
                null,
                InputOption::VALUE_REQUIRED,
                'Bench samples per workload (more = stabler)',
                '15',
            )
            ->addOption(
                'revs',
                null,
                InputOption::VALUE_REQUIRED,
                'Inner repetitions per sample',
                '5',
            )
            ->addOption(
                'runner',
                null,
                InputOption::VALUE_REQUIRED,
                'Runner label to record in meta',
                getenv('RUNNER_OS') !== false ? strtolower((string) getenv('RUNNER_OS')) : 'local',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outPath  = Cast::toString($input->getOption('out'));
        $margin   = Cast::toInt($input->getOption('margin'), 20);
        $samples  = Cast::toInt($input->getOption('samples'), 15);
        $revs     = Cast::toInt($input->getOption('revs'), 5);
        $runner   = Cast::toString($input->getOption('runner'), 'local');

        $output->writeln(sprintf(
            'Capturing floor: samples=%d revs=%d margin=%d%% runner=%s',
            $samples,
            $revs,
            $margin,
            $runner,
        ));
        $output->writeln('');

        try {
            $report = (new Benchmarker())->run(new BenchOptions(
                samples: $samples,
                revs: $revs,
            ));
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Bench failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $multiplier = 1.0 + ($margin / 100.0);

        /** @var array<string, array<string, int>> $budget */
        $budget = [];
        foreach ($report->workloads as $name => $workload) {
            if (in_array($name, self::EXCLUDED_WORKLOADS, true)) {
                continue;
            }
            $budget[$name] = [
                'encode'     => (int) ceil($workload->encode->nsPerPayload * $multiplier),
                'decode'     => (int) ceil($workload->decode->nsPerPayload * $multiplier),
                'round_trip' => (int) ceil($workload->roundTrip->nsPerPayload * $multiplier),
            ];
        }

        $payload = [
            'meta' => [
                'captured_on' => date('Y-m-d'),
                'runner'      => $runner,
                'php'         => PHP_VERSION,
                'margin_pct'  => $margin,
                'note'        => sprintf(
                    'Captured %s with samples=%d revs=%d. Each value = measured ns/payload * %.2f.',
                    date('c'),
                    $samples,
                    $revs,
                    $multiplier,
                ),
            ],
            'floor_ns_per_payload' => $budget,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $output->writeln('<error>Failed to encode floor JSON.</error>');

            return Command::FAILURE;
        }
        if (file_put_contents($outPath, $json . "\n") === false) {
            $output->writeln(sprintf('<error>Failed to write floor file: %s</error>', $outPath));

            return Command::FAILURE;
        }

        $output->writeln('Captured numbers (with margin applied):');
        foreach ($budget as $name => $ops) {
            $output->writeln(sprintf(
                '  %-22s encode=%d  decode=%d  round_trip=%d',
                $name,
                $ops['encode'],
                $ops['decode'],
                $ops['round_trip'],
            ));
        }
        $output->writeln('');
        $output->writeln(sprintf('<info>Wrote %s — review the diff and commit.</info>', $outPath));

        return Command::SUCCESS;
    }
}
