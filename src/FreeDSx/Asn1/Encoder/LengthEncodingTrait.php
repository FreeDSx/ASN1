<?php
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

/**
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
trait LengthEncodingTrait
{
    /**
     * Long-form definite-length octet encoding, shared by BER-based encoders.
     *
     * @throws EncoderException
     */
    protected function encodeLongDefiniteLength(int $num): string
    {
        $bytes = '';
        while ($num) {
            $bytes = (chr((int) ($num % 256))) . $bytes;
            $num = (int) ($num / 256);
        }

        $length = strlen($bytes);
        if ($length >= 127) {
            throw new EncoderException('The encoded length cannot be greater than or equal to 127 bytes');
        }

        return chr(0x80 | $length) . $bytes;
    }
}
