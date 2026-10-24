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

namespace Tests\Unit\FreeDSx\Asn1\Helper;

use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Helper\AttributeEntryEncoder;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\OctetStringType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttributeEntryEncoderTest extends TestCase
{
    private const APP_TAG = 4;

    private AttributeEntryEncoder $subject;

    private BerEncoder $encoder;

    protected function setUp(): void
    {
        $this->subject = new AttributeEntryEncoder();
        $this->encoder = new BerEncoder();
    }

    public function test_it_encodes_a_typical_entry_identically(): void
    {
        $this->assertEncodesEntryIdentically(
            'cn=seed-1,dc=foo,dc=bar',
            [
                ['cn', ['seed-1']],
                ['objectClass', ['inetOrgPerson', 'extensibleObject']],
                ['sn', ['Seeded']],
                ['mail', ['seed-1@foo.bar']],
                ['uidNumber', ['1001']],
            ],
        );
    }

    public function test_it_encodes_an_entry_with_no_attributes_identically(): void
    {
        $this->assertEncodesEntryIdentically(
            'dc=foo,dc=bar',
            [],
        );
    }

    public function test_it_encodes_an_attribute_with_no_values_identically(): void
    {
        $this->assertEncodesEntryIdentically(
            'cn=empty,dc=foo,dc=bar',
            [['cn', []]],
        );
    }

    public function test_it_encodes_multivalued_and_unicode_values_identically(): void
    {
        $this->assertEncodesEntryIdentically(
            'cn=Iván,dc=foo,dc=bar',
            [
                ['member', ['cn=a,dc=foo', 'cn=b,dc=foo', 'cn=c,dc=foo']],
                ['displayName', ['Iván Ñoño 東京']],
                ['userCertificate;binary', ["\x00\x01\x02\xff"]],
            ],
        );
    }

    public function test_it_encodes_many_small_attributes_forcing_long_form_containers_identically(): void
    {
        $attributes = [];
        for ($i = 0; $i < 60; $i++) {
            $attributes[] = ["attr-{$i}", ["value-{$i}"]];
        }

        $this->assertEncodesEntryIdentically(
            'cn=wide,dc=foo,dc=bar',
            $attributes,
        );
    }

    #[DataProvider('lengthBoundaryProvider')]
    public function test_it_encodes_values_across_length_boundaries_identically(int $length): void
    {
        $this->assertEncodesEntryIdentically(
            'cn=boundary,dc=foo,dc=bar',
            [
                [str_repeat('d', $length), [str_repeat('v', $length)]],
            ],
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function lengthBoundaryProvider(): array
    {
        return [
            '127 (short form max)' => [127],
            '128 (long form min)' => [128],
            '255 (one length octet max)' => [255],
            '256 (two length octets)' => [256],
            '65535 (two length octets max)' => [65535],
            '65536 (three length octets)' => [65536],
        ];
    }

    public function test_it_rejects_an_unsupported_high_application_tag(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->encodeEntry(
            31,
            'cn=x',
            [],
        );
    }

    #[DataProvider('messageIdProvider')]
    public function test_it_encodes_a_message_identically_for_any_message_id(int $messageId): void
    {
        $this->assertEncodesMessageIdentically(
            $messageId,
            [['cn', ['foo']]],
        );
    }

    public function test_it_encodes_a_message_with_a_long_form_length_identically(): void
    {
        $attributes = [];
        for ($i = 0; $i < 60; $i++) {
            $attributes[] = ["attr-{$i}", ["value-{$i}"]];
        }

        $this->assertEncodesMessageIdentically(
            7,
            $attributes,
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function messageIdProvider(): array
    {
        return [
            'zero' => [0],
            'one octet at its limit' => [127],
            'two octets with a leading zero' => [128],
            'two octets at their limit' => [32767],
            'three octets' => [32768],
            'three octets at their limit' => [8388607],
            'four octets' => [8388608],
            'four octets at the RFC 4511 maximum' => [2147483647],
            'five octets' => [2147483648],
            'the native maximum' => [PHP_INT_MAX],
            'negative one' => [-1],
            'one negative octet at its limit' => [-128],
            'two negative octets' => [-129],
            'two negative octets at their limit' => [-32768],
            'three negative octets' => [-32769],
            'three negative octets at their limit' => [-8388608],
            'four negative octets' => [-8388609],
            'four negative octets at their limit' => [-2147483648],
            'five negative octets' => [-2147483649],
            'the native minimum plus one' => [PHP_INT_MIN + 1],
        ];
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $attributes
     */
    private function assertEncodesMessageIdentically(
        int $messageId,
        array $attributes,
    ): void {
        self::assertSame(
            $this->encoder->encode(Asn1::sequence(
                Asn1::integer($messageId),
                $this->buildGraph(
                    self::APP_TAG,
                    'cn=foo,dc=foo,dc=bar',
                    $attributes,
                ),
            )),
            $this->subject->encodeMessage(
                $messageId,
                self::APP_TAG,
                'cn=foo,dc=foo,dc=bar',
                $attributes,
            ),
        );
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $attributes
     */
    private function assertEncodesEntryIdentically(string $primaryId, array $attributes): void
    {
        self::assertSame(
            $this->encoder->encode($this->buildGraph(self::APP_TAG, $primaryId, $attributes)),
            $this->subject->encodeEntry(
                self::APP_TAG,
                $primaryId,
                $attributes,
            ),
        );
    }

    /**
     * Builds the equivalent type graph the dedicated encoder must match byte-for-byte.
     *
     * @param list<array{0: string, 1: list<string>}> $attributes
     * @return AbstractType<mixed>
     */
    private function buildGraph(int $appTag, string $primaryId, array $attributes): AbstractType
    {
        $partialAttributes = Asn1::sequenceOf();
        foreach ($attributes as [$description, $values]) {
            $valueTypes = array_map(
                static fn(string $value): OctetStringType => Asn1::octetString($value),
                $values,
            );
            $partialAttributes->addChild(Asn1::sequence(
                Asn1::octetString($description),
                Asn1::setOf(...$valueTypes),
            ));
        }

        return Asn1::application($appTag, Asn1::sequence(
            Asn1::octetString($primaryId),
            $partialAttributes,
        ));
    }
}
