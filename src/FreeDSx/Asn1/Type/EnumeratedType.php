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
 * Represents an ASN1 enumerated type.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class EnumeratedType extends AbstractType
{
    use BigIntTrait;

    protected $tagNumber = self::TAG_TYPE_ENUMERATED;

    /**
     * @param string|int $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param string|int $tagNumber
     * @param int $class
     * @param string|int $value
     * @return EnumeratedType
     */
    public static function withTag($tagNumber, int $class, $value)
    {
        $type = new self($value);
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
