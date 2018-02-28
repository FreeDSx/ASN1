<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1\Exception;

use FreeDSx\Asn1\Exception\EncoderException;
use PhpSpec\ObjectBehavior;

class EncoderExceptionSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(EncoderException::class);
    }

    function it_should_extend_exception()
    {
        $this->shouldBeAnInstanceOf('\Exception');
    }
}
