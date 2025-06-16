<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    // Evento que dispara quando um usuário é criado
    [
        'eventname'   => '\core\event\user_created',
        'callback'    => '\local_avaead\observer::user_created',
    ],
);