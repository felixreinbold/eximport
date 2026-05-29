<?php

$sMetadataVersion = '2.1';


$aModule = [
    'id'           => 'eximport',
    'title'        => [
        'de' => 'Suedlicht eximport Excel Import/Export',
        'en' => 'Suedlicht eximport Excel Import/Export',
    ],
    'description'  => [
        'de' => 'Artikel massenweise via Excel bearbeiten.',
        'en' => 'Bulk edit articles via Excel.',
    ],
    'thumbnail' => 'pictures/logo.png',
    'version'      => '1.0.0',
    'author'       => 'felixreinbold',
    'controllers'  => [
        'eximport_main' => \Suedlicht\Eximport\Controller\Admin\EximMainController::class,
    ],
];