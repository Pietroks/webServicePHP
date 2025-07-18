<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Definição para o cache de vídeos
    'videos' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 900, // 15 minutos, igual ao que definimos no código
    ],
    // Definição para o cache de PDFs
    'pdfs' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 900, // 15 minutos
    ],
];