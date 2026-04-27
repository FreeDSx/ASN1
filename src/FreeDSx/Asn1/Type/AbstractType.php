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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use function count;

/**
 * Abstract ASN.1 type.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
/**
 * @template-covariant T
 *
 * @implements IteratorAggregate<int, AbstractType<mixed>>
 */
abstract class AbstractType implements Countable, IteratorAggregate
{
    public const TAG_CLASS_UNIVERSAL = 0x00;

    public const TAG_CLASS_CONTEXT_SPECIFIC = 0x80;

    public const TAG_CLASS_APPLICATION = 0x40;

    public const TAG_CLASS_PRIVATE = 0xC0;

    public const TAG_TYPE_BOOLEAN = 0x01;

    public const TAG_TYPE_INTEGER = 0x02;

    public const TAG_TYPE_BIT_STRING = 0x03;

    public const TAG_TYPE_OCTET_STRING = 0x04;

    public const TAG_TYPE_NULL = 0x05;

    public const TAG_TYPE_OID = 0x06;

    public const TAG_TYPE_OBJECT_DESCRIPTOR = 0x07;

    public const TAG_TYPE_EXTERNAL = 0x08;

    public const TAG_TYPE_REAL = 0x09;

    public const TAG_TYPE_ENUMERATED = 0x0A;

    public const TAG_TYPE_EMBEDDED_PDV = 0x0B;

    public const TAG_TYPE_UTF8_STRING = 0x0C;

    public const TAG_TYPE_RELATIVE_OID = 0x0D;

    public const TAG_TYPE_SEQUENCE = 0x10;

    public const TAG_TYPE_SET = 0x11;

    public const TAG_TYPE_NUMERIC_STRING = 0x12;

    public const TAG_TYPE_PRINTABLE_STRING = 0x13;

    public const TAG_TYPE_TELETEX_STRING = 0x14;

    public const TAG_TYPE_VIDEOTEX_STRING = 0x15;

    public const TAG_TYPE_IA5_STRING = 0x16;

    public const TAG_TYPE_UTC_TIME = 0x17;

    public const TAG_TYPE_GENERALIZED_TIME = 0x18;

    public const TAG_TYPE_GRAPHIC_STRING = 0x19;

    public const TAG_TYPE_VISIBLE_STRING = 0x1A;

    public const TAG_TYPE_GENERAL_STRING = 0x1B;

    public const TAG_TYPE_UNIVERSAL_STRING = 0x1C;

    public const TAG_TYPE_CHARACTER_STRING = 0x1D;

    public const TAG_TYPE_BMP_STRING = 0x1E;

    /**
     * Used in the tag to denote a constructed type.
     */
    public const CONSTRUCTED_TYPE = 0x20;

    /**
     * @var T
     */
    protected $value;

    /**
     * @var int|string|null
     */
    protected $tagNumber;

    /**
     * @var int
     */
    protected $taggingClass = self::TAG_CLASS_UNIVERSAL;

    /**
     * @var bool
     */
    protected $isConstructed = false;

    /**
     * @var array<AbstractType<mixed>>
     */
    protected $children = [];

    /**
     * @param T $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getIsConstructed(): bool
    {
        return $this->isConstructed;
    }

    /**
     * @return $this
     */
    public function setIsConstructed(bool $isConstructed)
    {
        $this->isConstructed = $isConstructed;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTagClass(int $taggingClass)
    {
        $this->taggingClass = $taggingClass;

        return $this;
    }

    public function getTagClass(): int
    {
        return $this->taggingClass;
    }

    /**
     * @return int|string|null
     */
    public function getTagNumber()
    {
        return $this->tagNumber;
    }

    /**
     * @param int|string|null $int
     *
     * @return $this
     */
    public function setTagNumber($int)
    {
        $this->tagNumber = $int;

        return $this;
    }

    /**
     * @return T
     */
    public function getValue()
    {
        return $this->value;
    }

    public function hasChild(int $index): bool
    {
        return isset($this->children[$index]);
    }

    /**
     * @param AbstractType<mixed> ...$types
     *
     * @return $this
     */
    public function setChildren(...$types)
    {
        $this->children = $types;

        return $this;
    }

    /**
     * @return array<AbstractType<mixed>>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return AbstractType<mixed>|null
     */
    public function getChild(int $index): ?AbstractType
    {
        return $this->children[$index] ?? null;
    }

    /**
     * @param AbstractType<mixed> ...$types
     *
     * @return $this
     */
    public function addChild(...$types)
    {
        foreach ($types as $type) {
            $this->children[] = $type;
        }

        return $this;
    }

    public function count(): int
    {
        return count($this->children);
    }

    /**
     * @return ArrayIterator<int, AbstractType<mixed>>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->children);
    }
}
