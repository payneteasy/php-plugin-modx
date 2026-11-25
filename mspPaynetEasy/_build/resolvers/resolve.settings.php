<?php

/** @var xPDOSimpleObject $object */
if ($object->xpdo) {
    /* @var modX $modx */
    $modx = $object->xpdo;

    /** @var array $options */
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:

            $sql = "CREATE TABLE IF NOT EXISTS payneteasy_payments (id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, paynet_order_id bigint unsigned NOT NULL, merchant_order_id int NOT NULL)";
            $modx->query($sql);
            // Gateway ids do not fit into int: widen the column of tables created by older versions
            $modx->query("ALTER TABLE payneteasy_payments MODIFY paynet_order_id bigint unsigned NOT NULL");

            $payment = $modx->getObject(msPayment::class, ['class' => 'PaynetEasy']);

            if (!$payment) {
                $q = $modx->newObject(msPayment::class);
                $q->fromArray([
                    'name' => 'PaynetEasy Payment',
                    'rank' => 0,
                    'active' => 1,
                    'class' => 'PaynetEasy',
                    'logo' => '/mspPaynetEasy/logo.png',
                    'description' => 'PaynetEasy Payment'
                ]);
                $save = $q->save();
            }

            /* @var miniShop2 $miniShop2 */
            $miniShop2 = $modx->getService('minishop2');

            if ($miniShop2) {
                $miniShop2->addService(
                    'payment',
                    'PaynetEasy',
                    '{core_path}components/minishop2/custom/payment/payneteasy.class.php'
                );
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:

            $sql = "DROP TABLE `payneteasy_payments`";
            $modx->query($sql);

            $miniShop2 = $modx->getService('minishop2');
            $miniShop2->removeService(
                'payment',
                'PaynetEasy'
            );
            $payment = $modx->getObject(msPayment::class, ['class' => 'PaynetEasy']);
            if ($payment) {
                $payment->remove();
            }
            $modx->removeCollection(modSystemSetting::class, ['key:LIKE' => 'ms2\_payment\_rb\_%']);
            break;
    }
}
return true;