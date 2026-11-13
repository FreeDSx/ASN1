<?php

/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Asn1\Exception;

use Throwable;

/**
 * Thrown when the encoder determines it doesn't have the full length of data to construct the PDU. If we don't have
 * enough data in a non-PDU ASN1 element, then an Encoder Exception will be thrown instead.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class PartialPduException extends EncoderException
{
    /**
     * @param int|null $pduLength the bytes of the whole PDU, or null when its length has not arrived yet.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly ?int $pduLength = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    /**
     * The bytes of the whole PDU, its tag and length included, or null when its length has not arrived yet.
     */
    public function getPduLength(): ?int
    {
        return $this->pduLength;
    }
}
