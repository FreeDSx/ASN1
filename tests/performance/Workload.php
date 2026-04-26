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

namespace Tests\Performance\FreeDSx\Asn1;

use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Type\AbstractType;

/**
 * Deterministic corpus generator for encoder benchmarks.
 *
 * Each workload returns a list of AbstractType payloads representative of a
 * specific shape we want to measure. The RNG is seeded so two bench runs see
 * identical input.
 */
final class Workload
{
    private const SEED = 0x4153_4e31;

    /**
     * @return array<string, list<AbstractType<mixed>>>
     */
    public static function all(): array
    {
        return [
            'ldap_search_entry' => self::ldapSearchEntries(200),
            'string_heavy' => self::stringHeavy(500),
            'oid_heavy' => self::oidHeavy(500),
            'integer_heavy' => self::integerHeavy(2000),
            'mixed_message' => self::mixedMessages(100),
            'primitives_baseline' => self::primitivesBaseline(2000),
        ];
    }

    /**
     * LDAP-shaped: each entry is Sequence(OctetString DN, Sequence(Sequence(OctetString attr, Set(OctetString[]))×5))
     *
     * @return list<AbstractType<mixed>>
     */
    private static function ldapSearchEntries(int $count): array
    {
        $rng = self::rng();
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $attrs = [];
            for ($a = 0; $a < 5; $a++) {
                $values = [];
                for ($v = 0; $v < 3; $v++) {
                    $values[] = Asn1::octetString(self::randomString($rng, 16, 64));
                }
                $attrs[] = Asn1::sequence(
                    Asn1::octetString('attr-' . self::randomString($rng, 4, 12)),
                    Asn1::setOf(...$values),
                );
            }
            $entries[] = Asn1::sequence(
                Asn1::octetString('cn=user' . $i . ',dc=example,dc=org'),
                Asn1::sequence(...$attrs),
            );
        }

        return $entries;
    }

    /**
     * Sequence of long octet strings — stresses AbstractStringType constructor and length encoding.
     *
     * @return list<AbstractType<mixed>>
     */
    private static function stringHeavy(int $count): array
    {
        $rng = self::rng();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = Asn1::sequence(
                Asn1::octetString(self::randomString($rng, 64, 256)),
                Asn1::utf8String(self::randomString($rng, 8, 32)),
                Asn1::printableString(self::randomString($rng, 8, 32)),
                Asn1::ia5String(self::randomString($rng, 8, 32)),
            );
        }

        return $items;
    }

    /**
     * Sequences of OIDs — stresses OidType encoding.
     *
     * @return list<AbstractType<mixed>>
     */
    private static function oidHeavy(int $count): array
    {
        $rng = self::rng();
        $oids = [
            '1.3.6.1.4.1.1466.20037',
            '1.3.6.1.4.1.4203.1.5.1',
            '2.5.4.3',
            '2.5.4.4',
            '1.2.840.113549.1.1.11',
            '1.3.6.1.4.1.311.21.20',
            '1.2.840.113549',
            '0.9.2342.19200300.100.1.1',
        ];
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $children = [];
            for ($k = 0; $k < 6; $k++) {
                $children[] = Asn1::oid($oids[$rng() % count($oids)]);
            }
            $items[] = Asn1::sequenceOf(...$children);
        }

        return $items;
    }

    /**
     * Many integer + enumerated values — exercises the integer encoding path.
     *
     * @return list<AbstractType<mixed>>
     */
    private static function integerHeavy(int $count): array
    {
        $rng = self::rng();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = Asn1::sequence(
                Asn1::integer(($rng() % 200000) - 100000),
                Asn1::integer($rng() % 1000),
                Asn1::enumerated($rng() % 16),
                Asn1::boolean(($rng() & 1) === 1),
            );
        }

        return $items;
    }

    /**
     * Approximate LDAPMessage envelope wrapping a SearchResultEntry payload.
     *
     * @return list<AbstractType<mixed>>
     */
    private static function mixedMessages(int $count): array
    {
        $rng = self::rng();
        $messages = [];
        for ($i = 0; $i < $count; $i++) {
            $attrs = [];
            for ($a = 0; $a < 8; $a++) {
                $values = [];
                for ($v = 0; $v < 2; $v++) {
                    $values[] = Asn1::octetString(self::randomString($rng, 8, 48));
                }
                $attrs[] = Asn1::sequence(
                    Asn1::octetString('attr-' . self::randomString($rng, 4, 10)),
                    Asn1::setOf(...$values),
                );
            }
            $payload = Asn1::application(4, Asn1::sequence(
                Asn1::octetString('cn=u' . $i . ',ou=people,dc=example,dc=org'),
                Asn1::sequence(...$attrs),
            ));
            $messages[] = Asn1::sequence(
                Asn1::integer($i + 1),
                $payload,
            );
        }

        return $messages;
    }

    /**
     * Synthetic small primitives — isolates per-type overhead from collection effects.
     *
     * @return list<AbstractType<mixed>>
     */
    private static function primitivesBaseline(int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = match ($i & 0x3) {
                0 => Asn1::boolean(($i & 1) === 1),
                1 => Asn1::integer($i),
                2 => Asn1::null(),
                default => Asn1::octetString('x' . $i),
            };
        }

        return $items;
    }

    /**
     * Returns a deterministic mt_rand-style closure.
     *
     * @return callable(): int
     */
    private static function rng(): callable
    {
        mt_srand(self::SEED);

        return static fn (): int => mt_rand();
    }

    /**
     * @param callable(): int $rng
     */
    private static function randomString(callable $rng, int $minLen, int $maxLen): string
    {
        $len = $minLen + ($rng() % max(1, ($maxLen - $minLen + 1)));
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_.';
        $alphaLen = strlen($alphabet);
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[$rng() % $alphaLen];
        }

        return $out;
    }
}
