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
 * Represents an OID ASN1 type.
 *
 * @extends AbstractType<string>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class OidType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_OID;

    public function __construct(string $value)
    {
        parent::__construct($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return $this
     */
    public function setValue(string $oid)
    {
        $this->value = $oid;

        return $this;
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
