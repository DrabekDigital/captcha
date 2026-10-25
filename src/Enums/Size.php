<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Enums;

enum Size: string
{
    case NORMAL = 'normal';
    case COMPACT = 'compact';
    /** Turnstile only, widget takes the full width of its container */
    case FLEXIBLE = 'flexible';

    public function isSupportedBy(CaptchaType $type): bool
    {
        return match ($this) {
            self::NORMAL, self::COMPACT => true,
            self::FLEXIBLE => $type === CaptchaType::TURNSTILE,
        };
    }
}
