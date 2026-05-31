<?php

use App\Message\ImportNewsMessage;
use App\Message\SendVerificationCodeMessage;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework) {
    $messenger = $framework->messenger();

    $messenger->transport('async')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->option('auto_setup', true);

    // На проде замените на transport 'async_amqp' с AMQP DSN
    $messenger->routing(ImportNewsMessage::class)->senders(['async']);
    $messenger->routing(SendVerificationCodeMessage::class)->senders(['async']);

    $messenger->defaultBus('messenger.bus.default');
};
