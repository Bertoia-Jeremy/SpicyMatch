<?php

declare(strict_types=1);

namespace App\Seo\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class NoIndex
{
}
