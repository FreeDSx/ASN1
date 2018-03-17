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
        $data = self::pad($this->value);
        for ($i = 0; $i < $length / 8; $i++) {
            $bytes .= chr(bindec(substr($data, $i * 8, 8)));
        }

        return $bytes;
    }

    /**
     * Construct the bit string from a binary string value.
     *
     * @param $bytes
     * @return BitStringType
     */
    public static function fromBinary($bytes)
    {
        $bitstring = '';

        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $bitstring .= sprintf('%08d', decbin(ord($bytes[$i])));
        }

        return new self($bitstring);
    }

    /**
     * Construct the bit string from an integer.
     *
     * @param int $int
     * @return BitStringType
     */
    public static function fromInteger(int $int)
    {
        return new self(self::pad(decbin($int)));
    }

    /**
     * Ensures the bit string is always padded as a multiple of 8.
     *
     * @param string $bitstring
     * @return string
     */
    protected static function pad(string $bitstring)
    {
        $length = strlen($bitstring);
        if (($length % 8) !== 0) {
            $bitstring = str_pad($bitstring, $length + (8 - ($length % 8)), '0', STR_PAD_LEFT);
        }

        return $bitstring;
    }
}
