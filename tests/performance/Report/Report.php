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

namespace Tests\Performance\FreeDSx\Asn1\Report;

use InvalidArgumentException;
use RuntimeException;

/**
 * One bench run's full output: meta block + per-workload results.
 */
final readonly class Report
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, WorkloadResult> $workloads
     */
    public function __construct(
        public array $meta,
        public array $workloads,
    ) {
    }

    public function toJson(): string
    {
        $payload = [
            'meta' => $this->meta,
            'workloads' => array_map(static fn (WorkloadResult $w): array => $w->toArray(), $this->workloads),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode bench report as JSON.');
        }

        return $json . "\n";
    }

    public function writeTo(string $path): void
    {
        if (file_put_contents($path, $this->toJson()) === false) {
            throw new RuntimeException("Failed to write bench report to: $path");
        }
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Report not found: $path");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Failed to read report: $path");
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['workloads']) || !is_array($data['workloads'])) {
            throw new InvalidArgumentException("Malformed report (missing 'workloads' object): $path");
        }

        $rawMeta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        /** @var array<string, mixed> $meta */
        $meta = [];
        foreach ($rawMeta as $key => $value) {
            if (is_string($key)) {
                $meta[$key] = $value;
            }
        }

        $workloads = [];
        foreach ($data['workloads'] as $name => $entry) {
            if (!is_string($name) || !is_array($entry)) {
                continue;
            }
            /** @var array<string, mixed> $stringKeyed */
            $stringKeyed = [];
            foreach ($entry as $k => $v) {
                if (is_string($k)) {
                    $stringKeyed[$k] = $v;
                }
            }
            $workloads[$name] = WorkloadResult::fromArray($stringKeyed);
        }

        return new self(
            meta: $meta,
            workloads: $workloads,
        );
    }
}
