<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'local/integracao_ava:managesettings' => array(
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:config',
    ),
);