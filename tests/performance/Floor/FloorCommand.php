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
use Tests\Performance\FreeDSx\Asn1\Report\Report;
use Tests\Performance\FreeDSx\Asn1\Support\Cast;
use Throwable;

/**
 * `composer bench-vs-floor` — runs the bench and fails if any workload/op
 * exceeds the per-operation ceiling recorded in tests/performance/baseline-floor.json.
 */
final class FloorCommand extends Command
{
    protected static $defaultName = 'bench-vs-floor';

    protected static $defaultDescription = 'Run the bench and check that no workload exceeds its perf budget.';

    protected function configure(): void
    {
        $this
            ->addOption(
                'floor',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the floor JSON',
                __DIR__ . '/../baseline-floor.json',
            )
            ->addOption(
                'samples',
                null,
                InputOption::VALUE_REQUIRED,
                'Bench samples per workload',
                '7',
            )
            ->addOption(
                'revs',
                null,
                InputOption::VALUE_REQUIRED,
                'Inner repetitions per sample',
                '3',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $floorPath = Cast::toString($input->getOption('floor'));
        $samples   = Cast::toInt($input->getOption('samples'), 7);
        $revs      = Cast::toInt($input->getOption('revs'), 3);

        try {
            $floor = FloorReport::fromFile($floorPath);
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Floor: %s   margin=%d%%   captured_on=%s   runner=%s',
            $floorPath,
            $floor->marginPct,
            $floor->capturedOn,
            $floor->runner,
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

        return $this->renderAndJudge(
            $floor,
            $report,
            $output,
        );
    }

    private function renderAndJudge(
        FloorReport $floor,
        Report $report,
        OutputInterface $output
    ): int {
        $output->writeln(sprintf(
            '%-22s %-11s %12s %12s   %s',
            'workload',
            'op',
            'measured',
            'floor',
            'verdict',
        ));
        $output->writeln(str_repeat('-', 80));

        $breaches = [];
        foreach ($floor->budget as $name => $ops) {
            $workload = $report->workloads[$name] ?? null;
            if ($workload === null) {
                $output->writeln(sprintf('%-22s (workload not run)', $name));
                continue;
            }
            foreach ($ops as $op => $ceilingNs) {
                $measured = $workload->op($op)->nsPerPayload;
                $exceeds  = $measured > $ceilingNs;
                $marker   = $exceeds ? '!! OVER' : '   ok';

                if ($exceeds) {
                    $breaches[] = sprintf(
                        '%s/%s: measured %.0f ns/payload > floor %d ns/payload (%+0.1f%%)',
                        $name,
                        $op,
                        $measured,
                        $ceilingNs,
                        ($measured - $ceilingNs) / $ceilingNs * 100.0,
                    );
                }

                $output->writeln(sprintf(
                    '%-22s %-11s %10.0f ns %10d ns   %s',
                    $name,
                    $op,
                    $measured,
                    $ceilingNs,
                    $marker,
                ));
            }
        }
        $output->writeln('');

        if ($breaches === []) {
            $output->writeln(sprintf(
                '<info>OK — all %d budgeted operations under their floor.</info>',
                $floor->totalBudgetedOps(),
            ));

            return Command::SUCCESS;
        }
        $output->writeln(sprintf(
            '<error>FLOOR BREACH — %d operation(s) exceeded their budget:</error>',
            count($breaches),
        ));
        foreach ($breaches as $line) {
            $output->writeln('  ' . $line);
        }

        return Command::FAILURE;
    }
}
