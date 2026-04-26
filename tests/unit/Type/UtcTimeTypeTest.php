<?php

declare(strict_types=1);

/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\FreeDSx\Asn1\Type;

use DateTime;
use FreeDSx\Asn1\Exception\InvalidArgumentException;
use FreeDSx\Asn1\Type\AbstractTimeType;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\UtcTimeType;
use PHPUnit\Framework\TestCase;

final class UtcTimeTypeTest extends TestCase
{
    private UtcTimeType $subject;

    protected function setUp(): void
    {
        $this->subject = new UtcTimeType(new DateTime());
    }

    public function test_it_should_be_constructed_with_a_datetime_of_the_current_time_if_none_is_given(): void
    {
        $subject = new UtcTimeType();

        self::assertInstanceOf(DateTime::class, $subject->getValue());
    }

    public function test_it_should_default_to_seconds(): void
    {
        self::assertSame(
            AbstractTimeType::FORMAT_SECONDS,
            $this->subject->getDateTimeFormat(),
        );
    }

    public function test_it_should_default_to_a_utc_timezone_ending(): void
    {
        self::assertSame(
            AbstractTimeType::TZ_UTC,
            $this->subject->getTimeZoneFormat(),
        );
    }

    public function test_it_should_set_the_value(): void
    {
        $date = new DateTime();

        self::assertInstanceOf(DateTime::class, $this->subject->getValue());
        self::assertSame(
            $date,
            $this->subject->setValue($date)->getValue(),
        );
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_UTC_TIME,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_set_the_time_format(): void
    {
        $this->subject->setDateTimeFormat(AbstractTimeType::FORMAT_SECONDS);
        $this->subject->setDateTimeFormat(AbstractTimeType::FORMAT_MINUTES);

        self::assertSame(
            AbstractTimeType::FORMAT_MINUTES,
            $this->subject->getDateTimeFormat(),
        );
    }

    public function test_it_should_set_the_timezone_format(): void
    {
        $this->subject->setTimeZoneFormat(AbstractTimeType::TZ_UTC);
        $this->subject->setTimeZoneFormat(AbstractTimeType::TZ_DIFF);

        self::assertSame(
            AbstractTimeType::TZ_DIFF,
            $this->subject->getTimeZoneFormat(),
        );
    }

    public function test_it_should_not_allow_setting_the_time_format_to_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->subject->setDateTimeFormat(AbstractTimeType::FORMAT_HOURS);
    }

    public function test_it_should_not_allow_setting_the_time_format_to_fractions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->subject->setDateTimeFormat(AbstractTimeType::FORMAT_FRACTIONS);
    }

    public function test_it_should_not_allow_setting_the_timezone_format_to_local(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->subject->setTimeZoneFormat(AbstractTimeType::TZ_LOCAL);
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        $dt = new DateTime();

        self::assertEquals(
            (new UtcTimeType($dt))
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
                ->setTagNumber(1),
            UtcTimeType::withTag(
                1,
                AbstractType::TAG_CLASS_APPLICATION,
                false,
                $dt,
                AbstractTimeType::FORMAT_SECONDS,
                AbstractTimeType::TZ_UTC,
            ),
        );
    }
}
