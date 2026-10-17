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

namespace FreeDSx\Asn1\Exception;

/**
 * Thrown when constructed types are nested deeper than the configured maximum.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class PduDepthException extends EncoderException {}
