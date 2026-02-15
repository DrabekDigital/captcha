<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha;

use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Form;
use Nette\Utils\Html;
use Stringable;

/**
 * Captcha form control for Turnstile and hCaptcha
 */
class CaptchaControl extends BaseControl
{
    private string $siteKey;

    private string $type;

    private string $theme = 'auto';

    private string $size = 'normal';

    private bool $required = true;

    /*?string|HtmlStringable*/ private $message = null; // @phpstan-ignore-line

    /*?string|HtmlStringable*/ private $managedMessagePending = null; // @phpstan-ignore-line
    /*?string|HtmlStringable*/ private $managedMessageResolved = null; // @phpstan-ignore-line

    private bool $isInvisible = false;

    private CaptchaValidator $validator;

    private bool $requiredRuleAdded = false;

    /**
     * @param CaptchaValidator $validator
     * @param string|null $label
     * @param string $siteKey
     * @param string $type
     */
    public function __construct(CaptchaValidator $validator, ?string $label = null, string $siteKey = '', string $type = 'turnstile')
    {
        parent::__construct($label);
        $this->validator = $validator;
        $this->siteKey = $siteKey;
        $this->type = $type;
        $this->setOmitted();
    }

    /**
     * Set captcha theme
     *
     * @param string $theme
     * @return static
     */
    public function setTheme(string $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    /**
     * Set captcha size
     *
     * @param string $size
     * @return static
     */
    public function setSize(string $size): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Set if captcha is required
     *
     * @param string|\Stringable|Html|object|bool $value
     * @return static
     */
    public function setRequired(/*string|Stringable|Html|object|bool*/ $value = true)
    {
        if (is_string($value) || $value instanceof Stringable || $value instanceof Html) {
            $this->required = true;
            $this->message = $value;
        } elseif (is_bool($value)) {
            $this->required = $value;
        } else {
            $this->required = true;
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
     * @param string|Html $messagePending
     * @param string|Html $messageResolved
     * @return static
     */
    public function setManagedMessages(/*string|Html*/ $messagePending, /*string|Html*/ $messageResolved): self
    {
        if ($this->type === 'hcaptcha') {
            throw new \InvalidArgumentException('hCaptcha integration does not support managed mode');
        }
        // validate type of $messagePending and $messageResolved to be string or Html
        if (!is_string($messagePending) && !($messagePending instanceof Html)) { // @phpstan-ignore-line
            throw new \InvalidArgumentException('Message must be an instance of string or HtmlStringable');
        }
        if (!is_string($messageResolved) && !($messageResolved instanceof Html)) { // @phpstan-ignore-line
            throw new \InvalidArgumentException('Message must be an instance of string or HtmlStringable');
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
        if ($this->type === 'hcaptcha') {
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
        if (!$form instanceof Form) {
            return false;
        }

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
        if ($stripHtml && $this->message instanceof Html) {
            return strip_tags($this->message->__toString());
        }
        if ($this->message instanceof Stringable || $this->message instanceof Html) {
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
        switch ($this->type) {
            case 'turnstile':
                return is_string($httpData['cf-turnstile-response'] ?? null) ? $httpData['cf-turnstile-response'] : null;
            case 'hcaptcha':
                return is_string($httpData['h-captcha-response'] ?? null) ? $httpData['h-captcha-response'] : null;
            default:
                return null;
        }
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
        
        switch ($this->type) {
            case 'turnstile':
                $el->addAttributes([
                    'class' => 'cf-turnstile',
                    'data-sitekey' => $this->siteKey,
                    'data-theme' => $this->theme,
                    'data-size' => $this->size,
                    'data-require-turnstile' => $this->getMessage(true),
                ]);
                break;
                
            case 'hcaptcha':
                $el->addAttributes([
                    'class' => 'h-captcha',
                    'data-sitekey' => $this->siteKey,
                    'data-theme' => $this->theme,
                    'data-size' => $this->size,
                    'data-require-hcaptcha' => $this->getMessage(true),
                ]);
                break;
        }

        if (!$this->isInvisible && ((isset($this->managedMessagePending) || isset($this->managedMessageResolved)))) {
            $el2 = Html::el('div');
            $el2->addAttributes([
                'class' => 'captcha-managed-invisible-message-pending',
                'style' => 'display: none;',
            ]);
            $el2->setText($this->translate($this->managedMessagePending)); // @phpstan-ignore-line

            $el3 = Html::el('div');
            $el3->addAttributes([
                'class' => 'captcha-managed-invisible-message-resolved',
                'style' => 'display: none;',
            ]);
            $el3->setText($this->translate($this->managedMessageResolved)); // @phpstan-ignore-line

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
        if (!$form instanceof Form) {
            return false;
        }

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
        if (!$form instanceof Form) {
            return null;
        }

        $httpData = $form->getHttpData();
        if (!is_array($httpData)) {
            return null;
        }

        return $this->getCaptchaResponse($httpData);
    }
}
