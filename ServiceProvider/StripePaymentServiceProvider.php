<?php

/*
 * This file is part of the StripePayment
 *
 * Copyright (C) 2018 StringTech Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\StripePayment\ServiceProvider;

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Plugin\StripePayment\Form\Type\StripePaymentConfigType;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

class StripePaymentServiceProvider implements ServiceProviderInterface
{

    public function register(BaseApplication $app)
    {
        // admin
        $app->match('/' . $app['config']['admin_route'] . '/plugin/StripePayment/config', 'Plugin\StripePayment\Controller\ConfigController::index')->bind('stripe_payment_config');

        // cart
//        $app->match('/cart/buystep', 'Plugin\StripePayment\Controller\StripePaymentController::buystep')->bind('cart_buystep');

        // shopping
        $app->match('/shopping/confirm', 'Plugin\StripePayment\Controller\StripePaymentController::confirm')->bind('shopping_confirm');

//        $app->match('/shopping/pre_charge', 'Plugin\StripePayment\Controller\StripePaymentController::precharge')->bind('shopping_pre_charge');

        $app->match('/shopping/charge', 'Plugin\StripePayment\Controller\StripePaymentController::charge')->bind('shopping_charge');


        // Form
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new StripePaymentConfigType();
            return $types;
        }));

        // Repository
        $app['eccube.plugin.repository.stripe_payment_config'] = $app->share(function () use ($app) {
            return $app['orm.em']->getRepository('Plugin\StripePayment\Entity\StripePaymentConfig');
        });

        // Service
        $app['eccube.plugin.service.stripe_payment'] = $app->share(function () use ($app) {
            return new \Plugin\StripePayment\Service\StripePaymentService($app, $app['eccube.service.shopping']);
        });


        // メッセージ登録
        // $file = __DIR__ . '/../Resource/locale/message.' . $app['locale'] . '.yml';
        // $app['translator']->addResource('yaml', $file, $app['locale']);

        // load config
        // プラグイン独自の定数はconfig.ymlの「const」パラメータに対して定義し、$app['stripeconfig']['定数名']で利用可能
        // if (isset($app['config']['Stripe']['const'])) {
        //     $config = $app['config'];
        //     $app['stripeconfig'] = $app->share(function () use ($config) {
        //         return $config['Stripe']['const'];
        //     });
        // }

        // Config
        $app['config'] = $app->share($app->extend('config', function ($config) {
            $addNavi['id'] = 'admin_stripe_payment_config';
            $addNavi['name'] = 'Stripe設定';
            $addNavi['url'] = 'stripe_payment_config';
            $nav = $config['nav'];
            foreach ($nav as $key => $val) {
                if ('setting' == $val['id']) {
                    $nav[$key]['child'][] = $addNavi;
                }
            }
            $config['nav'] = $nav;

            return $config;
        }));

        // ログファイル設定
        $app['monolog.logger.stripe'] = $app->share(function ($app) {

            $logger = new $app['monolog.logger.class']('stripe');

            $filename = $app['config']['root_dir'].'/app/log/stripe.log';
            $RotateHandler = new RotatingFileHandler($filename, $app['config']['log']['max_files'], Logger::INFO);
            $RotateHandler->setFilenameFormat(
                'stripe_{date}',
                'Y-m-d'
            );

            $logger->pushHandler(
                new FingersCrossedHandler(
                    $RotateHandler,
                    new ErrorLevelActivationStrategy(Logger::ERROR),
                    0,
                    true,
                    true,
                    Logger::INFO
                )
            );

            return $logger;
        });

    }

    public function boot(BaseApplication $app)
    {
    }

}
