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
 * Represents the various ASN1 string types.
 *
 * @extends AbstractType<string>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
abstract class AbstractStringType extends AbstractType
{
    /**
     * @var bool
     */
    protected $isCharRestricted = false;

    public function __construct(string $value = '')
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
    public function setValue(string $value)
    {
        $this->value = $value;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }

    public function isCharacterRestricted(): bool
    {
        return $this->isCharRestricted;
    }

    /**
     * @param int|string $tagNumber
     *
     * @return static
     */
    public static function withTag(
        $tagNumber,
        int $class,
        bool $isConstructed,
        string $value = '',
    ) {
        $type = new static($value);
        $type->taggingClass = $class;
        $type->tagNumber = $tagNumber;
        $type->isConstructed = $isConstructed;

        return $type;
    }
}
