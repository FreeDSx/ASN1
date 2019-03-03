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
use FreeDSx\Asn1\Exception\InvalidArgumentException;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type as EncodedType;
use FreeDSx\Asn1\Type\AbstractStringType;
use FreeDSx\Asn1\Type\AbstractTimeType;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\BooleanType;
use FreeDSx\Asn1\Type\EnumeratedType;
use FreeDSx\Asn1\Type\GeneralizedTimeType;
use FreeDSx\Asn1\Type\IncompleteType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\NullType;
use FreeDSx\Asn1\Type\OidType;
use FreeDSx\Asn1\Type\RealType;
use FreeDSx\Asn1\Type\RelativeOidType;
use FreeDSx\Asn1\Type\SetOfType;
use FreeDSx\Asn1\Type\SetType;
use FreeDSx\Asn1\Type\UtcTimeType;

/**
 * Basic Encoding Rules (BER) encoder.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class BerEncoder implements EncoderInterface
{
    /**
     * @var array
     */
    protected $tagMap = [
        AbstractType::TAG_CLASS_APPLICATION => [],
        AbstractType::TAG_CLASS_CONTEXT_SPECIFIC => [],
        AbstractType::TAG_CLASS_PRIVATE => [],
    ];

    /**
     * @var array
     */
    protected $options = [
        'bitstring_padding' => '0',
    ];

    /**
     * @var bool
     */
    protected $isGmpAvailable;

    /**
     * @var int
     */
    protected $pos;

    /**
     * @var int|null
     */
    protected $lastPos;

    /**
     * @var int
     */
    protected $maxLen;

    /**
     * @var string
     */
    protected $binary;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->isGmpAvailable = \extension_loaded('gmp');
        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($binary, array $tagMap = []) : AbstractType
    {
        $this->startEncoding($binary);
        if ($this->maxLen === 0) {
            throw new InvalidArgumentException('The data to decode cannot be empty.');
        } elseif ($this->maxLen === 1) {
            throw new PartialPduException('Received only 1 byte of data.');
        }
        $type = $this->decodeBytes($tagMap, true);
        $this->stopEncoding();

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(IncompleteType $type, int $tagType, array $tagMap = []) : AbstractType
    {
        $this->startEncoding($type->getValue());
        $newType = $this->getDecodedType($tagType, $type->getIsConstructed(), $this->maxLen, $tagMap);
        $this->stopEncoding();
        $newType->setTagNumber($type->getTagNumber())
            ->setTagClass($type->getTagClass());

        return $newType;
    }

    /**
     * {@inheritdoc}
     */
    public function encode(AbstractType $type) : string
    {
        $valueBytes = $this->getEncodedValue($type);
        $lengthBytes = $this->getEncodedLength(strlen($valueBytes));

        return $this->getEncodedTag($type).$lengthBytes.$valueBytes;
    }

    /**
     * Map universal types to specific tag class values when decoding.
     *
     * @param int $class
     * @param array $map
     * @return $this
     */
    public function setTagMap(int $class, array $map)
    {
        if (isset($this->tagMap[$class])) {
            $this->tagMap[$class] = $map;
        }

        return $this;
    }

    /**
     * Get the options for the encoder.
     *
     * @return array
     */
    public function getOptions() : array
    {
        return $this->options;
    }

    /**
     * Set the options for the encoder.
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        if (isset($options['bitstring_padding']) && \is_string($options['bitstring_padding'])) {
            $this->options['bitstring_padding'] = $options['bitstring_padding'];
        }

        return $this;
    }

    /**
     * @return int|null
     */
    public function getLastPosition() : ?int
    {
        return $this->lastPos;
    }

    protected function startEncoding(string $binary) : void
    {
        $this->binary = $binary;
        $this->lastPos = null;
        $this->pos = 0;
        $this->maxLen = \strlen($this->binary);
    }

    protected function stopEncoding() : void
    {
        $this->binary = null;
        $this->maxLen = 0;
        $this->lastPos = $this->pos;
        $this->pos = 0;
    }

    /**
     * Given a specific tag type / map, decode and construct the type.
     *
     * @param int|null $tagType
     * @param bool $isConstructed
     * @param int $length
     * @param array $tagMap
     * @return AbstractType
     * @throws EncoderException
     */
    protected function getDecodedType(?int $tagType, bool $isConstructed, $length, array $tagMap) : AbstractType
    {
        if (($this->maxLen - $this->pos) < $length) {
            throw new EncoderException('The actual length is less than the expected length.');
        }
        if ($tagType === null) {
            $type = new IncompleteType(\substr($this->binary, $this->pos, $length));
            $this->pos += $length;

            return $type;
        }

        switch ($tagType) {
            case AbstractType::TAG_TYPE_BOOLEAN:
                if ($length !== 1 || $isConstructed) {
                    throw new EncoderException(sprintf(
                        'The encoded boolean type is malformed.',
                        $length
                    ));
                }
                return $this->decodeBoolean();
                break;
            case AbstractType::TAG_TYPE_NULL:
                if ($length !== 0 || $isConstructed) {
                    throw new EncoderException('The encoded null type is malformed.');
                }
                return new EncodedType\NullType();
                break;
            case AbstractType::TAG_TYPE_INTEGER:
                if ($isConstructed) {
                    throw new EncoderException('The encoded integer type is malformed.');
                }
                return new EncodedType\IntegerType($this->decodeInteger($length));
                break;
            case AbstractType::TAG_TYPE_ENUMERATED:
                if ($isConstructed) {
                    throw new EncoderException('The encoded enumerated type is malformed.');
                }
                return new EncodedType\EnumeratedType($this->decodeInteger($length));
                break;
            case AbstractType::TAG_TYPE_REAL:
                if ($isConstructed) {
                    throw new EncoderException('The encoded real type is malformed.');
                }
                return new RealType($this->decodeReal($length));
                break;
            case AbstractType::TAG_TYPE_BIT_STRING:
                return new BitStringType($this->decodeBitString($length));
                break;
            case AbstractType::TAG_TYPE_OID:
                if ($isConstructed) {
                    throw new EncoderException('The encoded OID type is malformed.');
                }
                return new OidType($this->decodeOid($length));
                break;
            case AbstractType::TAG_TYPE_RELATIVE_OID:
                if ($isConstructed) {
                    throw new EncoderException('The encoded relative OID type is malformed.');
                }
                return new RelativeOidType($this->decodeRelativeOid($length));
                break;
            case AbstractType::TAG_TYPE_GENERALIZED_TIME:
                return $this->decodeGeneralizedTime($length);
                break;
            case AbstractType::TAG_TYPE_UTC_TIME:
                return $this->decodeUtcTime($length);
                break;
            case AbstractType::TAG_TYPE_OCTET_STRING:
                $type = new EncodedType\OctetStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_GENERAL_STRING:
                $type = new EncodedType\GeneralStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_VISIBLE_STRING:
                $type = new EncodedType\VisibleStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_BMP_STRING:
                $type = new EncodedType\BmpStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_CHARACTER_STRING:
                $type = new EncodedType\CharacterStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_UNIVERSAL_STRING:
                $type = new EncodedType\UniversalStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_GRAPHIC_STRING:
                $type = new EncodedType\GraphicStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_VIDEOTEX_STRING:
                $type = new EncodedType\VideotexStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_TELETEX_STRING:
                $type = new EncodedType\TeletexStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_PRINTABLE_STRING:
                $type = new EncodedType\PrintableStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_NUMERIC_STRING:
                $type = new EncodedType\NumericStringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_IA5_STRING:
                $type = new EncodedType\IA5StringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_UTF8_STRING:
                $type = new EncodedType\Utf8StringType(\substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                return $type;
                break;
            case AbstractType::TAG_TYPE_SEQUENCE:
                if (!$isConstructed) {
                    throw new EncoderException('The encoded sequence type is malformed.');
                }
                return new EncodedType\SequenceType(...$this->decodeConstructedType($length, $tagMap));
                break;
            case AbstractType::TAG_TYPE_SET:
                if (!$isConstructed) {
                    throw new EncoderException('The encoded set type is malformed.');
                }
                return new EncodedType\SetType(...$this->decodeConstructedType($length, $tagMap));
                break;
            default:
                throw new EncoderException(sprintf('Unable to decode value to a type for tag %s.', $tagType));
        }
    }

    /**
     * Get the encoded value for a specific type.
     *
     * @param AbstractType $type
     * @return string
     * @throws EncoderException
     */
    protected function getEncodedValue(AbstractType $type)
    {
        $bytes = null;

        switch ($type) {
            case $type instanceof BooleanType:
                $bytes = $this->encodeBoolean($type);
                break;
            case $type instanceof IntegerType:
            case $type instanceof EnumeratedType:
                $bytes = $this->encodeInteger($type);
                break;
            case $type instanceof RealType:
                $bytes = $this->encodeReal($type);
                break;
            case $type instanceof AbstractStringType:
                $bytes = $type->getValue();
                break;
            case $type instanceof SetOfType:
                $bytes = $this->encodeSetOf($type);
                break;
            case $type instanceof SetType:
                $bytes = $this->encodeSet($type);
                break;
            case $type->getIsConstructed():
                $bytes = $this->encodeConstructedType(...$type->getChildren());
                break;
            case $type instanceof BitStringType:
                $bytes = $this->encodeBitString($type);
                break;
            case $type instanceof OidType:
                $bytes = $this->encodeOid($type);
                break;
            case $type instanceof RelativeOidType:
                $bytes = $this->encodeRelativeOid($type);
                break;
            case $type instanceof GeneralizedTimeType:
                $bytes = $this->encodeGeneralizedTime($type);
                break;
            case $type instanceof UtcTimeType:
                $bytes = $this->encodeUtcTime($type);
                break;
            case $type instanceof NullType:
                break;
            default:
                throw new EncoderException(sprintf('The type "%s" is not currently supported.', $type));
        }

        return $bytes;
    }

    /**
     * @param array $tagMap
     * @param bool $isRoot
     * @return AbstractType
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeBytes(array $tagMap, bool $isRoot = false) : AbstractType
    {
        $tagMap = $tagMap + $this->tagMap;

        $tag = $this->getDecodedTag($isRoot);
        $length = $this->getDecodedLength();
        $tagType = $this->getTagType($tag['number'], $tag['class'], $tagMap);

        if (($this->maxLen - $this->pos) < $length['value_length']) {
            $message = sprintf(
                'The expected byte length was %s, but received %s.',
                $length['value_length'],
                ($this->maxLen - $this->pos)
            );
            if ($isRoot) {
                throw new PartialPduException($message);
            } else {
                throw new EncoderException($message);
            }
        }

        $type = $this->getDecodedType($tagType, $tag['constructed'], $length['value_length'], $tagMap);
        $type->setTagClass($tag['class']);
        $type->setTagNumber($tag['number']);
        $type->setIsConstructed($tag['constructed']);

        return $type;
    }

    /**
     * From a specific tag number and class try to determine what universal ASN1 type it should be mapped to. If there
     * is no mapping defined it will return null. In this case the binary data will be wrapped into an IncompleteType.
     *
     * @param int|string $tagNumber
     * @param int $tagClass
     * @param array $map
     * @return int|null
     */
    protected function getTagType($tagNumber, int $tagClass, array $map) : ?int
    {
        if ($tagClass === AbstractType::TAG_CLASS_UNIVERSAL) {
            return $tagNumber;
        }

        return $map[$tagClass][$tagNumber] ?? null;
    }

    /**
     * @return array
     * @throws EncoderException
     */
    protected function getDecodedLength() : array
    {
        $info = ['value_length' => isset($this->binary[$this->pos]) ? \ord($this->binary[$this->pos++]) : 0, 'length_length' => 1];

        if ($info['value_length'] === 128) {
            throw new EncoderException('Indefinite length encoding is not currently supported.');
        }

        # Long definite length has a special encoding.
        if ($info['value_length'] > 127) {
            $info = $this->decodeLongDefiniteLength($info);
        }

        return $info;
    }

    /**
     * @param array $info
     * @return array
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeLongDefiniteLength(array $info) : array
    {
        # The length of the length bytes is in the first 7 bits. So remove the MSB to get the value.
        $info['length_length'] = $info['value_length'] & ~0x80;

        # The value of 127 is marked as reserved in the spec
        if ($info['length_length'] === 127) {
            throw new EncoderException('The decoded length cannot be equal to 127 bytes.');
        }
        if (($info['length_length'] + 1) > ($this->maxLen - $this->pos)) {
            throw new PartialPduException('Not enough data to decode the length.');
        }
        $endAt = $this->pos + $info['length_length'];

        # Base 256 encoded
        $info['value_length'] = 0;
        for ($this->pos; $this->pos < $endAt; $this->pos++) {
            $info['value_length'] = $info['value_length'] * 256 + \ord($this->binary[$this->pos]);
        }
        # Add the byte that represents the length of the length
        $info['length_length']++;

        return $info;
    }

    /**
     * @param bool $isRoot
     * @return array
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function getDecodedTag(bool $isRoot) : array
    {
        $tag = \ord($this->binary[$this->pos++]);
        $info = ['class' => null, 'number' => null, 'constructed' => null, 'length' => 1];

        if ($tag & AbstractType::TAG_CLASS_APPLICATION && $tag & AbstractType::TAG_CLASS_CONTEXT_SPECIFIC) {
            $info['class'] = AbstractType::TAG_CLASS_PRIVATE;
        } elseif ($tag & AbstractType::TAG_CLASS_APPLICATION) {
            $info['class'] = AbstractType::TAG_CLASS_APPLICATION;
        } elseif ($tag & AbstractType::TAG_CLASS_CONTEXT_SPECIFIC) {
            $info['class'] = AbstractType::TAG_CLASS_CONTEXT_SPECIFIC;
        } else {
            $info['class'] = AbstractType::TAG_CLASS_UNIVERSAL;
        }
        $info['constructed'] = (bool) ($tag & AbstractType::CONSTRUCTED_TYPE);
        $info['number'] = $tag & ~0xe0;

        # Less than or equal to 30 is a low tag number represented in a single byte.
        if ($info['number'] <= 30) {
            return $info;
        }

        # A high tag number is determined using VLQ (like the OID identifier encoding) of the subsequent bytes.
        try {
            ['value' => $info['number'], 'length' => $info['length']] = $this->getVlqBytesToInt();
            # It's possible we only got part of the VLQ for the high tag, as there is no way to know it's ending length.
        } catch (EncoderException $e) {
            if ($isRoot) {
                throw new PartialPduException(
                    'Not enough data to decode the high tag number. No ending byte encountered for the VLQ bytes.'
                );
            }
            throw $e;
        }
        $info['length'] += 1;

        return $info;
    }

    /**
     * Given what should be VLQ bytes represent an int, get the int and the length of bytes.
     *
     * @return array
     * @throws EncoderException
     */
    protected function getVlqBytesToInt() : array
    {
        $value = 0;
        $isBigInt = false;
        $startAt = $this->pos;

        for ($this->pos; $this->pos < $this->maxLen; $this->pos++) {
            if (!$isBigInt) {
                $lshift = $value << 7;
                # An overflow bitshift will result in a negative number. This will check if GMP is available and flip it
                # to a bigint safe method in one shot.
                if ($lshift < 0) {
                    $isBigInt = true;
                    $this->throwIfBigIntGmpNeeded(true);
                    $value = \gmp_init($value);
                }
            }
            if ($isBigInt) {
                $lshift = \gmp_mul($value, \gmp_pow("2", 7));
            }
            $orVal = (\ord($this->binary[$this->pos]) & 0x7f);
            if ($isBigInt) {
                $value = \gmp_or($lshift, \gmp_init($orVal));
            } else {
                $value = $lshift | $orVal;
            }
            # We have reached the last byte if the MSB is not set.
            if ((\ord($this->binary[$this->pos]) & 0x80) === 0) {
                $this->pos++;
                return ['length' => ($this->pos - $startAt) + 1, 'value' => ($isBigInt ? \gmp_strval($value) : $value)];
            }
        }

        throw new EncoderException('Expected an ending byte to decode a VLQ, but none was found.');
    }

    /**
     * Get the bytes that represent variable length quantity.
     *
     * @param string|int $int
     * @return string
     * @throws EncoderException
     */
    protected function intToVlqBytes($int)
    {
        $bigint = \is_float($int + 0);
        $this->throwIfBigIntGmpNeeded($bigint);

        if ($bigint) {
            $int = \gmp_init($int);
            $bytes = \chr(\gmp_intval(\gmp_and(\gmp_init(0x7f), $int)));
            $int = \gmp_div($int, \gmp_pow(2, 7));
            $intVal = \gmp_intval($int);
        } else {
            $bytes = \chr(0x7f & $int);
            $int >>= 7;
            $intVal = $int;
        }

        while ($intVal > 0) {
            if ($bigint) {
                $bytes = \chr(\gmp_intval(\gmp_or(\gmp_and(\gmp_init(0x7f), $int), \gmp_init(0x80)))).$bytes;
                $int = \gmp_div($int, \gmp_pow("2", 7));
                $intVal = \gmp_intval($int);
            } else {
                $bytes = \chr((0x7f & $int) | 0x80).$bytes;
                $int >>= 7;
                $intVal = $int;
            }
        }

        return $bytes;
    }

    /**
     * Get the encoded tag byte(s) for a given type.
     *
     * @param AbstractType $type
     * @return string
     * @throws EncoderException
     */
    protected function getEncodedTag(AbstractType $type)
    {
        # The first byte of a tag always contains the class (bits 8 and 7) and whether it is constructed (bit 6).
        $tag = $type->getTagClass() | ($type->getIsConstructed() ? AbstractType::CONSTRUCTED_TYPE : 0);

        # For a high tag (>=31) we flip the first 5 bits on (0x1f) to make the first byte, then the subsequent bytes is
        # the VLV encoding of the tag number.
        if ($type->getTagNumber() >= 31) {
            $bytes = \chr($tag | 0x1f).$this->intToVlqBytes($type->getTagNumber());
            # For a tag less than 31, everything fits comfortably into a single byte.
        } else {
            $bytes = \chr($tag | $type->getTagNumber());
        }

        return $bytes;
    }

    /**
     * @param int $num
     * @return string
     * @throws EncoderException
     */
    protected function getEncodedLength(int $num)
    {
        # Short definite length, nothing to do
        if ($num < 128) {
            return \chr($num);
        } else {
            return $this->encodeLongDefiniteLength($num);
        }
    }

    /**
     * @param int $num
     * @return string
     * @throws EncoderException
     */
    protected function encodeLongDefiniteLength(int $num)
    {
        $bytes = '';
        while ($num) {
            $bytes = (\chr((int) ($num % 256))).$bytes;
            $num = (int) ($num / 256);
        }

        $length = \strlen($bytes);
        if ($length >= 127) {
            throw new EncoderException('The encoded length cannot be greater than or equal to 127 bytes');
        }

        return \chr(0x80 | $length).$bytes;
    }

    /**
     * @param BooleanType $type
     * @return string
     */
    protected function encodeBoolean(BooleanType $type)
    {
        return $type->getValue() ? "\xFF" : "\x00";
    }

    /**
     * @param BitStringType $type
     * @return string
     */
    protected function encodeBitString(BitStringType $type)
    {
        $data = $type->getValue();
        $length = \strlen($data);
        $unused = 0;
        if ($length % 8) {
            $unused = 8 - ($length % 8);
            $data = \str_pad($data, $length + $unused, $this->options['bitstring_padding']);
        }

        $bytes = \chr($unused);
        for ($i = 0; $i < \strlen($data) / 8; $i++) {
            $bytes .= \chr(\bindec(\substr($data, $i * 8, 8)));
        }

        return $bytes;
    }

    /**
     * @param RelativeOidType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeRelativeOid(RelativeOidType $type)
    {
        $oids = \explode('.', $type->getValue());

        $bytes = '';
        foreach ($oids as $oid) {
            $bytes .= $this->intToVlqBytes($oid);
        }

        return $bytes;
    }

    /**
     * @param OidType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeOid(OidType $type)
    {
        $oids = \explode('.', $type->getValue());
        if (\count($oids) < 2) {
            throw new EncoderException(sprintf('To encode the OID it must have at least 2 components: %s', $type->getValue()));
        }

        # The first and second components of the OID are represented by one byte using the formula: (X * 40) + Y
        $bytes = \chr(($oids[0] * 40) + $oids[1]);
        $length = \count($oids);
        for ($i = 2; $i < $length; $i++) {
            $bytes .= $this->intToVlqBytes($oids[$i]);
        }

        return $bytes;
    }

    /**
     * @param GeneralizedTimeType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeGeneralizedTime(GeneralizedTimeType $type)
    {
        return $this->encodeTime($type, 'YmdH');
    }

    /**
     * @param UtcTimeType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeUtcTime(UtcTimeType $type)
    {
        return $this->encodeTime($type, 'ymdH');
    }

    /**
     * @param AbstractTimeType $type
     * @param string $format
     * @return string
     * @throws EncoderException
     */
    protected function encodeTime(AbstractTimeType $type, string $format)
    {
        if ($type->getDateTimeFormat() === GeneralizedTimeType::FORMAT_SECONDS || $type->getDateTimeFormat() === GeneralizedTimeType::FORMAT_FRACTIONS) {
            $format .= 'is';
        } elseif ($type->getDateTimeFormat() === GeneralizedTimeType::FORMAT_MINUTES) {
            $format .= 'i';
        }

        # Is it possible to construct a datetime object in this way? Seems better to be safe with this check.
        if ($type->getValue()->format('H') === '24') {
            throw new EncoderException('Midnight must only be specified by 00, not 24.');
        }

        return $this->formatDateTime(
            clone $type->getValue(),
            $type->getDateTimeFormat(),
            $type->getTimeZoneFormat(),
            $format
        );
    }

    /**
     * @param \DateTime $dateTime
     * @param string $dateTimeFormat
     * @param string $tzFormat
     * @param string $format
     * @return string
     */
    protected function formatDateTime(\DateTime $dateTime, string $dateTimeFormat, string $tzFormat, string $format)
    {
        if ($tzFormat === GeneralizedTimeType::TZ_LOCAL) {
            $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } elseif ($tzFormat === GeneralizedTimeType::TZ_UTC) {
            $dateTime->setTimezone(new \DateTimeZone('UTC'));
        }
        $value = $dateTime->format($format);

        # Fractions need special formatting, so we cannot directly include them in the format above.
        $ms = '';
        if ($dateTimeFormat === GeneralizedTimeType::FORMAT_FRACTIONS) {
            $ms = (string) \rtrim($dateTime->format('u'), '0');
        }

        $tz = '';
        if ($tzFormat === GeneralizedTimeType::TZ_UTC) {
            $tz = 'Z';
        } elseif ($tzFormat === GeneralizedTimeType::TZ_DIFF) {
            $tz = $dateTime->format('O');
        }

        return $value.($ms !== '' ? '.'.$ms : '').$tz;
    }

    /**
     * @param AbstractType|IntegerType|EnumeratedType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeInteger(AbstractType $type) : string
    {
        $isBigInt = $type->isBigInt();
        $this->throwIfBigIntGmpNeeded($isBigInt);
        $int = $isBigInt ? \gmp_abs($type->getValue()) : \abs((int) $type->getValue());
        # Seems like a hack to check the big int this way...but probably the quickest
        $isNegative = $isBigInt ? $type->getValue()[0] === '-' : ($type->getValue() < 0);

        # Subtract one for Two's Complement...
        if ($isNegative) {
            $int = $isBigInt ? \gmp_sub($int, '1') : $int - 1;
        }

        if ($isBigInt) {
            $bytes = \gmp_export($int);
        } else {
            # dechex can produce uneven hex while hex2bin requires it to be even
            $hex = \dechex($int);
            $bytes = \hex2bin((\strlen($hex) % 2) === 0 ? $hex : '0' . $hex);
        }

        # Two's Complement, invert the bits...
        if ($isNegative) {
            $len = \strlen($bytes);
            for ($i = 0; $i < $len; $i++) {
                $bytes[$i] = ~$bytes[$i];
            }
        }

        # MSB == Most Significant Bit. The one used for the sign.
        $msbSet = (bool) (\ord($bytes[0]) & 0x80);
        if (!$isNegative && $msbSet) {
            $bytes = "\x00".$bytes;
        } elseif ($isNegative && !$msbSet) {
            $bytes = "\xFF".$bytes;
        }

        return $bytes;
    }

    /**
     * @param RealType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeReal(RealType $type)
    {
        $real = $type->getValue();

        # If the value is zero, the contents are omitted
        if ($real === ((float) 0)) {
            return '';
        }
        # If this is infinity, then a single octet of 0x40 is used.
        if ($real === INF) {
            return "\x40";
        }
        # If this is negative infinity, then a single octet of 0x41 is used.
        if ($real === -INF) {
            return "\x41";
        }

        // @todo Real type encoding/decoding is rather complex. Need to implement this yet.
        throw new EncoderException('Real type encoding of this value not yet implemented.');
    }

    /**
     * @param int $length
     * @return GeneralizedTimeType
     * @throws EncoderException
     */
    protected function decodeGeneralizedTime($length) : GeneralizedTimeType
    {
        return new GeneralizedTimeType(...$this->decodeTime('YmdH', GeneralizedTimeType::TIME_REGEX, GeneralizedTimeType::REGEX_MAP, $length));
    }

    /**
     * @param int $length
     * @return UtcTimeType
     * @throws EncoderException
     */
    protected function decodeUtcTime($length) : UtcTimeType
    {
        return new UtcTimeType(...$this->decodeTime('ymdH', UtcTimeType::TIME_REGEX, UtcTimeType::REGEX_MAP, $length));
    }

    /**
     * @param string $format
     * @param string $regex
     * @param array $matchMap
     * @param int $length
     * @return array
     * @throws EncoderException
     */
    protected function decodeTime(string $format, string $regex, array $matchMap, $length) : array
    {
        $bytes = \substr($this->binary, $this->pos, $length);
        $this->pos += $length;
        if (!\preg_match($regex, $bytes, $matches)) {
            throw new EncoderException('The datetime format is invalid and cannot be decoded.');
        }
        if ($matches[$matchMap['hours']] === '24') {
            throw new EncoderException('Midnight must only be specified by 00, but got 24.');
        }
        $tzFormat = AbstractTimeType::TZ_LOCAL;
        $dtFormat = AbstractTimeType::FORMAT_HOURS;

        # Minutes
        if (isset($matches[$matchMap['minutes']]) && $matches[$matchMap['minutes']] !== '') {
            $dtFormat = AbstractTimeType::FORMAT_MINUTES;
            $format .= 'i';
        }
        # Seconds
        if (isset($matches[$matchMap['seconds']]) && $matches[$matchMap['seconds']] !== '') {
            $dtFormat = AbstractTimeType::FORMAT_SECONDS;
            $format .= 's';
        }
        # Fractions of a second
        if (isset($matchMap['fractions']) && isset($matches[$matchMap['fractions']]) && $matches[$matchMap['fractions']] !== '') {
            $dtFormat = AbstractTimeType::FORMAT_FRACTIONS;
            $format .= '.u';
        }
        # Timezone
        if (isset($matches[$matchMap['timezone']]) && $matches[$matchMap['timezone']] !== '') {
            $tzFormat = $matches[$matchMap['timezone']] === 'Z' ? AbstractTimeType::TZ_UTC : AbstractTimeType::TZ_DIFF;
            $format .= 'T';
        }
        $this->validateDateFormat($matches, $matchMap);

        $dateTime = \DateTime::createFromFormat($format, $bytes);
        if ($dateTime === false) {
            throw new EncoderException('Unable to decode time to a DateTime object.');
        }
        $bytes = null;

        return [$dateTime, $dtFormat, $tzFormat];
    }

    /**
     * Some encodings have specific restrictions. Allow them to override and validate this.
     *
     * @param array $matches
     * @param array $matchMap
     */
    protected function validateDateFormat(array $matches, array $matchMap)
    {
    }

    /**
     * @param $length
     * @return string
     * @throws EncoderException
     */
    protected function decodeOid($length) : string
    {
        if ($length === 0) {
            throw new EncoderException('Zero length not permitted for an OID type.');
        }
        # The first 2 digits are contained within the first byte
        $byte = \ord($this->binary[$this->pos++]);
        $first = (int) ($byte / 40);
        $second =  $byte - (40 * $first);
        $length--;

        $oid = $first.'.'.$second;
        if ($length) {
            $oid .= '.'.$this->decodeRelativeOid($length);
        }

        return $oid;
    }

    /**
     * @param $length
     * @return string
     * @throws EncoderException
     */
    protected function decodeRelativeOid($length) : string
    {
        if ($length === 0) {
            throw new EncoderException('Zero length not permitted for an OID type.');
        }
        $oid = '';
        $endAt = $this->pos + $length;

        while ($this->pos < $endAt) {
            ['value' => $int] = $this->getVlqBytesToInt();
            $oid .= ($oid === '' ? '' : '.').$int;
        }

        return $oid;
    }

    /**
     * @return BooleanType
     */
    protected function decodeBoolean() : BooleanType
    {
        return new BooleanType(\ord($this->binary[$this->pos++]) !== 0);
    }

    /**
     * @param int $length
     * @return string
     * @throws EncoderException
     */
    protected function decodeBitString($length) : string
    {
        # The first byte represents the number of unused bits at the end.
        $unused = \ord($this->binary[$this->pos++]);

        if ($unused > 7) {
            throw new EncoderException(sprintf(
                'The unused bits in a bit string must be between 0 and 7, got: %s',
                $unused
            ));
        }
        if ($unused > 0 && $length < 1) {
            throw new EncoderException(sprintf(
                'If the bit string is empty the unused bits must be set to 0. However, it is set to %s with %s octets.',
                $unused,
                $length
            ));
        }
        $length--;

        return $this->binaryToBitString($length, $unused);
    }

    /**
     * @param int $length
     * @param int $unused
     * @return string
     */
    protected function binaryToBitString(int $length, int $unused) : string
    {
        $bitstring = '';
        $endAt = $this->pos + $length;

        for ($this->pos; $this->pos < $endAt; $this->pos++) {
            $octet = \sprintf( "%08d", \decbin(\ord($this->binary[$this->pos])));
            if ($this->pos === ($endAt - 1) && $unused) {
                $bitstring .= \substr($octet, 0, ($unused * -1));
            } else {
                $bitstring .= $octet;
            }
        }

        return $bitstring;
    }

    /**
     * @param int $length
     * @return string|int number
     * @throws EncoderException
     */
    protected function decodeInteger($length)
    {
        if ($length === 0) {
            throw new EncoderException('Zero length not permitted for an integer type.');
        }
        $bytes = \substr($this->binary, $this->pos, $length);
        $this->pos += $length;

        $isNegative = (\ord($bytes[0]) & 0x80);
        # Need to reverse Two's Complement. Invert the bits...
        if ($isNegative) {
            for ($i = 0; $i < $length; $i++) {
                $bytes[$i] = ~$bytes[$i];
            }
        }
        $int = \hexdec(\bin2hex($bytes));

        $isBigInt = \is_float($int);
        $this->throwIfBigIntGmpNeeded($isBigInt);
        if ($isBigInt) {
            $int = \gmp_import($bytes);
        }
        $bytes = null;

        # Complete Two's Complement by adding 1 and turning it negative...
        if ($isNegative) {
            $int = $isBigInt ? \gmp_neg(\gmp_add($int, "1")) : ($int + 1) * -1;
        }

        return $isBigInt ? \gmp_strval($int) : $int;
    }

    /**
     * @param bool $isBigInt
     * @throws EncoderException
     */
    protected function throwIfBigIntGmpNeeded(bool $isBigInt) : void
    {
        if ($isBigInt && !$this->isGmpAvailable) {
            throw new EncoderException(sprintf(
                'An integer higher than PHP_INT_MAX int (%s) was encountered and the GMP extension is not loaded.',
                PHP_INT_MAX
            ));
        }
    }

    /**
     * @param int $length
     * @return float
     * @throws EncoderException
     */
    protected function decodeReal($length) : float
    {
        if ($length === 0) {
            return 0;
        }

        $ident = \ord($this->binary[$this->pos++]);
        if ($ident === 0x40) {
            return INF;
        }
        if ($ident === 0x41) {
            return -INF;
        }

        // @todo Real type encoding/decoding is rather complex. Need to implement this yet.
        throw new EncoderException('The real type encoding encountered is not yet implemented.');
    }

    /**
     * Encoding subsets may require specific ordering on set types. Allow this to be overridden.
     *
     * @param SetType $set
     * @return string
     * @throws EncoderException
     */
    protected function encodeSet(SetType $set)
    {
        return $this->encodeConstructedType(...$set->getChildren());
    }

    /**
     * Encoding subsets may require specific ordering on set of types. Allow this to be overridden.
     *
     * @param SetOfType $set
     * @return string
     * @throws EncoderException
     */
    protected function encodeSetOf(SetOfType $set)
    {
        return $this->encodeConstructedType(...$set->getChildren());
    }

    /**
     * @param AbstractType[] $types
     * @return string
     * @throws EncoderException
     */
    protected function encodeConstructedType(AbstractType ...$types)
    {
        $bytes = '';

        foreach ($types as $type) {
            $bytes .= $this->encode($type);
        }

        return $bytes;
    }

    /**
     * @param int $length
     * @param array $tagMap
     * @return array
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeConstructedType($length, array $tagMap)
    {
        $children = [];
        $endAt = $this->pos + $length;

        while ($this->pos < $endAt) {
            $children[] = $this->decodeBytes($tagMap);
        }

        return $children;
    }
}
