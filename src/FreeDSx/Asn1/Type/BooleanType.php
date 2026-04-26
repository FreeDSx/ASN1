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
 * Represents an ASN1 boolean type.
 *
 * @extends AbstractType<bool>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class BooleanType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_BOOLEAN;

    public function __construct(bool $value)
    {
        parent::__construct($value);
    }

    public function getValue(): bool
    {
        return $this->value;
    }

    /**
     * @return $this
     */
    public function setValue(bool $value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param int|string $tagNumber
     */
    public static function withTag(
        $tagNumber,
        int $class,
        bool $value
    ): self
    {
        $type = new self($value);
        $type->taggingClass = $class;
        $type->tagNumber = $tagNumber;

        return $type;
    }
}
