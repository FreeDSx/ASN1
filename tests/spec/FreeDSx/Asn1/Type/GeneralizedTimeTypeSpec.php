<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1\Type;

use FreeDSx\Asn1\Exception\InvalidArgumentException;
use FreeDSx\Asn1\Type\AbstractTimeType;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\GeneralizedTimeType;
use PhpSpec\ObjectBehavior;

class GeneralizedTimeTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new \DateTime());
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(GeneralizedTimeType::class);
    }

    function it_should_be_constructed_with_a_datetime_of_the_current_time_if_none_is_given()
    {
        $this->beConstructedWith();
        $this->getValue()->shouldBeAnInstanceOf(\DateTime::class);
    }

    function it_should_extend_abstract_time()
    {
        $this->shouldBeAnInstanceOf(AbstractTimeType::class);
    }

    function it_should_default_to_fractions_of_a_second()
    {
        $this->getDateTimeFormat()->shouldBeEqualTo(AbstractTimeType::FORMAT_FRACTIONS);
    }

    function it_should_default_to_a_UTC_timezone_ending()
    {
        $this->getTimeZoneFormat()->shouldBeEqualTo(AbstractTimeType::TZ_UTC);
    }

    function it_should_set_the_value()
    {
        $date = new \DateTime();
        $this->getValue()->shouldBeAnInstanceOf(\DateTime::class);
        $this->setValue($date)->getValue()->shouldBeEqualTo($date);
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_GENERALIZED_TIME);
    }

    function it_should_set_the_time_format()
    {
        $this->setDateTimeFormat(AbstractTimeType::FORMAT_SECONDS);
        $this->setDateTimeFormat(AbstractTimeType::FORMAT_MINUTES);
        $this->setDateTimeFormat(AbstractTimeType::FORMAT_FRACTIONS);
        $this->setDateTimeFormat(AbstractTimeType::FORMAT_HOURS);
        $this->getDateTimeFormat()->shouldBeEqualTo(AbstractTimeType::FORMAT_HOURS);
    }

    function it_should_set_the_timezone_format()
    {
        $this->setTimeZoneFormat(AbstractTimeType::TZ_UTC);
        $this->setTimeZoneFormat(AbstractTimeType::TZ_DIFF);
        $this->setTimeZoneFormat(AbstractTimeType::TZ_LOCAL);
        $this->getTimeZoneFormat()->shouldBeEqualTo(AbstractTimeType::TZ_LOCAL);
    }

    function it_should_not_allow_setting_the_time_format_to_something_invalid()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setDateTimeFormat', ['foo']);
    }

    function it_should_not_allow_setting_the_timezone_format_to_something_invalid()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setTimeZoneFormat', ['foo']);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $dt = new \DateTime();

        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, $dt,AbstractTimeType::FORMAT_FRACTIONS, AbstractTimeType::TZ_UTC)->shouldBeLike(
            (new GeneralizedTimeType($dt))->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setTagNumber(1)
        );
    }
}
