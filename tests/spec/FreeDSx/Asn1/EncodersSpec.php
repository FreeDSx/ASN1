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

use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Encoder\DerEncoder;
use FreeDSx\Asn1\Encoders;
use PhpSpec\ObjectBehavior;

class EncodersSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Encoders::class);
    }

    function it_should_get_a_ber_encoder()
    {
        $this::ber()->shouldBeAnInstanceOf(BerEncoder::class);
    }

    function it_should_get_a_der_encoder()
    {
        $this::der()->shouldBeAnInstanceOf(DerEncoder::class);
    }
}
