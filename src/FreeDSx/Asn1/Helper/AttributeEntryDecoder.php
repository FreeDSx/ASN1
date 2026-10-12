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
use FreeDSx\Asn1\Exception\PartialPduException;

use function ord;
use function sprintf;
use function strlen;
use function substr;

/**
 * A dedicated BER decoder for the RFC 4511 LDAPMessage carrying a SearchResultEntry / AddRequest.
 *
 * This is the decoding counterpart to the attribute entry encoder.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final class AttributeEntryDecoder
{
    use BerEncodingTrait;

    private const INTEGER = 0x02;

    private const OCTET_STRING = 0x04;

    private const SEQUENCE = 0x30;

    private const SET = 0x31;

    /**
     * Decodes the message with the tagged attribute entry, or null when its shape needs the type graph instead.
     *
     * @throws PartialPduException
     * @throws EncoderException
     */
    public function decode(
        string $bytes,
        int $appTag,
    ): ?AttributeEntryMessage {
        $entryTag = $this->applicationTag($appTag);
        $received = strlen($bytes);
        if ($received < 2) {
            throw new PartialPduException('Not enough data to decode the message.');
        }
        if (ord($bytes[0]) !== self::SEQUENCE) {
            return null;
        }

        $pos = 2;
        $length = ord($bytes[1]);
        if ($length > 127) {
            $count = $length & 0x7f;
            $length = $this->decodeLongDefiniteLength(
                $bytes,
                $pos,
                $received,
                $count,
            );
            $pos += $count;
        }
        if ($length > $received - $pos) {
            throw new PartialPduException(sprintf(
                'The expected byte length was %d, but received %d.',
                $length,
                $received - $pos,
            ));
        }

        # The message is fully received, so running out of data within it means it is malformed.
        try {
            return $this->decodeMessage(
                $bytes,
                $pos,
                $pos + $length,
                $entryTag,
            );
        } catch (PartialPduException $e) {
            throw new EncoderException(
                $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @throws EncoderException
     * @throws PartialPduException
     */
    private function decodeMessage(
        string $bytes,
        int $pos,
        int $messageEnd,
        int $entryTag,
    ): ?AttributeEntryMessage {
        if ($pos >= $messageEnd || ord($bytes[$pos]) !== self::INTEGER) {
            return null;
        }
        $length = $this->expect(
            $bytes,
            $pos,
            $messageEnd,
            self::INTEGER,
        );
        if ($length === 0 || $length > PHP_INT_SIZE) {
            return null;
        }
        $messageId = $this->readNativeInteger(
            $bytes,
            $pos,
            $length,
        );
        $pos += $length;

        if ($pos >= $messageEnd || ord($bytes[$pos]) !== $entryTag) {
            return null;
        }
        $length = $this->expect(
            $bytes,
            $pos,
            $messageEnd,
            $entryTag,
        );
        # Controls, or anything else following the entry, are left to the type graph.
        if ($pos + $length !== $messageEnd) {
            return null;
        }

        $length = $this->expect(
            $bytes,
            $pos,
            $messageEnd,
            self::OCTET_STRING,
        );
        $primaryId = substr(
            $bytes,
            $pos,
            $length,
        );
        $pos += $length;

        return new AttributeEntryMessage(
            $messageId,
            $primaryId,
            $this->decodeAttributes(
                $bytes,
                $pos,
                $messageEnd,
            ),
            $messageEnd,
        );
    }

    /**
     * Decodes the attribute list, ignoring any components after it, and after the values of each attribute.
     *
     * @return list<array{0: string, 1: list<string>}>
     *
     * @throws EncoderException
     * @throws PartialPduException
     */
    private function decodeAttributes(
        string $bytes,
        int $pos,
        int $entryEnd,
    ): array {
        $length = $this->expect(
            $bytes,
            $pos,
            $entryEnd,
            self::SEQUENCE,
        );
        $listEnd = $pos + $length;

        $attributes = [];
        while ($pos < $listEnd) {
            $length = $this->expect(
                $bytes,
                $pos,
                $listEnd,
                self::SEQUENCE,
            );
            $attributeEnd = $pos + $length;

            $length = $this->expect(
                $bytes,
                $pos,
                $attributeEnd,
                self::OCTET_STRING,
            );
            $description = substr(
                $bytes,
                $pos,
                $length,
            );
            $pos += $length;

            $length = $this->expect(
                $bytes,
                $pos,
                $attributeEnd,
                self::SET,
            );
            $valuesEnd = $pos + $length;

            $values = [];
            while ($pos < $valuesEnd) {
                $length = $this->expect(
                    $bytes,
                    $pos,
                    $valuesEnd,
                    self::OCTET_STRING,
                );
                $values[] = substr(
                    $bytes,
                    $pos,
                    $length,
                );
                $pos += $length;
            }
            $attributes[] = [$description, $values];
            $pos = $attributeEnd;
        }

        return $attributes;
    }

    /**
     * Reads the header of an element that must have the given tag and fit before the end offset, returning its length.
     *
     * @throws EncoderException
     * @throws PartialPduException
     */
    private function expect(
        string $bytes,
        int &$pos,
        int $end,
        int $tag,
    ): int {
        if ($end - $pos < 2 || ord($bytes[$pos]) !== $tag) {
            throw new EncoderException(sprintf(
                'Expected an element with tag 0x%02x in the attribute entry message.',
                $tag,
            ));
        }
        $length = ord($bytes[$pos + 1]);
        $pos += 2;
        if ($length > 127) {
            $count = $length & 0x7f;
            $length = $this->decodeLongDefiniteLength(
                $bytes,
                $pos,
                $end,
                $count,
            );
            $pos += $count;
        }
        if ($length > $end - $pos) {
            throw new EncoderException(sprintf(
                'The expected byte length was %d, but received %d.',
                $length,
                $end - $pos,
            ));
        }

        return $length;
    }
}
