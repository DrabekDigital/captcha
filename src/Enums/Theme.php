<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Enums;

enum Theme: string
{
    case LIGHT = 'light';

    case DARK = 'dark';

    case AUTO = 'auto';
}
