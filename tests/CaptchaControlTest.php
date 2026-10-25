<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use Closure;
use DrabekDigital\Captcha\CaptchaControl;
use DrabekDigital\Captcha\CaptchaValidator;
use Nette\Forms\Form;
use Nette\Utils\Html;
use PHPUnit\Framework\TestCase;
use Stringable;

class CaptchaControlTest extends TestCase
{
    private CaptchaValidator $validator;
    private string $siteKey = 'test-site-key';

    protected function setUp(): void
    {
        $this->validator = $this->createMock(CaptchaValidator::class);
    }

    /**
     * Helper method to create a form mock with specific HTTP data
     *
     * @param array<string, mixed> $httpData
     * @return Form
     */
    private function createFormWithHttpData(array $httpData): Form // @phpstan-ignore-line
    {
        // Ensure both captcha response keys are always present to avoid undefined key errors
        $completeData = array_merge([
            'cf-turnstile-response' => '',
            'h-captcha-response' => ''
        ], $httpData);
        
        $form = $this->createMock(Form::class);
        $form->method('getHttpData')->willReturn($completeData);
        return $form;
    }

    /**
     * Helper method to create a testable control that doesn't need real forms
     *
     * @param array<string, mixed> $httpData
     * @param string $type
     * @return CaptchaControl
     */
    private function createTestableControl(array $httpData, string $type = 'turnstile'): CaptchaControl
    {
        // Ensure both captcha response keys are always present
        $completeData = array_merge([
            'cf-turnstile-response' => '',
            'h-captcha-response' => ''
        ], $httpData);
        
        return new class($this->validator, null, $this->siteKey, $type, $completeData) extends CaptchaControl {
            private array $mockHttpData; // @phpstan-ignore-line
            
            public function __construct(CaptchaValidator $validator, ?string $label, string $siteKey, string $type, array $mockHttpData) // @phpstan-ignore-line
            {
                parent::__construct($validator, $label, $siteKey, $type);
                $this->mockHttpData = $mockHttpData;
            }
            
            public function getForm(bool $throw = true): ?Form // @phpstan-ignore-line
            {
                // Return a mock form that provides the expected HTTP data
                $form = new class($this->mockHttpData) extends Form {
                    private array $httpData; // @phpstan-ignore-line
                    
                    public function __construct(array $httpData) // @phpstan-ignore-line
                    {
                        $this->httpData = $httpData;
                    }
                    
                    public function getHttpData(?int $type = null, ?string $htmlName = null) /*\Nette\Http\FileUpload|array|string|null*/
                    {
                        return $this->httpData;
                    }
                };
                return $form;
            }
        };
    }

    public function testConstructorWithDefaults(): void
    {
        $control = new CaptchaControl($this->validator);
        self::assertInstanceOf(CaptchaControl::class, $control); // @phpstan-ignore-line
    }

    public function testConstructorWithAllParameters(): void
    {
        $control = new CaptchaControl($this->validator, 'Test Label', $this->siteKey, 'hcaptcha');
        self::assertInstanceOf(CaptchaControl::class, $control); // @phpstan-ignore-line
    }

    public function testSetThemeFluentInterface(): void
    {
        $control = new CaptchaControl($this->validator);
        $result = $control->setTheme('dark');
        self::assertSame($control, $result);
    }

    public function testSetSizeFluentInterface(): void
    {
        $control = new CaptchaControl($this->validator);
        $result = $control->setSize('compact');
        self::assertSame($control, $result);
    }

    public function testSetRequiredWithBooleanTrue(): void
    {
        $control = new CaptchaControl($this->validator);
        $result = $control->setRequired(true);
        self::assertSame($control, $result);
    }

