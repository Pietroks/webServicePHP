<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    // Observador para o evento de inscrição de um usuário em um curso.
    array(
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'local_integracao_ava_observer::user_enrolment_created',
    ),
);