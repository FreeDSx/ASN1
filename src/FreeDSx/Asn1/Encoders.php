<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Asn1;

use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Encoder\EncoderOptions;
use FreeDSx\Asn1\Encoder\DerEncoder;

/**
 * Simple factory methods for easily getting an encoder instance for encoding / decoding.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Encoders
{
    public static function ber(EncoderOptions $options = new EncoderOptions()): BerEncoder
    {
        return new BerEncoder($options);
    }

    public static function der(EncoderOptions $options = new EncoderOptions()): DerEncoder
    {
        return new DerEncoder($options);
    }
}
