<?php

declare(strict_types=1);

namespace App\Enum;

enum GdprRequestType: string
{
    case ACCESS = 'access';
    case RECTIFICATION = 'rectification';
    case ERASURE = 'erasure';
    case PORTABILITY = 'portability';
    case OPPOSITION = 'opposition';

    public function label(): string
    {
        return 'enum.gdpr_request_type.'.$this->value;
    }
}
