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
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
abstract class AbstractStringType extends AbstractType
{
    /**
     * @var bool
     */
    protected $isCharRestricted = false;

    public function __construct($value = '')
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
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * @return bool
     */
    public function isCharacterRestricted()
    {
        return $this->isCharRestricted;
    }

    /**
     * @param string|int $tagNumber
     * @param int $class
     * @param bool $isConstructed
     * @param string $value
     * @return AbstractStringType
     */
    public static function withTag($tagNumber, int $class, bool $isConstructed, $value = '')
    {
        $type = new static($value);
        $type->taggingClass = $class;
        $type->tagNumber = $tagNumber;
        $type->isConstructed = $isConstructed;

        return $type;
    }
}
