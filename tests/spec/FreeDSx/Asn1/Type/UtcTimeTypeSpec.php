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
use FreeDSx\Asn1\Type\UtcTimeType;
use PhpSpec\ObjectBehavior;

class UtcTimeTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new \DateTime());
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(UtcTimeType::class);
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

    function it_should_default_to_seconds()
    {
        $this->getDateTimeFormat()->shouldBeEqualTo(AbstractTimeType::FORMAT_SECONDS);
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
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_UTC_TIME);
    }

    function it_should_set_the_time_format()
    {
        $this->setDateTimeFormat(AbstractTimeType::FORMAT_SECONDS);
        $this->setDateTimeFormat(AbstractTimeType::FORMAT_MINUTES);
        $this->getDateTimeFormat()->shouldBeEqualTo(AbstractTimeType::FORMAT_MINUTES);
    }

    function it_should_set_the_timezone_format()
    {
        $this->setTimeZoneFormat(AbstractTimeType::TZ_UTC);
        $this->setTimeZoneFormat(AbstractTimeType::TZ_DIFF);
        $this->getTimeZoneFormat()->shouldBeEqualTo(AbstractTimeType::TZ_DIFF);
    }

    function it_should_not_allow_setting_the_time_format_to_hours_or_fractions()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setDateTimeFormat', [AbstractTimeType::FORMAT_HOURS]);
        $this->shouldThrow(InvalidArgumentException::class)->during('setDateTimeFormat', [AbstractTimeType::FORMAT_FRACTIONS]);
    }

    function it_should_not_allow_setting_the_timezone_format_to_local()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setTimeZoneFormat', [AbstractTimeType::TZ_LOCAL]);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $dt = new \DateTime();

        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, $dt,AbstractTimeType::FORMAT_SECONDS, AbstractTimeType::TZ_UTC)->shouldBeLike(
            (new UtcTimeType($dt))->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setTagNumber(1)
        );
    }
}
