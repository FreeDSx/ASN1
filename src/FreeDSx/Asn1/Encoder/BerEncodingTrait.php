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

namespace FreeDSx\Asn1\Encoder;

use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\AbstractType;

use function chr;
use function ord;
use function sprintf;
use function strlen;

/**
 * Stateless operations on raw BER bytes, shared by the encoders and the fast-path helpers.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
trait BerEncodingTrait
{
    /**
     * Long-form definite-length octet encoding.
     */
    protected function encodeLongDefiniteLength(int $num): string
    {
        $bytes = '';
        while ($num) {
            $bytes = chr($num & 0xff) . $bytes;
            $num >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * Long-form definite-length decoding of the count of octets at the position, which the caller advances past.
     *
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeLongDefiniteLength(
        string $bytes,
        int $pos,
        int $end,
        int $count,
    ): int {
        if ($count === 0) {
            throw new EncoderException('Indefinite length encoding is not currently supported.');
        }
        # The value of 127 is marked as reserved in the spec.
        if ($count === 127) {
            throw new EncoderException('The decoded length cannot be equal to 127 bytes.');
        }
        if ($count >= PHP_INT_SIZE) {
            throw new EncoderException(sprintf(
                'A length encoded in %d octets is not supported.',
                $count,
            ));
        }
        if ($count > $end - $pos) {
            throw new PartialPduException('Not enough data to decode the length.');
        }

        $length = 0;
        for ($i = 0; $i < $count; $i++) {
            $length = ($length << 8) | ord($bytes[$pos + $i]);
        }

        return $length;
    }

    /**
     * Two's complement decoding of an integer no longer than a native int, by sign-extending the first octet.
     */
    protected function readNativeInteger(
        string $bytes,
        int $pos,
        int $length,
    ): int {
        $int = ord($bytes[$pos]);
        if ($int > 127) {
            $int -= 256;
        }
        for ($i = 1; $i < $length; $i++) {
            $int = ($int << 8) | ord($bytes[$pos + $i]);
        }

        return $int;
    }

    /**
     * The identifier octet of a constructed application tag, which must use the low tag number form.
     *
     * @throws EncoderException
     */
    protected function applicationTag(int $appTag): int
    {
        # The high tag number form is not needed for LDAP PDUs.
        if ($appTag < 0 || $appTag >= 31) {
            throw new EncoderException(sprintf(
                'The application tag "%d" is not supported by the fast-path helpers.',
                $appTag,
            ));
        }

        return AbstractType::TAG_CLASS_APPLICATION | AbstractType::CONSTRUCTED_TYPE | $appTag;
    }
}
