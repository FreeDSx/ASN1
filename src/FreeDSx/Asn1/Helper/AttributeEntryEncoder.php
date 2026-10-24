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

namespace FreeDSx\Asn1\Helper;

use FreeDSx\Asn1\Encoder\BerEncodingTrait;
use FreeDSx\Asn1\Exception\EncoderException;

use function chr;
use function ltrim;
use function ord;
use function pack;
use function strlen;
use function substr;

/**
 * A dedicated BER encoder for optimizing attribute encoding in LDAP. The object graph of this library can be slow on
 * encoding when there are a lot of objects involved. This exists as a performance optimization in this library to keep
 * the ASN1 logic here, and this is genuinely needed to improve encoding speed on common search operations.
 *
 * This is the RFC 4511 SearchResultEntry / AddRequest AttributeList body.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final class AttributeEntryEncoder
{
    use BerEncodingTrait;

    private const INTEGER = "\x02";

    private const OCTET_STRING = "\x04";

    private const SEQUENCE = "\x30";

    private const SET_OF = "\x31";

    /**
     * Encodes the tagged attribute entry to definite-length BER, byte-identical to encoding the equivalent type graph.
     *
     * The normal length encoding is deliberately inline for performance reasons.
     *
     * @param int $appTag The application tag number (e.g. the SearchResultEntry tag).
     * @param string $primaryId The leading octet string (e.g. the entry DN).
     * @param iterable<array{0: string, 1: array<string>}> $attributes Each entry is [description, values].
     * @throws EncoderException
     */
    public function encodeEntry(
        int $appTag,
        string $primaryId,
        iterable $attributes,
    ): string {
        $attributeList = '';

        foreach ($attributes as [$description, $values]) {
            $set = '';
            foreach ($values as $value) {
                $length = strlen($value);
                $set .= self::OCTET_STRING . ($length < 128 ? chr($length) : $this->encodeLongDefiniteLength($length)) . $value;
            }

            $descriptionLength = strlen($description);
            $setLength = strlen($set);
            $attribute = self::OCTET_STRING
                . ($descriptionLength < 128 ? chr($descriptionLength) : $this->encodeLongDefiniteLength($descriptionLength))
                . $description
                . self::SET_OF
                . ($setLength < 128 ? chr($setLength) : $this->encodeLongDefiniteLength($setLength))
                . $set;

            $attributeLength = strlen($attribute);
            $attributeList .= self::SEQUENCE
                . ($attributeLength < 128 ? chr($attributeLength) : $this->encodeLongDefiniteLength($attributeLength))
                . $attribute;
        }

        // this is intentionally left duplicated for performance reasons.
        $primaryIdLength = strlen($primaryId);
        $attributeListLength = strlen($attributeList);
        $body = self::OCTET_STRING
            . ($primaryIdLength < 128 ? chr($primaryIdLength) : $this->encodeLongDefiniteLength($primaryIdLength))
            . $primaryId
            . self::SEQUENCE
            . ($attributeListLength < 128 ? chr($attributeListLength) : $this->encodeLongDefiniteLength($attributeListLength))
            . $attributeList;

        $bodyLength = strlen($body);

        return chr($this->applicationTag($appTag))
            . ($bodyLength < 128 ? chr($bodyLength) : $this->encodeLongDefiniteLength($bodyLength))
            . $body;
    }

    /**
     * Encodes the LDAPMessage of a message ID and the tagged attribute entry without controls.
     *
     * @param iterable<array{0: string, 1: array<string>}> $attributes Each entry is [description, values].
     * @throws EncoderException
     */
    public function encodeMessage(
        int $messageId,
        int $appTag,
        string $primaryId,
        iterable $attributes,
    ): string {
        $id = $this->integerOctets($messageId);
        $body = self::INTEGER
            . chr(strlen($id))
            . $id
            . $this->encodeEntry(
                $appTag,
                $primaryId,
                $attributes,
            );
        $bodyLength = strlen($body);

        return self::SEQUENCE
            . ($bodyLength < 128 ? chr($bodyLength) : $this->encodeLongDefiniteLength($bodyLength))
            . $body;
    }

    /**
     * The fewest two's complement octets holding the integer, as the type graph encodes it.
     */
    private function integerOctets(int $int): string
    {
        if ($int >= -0x80 && $int < 0x80) {
            return chr($int & 0xff);
        }
        if ($int >= -0x8000 && $int < 0x8000) {
            return pack(
                'n',
                $int,
            );
        }
        if ($int >= -0x800000 && $int < 0x800000) {
            return substr(
                pack(
                    'N',
                    $int,
                ),
                1,
            );
        }
        if ($int >= -0x80000000 && $int < 0x80000000) {
            return pack(
                'N',
                $int,
            );
        }

        return $this->wideIntegerOctets($int);
    }

    /**
     * The fewest two's complement octets holding an integer wider than 32 bits.
     */
    private function wideIntegerOctets(int $int): string
    {
        $sign = $int < 0 ? "\xff" : "\x00";
        $octets = ltrim(
            pack(
                'J',
                $int,
            ),
            $sign,
        );

        return (ord($octets[0]) & 0x80) === (ord($sign) & 0x80)
            ? $octets
            : $sign . $octets;
    }
}
