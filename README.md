# Nette Captcha Extension

A comprehensive Nette Forms extension for integrating **Cloudflare Turnstile** and **hCaptcha** with your Nette applications.

## Features

- ✅ **Cloudflare Turnstile** integration (visible, managed, invisible modes)
- ✅ **hCaptcha** integration (visible, invisible modes)
- ✅ **PHP 8.3+** support
- ✅ **Server-side validation**
- ✅ **Flexible configuration**
- ✅ **Easy form integration**
- ✅ **Customizable themes and sizes**

## Requirements

- **PHP**: 8.3+
- **Nette Forms**: 3.2+
- **Extensions**: `curl` (used by default) or `allow_url_fopen` for server-side verification



## Installation

Install via Composer:

```bash
composer require drabek-digital/captcha
```

## Configuration

Register the extension in your `config.neon`:

```neon
extensions:
    captcha: DrabekDigital\Captcha\DI\CaptchaExtension

captcha:
    type: turnstile          # or hcaptcha
    secretKey: your-secret-key
    siteKey: your-site-key
    theme: auto              # light, dark, auto (optional)
    size: normal             # normal, compact, flexible (Turnstile only) (optional)
```

### Configuration Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `type` | string | No | `turnstile` | Captcha provider (`turnstile` or `hcaptcha`) |
| `secretKey` | string | **Yes** | - | Your secret key from the captcha provider |
| `siteKey` | string | **Yes** | - | Your site key from the captcha provider |
| `verifyUrl` | string | No | - | Custom verification URL (uses default if not set) |
| `theme` | string | No | `auto` | Theme: `light`, `dark`, or `auto` (hCaptcha does not support `auto` and uses its default `light` theme) |
| `size` | string | No | `normal` | Size: `normal`, `compact` or `flexible` (Turnstile only) |

## Usage

### Basic Usage

First do not forget to render relevant JS script include in your Latte templates:

```latte
{* This will include Turnstile or hCaptcha JS code *}
{DrabekDigital\Captcha\Assets\CaptchaFrontend::getJavascriptStatic(\DrabekDigital\Captcha\Enums\CaptchaType::TURNSTILE)|noescape} {* <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script> *}
{DrabekDigital\Captcha\Assets\CaptchaFrontend::getJavascriptStatic(\DrabekDigital\Captcha\Enums\CaptchaType::HCAPTCHA)|noescape} {* <script src="https://js.hcaptcha.com/1/api.js" async defer></script> *}

{* Include local JS to make forms validation (prevent submission when verification is missing) and to show managed states labels *}
{* ... or manually link <LIBRARY PATH>/src/Assets/captcha-validation.js *}
{DrabekDigital\Captcha\Assets\CaptchaFrontend::getLocalJavascriptStatic()|noescape}
```

```php
use Nette\Application\UI\Form;

$form = new Form;

// Simple captcha (required by default)
// The validator is automatically injected by the DI extension
$form->addCaptcha('captcha', 'Verify you are human');

// Handle form submission
$form->onSuccess[] = function($form) {
    $values = $form->getValues();
    // Process form...
};
```

### Advanced Usage

```php
// All supported method signatures:

// 1. Simple required visible captcha (Turnstile + hCaptcha)
$form->addCaptcha('captcha', 'Bot protection')
    ->setRequired(true);

// 2. Simple required visible captcha with custom required message (Turnstile + hCaptcha)
$form->addCaptcha('captcha', 'Bot protection')
    ->setRequired('Please verify you are human');

// 3. Simple managed captcha (only for Turnstile)
// The control + label will be rendered but the captcha can be shown or hidden based on Turnstile decision so therefore the JS code shows these messages
$form->addCaptcha('captcha', 'Bot protection')
    ->setManagedMessages('Check will be performed on background', Html::el('em')->setHtml('Check has been performed successfully.'));

// 4. Invisible captcha (Turnstile + hCaptcha)
// The control + label will be hidden, the only thing that can be shown is required message.
// Turnstile: choose the Invisible widget mode for the site key in the Cloudflare dashboard.
// hCaptcha: rendered as invisible and executed by the bundled JS when the form is submitted.
$form->addCaptcha('captcha', 'Bot protection')
    ->setInvisible(true);
```

### Manual Instantiation (Advanced)

If you need to create the control manually (e.g., for testing):

```php
use DrabekDigital\Captcha\CaptchaControl;
use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;

// Create validator manually
$validator = new CaptchaValidator('secret-key', CaptchaType::TURNSTILE);

// Create control (validator is the first mandatory parameter)
$captcha = new CaptchaControl($validator, 'Captcha', 'site-key', CaptchaType::TURNSTILE);
```

### Custom HTTP Client

Server-side verification uses `curl` when available and falls back to PHP streams (`allow_url_fopen`). To use your own HTTP client (proxy, retries, logging…), register any [PSR-18](https://www.php-fig.org/psr/psr-18/) client as a service, it is autowired into the validator. Clients that do not implement the [PSR-17](https://www.php-fig.org/psr/psr-17/) request and stream factories themselves need the factories registered too:

```neon
services:
    # Symfony HttpClient implements the PSR-17 factories itself
    - Symfony\Component\HttpClient\Psr18Client

    # Guzzle needs the factories
    - GuzzleHttp\Client
    - GuzzleHttp\Psr7\HttpFactory
```

Without DI pass them to the constructor:

```php
$validator = new CaptchaValidator('secret-key', CaptchaType::TURNSTILE, httpClient: $client, requestFactory: $factory, streamFactory: $factory);
```

## Limitations

- Only implicit rendering support for both providers (primarily due to reliability).
- For hCaptcha passive mode is not supported at all (no access).
- Custom verification endpoint was not tested.
- Other captcha providers may work but will need custom JS to ensure form validation to work.

## License

MIT License - see LICENSE file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request, but better to consult via issues before large efforts.

## Support

For support, please create an issue on GitHub.