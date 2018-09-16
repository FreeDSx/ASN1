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
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class IntegerType extends AbstractType
{
    use BigIntTrait;

    protected $tagNumber = self::TAG_TYPE_INTEGER;

    public function __construct($integer)
    {
        $this->validate($integer);
        parent::__construct($integer);
    }

    /**
     * @param int|string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->validate($value);
        $this->value = $value;

        return $this;
    }
}
