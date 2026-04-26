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
 * Represents an ASN1 integer type.
 *
 * @extends AbstractType<int|string>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class IntegerType extends AbstractType
{
    use BigIntTrait;

    protected $tagNumber = self::TAG_TYPE_INTEGER;

    /**
     * @return int|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param int|string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param int|string $tagNumber
     * @param int|string $value
     */
    public static function withTag(
        $tagNumber,
        int $class,
        $value
    ): self
    {
        $type = new self($value);
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
