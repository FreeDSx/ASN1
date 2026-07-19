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

use FreeDSx\Asn1\Encoder\LengthEncodingTrait;
use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Type\AbstractType;

use function chr;
use function sprintf;
use function strlen;

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
    use LengthEncodingTrait;

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
    public function encode(
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

        $primaryIdLength = strlen($primaryId);
        $attributeListLength = strlen($attributeList);
        $body = self::OCTET_STRING
            . ($primaryIdLength < 128 ? chr($primaryIdLength) : $this->encodeLongDefiniteLength($primaryIdLength))
            . $primaryId
            . self::SEQUENCE
            . ($attributeListLength < 128 ? chr($attributeListLength) : $this->encodeLongDefiniteLength($attributeListLength))
            . $attributeList;

        $bodyLength = strlen($body);

        return $this->applicationTag($appTag)
            . ($bodyLength < 128 ? chr($bodyLength) : $this->encodeLongDefiniteLength($bodyLength))
            . $body;
    }

    /**
     * @throws EncoderException
     */
    private function applicationTag(int $appTag): string
    {
        # Application class (0x40) + constructed (0x20). The high-tag form is not needed for LDAP PDUs.
        if ($appTag < 0 || $appTag >= 31) {
            throw new EncoderException(sprintf(
                'The application tag "%d" is not supported by the fast-path encoder.',
                $appTag,
            ));
        }

        return chr(AbstractType::TAG_CLASS_APPLICATION | AbstractType::CONSTRUCTED_TYPE | $appTag);
    }
}
