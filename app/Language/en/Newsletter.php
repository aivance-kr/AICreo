<?php

declare(strict_types=1);

// Newsletter — subscription screen messages and mail copy. Keys mirror app/Language/ko/Newsletter.php.
return [
    'privacyNotice'      => 'We collect only your email address, use it solely to send new post alerts, and stop sending once you unsubscribe. No email is sent before you confirm your subscription.',
    'confirmationSent'   => 'We sent you a confirmation email. Click the link in it to complete your subscription.',
    'invalidEmail'       => 'Please enter a valid email address.',
    'tooManyRequests'    => 'Too many requests. Please try again later.',
    'confirmOkTitle'     => 'You are subscribed',
    'confirmOkMessage'   => 'We will email you whenever a new post is published. You can unsubscribe at any time using the link at the bottom of the email.',
    'invalidLinkTitle'   => 'This link is not valid',
    'confirmFailMessage' => 'This confirmation link has expired or you have already unsubscribed. Please sign up again using the subscription form.',
    'unsubOkTitle'       => 'You have been unsubscribed',
    'unsubOkMessage'     => 'We will no longer send you the newsletter.',
    'unsubFailMessage'   => 'Please check the unsubscribe link.',
    'pageTitle'          => 'Newsletter',
    'pageDesc'           => 'Newsletter subscription',
    'mailConfirmIntro'   => 'We received your request to subscribe to new post alerts from {0}.',
    'mailConfirmGuide'   => 'Click the link below to complete your subscription. If you did not sign up, please ignore this email.',
    'mailConfirmLink'    => 'Confirm subscription',
    'mailConfirmSubject' => '[{0}] Please confirm your subscription',
    'mailReadPost'       => 'Read post',
    'mailUnsubscribe'    => 'Unsubscribe',
];
