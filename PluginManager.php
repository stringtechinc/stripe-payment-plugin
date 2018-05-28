<?php

/*
 * This file is part of the StripePayment
 *
 * Copyright (C) 2018 StringTech Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\StripePayment;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Util\Cache;
use Plugin\StripePayment\Entity\StripePaymentConfig;

class PluginManager extends AbstractPluginManager
{

    private $paymentMethod = 'Alipay';

    /**
     * プラグインインストール時の処理
     *
     * @param $config
     * @param Application $app
     * @throws \Exception
     */
    public function install($config, Application $app)
    {
        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code']);
    }

    /**
     * プラグイン削除時の処理
     *
     * @param $config
     * @param Application $app
     */
    public function uninstall($config, Application $app)
    {
        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code'], 0);
    }

    /**
     * プラグイン有効時の処理
     *
     * @param $config
     * @param Application $app
     * @throws \Exception
     */
    public function enable($config, Application $app)
    {
        $em = $app['orm.em'];
        $em->getConnection()->beginTransaction();
        try {
            $softDeleteFilter = $em->getFilters()->getFilter('soft_delete');
            $softDeleteFilter->setExcludes(array(
                'Eccube\Entity\Payment'
            ));
            $StripePaymentConfig = $em->getRepository('Plugin\StripePayment\Entity\StripePaymentConfig')->find(1);
            if (!$StripePaymentConfig) {
                $Payment = new Payment();
                $rank = $app['eccube.repository.payment']->findOneBy(array(), array('rank' => 'DESC'))->getRank() + 1;
                $Payment->setMethod($this->paymentMethod);
                $Payment->setCharge(0);
                $Payment->setRuleMin(0);
                $Payment->setFixFlg(Constant::ENABLED);
                $Payment->setChargeFlg(Constant::ENABLED);
                $Payment->setRank($rank);
                $Payment->setDelFlg(Constant::DISABLED);
                $em->persist($Payment);
                $em->flush($Payment);

                $StripePaymentConfig = new StripePaymentConfig();
                $StripePaymentConfig->setId(1);
                $StripePaymentConfig->setLivePublicKey('live public key');
                $StripePaymentConfig->setLiveSecretKey('live secret key');
                $StripePaymentConfig->setTestPublicKey('test public key');
                $StripePaymentConfig->setTestSecretKey('test secret key');
                $StripePaymentConfig->setPaymentId($Payment->getId());
                $em->persist($StripePaymentConfig);
                $em->flush($StripePaymentConfig);
            } else {
                $Payment = $app['eccube.repository.payment']->find($StripePaymentConfig->getPaymentId());
                if ($Payment) {
                    $Payment->setDelFlg(Constant::DISABLED);
                    $em->flush($Payment);
                }
            }
            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            throw $e;
        }
    }

    /**
     * プラグイン無効時の処理
     *
     * @param $config
     * @param Application $app
     * @throws \Exception
     */
    public function disable($config, Application $app)
    {
        $StripePaymentConfig = $app['orm.em']->getRepository('Plugin\StripePayment\Entity\StripePaymentConfig')->find(1);
        if ($StripePaymentConfig) {
            $Payment = $app['eccube.repository.payment']->find($StripePaymentConfig->getPaymentId());
            if ($Payment) {
                $Payment->setDelFlg(Constant::ENABLED);
               $app['orm.em']->flush($Payment);
            }
        }
    }

    /**
     * プラグイン更新時の処理
     *
     * @param $config
     * @param Application $app
     * @throws \Exception
     */
    public function update($config, Application $app)
    {
         $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code']);
    }
}
