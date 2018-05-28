<?php

/*
 * This file is part of the StripePayment
 *
 * Copyright (C) 2018 StringTech Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\StripePayment\Controller;

use Eccube\Application;
use Plugin\StripePayment\Entity\StripePaymentConfig;
use Symfony\Component\HttpFoundation\Request;

class ConfigController
{
    /**
     * Setting Page
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Application $app, Request $request)
    {
        $Config = $app['eccube.plugin.repository.stripe_payment_config']->find(1);

        if (!$Config) {
            $Config = new StripePaymentConfig();
        }

        $form = $app['form.factory']->createBuilder('stripe_payment_config', $Config)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Plugin\StripePayment\Entity\StripePaymentConfig $Config */
            $StripePaymentConfig = $form->getData();
            $StripePaymentConfig->setId(1);
            $app['orm.em']->persist($StripePaymentConfig);
            $app['orm.em']->flush();
            return $app->redirect($app->url('stripe_payment_config'));
        }
        return $app->render('StripePayment/Resource/template/admin/config.twig', array(
            'form' => $form->createView(),
        ));
    }
}
