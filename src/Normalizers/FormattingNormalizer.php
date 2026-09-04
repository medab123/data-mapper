<?php

declare(strict_types=1);

namespace Medox\DataMapper\Normalizers;

use Medox\DataMapper\Contracts\NormalizerInterface;

/**
 * Treats letter case, surrounding whitespace and a byte-order mark as formatting.
 *
 * Nothing else. "stock_number" and "Stock Number" stay different names, and "Used" and
 * "U sed" stay different values, because guessing across separators would start matching
 * things nobody wrote.
 *
 * The BOM matters more than it looks: it is the first bytes of a UTF-8 file, so it lands
 * on the first column of a CSV header and on the first value of the first row, and it is
 * invisible in every tool that would be used to diagnose why nothing matched.
 */
final class FormattingNormalizer implements NormalizerInterface
{
    public function normalize(string $value): string
    {
        return mb_strtolower(trim(str_replace("\u{FEFF}", '', $value)));
    }
}
