<?php

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework) {
    $messenger = $framework->messenger();

    $messenger->transport('async')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->option('auto_setup', true);

    $messenger->defaultBus('messenger.bus.default');
};