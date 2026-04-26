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

use InvalidArgumentException;
use RuntimeException;
use Tests\Performance\FreeDSx\Asn1\Support\Cast;

/**
 * Parsed view of baseline-floor.json.
 */
final readonly class FloorReport
{
    /**
     * @param array<string, array<string, int>> $budget Workload => (op => max ns/payload).
     */
    public function __construct(
        public string $capturedOn,
        public string $runner,
        public int $marginPct,
        public array $budget,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Floor file not found: $path");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Failed to read floor file: $path");
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException("Malformed floor JSON: $path");
        }

        $rawMeta   = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $rawBudget = is_array($data['floor_ns_per_payload'] ?? null) ? $data['floor_ns_per_payload'] : [];

        /** @var array<string, array<string, int>> $budget */
        $budget = [];
        foreach ($rawBudget as $name => $ops) {
            if (!is_string($name) || !is_array($ops)) {
                continue;
            }
            $entry = [];
            foreach ($ops as $op => $ceiling) {
                if (is_string($op) && (is_int($ceiling) || is_float($ceiling))) {
                    $entry[$op] = (int) $ceiling;
                }
            }
            $budget[$name] = $entry;
        }

        return new self(
            capturedOn: Cast::toString($rawMeta['captured_on'] ?? null, '?'),
            runner: Cast::toString($rawMeta['runner'] ?? null, '?'),
            marginPct: Cast::toInt($rawMeta['margin_pct'] ?? null, 0),
            budget: $budget,
        );
    }

    public function totalBudgetedOps(): int
    {
        $n = 0;
        foreach ($this->budget as $ops) {
            $n += count($ops);
        }

        return $n;
    }
}
