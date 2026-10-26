/**
 * Adds custom validation for hCaptcha and Turnstile
 */
(() => {
    'use strict';

    const TURNSTILE_SELECTOR = '.cf-turnstile';
    const TURNSTILE_RESPONSE_SELECTOR = 'input[name="cf-turnstile-response"]';
    const HCAPTCHA_RESPONSE_SELECTOR = 'textarea[name="h-captcha-response"]';
    const DEFAULT_MESSAGE = 'Please complete the captcha verification.';

    let validationInitialized = false;
    let monitorInitialized = false;

    const nonEmpty = (value) => (value && value.trim() !== '' ? value : null);

    const getTurnstileResponse = (container) => {
        // The input is rendered inside the widget, older widgets may place it elsewhere in the form
        const input = container.querySelector(TURNSTILE_RESPONSE_SELECTOR)
            ?? container.closest('form')?.querySelector(TURNSTILE_RESPONSE_SELECTOR);
        return nonEmpty(input?.value);
    };

    const getHcaptchaResponse = (container) => nonEmpty(container.querySelector(HCAPTCHA_RESPONSE_SELECTOR)?.value);

    const showNetteStyleModal = (message) => {
        const dialog = document.createElement('dialog');

        // Check if browser supports dialog element
        if (!dialog.showModal) {
            alert(message);
            return;
        }

        // Create modal styling (same as Nette's modal)
        const style = document.createElement('style');
        style.innerText = '.netteFormsModal { text-align: center; margin: auto; border: 2px solid black; padding: 1rem } .netteFormsModal button { padding: .1em 2em }';

        const button = document.createElement('button');
        button.innerText = 'OK';
        button.onclick = () => dialog.remove();

        dialog.setAttribute('class', 'netteFormsModal');
        dialog.innerText = message + '\n\n';
        dialog.append(style, button);
        document.body.append(dialog);
        dialog.showModal();

        // Focus the button for better accessibility
        button.focus();
    };

    const showValidationError = (message, element) => {
        const errorMessage = nonEmpty(message) ?? DEFAULT_MESSAGE;

        // Use Nette's error reporting if available
        if (typeof Nette !== 'undefined' && typeof Nette.showFormErrors === 'function') {
            try {
                Nette.showFormErrors(element.closest('form'), [{ message: errorMessage, element }]);
                return;
            } catch (e) {
                console.error('Error showing validation error:', e); // Fallthrough to custom handling
            }
        }

        // Create a Nette-style modal if Nette is loaded but its error reporting isn't usable
        if (typeof Nette !== 'undefined' || document.querySelector('script[src*="nette-forms"]')) {
            showNetteStyleModal(errorMessage);
        } else {
            alert(errorMessage);
        }
    };

    const executeInvisibleHcaptcha = (container, form, submitter) => {
        const message = container.getAttribute('data-require-hcaptcha');
        const widgetId = container.getAttribute('data-hcaptcha-widget-id')
            ?? container.querySelector('iframe[data-hcaptcha-widget-id]')?.getAttribute('data-hcaptcha-widget-id');
        if (typeof hcaptcha === 'undefined' || !widgetId) {
            showValidationError(message, container);
            return;
        }

        // The form is submitted again with the original submitter once solved
        hcaptcha.execute(widgetId, { async: true }).then(
            () => (typeof form.requestSubmit === 'function' ? form.requestSubmit(submitter ?? undefined) : form.submit()),
            () => showValidationError(message, container)
        );
    };

    /**
     * Returns false when the form must not be submitted yet
     */
    const validateCaptchas = (form, submitter) => {
        for (const container of form.querySelectorAll('[data-require-hcaptcha]')) {
            if (getHcaptchaResponse(container)) {
                continue;
            }
            if (container.getAttribute('data-size') === 'invisible') {
                executeInvisibleHcaptcha(container, form, submitter);
            } else {
                showValidationError(container.getAttribute('data-require-hcaptcha'), container.querySelector('iframe') ?? container);
            }
            return false;
        }

        for (const container of form.querySelectorAll('[data-require-turnstile]')) {
            if (!getTurnstileResponse(container)) {
                showValidationError(container.getAttribute('data-require-turnstile'), container);
                return false;
            }
        }

        return true;
    };

    function initCaptchaValidation() {
        if (validationInitialized) {
            return;
        }
        validationInitialized = true;

        // Capture phase runs before submit handlers on the form itself, e.g. AJAX submission by Naja
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            // Buttons without validation (Nette validation scope) skip the captcha on the server too
            if (event.submitter?.hasAttribute('formnovalidate')) {
                return;
            }

            if (!validateCaptchas(form, event.submitter)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    }

    const toggle = (element, visible) => {
        if (element) {
            element.style.display = visible ? '' : 'none';
        }
    };

    /**
     * Shows managed messages of invisible Turnstile according to its state
     */
    const updateManagedMessages = (widget) => {
        // Messages are rendered next to the widget
        const scope = widget.parentNode;
        const pendingMessage = scope?.querySelector('.captcha-managed-invisible-message-pending');
        const resolvedMessage = scope?.querySelector('.captcha-managed-invisible-message-resolved');
        if (!pendingMessage && !resolvedMessage) {
            return;
        }

        // Visible captcha shows its own state
        if (widget.querySelector('iframe')) {
            toggle(pendingMessage, false);
            toggle(resolvedMessage, false);
            return;
        }

        const solved = getTurnstileResponse(widget) !== null;
        toggle(pendingMessage, !solved);
        toggle(resolvedMessage, solved);
    };

    const updateAllManagedMessages = () => document.querySelectorAll(TURNSTILE_SELECTOR).forEach(updateManagedMessages);

    function initCaptchaDOMMonitor() {
        if (monitorInitialized) {
            return;
        }
        monitorInitialized = true;

        // Called by Turnstile through data-callback, data-expired-callback and data-timeout-callback
        window.captchaTurnstileUpdate = updateAllManagedMessages;

        const startMonitoring = () => {
            updateAllManagedMessages();

            // Widgets added later (e.g. AJAX snippets) and widgets rendered by Turnstile
            new MutationObserver((mutations) => {
                const widgets = new Set();
                for (const mutation of mutations) {
                    const target = mutation.target instanceof Element ? mutation.target.closest(TURNSTILE_SELECTOR) : null;
                    if (target) {
                        widgets.add(target);
                    }
                    for (const node of mutation.addedNodes) {
                        if (node instanceof Element) {
                            if (node.matches(TURNSTILE_SELECTOR)) {
                                widgets.add(node);
                            }
                            node.querySelectorAll(TURNSTILE_SELECTOR).forEach((widget) => widgets.add(widget));
                        }
                    }
                }
                widgets.forEach(updateManagedMessages);
            }).observe(document.body, { childList: true, subtree: true });
        };

        if (document.body) {
            startMonitoring();
        } else {
            document.addEventListener('DOMContentLoaded', startMonitoring);
        }
    }

    initCaptchaValidation();
    initCaptchaDOMMonitor();

    // Export for manual initialization if needed
    window.initCaptchaValidation = initCaptchaValidation;
    window.initCaptchaDOMMonitor = initCaptchaDOMMonitor;
})();
