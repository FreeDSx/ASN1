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
 * Represents a Relative OID type.
 *
 * @extends AbstractType<string>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class RelativeOidType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_RELATIVE_OID;

    public function __construct(string $value)
    {
        parent::__construct($value);
    }

    /**
     * @return $this
     */
    public function setValue(string $relativeOid)
    {
        $this->value = $relativeOid;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }

    /**
     * @param int|string $tagNumber
     */
    public static function withTag(
        $tagNumber,
        int $class,
        string $value
    ): self
    {
        $type = new self($value);
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
