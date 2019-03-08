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
 * Represents a Sequence type.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class SequenceType extends AbstractType
{
    /**
     * @var int
     */
    protected $tagNumber = self::TAG_TYPE_SEQUENCE;

    /**
     * @var bool
     */
    protected $isConstructed = true;

    /**
     * @param AbstractType ...$types
     */
    public function __construct(...$types)
    {
        parent::__construct(null);
        $this->children = $types;
    }

    /**
     * @param string|int $tagNumber
     * @param int $class
     * @param array $children
     * @return SequenceType
     */
    public static function withTag($tagNumber, int $class, array $children = [])
    {
        $type = new static();
        $type->children = $children;
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
