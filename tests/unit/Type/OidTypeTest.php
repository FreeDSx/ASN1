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

use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\OidType;
use PHPUnit\Framework\TestCase;

final class OidTypeTest extends TestCase
{
    private OidType $subject;

    protected function setUp(): void
    {
        $this->subject = new OidType('1.2.3');
    }

    public function test_it_should_get_the_value(): void
    {
        self::assertSame(
            '1.2.3',
            $this->subject->getValue(),
        );
        self::assertSame(
            '1.2.3.4',
            $this->subject->setValue('1.2.3.4')->getValue(),
        );
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_OID,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new OidType('1.2.3.4'))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
                ->setValue('1.2.3.4'),
            OidType::withTag(1, AbstractType::TAG_CLASS_APPLICATION, '1.2.3.4'),
        );
    }
}
