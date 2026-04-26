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
use Tests\Performance\FreeDSx\Asn1\Support\Cast;

/**
 * Bench results for one workload (encode + decode + round_trip).
 */
final readonly class WorkloadResult
{
    public function __construct(
        public int      $payloads,
        public int      $encodedBytes,
        public OpResult $encode,
        public OpResult $decode,
        public OpResult $roundTrip,
    ) {
    }

    /**
     * @return array{payloads: int, encoded_bytes: int, encode: array<string, mixed>, decode: array<string, mixed>, round_trip: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'payloads' => $this->payloads,
            'encoded_bytes' => $this->encodedBytes,
            'encode' => $this->encode->toArray(),
            'decode' => $this->decode->toArray(),
            'round_trip' => $this->roundTrip->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            payloads: Cast::toInt($raw['payloads'] ?? 0),
            encodedBytes: Cast::toInt($raw['encoded_bytes'] ?? 0),
            encode: OpResult::fromArray(self::stringKeyedArray($raw['encode'] ?? null)),
            decode: OpResult::fromArray(self::stringKeyedArray($raw['decode'] ?? null)),
            roundTrip: OpResult::fromArray(self::stringKeyedArray($raw['round_trip'] ?? null)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $out[$key] = $entry;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function operations(): array
    {
        return ['encode', 'decode', 'round_trip'];
    }

    public function op(string $name): OpResult
    {
        return match ($name) {
            'encode' => $this->encode,
            'decode' => $this->decode,
            'round_trip' => $this->roundTrip,
            default => throw new InvalidArgumentException("Unknown operation: $name"),
        };
    }
}
