<?php

// Checking PHP Version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('Payment module "Payment system PAYNET" requires PHP version 7.4.0 or higher.');
}

/**
 * mspPaynetEasy build script
 *
 * @package mspPaynetEasy
 * @subpackage build
 */
set_time_limit(0);

require_once 'build.config.php';

/* define sources */
$root = dirname(__FILE__, 2) . '/';
$sources = [
    'root' => $root,
    'build' => $root . '_build/',
    'data' => $root . '_build/data/',
    'resolvers' => $root . '_build/resolvers/',
    'source_assets' => [
        'components/minishop2/payment/payneteasy.php',
        'components/csf/js/mgr/payneteasy-setting-fields.combo.js'
    ],
    'source_core' => [
        'components/minishop2/custom/payment/payneteasy.class.php',
        'components/minishop2/lexicon/en/msp.payneteasy.inc.php',
        'components/minishop2/lexicon/ru/msp.payneteasy.inc.php'
    ], 
    'docs' => $root . 'docs/'
];
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');

// Only a signed-in manager with the right to install packages may run the installer,
// and only by confirming it (POST with the manager token), not by opening a link.
if (!$modx->user || !$modx->user->hasSessionContext('mgr') || !$modx->hasPermission('packages')) {
    http_response_code(403);
    exit('Sign in to the MODX manager as an administrator, then open this page again.');
}
$token = (string) $modx->user->getUserToken('mgr');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $token === '' || !hash_equals($token, (string) ($_POST['token'] ?? ''))) {
    $t = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . PKG_NAME . ' ' . PKG_VERSION . '</title></head><body>'
        . '<h1>' . PKG_NAME . ' ' . PKG_VERSION . '</h1>'
        . '<form method="post"><input type="hidden" name="token" value="' . $t . '">'
        . '<button type="submit">Install</button> '
        . '<label><input type="checkbox" name="download" value="1"> download the package after install</label>'
        . '</form></body></html>';
    exit;
}
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

echo '<pre>'; /* used for nice formatting of log messages */
$tstart = str_replace(' ', '', microtime());
$modx->log(modX::LOG_LEVEL_INFO, "\n<br />Start time: {$tstart}\n");

$modx->log(modX::LOG_LEVEL_INFO, 'Created custom statuses.');
$customStatuses = [
    [
        'name' => 'On hold',
        'description' => 'Order on hold',
        'rank' => 7777,
        'color' => 'FFA500'
    ],
    [
        'name' => 'Returned',
        'description' => 'Order returned',
        'rank' => 8888,
        'color' => 'FF0000'
    ]
];
foreach ($customStatuses as $customStatus) {
    if (!$modx->getObject('msOrderStatus', ['rank' => $customStatus['rank']])) {
        $newStatus = $modx->newObject('msOrderStatus', [
            'name' => $customStatus['name'],
            'description' => $customStatus['description'],
            'rank' => $customStatus['rank'],
            'color' => $customStatus['color']
        ]);
        $newStatus->save();
    }
}

$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
$modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package.');

/* load system settings */
if (defined('BUILD_SETTING_UPDATE')) {
    $settings = include $sources['data'] . 'transport.settings.php';
    if (!is_array($settings)) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in settings.');
    } 
    else {
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => BUILD_SETTING_UPDATE,
        ];
        foreach ($settings as $setting) {
            $vehicle = $builder->createVehicle($setting, $attributes);
            $builder->putVehicle($vehicle);
        }
        $modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($settings) . ' System Settings.');
    }
    unset($settings, $setting, $attributes);
}

/* @var msPayment $payment */
$payment = $modx->newObject(msPayment::class);
$payment->fromArray([
    'name' => 'PaynetEasy Payment',
    'rank' => 0,
    'active' => 1,
    'class' => 'PaynetEasy',
    'logo' => '/mspPaynetEasy/logo.png',
    'description' => 'PaynetEasy Payment',
    'properties' => null // todo: setup minimal default properties
]);

/* create payment vehicle */
$attributes = [
    xPDOTransport::UNIQUE_KEY => 'name',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => false
];
$vehicle = $builder->createVehicle($payment, $attributes);

$modx->log(modX::LOG_LEVEL_INFO, 'Adding file resolvers to payment...');
foreach ($sources['source_assets'] as $file) {
    $dir = dirname($file) . '/';
    $vehicle->resolve('file', [
        'source' => $root . 'assets/' . $file,
        'target' => "return MODX_ASSETS_PATH . '{$dir}';",
    ]);
}
foreach ($sources['source_core'] as $file) {
    $dir = dirname($file) . '/';
    $vehicle->resolve('file', [
        'source' => $root . 'core/' . $file,
        'target' => "return MODX_CORE_PATH . '{$dir}';"
    ]);
}
unset($file, $attributes);

$resolvers = ['settings'];
foreach ($resolvers as $resolver) {
    if ($vehicle->resolve('php', ['source' => $sources['resolvers'] . 'resolve.' . $resolver . '.php'])) {
        $modx->log(modX::LOG_LEVEL_INFO, 'Added resolver "' . $resolver . '" to category.');
    } 
    else {
        $modx->log(modX::LOG_LEVEL_INFO, 'Could not add resolver "' . $resolver . '" to category.');
    }
}

flush();
$builder->putVehicle($vehicle);

/* now pack in the license file, readme and setup options */
$builder->setPackageAttributes([
    'changelog' => file_get_contents($sources['docs'] . 'changelog.txt'), 
    'license' => file_get_contents($sources['docs'] . 'license.txt'), 
    'readme' => file_get_contents($sources['docs'] . 'readme.txt')
]);
$modx->log(modX::LOG_LEVEL_INFO, 'Added package attributes and setup options.');

