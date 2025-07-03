<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\DI;

use DrabekDigital\Captcha\CaptchaControl;
use DrabekDigital\Captcha\CaptchaValidator;
use Nette\DI\CompilerExtension;
use Nette\Forms\Container;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Nette DI extension for Captcha/Turnstile/hCaptcha integration
 */
class CaptchaExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'type' => Expect::anyOf('turnstile', 'hcaptcha')->default('turnstile'),
            'secretKey' => Expect::string()->required(),
            'siteKey' => Expect::string()->required(),
            'verifyUrl' => Expect::string()->nullable(),
            'theme' => Expect::anyOf('light', 'dark', 'auto')->default('auto'),
            'size' => Expect::anyOf('normal', 'compact')->default('normal'),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        /** @var object{type: 'turnstile'|'hcaptcha', secretKey: string, siteKey: string, verifyUrl: string|null, theme: 'light'|'dark'|'auto', size: 'normal'|'compact'} $config */
        $config = $this->getConfig();

        // Register validator service
        $builder->addDefinition($this->prefix('validator'))
            ->setFactory(CaptchaValidator::class)
            ->setArguments([
                'secretKey' => $config->secretKey,
                'type' => $config->type,
                'verifyUrl' => $config->verifyUrl,
            ]);
    }

    public function afterCompile(ClassType $class): void
    {
        /** @var object{type: 'turnstile'|'hcaptcha', secretKey: string, siteKey: string, verifyUrl: string|null, theme: 'light'|'dark'|'auto', size: 'normal'|'compact'} $config */
        $config = $this->getConfig();
        
        $initialize = $class->getMethod('initialize');
        $initialize->addBody(
            Container::class . '::extensionMethod(?, function($form, ?string $name = null, ?string $label = null): DrabekDigital\Captcha\CaptchaControl {
                $validator = $this->getByType(?);
                $control = new ' . CaptchaControl::class . '($validator, $label, ?, ?);
                $control->setTheme(?);
                $control->setSize(?);
                
                $form->addComponent($control, $name);
                return $control;
            });',
            [
                'addCaptcha',
                CaptchaValidator::class,
                $config->siteKey,
                $config->type,
                $config->theme,
                $config->size
            ]
        );
    }
}
