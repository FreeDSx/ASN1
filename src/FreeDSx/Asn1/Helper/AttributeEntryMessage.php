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

namespace FreeDSx\Asn1\Helper;

/**
 * A decoded message carrying a tagged attribute entry, such as an RFC 4511 SearchResultEntry.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final readonly class AttributeEntryMessage
{
    /**
     * @param list<array{0: string, 1: list<string>}> $attributes Each entry is [description, values].
     * @param int $length The number of bytes the message occupied in the decoded data.
     */
    public function __construct(
        public int $messageId,
        public string $primaryId,
        public array $attributes,
        public int $length,
    ) {
    }
}
