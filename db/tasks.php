<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_ead_integration\task\sync_users',
        'blocking' => 0,
        'minute' => '0',      // Executa no minuto 0
        'hour' => '*/1',    // Executa a cada 1 hora
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];