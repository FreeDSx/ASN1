<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Asn1\Encoder;

use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Type\AbstractStringType;
use FreeDSx\Asn1\Type\AbstractTimeType;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\SetTrait;
use FreeDSx\Asn1\Type\SetType;

/**
 * Distinguished Encoding Rules (DER) encoder.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class DerEncoder extends BerEncoder
{
    use CerDerTrait,
        SetTrait;

    protected bool $validatesDecodedTypes = true;

    public function __construct(EncoderOptions $options = new EncoderOptions())
    {
        parent::__construct($options);
        $this->setOptions(new EncoderOptions(bitstringPadding: '0'));
    }

    /**
     * @param AbstractType<mixed> $type
     */
    public function encode(AbstractType $type): string
    {
        $this->validate($type);

        return parent::encode($type);
    }

    /**
     * {@inheritdoc}
     *
     * @param AbstractType<mixed> $type
     */
    protected function validateDecodedType(AbstractType $type): void
    {
        parent::validateDecodedType($type);
        $this->validate($type);
    }

    /**
     * {@inheritdoc}
     */
    protected function decodeLongDefiniteLength(
        string $bytes,
        int $pos,
        int $end,
        int $count,
    ): int {
        $length = parent::decodeLongDefiniteLength(
            $bytes,
            $pos,
            $end,
            $count,
        );

        if ($length < 127) {
            throw new EncoderException('DER must be encoded using the shortest possible length form, but it is not.');
        }

        return $length;
    }

    /**
     * {@inheritdoc}
     *
     * @throws EncoderException
     */
    protected function encodeSet(SetType $set): string
    {
        return $this->encodeConstructedType($this->canonicalize($set->getChildren()));
    }

    /**
     * @param AbstractType<mixed> $type
     *
     * @throws EncoderException
     */
    protected function validate(AbstractType $type): void
    {
        if ($type instanceof OctetStringType && $type->getIsConstructed()) {
            throw new EncoderException('The octet string must be primitive. It cannot be constructed.');
        }
        if ($type instanceof BitStringType && $type->getIsConstructed()) {
            throw new EncoderException('The bit string must be primitive. It cannot be constructed.');
        }
        if ($type instanceof AbstractStringType && $type->isCharacterRestricted() && $type->getIsConstructed()) {
            throw new EncoderException('Character restricted string types must be primitive.');
        }
        if ($type instanceof AbstractTimeType) {
            $this->validateTimeType($type);
        }
    }
}
