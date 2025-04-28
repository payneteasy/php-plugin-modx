<?php

/**
 * Loads system settings into build
 * @var modX $modx
 * @package mspPaynetEasy
 * @subpackage build
 */
$settings = [];

$configPrefix = 'ms2_payment_payneteasy_';

$tmp = [
    'live_url' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms2_payment',
    ],
    'sandbox_url' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms2_payment',
    ],
    'login' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms2_payment',
    ],
    'control_key' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms2_payment',
    ],
    'endpoint_id' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'ms2_payment',
    ],
    'payment_method' => [
        'value' => 'form',
        'xtype' => 'payneteasy_payment_method',
        'area' => 'ms2_payment',
    ],
    'sandbox' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms2_payment',
    ],
    'logging' => [
        'value' => true,
        'xtype' => 'combo-boolean',
        'area' => 'ms2_payment',
    ],
    'three_d_secure' => [
        'value' => false,
        'xtype' => 'combo-boolean',
        'area' => 'ms2_payment',
    ],
    'default_country' => [
        'value' => 'US',
        'xtype' => 'payneteasy_default_country',
        'area' => 'ms2_payment',
    ],
    'success_id' => [
        'xtype' => 'numberfield',
        'value' => 0,
        'area' => 'ms2_payment',
    ],
    'failure_id' => [
        'xtype' => 'numberfield',
        'value' => 0,
        'area' => 'ms2_payment',
    ]
];

foreach ($tmp as $k => $v) {
    /* @var modSystemSetting $setting */
    $setting = $modx->newObject(modSystemSetting::class);
    $setting->fromArray(array_merge(
        [
            'key' => $configPrefix . $k,
            'namespace' => 'minishop2',
            'editedon' => date('Y-m-d H:i:s'),
        ],
        $v
    ), '', true, true);

    $settings[] = $setting;
}

unset($tmp);
return $settings;