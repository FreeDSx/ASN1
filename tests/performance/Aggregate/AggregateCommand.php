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

namespace Tests\Performance\FreeDSx\Asn1\Aggregate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Performance\FreeDSx\Asn1\Report\Report;
use Tests\Performance\FreeDSx\Asn1\Support\Cast;
use Throwable;

/**
 * Symfony Console entry point for `composer bench-aggregate`.
 *
 * Both --baseline and --head are repeatable; they pair up positionally.
 *
 * Example: --baseline=base1.json --baseline=base2.json --head=head1.json --head=head2.json
 */
final class AggregateCommand extends Command
{
    protected static $defaultName = 'bench-aggregate';

    protected static $defaultDescription = 'Aggregate N paired bench reports with median Δ, range, and sign-consistency.';

    protected function configure(): void
    {
        $this
            ->addOption(
                'baseline',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Baseline JSON path (repeat for each pair)',
            )
            ->addOption(
                'head',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Head JSON path (repeat for each pair)',
            )
            ->addOption(
                'threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'Flag |median Δ%| ≥ this value as significant',
                '3.0',
            )
            ->addOption(
                'consistency',
                null,
                InputOption::VALUE_REQUIRED,
                'Required percent of pairs to agree with median direction',
                '80',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baselinePaths = Cast::toStringList($input->getOption('baseline'));
        $headPaths = Cast::toStringList($input->getOption('head'));
        $threshold = Cast::toFloat($input->getOption('threshold'), 3.0);
        $consistencyPc = Cast::toFloat($input->getOption('consistency'), 80.0);

        if ($baselinePaths === [] || $headPaths === [] || count($baselinePaths) !== count($headPaths)) {
            $output->writeln('<error>--baseline and --head must each be specified the same non-zero number of times.</error>');

            return Command::INVALID;
        }

        try {
            $baselineReports = array_map([Report::class, 'fromFile'], $baselinePaths);
            $headReports = array_map([Report::class, 'fromFile'], $headPaths);
            $rows = (new Aggregator())->aggregate($baselineReports, $headReports);
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $pairs = count($baselinePaths);
        $firstMeta = $baselineReports[0]->meta;

        $output->writeln(sprintf('Pairs:    %d', $pairs));
        $output->writeln(sprintf(
            'Baseline: %d files (PHP %s, opcache=%s, jit=%s, assertions=%d)',
            $pairs,
            Cast::toString($firstMeta['php'] ?? null, '?'),
            $this->boolStr($firstMeta['opcache_enabled'] ?? null),
            Cast::toString($firstMeta['jit'] ?? null, '?'),
            Cast::toInt($firstMeta['assertions'] ?? null, -1),
        ));
        $output->writeln(sprintf('Head:     %d files', $pairs));
        $output->writeln(sprintf(
            "Threshold: ±%.1f%%   Consistency required: %.0f%%\n",
            $threshold,
            $consistencyPc,
        ));

        $output->writeln(sprintf(
            '%-22s %-11s %9s   %-18s   %-12s  %s',
            'workload',
            'op',
            'med Δ%',
            'range Δ% (per-pair)',
            'consistency',
            'significant',
        ));
        $output->writeln(str_repeat('-', 100));

        $regressions = [];
        $improvements = [];
        foreach ($rows as $row) {
            $isRegression = $row->isRegression($threshold, $consistencyPc);
            $isImprovement = $row->isImprovement($threshold, $consistencyPc);
            if ($isRegression) {
                $regressions[] = sprintf(
                    '%s/%s: median +%s%% (%d/%d consistent)',
                    $row->workload,
                    $row->op,
                    number_format($row->medianDeltaPct, 1),
                    $row->sameDirectionCount,
                    $row->totalPairs,
                );
            } elseif ($isImprovement) {
                $improvements[] = sprintf(
                    '%s/%s: median %s%% (%d/%d consistent)',
                    $row->workload,
                    $row->op,
                    number_format($row->medianDeltaPct, 1),
                    $row->sameDirectionCount,
                    $row->totalPairs,
                );
            }

            $marker = '   ';
            if ($isRegression) {
                $marker = '!! ';
            } elseif ($isImprovement) {
                $marker = ' + ';
            }

            $output->writeln(sprintf(
                '%-22s %-11s %+8.1f%%  [%+5.1f, %+5.1f]   %2d/%-2d (%3.0f%%)   %s',
                $row->workload,
                $row->op,
                $row->medianDeltaPct,
                $row->minDeltaPct,
                $row->maxDeltaPct,
                $row->sameDirectionCount,
                $row->totalPairs,
                $row->consistencyPct(),
                $marker . ($isRegression ? 'REGRESSION' : ($isImprovement ? 'improvement' : '')),
            ));
        }
        $output->writeln('');

        if ($improvements !== []) {
            $output->writeln('Improvements:');
            foreach ($improvements as $line) {
                $output->writeln('  ' . $line);
            }
            $output->writeln('');
        }

        if ($regressions === []) {
            $output->writeln(sprintf(
                'OK — no significant regressions (threshold ±%.1f%%, consistency ≥ %.0f%%)',
                $threshold,
                $consistencyPc,
            ));

            return Command::SUCCESS;
        }
        $output->writeln(sprintf(
            'REGRESSION — %d workload/op pair(s) crossed the threshold:',
            count($regressions),
        ));
        foreach ($regressions as $line) {
            $output->writeln('  ' . $line);
        }

        return Command::FAILURE;
    }

    private function boolStr(mixed $v): string
    {
        if ($v === true) {
            return 'on';
        }
        if ($v === false) {
            return 'off';
        }

        return '?';
    }
}
