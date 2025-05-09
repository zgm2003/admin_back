<?php
return [
    'host'     => '127.0.0.1',
    'port'     => 5672,
    'user'     => 'admin',
    'password' => 'admin',
    'vhost'    => '/',
    'options'  => [
        'insist'      => false,
        'login_method'=> 'AMQPLAIN',
        'locale'     => 'en_US',
    ],
];
