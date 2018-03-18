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
 * Represents a Generalized Time type.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class GeneralizedTimeType extends AbstractType
{
    protected $tagNumber = self::TAG_TYPE_GENERALIZED_TIME;

    /**
     * @param \DateTime $dateTime
     */
    public function __construct(\DateTime $dateTime)
    {
        parent::__construct($dateTime);
    }

    /**
     * @param \DateTime $dateTime
     * @return $this
     */
    public function setValue(\DateTime $dateTime)
    {
        $this->value = $dateTime;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getValue() : \DateTime
    {
        return $this->value;
    }
}
