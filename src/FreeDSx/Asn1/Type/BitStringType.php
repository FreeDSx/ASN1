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
 * Represents a bit string type.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class BitStringType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_BIT_STRING;

    /**
     * @param string $value
     */
    public function __construct(string $value)
    {
        parent::__construct($value);
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get the integer representation of the bit string.
     *
     * @return int
     */
    public function toInteger() : int
    {
        return bindec($this->value);
    }

    /**
     * Get the packed binary representation.
     *
     * @return string
     */
    public function toBinary()
    {
        $bytes = '';

        $length = strlen($this->value);
        $data = (($length % 8) === 0) ? $this->value : str_pad($this->value, $length + (8 - ($length % 8)), '0');
        for ($i = 0; $i < $length / 8; $i++) {
            $bytes .= chr(bindec(substr($data, $i * 8, 8)));
        }

        return $bytes;
    }
}
