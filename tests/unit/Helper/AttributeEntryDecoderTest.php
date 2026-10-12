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
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Helper\AttributeEntryDecoder;
use FreeDSx\Asn1\Helper\AttributeEntryMessage;
use FreeDSx\Asn1\Type\OctetStringType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttributeEntryDecoderTest extends TestCase
{
    private const APP_TAG = 4;

    private AttributeEntryDecoder $subject;

    private BerEncoder $encoder;

    protected function setUp(): void
    {
        $this->subject = new AttributeEntryDecoder();
        $this->encoder = new BerEncoder();
    }

    public function test_it_decodes_a_typical_entry_message(): void
    {
        $this->assertDecodesIdentically(
            7,
            'cn=seed-1,dc=foo,dc=bar',
            [
                ['cn', ['seed-1']],
                ['objectClass', ['inetOrgPerson', 'extensibleObject']],
                ['sn', ['Seeded']],
                ['mail', ['seed-1@foo.bar']],
            ],
        );
    }

    public function test_it_decodes_an_entry_with_no_attributes(): void
    {
        $this->assertDecodesIdentically(
            1,
            'dc=foo,dc=bar',
            [],
        );
    }

    public function test_it_decodes_an_attribute_with_no_values(): void
    {
        $this->assertDecodesIdentically(
            1,
            'cn=empty,dc=foo,dc=bar',
            [['cn', []]],
        );
    }

    public function test_it_decodes_multivalued_and_binary_values(): void
    {
        $this->assertDecodesIdentically(
            1,
            'cn=Iván,dc=foo,dc=bar',
            [
                ['member', ['cn=a,dc=foo', 'cn=b,dc=foo', 'cn=c,dc=foo']],
                ['displayName', ['Iván Ñoño 東京']],
                ['userCertificate;binary', ["\x00\x01\x02\xff"]],
            ],
        );
    }

    public function test_it_decodes_many_small_attributes_within_long_form_containers(): void
    {
        $attributes = [];
        for ($i = 0; $i < 200; $i++) {
            $attributes[] = ["attr-{$i}", ["value-{$i}"]];
        }

        $this->assertDecodesIdentically(
            1,
            'cn=wide,dc=foo,dc=bar',
            $attributes,
        );
    }

    #[DataProvider('lengthBoundaryProvider')]
    public function test_it_decodes_values_across_length_boundaries(int $length): void
    {
        $this->assertDecodesIdentically(
            1,
            'cn=boundary,dc=foo,dc=bar',
            [
                [
                    str_repeat(
                        'd',
                        $length,
                    ),
                    [
                        str_repeat(
                            'v',
                            $length,
                        ),
                    ],
                ],
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

    #[DataProvider('messageIdProvider')]
    public function test_it_decodes_the_message_id(int $messageId): void
    {
        $this->assertDecodesIdentically(
            $messageId,
            'cn=id,dc=foo,dc=bar',
            [['cn', ['id']]],
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function messageIdProvider(): array
    {
        return [
            'zero' => [0],
            'one octet max' => [127],
            'two octets min' => [128],
            'two octets' => [255],
            'max message id' => [2147483647],
            'negative' => [-129],
        ];
    }

    public function test_it_decodes_an_add_request_shaped_message(): void
    {
        $bytes = $this->encodeMessage(
            3,
            8,
            'cn=new,dc=foo,dc=bar',
            [['objectClass', ['top']]],
        );

        self::assertEquals(
            new AttributeEntryMessage(
                3,
                'cn=new,dc=foo,dc=bar',
                [['objectClass', ['top']]],
                strlen($bytes),
            ),
            $this->subject->decode(
                $bytes,
                8,
            ),
        );
    }

    public function test_it_reports_the_length_of_the_first_message_when_more_data_follows(): void
    {
        $first = $this->encodeMessage(
            1,
            self::APP_TAG,
            'cn=first,dc=foo,dc=bar',
            [['cn', ['first']]],
        );
        $second = $this->encodeMessage(
            2,
            self::APP_TAG,
            'cn=second,dc=foo,dc=bar',
            [['cn', ['second']]],
        );

        self::assertSame(
            strlen($first),
            $this->subject->decode(
                $first . $second,
                self::APP_TAG,
            )?->length,
        );
    }

    public function test_it_ignores_components_after_the_attribute_list_and_after_the_values(): void
    {
        $bytes = $this->encoder->encode(Asn1::sequence(
            Asn1::integer(1),
            Asn1::application(self::APP_TAG, Asn1::sequence(
                Asn1::octetString('cn=extended,dc=foo,dc=bar'),
                Asn1::sequenceOf(Asn1::sequence(
                    Asn1::octetString('cn'),
                    Asn1::setOf(Asn1::octetString('extended')),
                    Asn1::octetString('trailing-in-attribute'),
                )),
                Asn1::octetString('trailing-in-entry'),
            )),
        ));

        self::assertEquals(
            new AttributeEntryMessage(
                1,
                'cn=extended,dc=foo,dc=bar',
                [['cn', ['extended']]],
                strlen($bytes),
            ),
            $this->subject->decode(
                $bytes,
                self::APP_TAG,
            ),
        );
    }

    public function test_it_returns_null_for_another_operation(): void
    {
        $bytes = $this->encoder->encode(Asn1::sequence(
            Asn1::integer(1),
            Asn1::application(5, Asn1::sequence(
                Asn1::enumerated(0),
                Asn1::octetString(''),
                Asn1::octetString(''),
            )),
        ));

        self::assertNull($this->subject->decode(
            $bytes,
            self::APP_TAG,
        ));
    }

    public function test_it_returns_null_when_controls_follow_the_entry(): void
    {
        $bytes = $this->encoder->encode(Asn1::sequence(
            Asn1::integer(1),
            Asn1::application(self::APP_TAG, Asn1::sequence(
                Asn1::octetString('cn=foo,dc=bar'),
                Asn1::sequenceOf(),
            )),
            Asn1::context(0, Asn1::sequenceOf(Asn1::sequence(Asn1::octetString('1.2.3')))),
        ));

        self::assertNull($this->subject->decode(
            $bytes,
            self::APP_TAG,
        ));
    }

    public function test_it_returns_null_when_the_message_is_not_a_sequence(): void
    {
        self::assertNull($this->subject->decode(
            hex2bin('0401ff'),
            self::APP_TAG,
        ));
    }

    public function test_it_returns_null_when_the_message_id_is_larger_than_a_native_int(): void
    {
        self::assertNull($this->subject->decode(
            hex2bin('300d' . '020900ffffffffffffffff' . '6400'),
            self::APP_TAG,
        ));
    }

    public function test_it_throws_a_partial_pdu_exception_for_every_truncation_of_a_message(): void
    {
        $attributes = [];
        for ($i = 0; $i < 20; $i++) {
            $attributes[] = ["attr-{$i}", ["value-{$i}"]];
        }
        $bytes = $this->encodeMessage(
            1,
            self::APP_TAG,
            'cn=partial,dc=foo,dc=bar',
            $attributes,
        );

        $notPartial = [];
        for ($i = 0; $i < strlen($bytes); $i++) {
            try {
                $this->subject->decode(
                    substr(
                        $bytes,
                        0,
                        $i,
                    ),
                    self::APP_TAG,
                );
                $notPartial[] = $i;
            } catch (PartialPduException) {
            }
        }

        self::assertSame(
            [],
            $notPartial,
        );
    }

    public function test_it_throws_an_encoder_exception_when_a_value_is_not_an_octet_string(): void
    {
        $bytes = $this->encoder->encode(Asn1::sequence(
            Asn1::integer(1),
            Asn1::application(self::APP_TAG, Asn1::sequence(
                Asn1::octetString('cn=foo,dc=bar'),
                Asn1::sequenceOf(Asn1::sequence(
                    Asn1::octetString('cn'),
                    Asn1::setOf(Asn1::integer(1)),
                )),
            )),
        ));

        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Expected an element with tag 0x04 in the attribute entry message.');

        $this->subject->decode(
            $bytes,
            self::APP_TAG,
        );
    }

    public function test_it_throws_an_encoder_exception_when_the_values_are_not_a_set(): void
    {
        $bytes = $this->encoder->encode(Asn1::sequence(
            Asn1::integer(1),
            Asn1::application(self::APP_TAG, Asn1::sequence(
                Asn1::octetString('cn=foo,dc=bar'),
                Asn1::sequenceOf(Asn1::sequence(
                    Asn1::octetString('cn'),
                    Asn1::sequence(Asn1::octetString('foo')),
                )),
            )),
        ));

        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Expected an element with tag 0x31 in the attribute entry message.');

        $this->subject->decode(
            $bytes,
            self::APP_TAG,
        );
    }

    public function test_it_throws_an_encoder_exception_when_an_attribute_overruns_the_attribute_list(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The expected byte length was 5, but received 2.');

        $this->subject->decode(
            hex2bin('3010' . '020101' . '640b' . '0400' . '3004' . '30050401613100'),
            self::APP_TAG,
        );
    }

    public function test_it_throws_an_encoder_exception_rather_than_a_partial_pdu_exception_for_a_truncated_inner_length(): void
    {
        $bytes = hex2bin('3014' . '020101' . '640f' . '0400' . '300b' . '3009' . '040161' . '3104' . '04840000');

        try {
            $this->subject->decode(
                $bytes,
                self::APP_TAG,
            );
        } catch (EncoderException $e) {
            self::assertNotInstanceOf(
                PartialPduException::class,
                $e,
            );

            return;
        }

        self::fail('Expected an encoder exception for the truncated inner length.');
    }

    public function test_it_rejects_an_unsupported_high_application_tag(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(
            hex2bin('3000'),
            31,
        );
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $attributes
     */
    private function assertDecodesIdentically(
        int $messageId,
        string $primaryId,
        array $attributes,
    ): void {
        $bytes = $this->encodeMessage(
            $messageId,
            self::APP_TAG,
            $primaryId,
            $attributes,
        );

        self::assertEquals(
            new AttributeEntryMessage(
                $messageId,
                $primaryId,
                $attributes,
                strlen($bytes),
            ),
            $this->subject->decode(
                $bytes,
                self::APP_TAG,
            ),
        );
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $attributes
     */
    private function encodeMessage(
        int $messageId,
        int $appTag,
        string $primaryId,
        array $attributes,
    ): string {
        $partialAttributes = Asn1::sequenceOf();
        foreach ($attributes as [$description, $values]) {
            $valueTypes = array_map(
                static fn (string $value): OctetStringType => Asn1::octetString($value),
                $values,
            );
            $partialAttributes->addChild(Asn1::sequence(
                Asn1::octetString($description),
                Asn1::setOf(...$valueTypes),
            ));
        }

        return $this->encoder->encode(Asn1::sequence(
            Asn1::integer($messageId),
            Asn1::application($appTag, Asn1::sequence(
                Asn1::octetString($primaryId),
                $partialAttributes,
            )),
        ));
    }
}
