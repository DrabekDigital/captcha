/**
 * Adds custom validation for hCaptcha and Turnstile
 */
(function() {
    'use strict';

    initCaptchaValidation();
    initCaptchaDOMMonitor();

    function initCaptchaValidation() {

        // Hook into form validation
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form.tagName || form.tagName.toLowerCase() !== 'form') {
                return;
            }

            // Check for hCaptcha validation requirements
            var hcaptchaContainers = form.querySelectorAll('[data-require-hcaptcha]');
            for (var i = 0; i < hcaptchaContainers.length; i++) {
                if (!validateHcaptcha(hcaptchaContainers[i])) {
                    e.preventDefault();
                    return false;
                }
            }

            // Check for Turnstile validation requirements
            var turnstileContainers = form.querySelectorAll('[data-require-turnstile]');
            for (var i = 0; i < turnstileContainers.length; i++) {
                if (!validateTurnstile(turnstileContainers[i], form)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }

    function initCaptchaDOMMonitor() {
        function startMonitoring() {
            // Initial check for existing cf-turnstile elements
            var turnstileElements = document.querySelectorAll('[class*="cf-turnstile"]');
            for (var i = 0; i < turnstileElements.length; i++) {
                setupTurnstileMonitor(turnstileElements[i]);
            }

            // Monitor for dynamically added cf-turnstile elements
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if the added node itself is a cf-turnstile element
                            if (node.className && node.className.indexOf('cf-turnstile') !== -1) {
                                setupTurnstileMonitor(node);
                            }
                            // Check if the added node contains cf-turnstile elements
                            var turnstileChildren = node.querySelectorAll ? node.querySelectorAll('[class*="cf-turnstile"]') : [];
                            for (var i = 0; i < turnstileChildren.length; i++) {
                                setupTurnstileMonitor(turnstileChildren[i]);
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        // Wait for DOM to be ready
        if (document.body) {
            startMonitoring();
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startMonitoring);
        } else {
            // Document is already loaded but body might not be available yet
            setTimeout(startMonitoring, 0);
        }
    }

    function setupTurnstileMonitor(turnstileElement) {
        // Check if there's an iframe under cf-turnstile
        var iframe = turnstileElement.querySelector('iframe');
        if (iframe) {
            // Visible captcha - no action needed
            return;
        }

        // No iframe found - likely invisible captcha, setup monitoring
        var form = turnstileElement.closest('form');
        if (!form) {
            return;
        }

        // Find message elements
        var pendingMessage = document.querySelector('.captcha-managed-invisible-message-pending');
        var resolvedMessage = document.querySelector('.captcha-managed-invisible-message-resolved');

        if (!pendingMessage && !resolvedMessage) {
            return;
        }

        var responseInput = form.querySelector('input[name="cf-turnstile-response"]');
        
        if (!responseInput) {
            // Show pending message initially
            updateMessageVisibility('', pendingMessage, resolvedMessage);
            
            // Monitor for the response input to be added
            var inputObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            var foundInput = null;
                            
                            // Check if the added node is the response input
                            if (node.name === 'cf-turnstile-response') {
                                foundInput = node;
                            }
                            // Check if the added node contains the response input
                            else if (node.querySelectorAll) {
                                foundInput = node.querySelector('input[name="cf-turnstile-response"]');
                            }
                            
                            if (foundInput) {
                                inputObserver.disconnect(); // Stop looking for the input
                                startResponseMonitoring(foundInput, pendingMessage, resolvedMessage);
                            }
                        }
                    });
                });
            });
            
            inputObserver.observe(form, {
                childList: true,
                subtree: true
            });
            
            return;
        }
        
        startResponseMonitoring(responseInput, pendingMessage, resolvedMessage);
    }

    function startResponseMonitoring(responseInput, pendingMessage, resolvedMessage) {
        // Initial state check
        updateMessageVisibility(responseInput.value, pendingMessage, resolvedMessage);

        // Monitor response input changes
        var observer = new MutationObserver(function() {
            updateMessageVisibility(responseInput.value, pendingMessage, resolvedMessage);
        });

        // Watch for attribute changes on the input
        observer.observe(responseInput, {
            attributes: true,
            attributeFilter: ['value']
        });

        // Also listen for input events
        responseInput.addEventListener('input', function() {
            updateMessageVisibility(responseInput.value, pendingMessage, resolvedMessage);
        });

        // Poll for value changes (in case value is set programmatically)
        var lastValue = responseInput.value;
        var pollInterval = setInterval(function() {
            if (responseInput.value !== lastValue) {
                lastValue = responseInput.value;
                updateMessageVisibility(responseInput.value, pendingMessage, resolvedMessage);
            }
            
            // Stop polling if element is removed from DOM
            if (!document.contains(responseInput)) {
                clearInterval(pollInterval);
            }
        }, 500);
    }

    function updateMessageVisibility(responseValue, pendingMessage, resolvedMessage) {
        var hasResponse = responseValue && responseValue.trim() !== '';

        if (pendingMessage) {
            pendingMessage.style.display = hasResponse ? 'none' : '';
        }

        if (resolvedMessage) {
            resolvedMessage.style.display = hasResponse ? '' : 'none';
        }
    }

    function findRequireHcaptchaContainer(elem) {
        // Look for the container with data-require-hcaptcha attribute
        var current = elem;
        while (current && current !== document) {
            if (current.hasAttribute && current.hasAttribute('data-require-hcaptcha')) {
                return current;
            }
            current = current.parentNode;
        }
        
        // If not found in parents, look in the form
        var form = elem.form || elem.closest('form');
        if (form) {
            return form.querySelector('[data-require-hcaptcha]');
        }
        
        return null;
    }

    function findRequireTurnstileContainer(elem) {
        // Look for the container with data-require-turnstile attribute
        var current = elem;
        while (current && current !== document) {
            if (current.hasAttribute && current.hasAttribute('data-require-turnstile')) {
                return current;
            }
            current = current.parentNode;
        }
        
        // If not found in parents, look in the form
        var form = elem.form || elem.closest('form');
        if (form) {
            return form.querySelector('[data-require-turnstile]');
        }
        
        return null;
    }

    function validateHcaptcha(container) {
        var iframe = container.querySelector('iframe[src*="hcaptcha.com"]');
        if (!iframe) {
            showValidationError(container.getAttribute('data-require-hcaptcha'));
            return false;
        }

        var response = iframe.getAttribute('data-hcaptcha-response');
        if (!response || response.trim() === '') {
            showValidationError(container.getAttribute('data-require-hcaptcha'));
            return false;
        }

        return true;
    }

    function validateTurnstile(container, form) {
        var responseInput = form.querySelector('input[name="cf-turnstile-response"]');
        if (!responseInput || !responseInput.value || responseInput.value.trim() === '') {
            showValidationError(container.getAttribute('data-require-turnstile'));
            return false;
        }

        return true;
    }

    function showValidationError(message) {
        var errorMessage = message && message.trim() !== '' ? message : 'Please complete the captcha verification.';
        
        // Try to use Nette's modal functionality if available
        if (typeof Nette !== 'undefined' && typeof Nette.showFormErrors === 'function') {
            // Try using Nette.showFormErrors if available
            Nette.showFormErrors(null, [{ message: errorMessage }]);
        } else {
            // Create a Nette-style modal if Nette is loaded but modal isn't accessible
            if (typeof Nette !== 'undefined' || document.querySelector('script[src*="nette-forms"]')) {
                showNetteStyleModal(errorMessage);
            } else {
                // Fallback to browser alert
                alert(errorMessage);
            }
        }
    }

    function showNetteStyleModal(message) {
        var dialog = document.createElement('dialog');
        
        // Check if browser supports dialog element
        if (!dialog.showModal) {
            alert(message);
            return;
        }

        // Create modal styling (same as Nette's modal)
        var style = document.createElement('style');
        style.innerText = '.netteFormsModal { text-align: center; margin: auto; border: 2px solid black; padding: 1rem } .netteFormsModal button { padding: .1em 2em }';

        // Create OK button
        var button = document.createElement('button');
        button.innerText = 'OK';
        button.onclick = function() {
            dialog.remove();
        };

        // Setup dialog
        dialog.setAttribute('class', 'netteFormsModal');
        dialog.innerText = message + '\n\n';
        dialog.append(style, button);
        document.body.append(dialog);
        dialog.showModal();
        
        // Focus the button for better accessibility
        button.focus();
    }

    // Export for manual initialization if needed
    if (typeof window !== 'undefined') {
        window.initCaptchaValidation = initCaptchaValidation;
        window.initCaptchaDOMMonitor = initCaptchaDOMMonitor;
    }
})(); 