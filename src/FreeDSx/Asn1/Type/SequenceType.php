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
 * @extends AbstractType<null>
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class SequenceType extends AbstractType
{
    /**
     * @var int|string
     */
    protected $tagNumber = self::TAG_TYPE_SEQUENCE;

    /**
     * @var bool
     */
    protected $isConstructed = true;

    /**
     * @param AbstractType<mixed> ...$types
     */
    public function __construct(...$types)
    {
        parent::__construct(null);
        $this->children = $types;
    }

    /**
     * @param int|string $tagNumber
     * @param array<int, AbstractType<mixed>> $children
     *
     * @return static
     */
    public static function withTag(
        $tagNumber,
        int $class,
        array $children = []
    )
    {
        $type = new static();
        $type->children = $children;
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
