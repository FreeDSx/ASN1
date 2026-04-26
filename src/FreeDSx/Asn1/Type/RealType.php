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
 * Represents an ASN.1 Real type.
 *
 * @extends AbstractType<float>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class RealType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_REAL;

    public function __construct(float $value)
    {
        parent::__construct($value);
    }

    /**
     * @return $this
     */
    public function setValue(float $value)
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * @param int|string $tagNumber
     */
    public static function withTag(
        $tagNumber,
        int $class,
        float $value
    ): self
    {
        $type = new self($value);
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
