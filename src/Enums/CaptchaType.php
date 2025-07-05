<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Enums;

enum CaptchaType: string
{
    case TURNSTILE = 'turnstile';
    case HCAPTCHA = 'hcaptcha';
}
