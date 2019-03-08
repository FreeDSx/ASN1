<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Asn1\Type;

/**
 * Functionality needed between integer / enums for big int validation / checking.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
trait BigIntTrait
{
    /**
     * Whether or not the contained value is larger than the PHP_INT_MAX value (represented as a string value).
     *
     * @return bool
     */
    public function isBigInt() : bool
    {
        if (\is_int($this->value)) {
            return false;
        }

        return \is_float($this->value + 0);
    }
}
