<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\DI;

use DrabekDigital\Captcha\CaptchaControl;
use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Enums\Size;
use DrabekDigital\Captcha\Enums\Theme;
use Nette\DI\CompilerExtension;
use Nette\Forms\Container;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Context;
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
            'type' => Expect::anyOf(...self::values(CaptchaType::cases()))->default(CaptchaType::TURNSTILE)->transform(fn (mixed $value, Context $context): CaptchaType => $value instanceof CaptchaType ? $value : (is_string($value) ? CaptchaType::from($value) : CaptchaType::TURNSTILE)),
            'secretKey' => Expect::string()->required(),
            'siteKey' => Expect::string()->required(),
            'verifyUrl' => Expect::string()->nullable(),
            'theme' => Expect::anyOf(...self::values(Theme::cases()))->default(Theme::AUTO)->transform(fn (mixed $value, Context $context): Theme => $value instanceof Theme ? $value : (is_string($value) ? Theme::from($value) : Theme::AUTO)),
            'size' => Expect::anyOf(...self::values(Size::cases()))->default(Size::NORMAL)->transform(fn (mixed $value, Context $context): Size => $value instanceof Size ? $value : (is_string($value) ? Size::from($value) : Size::NORMAL)),
        ]);
    }

    /**
     * @param list<\BackedEnum> $cases
     * @return list<int|string>
     */
    private static function values(array $cases): array
    {
        return array_map(fn (\BackedEnum $case): int|string => $case->value, $cases);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        /** @var object{type: CaptchaType, secretKey: string, siteKey: string, verifyUrl: string|null, theme: Theme, size: Size} $config */
        $config = $this->getConfig();

        if (!$config->size->isSupportedBy($config->type)) {
            throw new \Nette\DI\InvalidConfigurationException(sprintf("Size '%s' is not supported by %s", $config->size->value, $config->type->value));
        }

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
        /** @var object{type: CaptchaType, secretKey: string, siteKey: string, verifyUrl: string|null, theme: Theme, size: Size} $config */
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