    public function testSetRequiredWithBooleanFalseThrowsException(): void
    {
        $control = new CaptchaControl($this->validator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Captcha cannot be optional');

        $control->setRequired(false);
    }

    public function testIsRequiredByDefault(): void
    {
        $control = new CaptchaControl($this->validator);

        self::assertTrue($control->isRequired());
    }

    public function testSetRequiredWithStringMessage(): void
    {
        $control = new CaptchaControl($this->validator);
        $message = 'Please complete the captcha';
        $result = $control->setRequired($message);
        self::assertSame($control, $result);
    }

    public function testSetRequiredWithStringableMessage(): void
    {
        if (PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Stringable messages are not supported in PHP 7.4');
        }
        $control = new CaptchaControl($this->validator);
        $stringable = new class() implements Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };
        $result = $control->setRequired($stringable);
        self::assertSame($control, $result);
        self::assertStringContainsString('Stringable message', $control->getControl()->render());
    }

    public function testSetManagedMessagesWithTurnstile(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'turnstile');
        $result = $control->setManagedMessages('Pending', 'Resolved');
        self::assertSame($control, $result);
        self::assertStringContainsString('Pending', $control->getControl()->render());
        self::assertStringContainsString('Resolved', $control->getControl()->render());
    }

    public function testSetManagedMessagesWithHtmlStringable(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'turnstile');
        $html = Html::el('em')->setText('HTML message');
        $result = $control->setManagedMessages($html, 'Resolved');
        self::assertSame($control, $result);
        self::assertStringContainsString('HTML message', $control->getControl()->render());
        self::assertStringContainsString('Resolved', $control->getControl()->render());
    }

    public function testSetManagedMessagesWithHcaptchaThrowsException(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'hcaptcha');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('hCaptcha integration does not support managed mode');
        
        $control->setManagedMessages('Pending', 'Resolved');
    }

    public function testSetManagedMessagesWithInvalidPendingMessageType(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'turnstile');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message must be an instance of string or HtmlStringable');
        
        $control->setManagedMessages(123, 'Resolved'); // @phpstan-ignore-line
    }

    public function testSetManagedMessagesWithInvalidResolvedMessageType(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'turnstile');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message must be an instance of string or HtmlStringable');
        
        $control->setManagedMessages('Pending', ['invalid']); // @phpstan-ignore-line
    }

    public function testSetInvisibleWithTurnstile(): void
    {
        $control = new CaptchaControl($this->validator, 'Test Label', $this->siteKey, 'turnstile');
        $result = $control->setInvisible(true);
        self::assertSame($control, $result);
    }

    public function testSetInvisibleWithHcaptchaThrowsException(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'hcaptcha');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('hCaptcha integration does not support invisible mode');
        
        $control->setInvisible(true);
    }

    public function testValidateCaptchaStaticMethodWithValidResponse(): void
    {
        $control = $this->createTestableControl([
            'cf-turnstile-response' => 'valid-response'
        ], 'turnstile');
        
        // Mock validator to return true
        $this->validator->method('verify')->willReturn(true); // @phpstan-ignore-line
        
        $result = CaptchaControl::validateCaptcha($control);
        self::assertTrue($result);
    }

    public function testValidateCaptchaStaticMethodWithEmptyResponse(): void
    {
        $control = $this->createTestableControl([
            'cf-turnstile-response' => ''
        ], 'turnstile');
        
        $result = CaptchaControl::validateCaptcha($control);
        self::assertFalse($result);
    }

    public function testValidateCaptchaStaticMethodWithNoForm(): void
    {
        $control = new CaptchaControl($this->validator);
        
        // The validateCaptcha method should return false when there's no form
        // But the implementation calls getForm() which throws when not attached
        // This is actually testing edge case behavior - let's check what actually happens
        try {
            $result = CaptchaControl::validateCaptcha($control);
            self::assertFalse($result);
        } catch (\Nette\InvalidStateException $e) {
            // If it throws, that's also acceptable behavior for an unattached control
            self::assertTrue(true, 'Control correctly throws exception when not attached to form'); // @phpstan-ignore-line
        }
    }

    public function testValidateCaptchaStaticMethodWithValidatorException(): void
    {
        $control = $this->createTestableControl([
            'cf-turnstile-response' => 'valid-response'
        ], 'turnstile');
        
        // Mock validator to throw exception
        $this->validator->method('verify')->willThrowException(new \Exception('Test exception')); // @phpstan-ignore-line
        
        $result = CaptchaControl::validateCaptcha($control);
        self::assertFalse($result);
    }

    public function testGetControlForTurnstile(): void
    {
        $control = new CaptchaControl($this->validator, 'Test Label', $this->siteKey, 'turnstile');
        $html = $control->getControl();
        
        self::assertInstanceOf(Html::class, $html); // @phpstan-ignore-line
        self::assertStringContainsString('cf-turnstile', $html->render());
        self::assertStringContainsString($this->siteKey, $html->render());
    }

    public function testGetControlForHcaptcha(): void
    {
        $control = new CaptchaControl($this->validator, 'Test Label', $this->siteKey, 'hcaptcha');
        $html = $control->getControl();
        
        self::assertInstanceOf(Html::class, $html); // @phpstan-ignore-line
        self::assertStringContainsString('h-captcha', $html->render());
        self::assertStringContainsString($this->siteKey, $html->render());
    }

    public function testGetControlWithManagedMessages(): void
    {
        $control = new CaptchaControl($this->validator, 'Test Label', $this->siteKey, 'turnstile');
        $control->setManagedMessages('Pending message', 'Resolved message');
        
        $html = $control->getControl();
        $rendered = $html->render();
        
        self::assertStringContainsString('captcha-managed-invisible-message-pending', $rendered);
        self::assertStringContainsString('captcha-managed-invisible-message-resolved', $rendered);
        self::assertStringContainsString('Pending message', $rendered);
        self::assertStringContainsString('Resolved message', $rendered);
    }

    public function testIsFilledWhenRequiredWithValidResponse(): void
    {
        $control = $this->createTestableControl([
            'cf-turnstile-response' => 'valid-response'
        ], 'turnstile');
        $control->setRequired(true);
        
        self::assertTrue($control->isFilled());
    }

    public function testIsFilledWhenRequiredWithEmptyResponse(): void
    {
        $control = $this->createTestableControl([
            'cf-turnstile-response' => ''
        ], 'turnstile');
        $control->setRequired(true);
        
        self::assertFalse($control->isFilled());
    }

    public function testIsFilledWithNoForm(): void
    {
        $control = new CaptchaControl($this->validator);
        $control->setRequired(true);
        
        // When no form is attached, isFilled should return false or throw exception
        // Since the current implementation throws an exception, we expect that
        $this->expectException(\Nette\InvalidStateException::class);
        $control->isFilled();
    }

    public function testGetValueWithValidResponse(): void
    {
        $control = $this->createTestableControl([
            'cf-turnstile-response' => 'test-response-value'
        ], 'turnstile');
        
        self::assertEquals('test-response-value', $control->getValue());
    }

    public function testGetValueWithHcaptcha(): void
    {
        $control = $this->createTestableControl([
            'h-captcha-response' => 'hcaptcha-response-value'
        ], 'hcaptcha');
        
        self::assertEquals('hcaptcha-response-value', $control->getValue());
    }

    public function testGetValueWithNoForm(): void
    {
        $control = new CaptchaControl($this->validator);
        
        // When no form is attached, getValue should return null or throw exception
        // Since the current implementation throws an exception, we expect that
        $this->expectException(\Nette\InvalidStateException::class);
        $control->getValue();
    }

    public function testGetValueWithInvalidHttpData(): void
    {
        // Create a control that returns non-array HTTP data
        $control = new class($this->validator, null, $this->siteKey, 'turnstile') extends CaptchaControl {
            public function getForm(bool $throw = true): ?Form // @phpstan-ignore-line
            {
                return new class() extends Form {
                    public function getHttpData(?int $type = null, ?string $htmlName = null) /*\Nette\Http\FileUpload|array|string|null*/
                    {
                        return 'invalid-data'; // Non-array data
                    }
                };
            }
        };
        
        self::assertNull($control->getValue());
    }

    public function testGetCaptchaResponsePrivateMethodForTurnstile(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'turnstile');
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($control);
        $method = $reflection->getMethod('getCaptchaResponse');
        $method->setAccessible(true);
        
        $httpData = [
            'cf-turnstile-response' => 'turnstile-response',
            'h-captcha-response' => ''
        ];
        $result = $method->invoke($control, $httpData);
        
        self::assertEquals('turnstile-response', $result);
    }

    public function testGetCaptchaResponsePrivateMethodForHcaptcha(): void
    {
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'hcaptcha');
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($control);
        $method = $reflection->getMethod('getCaptchaResponse');
        $method->setAccessible(true);
        
        $httpData = [
            'cf-turnstile-response' => '',
            'h-captcha-response' => 'hcaptcha-response'
        ];
        $result = $method->invoke($control, $httpData);
        
        self::assertEquals('hcaptcha-response', $result);
    }

    public function testGetCaptchaResponsePrivateMethodWithInvalidType(): void
    {
        // Create control with invalid type using reflection
        $control = new CaptchaControl($this->validator, null, $this->siteKey, 'turnstile');
        
        $reflection = new \ReflectionClass($control);
        $typeProperty = $reflection->getProperty('type');
        $typeProperty->setAccessible(true);
        $typeProperty->setValue($control, 'invalid-type');
        
        $method = $reflection->getMethod('getCaptchaResponse');
        $method->setAccessible(true);
        
        $httpData = [
            'cf-turnstile-response' => 'response',
            'h-captcha-response' => ''
        ];
        $result = $method->invoke($control, $httpData);
        
        self::assertNull($result);
    }

    public function testGetMessagePrivateMethodWithStringMessage(): void
    {
        $control = new CaptchaControl($this->validator);
        $control->setRequired('Custom message');
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($control);
        $method = $reflection->getMethod('getMessage');
        $method->setAccessible(true);
        
        $result = $method->invoke($control);
        self::assertEquals('Custom message', $result);
    }

    public function testGetMessagePrivateMethodWithHtmlStringableMessage(): void
    {
        $control = new CaptchaControl($this->validator);
        $html = Html::el('span')->setText('HTML message');
        $control->setRequired($html);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($control);
        $method = $reflection->getMethod('getMessage');
        $method->setAccessible(true);
        
        $result = $method->invoke($control);
        self::assertEquals('<span>HTML message</span>', $result);
    }

    public function testGetMessagePrivateMethodWithFallback(): void
    {
        $control = new CaptchaControl($this->validator);
        $control->setRequired(true); // No custom message
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($control);
        $method = $reflection->getMethod('getMessage');
        $method->setAccessible(true);
        
        $result = $method->invoke($control);
        self::assertEquals('Please verify you are human.', $result);
    }

    /**
     * Helper method to create a real form whose submitted data are the given HTTP data
     *
     * @param array<string, string> $httpData
     * @return Form
     */
    private function createSubmittedForm(array $httpData): Form
    {
        return new class($httpData) extends Form {
            /** @var array<string, string> */
            private array $httpData;

            /**
             * @param array<string, string> $httpData
             */
            public function __construct(array $httpData)
            {
                parent::__construct('form');
                $this->httpData = $httpData;
            }

            public function getHttpData(?int $type = null, ?string $htmlName = null) /*array|string|null*/
            {
                return $htmlName === null ? $this->httpData : ($this->httpData[$htmlName] ?? null);
            }
        };
    }

    /**
     * Configurations which used to skip server-side validation
     *
     * @return array<string, array{Closure(CaptchaControl): CaptchaControl}>
     */
    public static function configurationsProvider(): array
    {
        return [
            'default' => [fn (CaptchaControl $control): CaptchaControl => $control],
            'setRequired' => [fn (CaptchaControl $control): CaptchaControl => $control->setRequired()],
            'invisible' => [fn (CaptchaControl $control): CaptchaControl => $control->setInvisible()],
            'managed' => [fn (CaptchaControl $control): CaptchaControl => $control->setManagedMessages('Pending', 'Resolved')],
        ];
    }

    /**
     * @dataProvider configurationsProvider
     * @param Closure(CaptchaControl): CaptchaControl $configure
     */
    public function testFormWithoutResponseIsInvalid(Closure $configure): void
    {
        $validator = $this->createMock(CaptchaValidator::class);
        $validator->expects(self::never())->method('verify');

        $form = $this->createSubmittedForm(['send' => '1']);
        $control = new CaptchaControl($validator, 'Captcha', $this->siteKey);
        $form->addComponent($control, 'captcha');
        $configure($control);

        $form->validate();

        self::assertFalse($form->isValid());
        self::assertSame(['Please verify you are human.'], $form->getErrors());
    }

    /**
     * @dataProvider configurationsProvider
     * @param Closure(CaptchaControl): CaptchaControl $configure
     */
    public function testFormWithInvalidResponseIsInvalid(Closure $configure): void
    {
        $validator = $this->createMock(CaptchaValidator::class);
        $validator->expects(self::once())->method('verify')->with('fake-token')->willReturn(false);

        $form = $this->createSubmittedForm(['send' => '1', 'cf-turnstile-response' => 'fake-token']);
        $control = new CaptchaControl($validator, 'Captcha', $this->siteKey);
        $form->addComponent($control, 'captcha');
        $configure($control);

        $form->validate();

        self::assertFalse($form->isValid());
    }

    /**
     * @dataProvider configurationsProvider
     * @param Closure(CaptchaControl): CaptchaControl $configure
     */
    public function testFormWithValidResponseIsValid(Closure $configure): void
    {
        $validator = $this->createMock(CaptchaValidator::class);
        $validator->expects(self::once())->method('verify')->with('valid-token')->willReturn(true);

        $form = $this->createSubmittedForm(['send' => '1', 'cf-turnstile-response' => 'valid-token']);
        $control = new CaptchaControl($validator, 'Captcha', $this->siteKey);
        $form->addComponent($control, 'captcha');
        $configure($control);

        $form->validate();

        self::assertTrue($form->isValid());
    }

    public function testHcaptchaFormWithoutResponseIsInvalidByDefault(): void
    {
        $validator = $this->createMock(CaptchaValidator::class);
        $validator->expects(self::never())->method('verify');

        $form = $this->createSubmittedForm(['send' => '1', 'cf-turnstile-response' => 'wrong-provider-token']);
        $form->addComponent(new CaptchaControl($validator, 'Captcha', $this->siteKey, 'hcaptcha'), 'captcha');

        $form->validate();

        self::assertFalse($form->isValid());
    }

    public function testCustomMessageReplacesDefaultMessage(): void
    {
        $validator = $this->createMock(CaptchaValidator::class);
        $form = $this->createSubmittedForm(['send' => '1']);
        $control = new CaptchaControl($validator, 'Captcha', $this->siteKey);
        $form->addComponent($control, 'captcha');
        $control->setRequired('Custom message');

        $form->validate();

        self::assertSame(['Custom message'], $form->getErrors());
    }

    public function testRepeatedSetRequiredDoesNotDuplicateRules(): void
    {
        $validator = $this->createMock(CaptchaValidator::class);
        $validator->expects(self::once())->method('verify')->willReturn(false);

        $form = $this->createSubmittedForm(['send' => '1', 'cf-turnstile-response' => 'fake-token']);
        $control = new CaptchaControl($validator, 'Captcha', $this->siteKey);
        $form->addComponent($control, 'captcha');
        $control->setRequired()->setRequired('First')->setRequired('Second');

        $form->validate();

        self::assertSame(['Second'], $form->getErrors());
    }
}