/* zip up package */
$modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
$builder->pack();
$modx->log(modX::LOG_LEVEL_INFO, '\n<br />Package Built.<br />');

$signature = $builder->getSignature();
if (defined('PKG_AUTO_INSTALL') && PKG_AUTO_INSTALL) {
    $sig = explode('-', $signature);
    $versionSignature = explode('.', $sig[1]);
    
    /* @var modTransportPackage $package */
    if (!$package = $modx->getObject('transport.modTransportPackage', ['signature' => $signature])) {
        $package = $modx->newObject('transport.modTransportPackage');
        $package->set('signature', $signature);
        $package->fromArray([
            'created' => date('Y-m-d h:i:s'),
            'updated' => null,
            'state' => 1,
            'workspace' => 1,
            'provider' => 0,
            'source' => $signature . '.transport.zip',
            'package_name' => $sig[0],
            'version_major' => $versionSignature[0],
            'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
            'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
        ]);
        if (!empty($sig[2])) {
            $r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($r) && !empty($r)) {
                $package->set('release', $r[0]);
                $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
            } 
            else {
                $package->set('release', $sig[2]);
            }
        }
        $package->save();
    }
    $package->install();
}

$modx->log(modX::LOG_LEVEL_INFO, 'Start adding a plug-in.');
// Создание плагина
$pluginName = 'PaynetEasySettingFields.php'; // Название вашего плагина
$eventName = 'OnManagerPageBeforeRender'; // Событие, при котором будет вызван ваш плагин

// Проверяем, существует ли плагин с таким именем
$existingPlugin = $modx->getObject('modPlugin', ['name' => $pluginName]);
if ($existingPlugin) {
    // Удаляем существующий плагин
    $existingPlugin->remove();
    $modx->log(modX::LOG_LEVEL_INFO, "Existing plugin '{$pluginName}' has been removed.");
}

$plugin = $modx->newObject('modPlugin');
$plugin->set('name', $pluginName);
$plugin->set('description', 'PaynetEasy custom setting fields');
$plugin->set('plugincode', '
    if ("OnManagerPageBeforeRender" !== $modx->event->name) {
    return; // exit if any other event happened
    }

    if ("system/settings" !== $_GET["a"]) {
    return; // exit if any other page than system settings loaded
    }

    $pathPrefix = MODX_ASSETS_URL . "components/csf/js/mgr/";

    $modx->controller->addLastJavascript($pathPrefix . "payneteasy-setting-fields.combo.js");
');
$plugin->set('disabled', 0);
// Сохранение плагина
if (!$plugin->save()) {
    $modx->log(modX::LOG_LEVEL_ERROR, "Could not save plugin: " . print_r($plugin->getErrors(), true));
}

// Привязка плагина к системному событию
$event = $modx->getObject('modEvent', ['name' => $eventName]);
if ($event) {
    $pluginEvent = $modx->newObject('modPluginEvent');
    $pluginEvent->set('pluginid', $plugin->get('id'));
    $pluginEvent->set('event', $eventName);
    $pluginEvent->save();
}

$modx->log(modX::LOG_LEVEL_INFO, 'End adding a plug-in.');

// Card fields of the direct payment form must not be kept in the miniShop2 order
// storage (session, usually stored in the database). The payment handler reads them
// from the order submit request.
$cardPluginName = 'PaynetEasyCardFields';
if ($existingPlugin = $modx->getObject('modPlugin', ['name' => $cardPluginName])) {
    $existingPlugin->remove();
}
$cardPlugin = $modx->newObject('modPlugin');
$cardPlugin->set('name', $cardPluginName);
$cardPlugin->set('description', 'PaynetEasy: keeps card data out of the order storage');
$cardPlugin->set('plugincode', '
    $cardFields = ["credit_card_number", "card_printed_name", "expire_month", "expire_year", "cvv2"];
    switch ($modx->event->name) {
        case "msOnBeforeAddToOrder":
            if (in_array($key, $cardFields, true)) {
                $modx->event->returnedValues["value"] = "";
            }
            break;
        case "msOnSubmitOrder":
            if (is_array($data)) {
                foreach ($cardFields as $field) {
                    unset($data[$field]);
                }
                $modx->event->returnedValues["data"] = $data;
            }
            break;
    }
');
$cardPlugin->set('disabled', 0);
if ($cardPlugin->save()) {
    foreach (['msOnBeforeAddToOrder', 'msOnSubmitOrder'] as $cardEvent) {
        $pluginEvent = $modx->newObject('modPluginEvent');
        $pluginEvent->set('pluginid', $cardPlugin->get('id'));
        $pluginEvent->set('event', $cardEvent);
        $pluginEvent->save();
    }
    $modx->log(modX::LOG_LEVEL_INFO, "Plugin '{$cardPluginName}' added.");
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, "Could not save plugin: " . print_r($cardPlugin->getErrors(), true));
}

// New plugins take effect only after the cache is refreshed
$modx->cacheManager->refresh();

if (!empty($_POST['download'])) {
    echo '<script>document.location.href = "/core/packages/' . $signature . '.transport.zip' . '";</script>';
}

$tend = str_replace(' ', '', microtime());
$totalTime = ($tend - $tstart);
$totalTime = sprintf('%2.4f s', $totalTime);

$modx->log(modX::LOG_LEVEL_INFO, "\n<br />Execution time: {$totalTime}\n");
echo '</pre>';