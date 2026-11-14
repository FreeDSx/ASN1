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

namespace FreeDSx\Asn1\Encoder;

use FreeDSx\Asn1\Exception\InvalidArgumentException;

use function sprintf;

/**
 * Options shared by ASN.1 encoders.
 */
final readonly class EncoderOptions
{
    /**
     * @param int $maxLength Reject a root PDU whose declared length exceeds this many bytes; 0 disables the limit.
     * @param int $maxDepth Reject constructed types nested deeper than this many levels within a single decode.
     * @param int $maxElements Reject a single decode holding more than this many elements; 0 disables the limit.
     * @param int $maxTagNumber Reject a tag numbered above this, at least 30; 0 allows any tag up to $maxLength octets.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $bitstringPadding = '0',
        public int $maxLength = 0,
        public int $maxDepth = 16,
        public int $maxElements = 0,
        public int $maxTagNumber = PHP_INT_MAX,
    ) {
        if ($maxDepth < 1) {
            throw new InvalidArgumentException(sprintf(
                'The max depth must be at least 1, but received %d.',
                $maxDepth,
            ));
        }

        if ($maxElements < 0) {
            throw new InvalidArgumentException(sprintf(
                'The max elements cannot be negative, but received %d.',
                $maxElements,
            ));
        }

        # Tags numbered up to 30 always use a single octet, where a lower maximum could not reach them.
        if ($maxTagNumber !== 0 && $maxTagNumber < 30) {
            throw new InvalidArgumentException(sprintf(
                'The max tag number must be 0 or at least 30, but received %d.',
                $maxTagNumber,
            ));
        }
    }

    /**
     * A copy of these options that pads bit strings with the given character.
     *
     * @throws InvalidArgumentException
     */
    public function withBitstringPadding(string $bitstringPadding): self
    {
        return new self(
            bitstringPadding: $bitstringPadding,
            maxLength: $this->maxLength,
            maxDepth: $this->maxDepth,
            maxElements: $this->maxElements,
            maxTagNumber: $this->maxTagNumber,
        );
    }
}
