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
 * Represents an ASN1 null type.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class NullType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_NULL;

    public function __construct()
    {
        parent::__construct(null);
    }

    public static function withTag($tagNumber, $class)
    {
        $type = new self();
        $type->tagNumber = $tagNumber;
        $type->taggingClass = $class;

        return $type;
    }
}
