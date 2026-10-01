<?php

declare(strict_types=1);

namespace App\Seo;

final class SeoText
{
    public const int SUMMARY_MAX_LENGTH = 160;

    public static function summary(?string $text, int $maxLength = self::SUMMARY_MAX_LENGTH): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
        if (mb_strlen($plain) <= $maxLength) {
            return $plain;
        }

        $cut = mb_substr($plain, 0, $maxLength - 1);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength / 2) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, ' ,;:.-—') . '…';
    }
}
