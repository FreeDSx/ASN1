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

namespace Tests\Performance\FreeDSx\Asn1\Compare;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Performance\FreeDSx\Asn1\Report\Report;
use Tests\Performance\FreeDSx\Asn1\Support\Cast;
use Throwable;

/**
 * Symfony Console entry point for `composer bench-compare`.
 */
final class CompareCommand extends Command
{
    protected static $defaultName = 'bench-compare';

    protected static $defaultDescription = 'Diff two single-pair bench reports.';

    protected function configure(): void
    {
        $this
            ->addArgument('baseline', InputArgument::REQUIRED, 'Path to baseline report JSON')
            ->addArgument('head', InputArgument::REQUIRED, 'Path to head report JSON')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Flag changes greater than ±PCT', '3.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = Cast::toFloat($input->getOption('threshold'), 3.0);
        $baselinePath = Cast::toString($input->getArgument('baseline'));
        $headPath = Cast::toString($input->getArgument('head'));

        try {
            $baseline = Report::fromFile($baselinePath);
            $head = Report::fromFile($headPath);
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Baseline: %s  (PHP %s, opcache=%s, jit=%s, assertions=%d)',
            $baselinePath,
            Cast::toString($baseline->meta['php'] ?? null, '?'),
            $this->boolStr($baseline->meta['opcache_enabled'] ?? null),
            Cast::toString($baseline->meta['jit'] ?? null, '?'),
            Cast::toInt($baseline->meta['assertions'] ?? null, -1),
        ));
        $output->writeln(sprintf(
            "Head:     %s  (PHP %s, opcache=%s, jit=%s, assertions=%d)\n",
            $headPath,
            Cast::toString($head->meta['php'] ?? null, '?'),
            $this->boolStr($head->meta['opcache_enabled'] ?? null),
            Cast::toString($head->meta['jit'] ?? null, '?'),
            Cast::toInt($head->meta['assertions'] ?? null, -1),
        ));

        $comparator = new Comparator();
        $rows = $comparator->compare($baseline, $head);

        $output->writeln(sprintf(
            "%-22s  %-25s  %-25s  %-25s",
            'workload',
            'encode (Δ%)',
            'decode (Δ%)',
            'round-trip (Δ%)',
        ));
        $output->writeln(str_repeat('-', 100));

        foreach ($rows as $row) {
            if (!$row->isComplete()) {
                $output->writeln(sprintf('%-22s  (missing on one side)', $row->workload));
                continue;
            }
            $output->writeln(sprintf(
                '%-22s  %-25s  %-25s  %-25s',
                $row->workload,
                $this->fmtCell($row, 'encode', $threshold),
                $this->fmtCell($row, 'decode', $threshold),
                $this->fmtCell($row, 'round_trip', $threshold),
            ));
        }
        $output->writeln('');

        $regressions = $comparator->regressions($rows, $threshold);
        $worst = $comparator->worstAbsoluteDelta($rows);

        if ($regressions === []) {
            $output->writeln(sprintf(
                'OK — no regression beyond ±%.1f%% (worst |Δ| = %.1f%%)',
                $threshold,
                $worst,
            ));

            return Command::SUCCESS;
        }
        $output->writeln(sprintf(
            'REGRESSION — %d workload/op pairs exceeded +%.1f%%:',
            count($regressions),
            $threshold,
        ));
        foreach ($regressions as $line) {
            $output->writeln('  ' . $line);
        }

        return Command::FAILURE;
    }

    private function fmtCell(ComparisonRow $row, string $op, float $threshold): string
    {
        $delta = $row->deltaPctFor($op);
        $marker = ' ';
        if ($delta > $threshold) {
            $marker = '!';
        } elseif ($delta < -$threshold) {
            $marker = '+';
        }

        return sprintf(
            '%6.0f→%6.0f ns %+5.1f%% %s',
            $row->baselineNsFor($op),
            $row->headNsFor($op),
            $delta,
            $marker,
        );
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
