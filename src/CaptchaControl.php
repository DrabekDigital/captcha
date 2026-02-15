<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha;

use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Enums\Size;
use DrabekDigital\Captcha\Enums\Theme;
use Nette\Forms\Controls\BaseControl;
use Nette\HtmlStringable;
use Nette\Utils\Html;
use Stringable;

/**
 * Captcha form control for Turnstile and hCaptcha
 */
class CaptchaControl extends BaseControl
{
    private Theme $theme = Theme::AUTO;

    private Size $size = Size::NORMAL;

    private bool $required = true;

    private string|HtmlStringable|Stringable|null $message = null;

    private string|HtmlStringable|Stringable|null $managedMessagePending = null;

    private string|HtmlStringable|Stringable|null $managedMessageResolved = null;

    private bool $isInvisible = false;

    private CaptchaValidator $validator;

    private bool $requiredRuleAdded = false;

    public function __construct(
        CaptchaValidator $validator,
        ?string $label = null,
        private readonly string $siteKey = '',
        private readonly CaptchaType $type = CaptchaType::TURNSTILE
    ) {
        parent::__construct($label);
        $this->validator = $validator;
        $this->setOmitted();
    }

    public function setTheme(Theme $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function setSize(Size $size): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Set if captcha is required
     *
     * @param string|Stringable|HtmlStringable|bool $value
     * @return static
     */
    public function setRequired(string|Stringable|HtmlStringable|bool $value = true): static
    {
        if (is_string($value) || $value instanceof HtmlStringable || $value instanceof Stringable) {
            $this->required = true;
            $this->message = $value;
        } else {
            $this->required = $value;
        }
        
        if ($this->required && !$this->requiredRuleAdded) {
            $this->getRules()->setRequired($this->getMessage());
            $this->addRule([$this, 'validateCaptcha'], $this->getMessage());
            $this->requiredRuleAdded = true;
        }
        
        return $this;
    }

    /**
     * Set message to be shown when the managed captcha is rendered as invisible
     *
     * @param string|HtmlStringable|Stringable $messagePending
     * @param string|HtmlStringable|Stringable $messageResolved
     * @return static
     */
    public function setManagedMessages(string|HtmlStringable|Stringable $messagePending, string|HtmlStringable|Stringable $messageResolved): self
    {
        if ($this->type === CaptchaType::HCAPTCHA) {
            throw new \InvalidArgumentException('hCaptcha integration does not support managed mode');
        }

        $this->managedMessagePending = $messagePending;
        $this->managedMessageResolved = $messageResolved;
        return $this;
    }

    /**
     * Set when captcha is always rendered as invisible to hide the field entry from the user
     *
     * @param bool $isInvisible
     * @return static
     */
    public function setInvisible(bool $isInvisible = true): self
    {
        if ($this->type === CaptchaType::HCAPTCHA) {
            throw new \InvalidArgumentException('hCaptcha integration does not support invisible mode');
        }

        $this->isInvisible = $isInvisible;
        $this->setCaption('');
        return $this;
    }

    /**
     * Validate captcha response
     *
     * @param CaptchaControl $control
     * @return bool
     */
    public static function validateCaptcha(self $control): bool
    {
        $form = $control->getForm();

        $httpData = $form->getHttpData();
        if (!is_array($httpData)) {
            return false;
        }

        $response = $control->getCaptchaResponse($httpData);

        if ($response === null || $response === '') {
            return false;
        }

        // Use injected validator (always available since it's mandatory)
        try {
            return $control->validator->verify($response);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getMessage(bool $stripHtml = false): string
    {
        if ($stripHtml && $this->message instanceof HtmlStringable) {
            return strip_tags($this->message->__toString());
        }
        if ($this->message instanceof HtmlStringable || $this->message instanceof Stringable) {
            return $this->message->__toString();
        }
        $fallbackMessage = $this->translate('Please verify you are human.');
        if (!is_string($fallbackMessage)) {
            $fallbackMessage = 'Please verify you are human.';
        }
        return is_string($this->message) ? $this->message : $fallbackMessage;
    }

    /**
     * Get captcha response from HTTP data
     *
     * @param array<mixed> $httpData
     * @return string|null
     */
    private function getCaptchaResponse(array $httpData): ?string
    {
        return match ($this->type) {
            CaptchaType::TURNSTILE => is_string($httpData['cf-turnstile-response'] ?? null) ? $httpData['cf-turnstile-response'] : null,
            CaptchaType::HCAPTCHA => is_string($httpData['h-captcha-response'] ?? null) ? $httpData['h-captcha-response'] : null,
        };
    }

    /**
     * Generate control's HTML element
     *
     * @return Html
     */
    public function getControl(): Html
    {
        $this->setOption('rendered', true);

        $elWrapper = Html::el('');

        $el = Html::el('div');
        
        match ($this->type) {
            CaptchaType::TURNSTILE => $el->addAttributes([
                'class' => 'cf-turnstile',
                'data-sitekey' => $this->siteKey,
                'data-theme' => $this->theme->value,
                'data-size' => $this->size->value,
                'data-require-turnstile' => $this->getMessage(true),
            ]),
            CaptchaType::HCAPTCHA => $el->addAttributes([
                'class' => 'h-captcha',
                'data-sitekey' => $this->siteKey,
                'data-theme' => $this->theme->value,
                'data-size' => $this->size->value,
                'data-require-hcaptcha' => $this->getMessage(true),
            ]),
        };

        if (!$this->isInvisible && ((isset($this->managedMessagePending) || isset($this->managedMessageResolved)))) {
            $el2 = Html::el('div');
            $el2->addAttributes([
                'class' => 'captcha-managed-invisible-message-pending',
                'style' => 'display: none;',
            ]);
            $el2->setText($this->translate($this->managedMessagePending));

            $el3 = Html::el('div');
            $el3->addAttributes([
                'class' => 'captcha-managed-invisible-message-resolved',
                'style' => 'display: none;',
            ]);
            $el3->setText($this->translate($this->managedMessageResolved));

            $elWrapper->addHtml($el2);
            $elWrapper->addHtml($el3);
        }

        $elWrapper->addHtml($el);

        return $elWrapper;
    }

    /**
     * Is control filled?
     *
     * @return bool
     */
    public function isFilled(): bool
    {
        if (!$this->required) {
            return true;
        }

        $form = $this->getForm();


        $httpData = $form->getHttpData();
        if (!is_array($httpData)) {
            return false;
        }

        $response = $this->getCaptchaResponse($httpData);

        return $response !== null && $response !== '';
    }

    /**
     * Get submitted value
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        $form = $this->getForm();

        $httpData = $form->getHttpData();
        if (!is_array($httpData)) {
            return null;
        }

        return $this->getCaptchaResponse($httpData);
    }
}
