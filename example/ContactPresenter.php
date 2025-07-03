<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Utils\Html;

/**
 * Example presenter showing how to use the Captcha extension
 */
class ContactPresenter extends Presenter
{
    protected function createComponentContactForm(): Form
    {
        $form = new Form;
        
        // Standard form fields
        $form->addText('name', 'Name:')
            ->setRequired('Please enter your name');
            
        $form->addEmail('email', 'Email:')
            ->setRequired('Please enter your email');
            
        $form->addTextArea('message', 'Message:')
            ->setRequired('Please enter your message')
            ->setHtmlAttribute('rows', 5);
        
        // Different ways to add captcha:
        // Note: The CaptchaValidator is automatically injected by the DI extension
        
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

        // 4. Invisible captcha (only for Turnstile)
        // The control + label will be hidden, the only thing that can be shown is required message.
        $form->addCaptcha('captcha', 'Bot protection')
            ->setInvisible(true);
        
        $form->addSubmit('submit', 'Send Message');
        
        $form->onSuccess[] = [$this, 'contactFormSucceeded'];
        
        return $form;
    }
    
    public function contactFormSucceeded(Form $form): void
    {
        $values = $form->getValues();
        
        // The captcha has already been validated automatically at this point
        // You can now safely process the form data
        
        // Example: Send email, save to database, etc.
        $this->sendContactEmail($values);
        
        $this->flashMessage('Thank you! Your message has been sent successfully.', 'success');
        $this->redirect('this');
    }
    
    private function sendContactEmail($values): void
    {
        // ...
    }
} 