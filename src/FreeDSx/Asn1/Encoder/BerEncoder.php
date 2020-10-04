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
     * Used to represent a bool false binary string.
     */
    protected const BOOL_FALSE = "\x00";

    /**
     * Used to represent a bool true binary string.
     */
    protected const BOOL_TRUE = "\xff";

    /**
     * Anything greater than this we assume we may need to deal with a bigint in an OIDs second component.
     */
    protected const MAX_SECOND_COMPONENT = PHP_INT_MAX - 80;

    /**
     * @var array
     */
    protected $tagMap = [
        AbstractType::TAG_CLASS_APPLICATION => [],
        AbstractType::TAG_CLASS_CONTEXT_SPECIFIC => [],
        AbstractType::TAG_CLASS_PRIVATE => [],
    ];

    protected $tmpTagMap = [];

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
     * @var string|null
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
        $this->startEncoding($binary, $tagMap);
        if ($this->maxLen === 0) {
            throw new InvalidArgumentException('The data to decode cannot be empty.');
        } elseif ($this->maxLen === 1) {
            throw new PartialPduException('Received only 1 byte of data.');
        }
        $type = $this->decodeBytes(true);
        $this->stopEncoding();

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(IncompleteType $type, int $tagType, array $tagMap = []) : AbstractType
    {
        $lastPos = $this->lastPos;
        $this->startEncoding($type->getValue(), $tagMap);
        $newType = $this->decodeBytes(false, $tagType, $this->maxLen, $type->getIsConstructed(), AbstractType::TAG_CLASS_UNIVERSAL);
        $this->stopEncoding();
        $newType->setTagNumber($type->getTagNumber())
            ->setTagClass($type->getTagClass());
        $this->lastPos = $lastPos;

        return $newType;
    }

    /**
     * {@inheritdoc}
     */
    public function encode(AbstractType $type) : string
    {
        switch ($type) {
            case $type instanceof BooleanType:
                $bytes = $type->getValue() ? self::BOOL_TRUE : self::BOOL_FALSE;
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
                $bytes = '';
                break;
            default:
                throw new EncoderException(sprintf(
                    'The type "%s" is not currently supported.',
                    get_class($type)
                ));
        }
        $length = \strlen($bytes);
        $bytes = ($length < 128)  ? \chr($length).$bytes : $this->encodeLongDefiniteLength($length).$bytes;

        # The first byte of a tag always contains the class (bits 8 and 7) and whether it is constructed (bit 6).
        $tag = $type->getTagClass() | ($type->getIsConstructed() ? AbstractType::CONSTRUCTED_TYPE : 0);

        $this->validateNumericInt($type->getTagNumber());
        # For a high tag (>=31) we flip the first 5 bits on (0x1f) to make the first byte, then the subsequent bytes is
        # the VLV encoding of the tag number.
        if ($type->getTagNumber() >= 31) {
            $bytes = \chr($tag | 0x1f).$this->intToVlqBytes($type->getTagNumber()).$bytes;
            # For a tag less than 31, everything fits comfortably into a single byte.
        } else {
            $bytes = \chr($tag | $type->getTagNumber()).$bytes;
        }

        return $bytes;
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

    protected function startEncoding(string $binary, array $tagMap) : void
    {
        $this->tmpTagMap = $tagMap + $this->tagMap;
        $this->binary = $binary;
        $this->lastPos = null;
        $this->pos = 0;
        $this->maxLen = \strlen($this->binary);
    }

    protected function stopEncoding() : void
    {
        $this->tmpTagMap = [];
        $this->binary = null;
        $this->maxLen = 0;
        $this->lastPos = $this->pos;
        $this->pos = 0;
    }

    /**
     * @param bool $isRoot
     * @param null|int $tagType
     * @param null|int $length
     * @param null|bool $isConstructed
     * @param null|int $class
     * @return AbstractType
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeBytes(bool $isRoot = false, $tagType = null, $length = null, $isConstructed = null, $class = null) : AbstractType
    {
        $tagNumber = $tagType;
        if ($tagType === null) {
            $tag = \ord($this->binary[$this->pos++]);
            $class = $tag & 0xc0;
            $isConstructed = (bool)($tag & AbstractType::CONSTRUCTED_TYPE);
            $tagNumber = $tag & ~0xe0;

            # Less than or equal to 30 is a low tag number represented in a single byte.
            # A high tag number is determined using VLQ (like the OID identifier encoding) of the subsequent bytes.
            if ($tagNumber > 30) {
                try {
                    $tagNumber = $this->getVlqBytesToInt();
                    # It's possible we only got part of the VLQ for the high tag, as there is no way to know its ending length.
                } catch (EncoderException $e) {
                    if ($isRoot) {
                        throw new PartialPduException(
                            'Not enough data to decode the high tag number. No ending byte encountered for the VLQ bytes.'
                        );
                    }
                    throw $e;
                }
            }

            $length = \ord($this->binary[$this->pos++]);
            if ($length === 128) {
                throw new EncoderException('Indefinite length encoding is not currently supported.');
            }
            if ($length > 128) {
                $length = $this->decodeLongDefiniteLength($length);
            }
            $tagType = ($class === AbstractType::TAG_CLASS_UNIVERSAL) ? $tagNumber : ($this->tmpTagMap[$class][$tagNumber] ?? null);

            if (($this->maxLen - $this->pos) < $length) {
                $message = sprintf(
                    'The expected byte length was %s, but received %s.',
                    $length,
                    ($this->maxLen - $this->pos)
                );
                if ($isRoot) {
                    throw new PartialPduException($message);
                } else {
                    throw new EncoderException($message);
                }
            }

            if ($tagType === null) {
                $type = new IncompleteType(\substr($this->binary, $this->pos, $length), $tagNumber, $class, $isConstructed);
                $this->pos += $length;

                return $type;
            }
        }

        # Yes...this huge switch statement should be a separate method. However, it is faster inline when decoding
        # lots of data (such as thousands of ASN.1 structures at a time).
        switch ($tagType) {
            case AbstractType::TAG_TYPE_BOOLEAN:
                if ($length !== 1 || $isConstructed) {
                    throw new EncoderException('The encoded boolean type is malformed.');
                }
                $type = EncodedType\BooleanType::withTag($tagNumber, $class, $this->decodeBoolean());
                break;
            case AbstractType::TAG_TYPE_NULL:
                if ($length !== 0 || $isConstructed) {
                    throw new EncoderException('The encoded null type is malformed.');
                }
                $type = EncodedType\NullType::withTag($tagNumber, $class);
                break;
            case AbstractType::TAG_TYPE_INTEGER:
                if ($isConstructed) {
                    throw new EncoderException('The encoded integer type is malformed.');
                }
                $type = EncodedType\IntegerType::withTag($tagNumber, $class, $this->decodeInteger($length));
                break;
            case AbstractType::TAG_TYPE_ENUMERATED:
                if ($isConstructed) {
                    throw new EncoderException('The encoded enumerated type is malformed.');
                }
                $type = EncodedType\EnumeratedType::withTag($tagNumber, $class, $this->decodeInteger($length));
                break;
            case AbstractType::TAG_TYPE_REAL:
                if ($isConstructed) {
                    throw new EncoderException('The encoded real type is malformed.');
                }
                $type = RealType::withTag($tagNumber, $class, $this->decodeReal($length));
                break;
            case AbstractType::TAG_TYPE_BIT_STRING:
                $type = EncodedType\BitStringType::withTag($tagNumber, $class, $isConstructed, $this->decodeBitString($length));
                break;
            case AbstractType::TAG_TYPE_OID:
                if ($isConstructed) {
                    throw new EncoderException('The encoded OID type is malformed.');
                }
                $type = OidType::withTag($tagNumber, $class, $this->decodeOid($length));
                break;
            case AbstractType::TAG_TYPE_RELATIVE_OID:
                if ($isConstructed) {
                    throw new EncoderException('The encoded relative OID type is malformed.');
                }
                $type = RelativeOidType::withTag($tagNumber, $class, $this->decodeRelativeOid($length));
                break;
            case AbstractType::TAG_TYPE_GENERALIZED_TIME:
                $type = EncodedType\GeneralizedTimeType::withTag($tagNumber, $class, $isConstructed, ...$this->decodeGeneralizedTime($length));
                break;
            case AbstractType::TAG_TYPE_UTC_TIME:
                $type = EncodedType\UtcTimeType::withTag($tagNumber, $class, $isConstructed, ...$this->decodeUtcTime($length));
                break;
            case AbstractType::TAG_TYPE_OCTET_STRING:
                $type = EncodedType\OctetStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_GENERAL_STRING:
                $type = EncodedType\GeneralStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_VISIBLE_STRING:
                $type = EncodedType\VisibleStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_BMP_STRING:
                $type = EncodedType\BmpStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_CHARACTER_STRING:
                $type = EncodedType\CharacterStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_UNIVERSAL_STRING:
                $type = EncodedType\UniversalStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_GRAPHIC_STRING:
                $type = EncodedType\GraphicStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_VIDEOTEX_STRING:
                $type = EncodedType\VideotexStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_TELETEX_STRING:
                $type = EncodedType\TeletexStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_PRINTABLE_STRING:
                $type = EncodedType\PrintableStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_NUMERIC_STRING:
                $type = EncodedType\NumericStringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_IA5_STRING:
                $type = EncodedType\IA5StringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_UTF8_STRING:
                $type = EncodedType\Utf8StringType::withTag($tagNumber, $class, $isConstructed, \substr($this->binary, $this->pos, $length));
                $this->pos += $length;
                break;
            case AbstractType::TAG_TYPE_SEQUENCE:
                if (!$isConstructed) {
                    throw new EncoderException('The encoded sequence type is malformed.');
                }
                $type = EncodedType\SequenceType::withTag($tagNumber, $class, $this->decodeConstructedType($length));
                break;
            case AbstractType::TAG_TYPE_SET:
                if (!$isConstructed) {
                    throw new EncoderException('The encoded set type is malformed.');
                }
                $type = EncodedType\SetType::withTag($tagNumber, $class, $this->decodeConstructedType($length));
                break;
            default:
                throw new EncoderException(sprintf('Unable to decode value to a type for tag %s.', $tagType));
        }

        return $type;
    }

    /**
     * @param int $length
     * @return int
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeLongDefiniteLength(int $length) : int
    {
        # The length of the length bytes is in the first 7 bits. So remove the MSB to get the value.
        $lengthOfLength = $length & ~0x80;

        # The value of 127 is marked as reserved in the spec
        if ($lengthOfLength === 127) {
            throw new EncoderException('The decoded length cannot be equal to 127 bytes.');
        }
        if ($lengthOfLength > ($this->maxLen - $this->pos)) {
            throw new PartialPduException('Not enough data to decode the length.');
        }
        $endAt = $this->pos + $lengthOfLength;

        # Base 256 encoded
        $length = 0;
        for ($this->pos; $this->pos < $endAt; $this->pos++) {
            $length = $length * 256 + \ord($this->binary[$this->pos]);
        }

        return $length;
    }

    /**
     * Given what should be VLQ bytes represent an int, get the int and the length of bytes.
     *
     * @return string|int
     * @throws EncoderException
     */
    protected function getVlqBytesToInt()
    {
        $value = 0;
        $lshift = 0;
        $isBigInt = false;

        for ($this->pos; $this->pos < $this->maxLen; $this->pos++) {
            if (!$isBigInt) {
                $lshift = $value << 7;
                # An overflow bitshift will result in a negative number or zero.
                # This will check if GMP is available and flip it to a bigint safe method in one shot.
                if ($value > 0 && $lshift <= 0) {
                    $isBigInt = true;
                    $this->throwIfBigIntGmpNeeded(true);
                    $value = \gmp_init($value);
                }
            }
            if ($isBigInt) {
                $lshift = \gmp_mul($value, \gmp_pow('2', 7));
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

                return $isBigInt ? \gmp_strval($value) : $value;
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
                $int = \gmp_div($int, \gmp_pow('2', 7));
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
     * @param string|integer $integer
     * @throws EncoderException
     */
    protected function validateNumericInt($integer) : void
    {
        if (\is_int($integer)) {
            return;
        }
        if (\is_string($integer) && \is_numeric($integer) && \strpos($integer, '.') === false) {
            return;
        }

        throw new EncoderException(sprintf(
            'The value to encode for "%s" must be numeric.',
            $integer
        ));
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
            $length = \strlen($data);
        }

        $bytes = \chr($unused);
        for ($i = 0; $i < $length / 8; $i++) {
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
        /** @var int[] $oids */
        $oids = \explode('.', $type->getValue());
        $length = \count($oids);
        if ($length < 2) {
            throw new EncoderException(sprintf(
                'To encode the OID it must have at least 2 components: %s',
                $type->getValue()
            ));
        }
        if ($oids[0] > 2) {
            throw new EncoderException(sprintf(
                'The value of the first OID component cannot be greater than 2. Received:  %s',
                $oids[0]
            ));
        }

        # The first and second components of the OID are represented using the formula: (X * 40) + Y
        if ($oids[1] > self::MAX_SECOND_COMPONENT) {
            $this->throwIfBigIntGmpNeeded(true);
            $firstAndSecond = \gmp_strval(\gmp_add((string)($oids[0] * 40), $oids[1]));
        } else {
            $firstAndSecond = ($oids[0] * 40) + $oids[1];
        }
        $bytes = $this->intToVlqBytes($firstAndSecond);

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
     * @param IntegerType|EnumeratedType $type
     * @return string
     * @throws EncoderException
     */
    protected function encodeInteger(AbstractType $type) : string
    {
        $int = $type->getValue();
        $this->validateNumericInt($int);
        $isBigInt = $type->isBigInt();
        $isNegative = ($int < 0);
        $this->throwIfBigIntGmpNeeded($isBigInt);
        if ($isNegative) {
            $int = $isBigInt ? \gmp_abs($type->getValue()) : ($int * -1);
        }

        # Subtract one for Two's Complement...
        if ($isNegative) {
            $int = $isBigInt ? \gmp_sub($int, '1') : $int - 1;
        }

        if ($isBigInt) {
            $bytes = \gmp_export($int);
        } else {
            # dechex can produce uneven hex while hex2bin requires it to be even
            $hex = \dechex($int);
            $bytes = \hex2bin((\strlen($hex) % 2) === 0 ? $hex : '0'.$hex);
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
            $bytes = self::BOOL_FALSE.$bytes;
        } elseif ($isNegative && !$msbSet) {
            $bytes = self::BOOL_TRUE.$bytes;
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
     * @return array
     * @throws EncoderException
     */
    protected function decodeGeneralizedTime($length) : array
    {
        return $this->decodeTime('YmdH', GeneralizedTimeType::TIME_REGEX, GeneralizedTimeType::REGEX_MAP, $length);
    }

    /**
     * @param int $length
     * @return array
     * @throws EncoderException
     */
    protected function decodeUtcTime($length) : array
    {
        return $this->decodeTime('ymdH', UtcTimeType::TIME_REGEX, UtcTimeType::REGEX_MAP, $length);
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
     * @param int $length
     * @return string
     * @throws EncoderException
     */
    protected function decodeOid($length) : string
    {
        if ($length === 0) {
            throw new EncoderException('Zero length not permitted for an OID type.');
        }
        # We need to get the first part here, as it's used to determine the first 2 components.
        $startedAt = $this->pos;
        $firstPart = $this->getVlqBytesToInt();

        if ($firstPart < 80) {
            $oid = \floor($firstPart / 40).'.'.($firstPart % 40);
        } else {
            $isBigInt = ($firstPart > PHP_INT_MAX);
            $this->throwIfBigIntGmpNeeded($isBigInt);
            # In this case, the first identifier is always 2.
            # But there is no limit on the value of the second identifier.
            $oid = '2.'.($isBigInt ? \gmp_strval(\gmp_sub($firstPart, '80')) : (int)$firstPart - 80);
        }

        # We could potentially have nothing left to decode at this point.
        $oidLength = $length - ($this->pos - $startedAt);
        $subIdentifiers = ($oidLength === 0) ? '' : '.'.$this->decodeRelativeOid($oidLength);

        return $oid.$subIdentifiers;
    }

    /**
     * @param int $length
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
            $oid .= ($oid === '' ? '' : '.').$this->getVlqBytesToInt();
        }

        return $oid;
    }

    /**
     * @return bool
     */
    protected function decodeBoolean() : bool
    {
        return ($this->binary[$this->pos++] !== self::BOOL_FALSE);
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
            $int = $isBigInt ? \gmp_neg(\gmp_add($int, '1')) : ($int + 1) * -1;
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
     * @param AbstractType ...$types
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
     * @return array
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeConstructedType($length)
    {
        $children = [];
        $endAt = $this->pos + $length;

        while ($this->pos < $endAt) {
            $children[] = $this->decodeBytes();
        }

        return $children;
    }
}
