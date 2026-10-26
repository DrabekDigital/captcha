<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Latte;

use DrabekDigital\Captcha\Assets\CaptchaFrontend;
use DrabekDigital\Captcha\Enums\CaptchaType;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Extension;

/**
 * Adds {captchaScripts} tag rendering the provider and validation scripts
 */
class CaptchaLatteExtension extends Extension
{
    public function __construct(
        private readonly CaptchaType $type
    ) {
    }

    public function getTags(): array
    {
        return [
            'captchaScripts' => fn (Tag $tag): AuxiliaryNode => new AuxiliaryNode(
                // uiNonce is provided by nette/application when the CSP nonce is used
                fn (PrintContext $context): string => $context->format(
                    'echo $this->global->captcha->renderScripts($this->global->uiNonce ?? null) %line;',
                    $tag->position,
                )
            ),
        ];
    }

    public function getProviders(): array
    {
        return [
            'captcha' => $this,
        ];
    }

    /**
     * @param string|null $nonce
     * @return string
     */
    public function renderScripts(?string $nonce = null): string
    {
        return CaptchaFrontend::getJavascriptStatic($this->type, $nonce) . "\n" . CaptchaFrontend::getLocalJavascriptStatic($nonce);
    }
}
