<?php

use App\Message\ImportNewsMessage;
use App\Message\SendTelegramConfirmationMessage;
use App\Message\SendVerificationCodeMessage;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework) {
    $messenger = $framework->messenger();

    $messenger->transport('async')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->option('auto_setup', true);

    $asyncTelegram = $messenger->transport('async_telegram')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->option('auto_setup', true);

    $asyncTelegram->retryStrategy()
        ->maxRetries(3)
        ->delay(1000)
        ->multiplier(2.0)
        ->maxDelay(0);

    $messenger->transport('failed')
        ->dsn('doctrine://default?queue_name=failed&auto_setup=true');

    $messenger->routing(ImportNewsMessage::class)->senders(['async']);
    $messenger->routing(SendVerificationCodeMessage::class)->senders(['async']);
    $messenger->routing(SendTelegramConfirmationMessage::class)->senders(['async_telegram']);

    $messenger->failureTransport('failed');
    $messenger->defaultBus('messenger.bus.default');
};
