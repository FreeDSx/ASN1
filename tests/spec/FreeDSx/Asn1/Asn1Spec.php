<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1;

use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\BmpStringType;
use FreeDSx\Asn1\Type\BooleanType;
use FreeDSx\Asn1\Type\CharacterStringType;
use FreeDSx\Asn1\Type\EnumeratedType;
use FreeDSx\Asn1\Type\GeneralizedTimeType;
use FreeDSx\Asn1\Type\GeneralStringType;
use FreeDSx\Asn1\Type\GraphicStringType;
use FreeDSx\Asn1\Type\IA5StringType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\NullType;
use FreeDSx\Asn1\Type\NumericStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\OidType;
use FreeDSx\Asn1\Type\PrintableStringType;
use FreeDSx\Asn1\Type\RealType;
use FreeDSx\Asn1\Type\RelativeOidType;
use FreeDSx\Asn1\Type\SequenceOfType;
use FreeDSx\Asn1\Type\SequenceType;
use FreeDSx\Asn1\Type\SetOfType;
use FreeDSx\Asn1\Type\SetType;
use FreeDSx\Asn1\Type\TeletexStringType;
use FreeDSx\Asn1\Type\UniversalStringType;
use FreeDSx\Asn1\Type\UtcTimeType;
use FreeDSx\Asn1\Type\Utf8StringType;
use FreeDSx\Asn1\Type\VideotexStringType;
use FreeDSx\Asn1\Type\VisibleStringType;
use PhpSpec\ObjectBehavior;

class Asn1Spec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType(Asn1::class);
    }

    public function it_should_construct_a_sequence_type()
    {
        $this::sequence(new IntegerType(1), new IntegerType(2))->shouldBeLike(new SequenceType(
            new IntegerType(1),
            new IntegerType(2)
        ));
    }

    public function it_should_construct_a_boolean_type()
    {
        $this::boolean(false)->shouldBeLike(new BooleanType(false));
    }

    public function it_should_construct_an_integer_type()
    {
        $this::integer(1)->shouldBeLike(new IntegerType(1));
    }

    public function it_should_construct_an_enumerated_type()
    {
        $this::enumerated(1)->shouldBeLike(new EnumeratedType(1));
    }

    public function it_should_construct_a_null_type()
    {
        $this::null()->shouldBeLike(new NullType());
    }

    public function it_should_construct_a_sequence_of_type()
    {
        $this::sequenceOf(new IntegerType(1), new IntegerType(2))->shouldBeLike(new SequenceOfType(
            new IntegerType(1),
            new IntegerType(2)
        ));
    }

    public function it_should_construct_a_set_type()
    {
        $this::set(new BooleanType(true), new BooleanType(false))->shouldBeLike(new SetType(
            new BooleanType(true),
            new BooleanType(false)
        ));
    }

    public function it_should_construct_a_set_of_type()
    {
        $this::setOf(new BooleanType(true), new BooleanType(false))->shouldBeLike(new SetOfType(
            new BooleanType(true),
            new BooleanType(false)
        ));
    }

    public function it_should_construct_an_octet_string_type()
    {
        $this::octetString('foo')->shouldBeLike(new OctetStringType('foo'));
    }

    public function it_should_construct_a_bit_string()
    {
        $this::bitString('1000')->shouldBeLike(new BitStringType('1000'));
    }

    public function it_should_construct_a_bit_string_from_an_integer()
    {
        $this::bitStringFromInteger(8)->shouldBeLike(new BitStringType('1000'));
    }

    public function it_should_construct_a_bit_string_from_binary()
    {
        $this::bitStringFromBinary(hex2bin('08'))->shouldBeLike(new BitStringType('1000'));
    }

    public function it_should_construct_an_oid()
    {
        $this::oid('1.2.3')->shouldBeLike(new OidType('1.2.3'));
    }

    public function it_should_construct_a_relative_oid()
    {
        $this::relativeOid('3.100')->shouldBeLike(new RelativeOidType('3.100'));
    }

    public function it_should_construct_a_bmp_string()
    {
        $this->bmpString('foo')->shouldBeLike(new BmpStringType('foo'));
    }

    public function it_should_construct_a_character_string()
    {
        $this->charString('foo')->shouldBeLike(new CharacterStringType('foo'));
    }

    public function it_should_construct_a_generalized_time_string()
    {
        $date = new \DateTime();
        $this->generalizedTime($date)->shouldBeLike(new GeneralizedTimeType($date));
        $this->generalizedTime()->shouldReturnAnInstanceOf(GeneralizedTimeType::class);
    }

    public function it_should_construct_a_utc_time_string()
    {
        $date = new \DateTime();
        $this->utcTime($date)->shouldBeLike(new UtcTimeType($date));
        $this->utcTime()->shouldReturnAnInstanceOf(UtcTimeType::class);
    }

    public function it_should_construct_a_general_string()
    {
        $this->generalString('foo')->shouldBeLike(new GeneralStringType('foo'));
    }

    public function it_should_construct_a_graphic_string()
    {
        $this->graphicString('foo')->shouldBeLike(new GraphicStringType('foo'));
    }

    public function it_should_construct_an_ia5_string()
    {
        $this->ia5String('foo')->shouldBeLike(new IA5StringType('foo'));
    }

    public function it_should_construct_a_numeric_string()
    {
        $this->numericString('123')->shouldBeLike(new NumericStringType('123'));
    }

    public function it_should_construct_a_printable_string()
    {
        $this->printableString('foo')->shouldBeLike(new PrintableStringType('foo'));
    }

    public function it_should_construct_a_teletex_string()
    {
        $this->teletexString('foo')->shouldBeLike(new TeletexStringType('foo'));
    }

    public function it_should_construct_a_universal_string()
    {
        $this->universalString('foo')->shouldBeLike(new UniversalStringType('foo'));
    }

    public function it_should_construct_a_utf8_string()
    {
        $this->utf8String('foo')->shouldBeLike(new Utf8StringType('foo'));
    }

    public function it_should_construct_a_videotex_string()
    {
        $this->videotexString('foo')->shouldBeLike(new VideotexStringType('foo'));
    }

    public function it_should_construct_a_visible_string()
    {
        $this->visibleString('foo')->shouldBeLike(new VisibleStringType('foo'));
    }

    public function it_should_construct_a_real_type()
    {
        $this::real(0)->shouldBeLike(new RealType(0));
    }

    public function it_should_tag_a_type_as_context_specific()
    {
        $this::context(5, new BooleanType(true))->shouldBeLike((new BooleanType(true))->setTagNumber(5)->setTagClass(AbstractType::TAG_CLASS_CONTEXT_SPECIFIC));
    }

    public function it_should_tag_a_type_as_universal()
    {
        $this::universal(6, new BooleanType(true))->shouldBeLike((new BooleanType(true))->setTagNumber(6)->setTagClass(AbstractType::TAG_CLASS_UNIVERSAL));
    }

    public function it_should_tag_a_type_as_private()
    {
        $this::private(5, new BooleanType(true))->shouldBeLike((new BooleanType(true))->setTagNumber(5)->setTagClass(AbstractType::TAG_CLASS_PRIVATE));
    }
}
