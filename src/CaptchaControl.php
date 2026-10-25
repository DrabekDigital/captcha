<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha;

use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Enums\Size;
use DrabekDigital\Captcha\Enums\Theme;
use Nette\Forms\Control;
use Nette\Forms\Controls\BaseControl;
use Nette\HtmlStringable;
use Nette\Utils\Html;
use Stringable;

/**
 * Captcha form control for Turnstile and hCaptcha
 */
class CaptchaControl extends BaseControl
{
    private const DEFAULT_MESSAGE = 'Please verify you are human.';

    private Theme $theme = Theme::AUTO;

    private Size $size = Size::NORMAL;

    private string|HtmlStringable|Stringable|null $message = null;

    private string|HtmlStringable|Stringable|null $managedMessagePending = null;

    private string|HtmlStringable|Stringable|null $managedMessageResolved = null;

    private bool $isInvisible = false;

    private CaptchaValidator $validator;

    public function __construct(
        CaptchaValidator $validator,
        ?string $label = null,
        private readonly string $siteKey = '',
        private readonly CaptchaType $type = CaptchaType::TURNSTILE
    ) {
        parent::__construct($label);
        $this->validator = $validator;
        $this->setOmitted();
        // Captcha is always required, otherwise the form would pass without server-side verification
        $this->setRequired();
    }

    public function setTheme(Theme $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function setSize(Size $size): self
    {
        if (!$size->isSupportedBy($this->type)) {
            throw new \InvalidArgumentException(sprintf('Size "%s" is not supported by %s', $size->value, $this->type->value));
        }
        if ($this->isInvisible && $this->type === CaptchaType::HCAPTCHA) {
            throw new \InvalidArgumentException('Size of invisible hCaptcha cannot be changed');
        }

        $this->size = $size;
        return $this;
    }

    /**
     * Set message shown when captcha is not solved (captcha is always required)
     *
     * @param string|Stringable|HtmlStringable|bool $value
     * @return static
     */
    public function setRequired(string|Stringable|HtmlStringable|bool $value = true): static
    {
        if ($value === false) {
            throw new \InvalidArgumentException('Captcha cannot be optional, remove the control from the form instead.');
        }

        if ($value !== true) {
            $this->message = $value;
        }

        // Re-add rules so they always carry the current message, the default one is translated by Nette on validation
        $message = $this->message !== null ? $this->getMessage() : self::DEFAULT_MESSAGE;
        $this->getRules()->removeRule([$this, 'validateCaptcha']);
        $this->getRules()->setRequired($message);
        $this->addRule([$this, 'validateCaptcha'], $message);

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
     * Turnstile invisible mode is configured on the site key in the Cloudflare dashboard, this only hides the label.
     * hCaptcha is rendered with the invisible size and executed by the bundled JS on form submission.
     *
     * @param bool $isInvisible
     * @return static
     */
    public function setInvisible(bool $isInvisible = true): self
    {
        $this->isInvisible = $isInvisible;
        $this->setCaption('');
        return $this;
    }

    /**
     * Validate captcha response
     *
     * @param Control $control
     * @return bool
     */
    public static function validateCaptcha(Control $control): bool
    {
        if (!$control instanceof self) {
            return false;
        }

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
        $fallbackMessage = $this->translate(self::DEFAULT_MESSAGE);
        if (!is_string($fallbackMessage)) {
            $fallbackMessage = self::DEFAULT_MESSAGE;
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
                // hCaptcha supports only light and dark, omit the attribute to use its default
                'data-theme' => $this->theme === Theme::AUTO ? null : $this->theme->value,
                // Invisible hCaptcha is a widget size, it is executed by the bundled JS on form submission
                'data-size' => $this->isInvisible ? 'invisible' : $this->size->value,
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
