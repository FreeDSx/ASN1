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

use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Encoder\DerEncoder;
use FreeDSx\Asn1\Encoder\EncoderInterface;
use FreeDSx\Asn1\Type\AbstractType;
use InvalidArgumentException;
use Tests\Performance\FreeDSx\Asn1\Report\OpResult;
use Tests\Performance\FreeDSx\Asn1\Report\Report;
use Tests\Performance\FreeDSx\Asn1\Report\WorkloadResult;
use Tests\Performance\FreeDSx\Asn1\Workload;

/**
 * Runs encode / decode / round-trip timings against a Workload corpus and
 * produces a Report. Stateless apart from the EncoderInterface it constructs.
 */
final class Benchmarker
{
    /**
     * LDAP-style application-class tag map (mirrors freedsx/ldap LdapEncoder).
     * Workloads exercising application-tagged payloads use this so decode
     * unfolds the inner structure rather than leaving an IncompleteType.
     *
     * @var array<int, array<int, int>>
     */
    private const LDAP_TAG_MAP = [
        AbstractType::TAG_CLASS_APPLICATION => [
            0 => AbstractType::TAG_TYPE_SEQUENCE,
            1 => AbstractType::TAG_TYPE_SEQUENCE,
            2 => AbstractType::TAG_TYPE_NULL,
            3 => AbstractType::TAG_TYPE_SEQUENCE,
            4 => AbstractType::TAG_TYPE_SEQUENCE,
            5 => AbstractType::TAG_TYPE_SEQUENCE,
            6 => AbstractType::TAG_TYPE_SEQUENCE,
            7 => AbstractType::TAG_TYPE_SEQUENCE,
            8 => AbstractType::TAG_TYPE_SEQUENCE,
            9 => AbstractType::TAG_TYPE_SEQUENCE,
            10 => AbstractType::TAG_TYPE_OCTET_STRING,
            11 => AbstractType::TAG_TYPE_SEQUENCE,
            12 => AbstractType::TAG_TYPE_SEQUENCE,
            13 => AbstractType::TAG_TYPE_SEQUENCE,
            14 => AbstractType::TAG_TYPE_SEQUENCE,
            15 => AbstractType::TAG_TYPE_SEQUENCE,
            16 => AbstractType::TAG_TYPE_INTEGER,
            19 => AbstractType::TAG_TYPE_SEQUENCE,
            23 => AbstractType::TAG_TYPE_SEQUENCE,
            24 => AbstractType::TAG_TYPE_SEQUENCE,
            25 => AbstractType::TAG_TYPE_SEQUENCE,
        ],
    ];

    /**
     * Workloads that need the LDAP tag map at decode time.
     *
     * @var list<string>
     */
    private const WORKLOADS_USING_LDAP_TAG_MAP = ['mixed_message'];

    /**
     * @param callable(string): void|null $progress  Optional progress sink (workload-line callback).
     */
    public function run(BenchOptions $opts, ?callable $progress = null): Report
    {
        $encoder = $this->buildEncoder($opts->encoder);
        $workloads = $this->resolveWorkloads($opts->workload);

        $meta = [
            'php' => PHP_VERSION,
            'opcache_enabled' => function_exists('opcache_get_status')
                && (opcache_get_status(false)['opcache_enabled'] ?? false),
            'jit' => (string) ini_get('opcache.jit'),
            'assertions' => (int) ini_get('zend.assertions'),
            'encoder' => $opts->encoder,
            'warmup' => $opts->warmup,
            'samples' => $opts->samples,
            'revs' => $opts->revs,
            'started' => date('c'),
        ];

        $results = [];
        foreach ($workloads as $name => $payloads) {
            $tagMap = in_array($name, self::WORKLOADS_USING_LDAP_TAG_MAP, true)
                ? self::LDAP_TAG_MAP
                : [];
            $encodedCorpus = array_map(
                static fn (AbstractType $t): string => $encoder->encode($t),
                $payloads,
            );
            $payloadCount = count($payloads);
            $byteCount = array_sum(array_map('strlen', $encodedCorpus));

            $encodeNs = $this->measure(
                static function () use ($encoder, $payloads): void {
                    foreach ($payloads as $t) {
                        $encoder->encode($t);
                    }
                },
                $opts,
            );

            $decodeNs = $this->measure(
                static function () use ($encoder, $encodedCorpus, $tagMap): void {
                    foreach ($encodedCorpus as $bin) {
                        $encoder->decode($bin, $tagMap);
                    }
                },
                $opts,
            );

            $roundTripNs = $this->measure(
                static function () use ($encoder, $payloads, $tagMap): void {
                    foreach ($payloads as $t) {
                        $encoder->decode($encoder->encode($t), $tagMap);
                    }
                },
                $opts,
            );

            $results[$name] = new WorkloadResult(
                payloads: $payloadCount,
                encodedBytes: $byteCount,
                encode: OpResult::fromSamples($encodeNs, $payloadCount),
                decode: OpResult::fromSamples($decodeNs, $payloadCount),
                roundTrip: OpResult::fromSamples($roundTripNs, $payloadCount),
            );

            if ($progress !== null) {
                $progress($this->formatProgressLine($name, $results[$name]));
            }
        }

        return new Report(meta: $meta, workloads: $results);
    }

    /**
     * @return array<string, list<AbstractType<mixed>>>
     */
    private function resolveWorkloads(?string $only): array
    {
        $all = Workload::all();
        if ($only === null) {
            return $all;
        }
        if (!isset($all[$only])) {
            throw new InvalidArgumentException("Unknown workload: $only");
        }

        return [$only => $all[$only]];
    }

    private function buildEncoder(string $name): EncoderInterface
    {
        return match ($name) {
            'ber' => new BerEncoder(),
            'der' => new DerEncoder(),
            default => throw new InvalidArgumentException("--encoder must be ber or der (got: $name)"),
        };
    }

    /**
     * @param callable(): void $fn
     *
     * @return list<int> Per-sample wall-clock duration in nanoseconds.
     */
    private function measure(callable $fn, BenchOptions $opts): array
    {
        for ($i = 0; $i < $opts->warmup; $i++) {
            $fn();
        }
        $samples = [];
        for ($s = 0; $s < $opts->samples; $s++) {
            $start = hrtime(true);
            for ($r = 0; $r < $opts->revs; $r++) {
                $fn();
            }
            $samples[] = (int) ((hrtime(true) - $start) / max(1, $opts->revs));
        }

        return $samples;
    }

    private function formatProgressLine(string $name, WorkloadResult $w): string
    {
        return sprintf(
            "%-22s payloads=%-5d  encode=%s  decode=%s  rt=%s",
            $name,
            $w->payloads,
            $this->fmtPerOp($w->encode->nsPerPayload, $w->encode->opsPerSec),
            $this->fmtPerOp($w->decode->nsPerPayload, $w->decode->opsPerSec),
            $this->fmtPerOp($w->roundTrip->nsPerPayload, $w->roundTrip->opsPerSec),
        );
    }

    private function fmtPerOp(float $nsPerPayload, float $opsPerSec): string
    {
        return sprintf('%7.0f ns/payload (%6.0f loops/s)', $nsPerPayload, $opsPerSec);
    }
}
