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
use Eccube\Event\EventArgs;
use Eccube\Event\EccubeEvents;
use Eccube\Exception\ShoppingException;
use Plugin\StripePayment\Exception\StripePaymentException;
use Symfony\Component\HttpFoundation\Request;

class StripePaymentController
{

    /**
     * Buy Step
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function buystep(Application $app, Request $request)
    {
        // check user status
        if (!$app->isGranted('ROLE_USER')) {
            return $app->redirect($app->url('entry'));
        }

        // check email address
        $Customer = $app['user'];
        if (strstr($Customer->getEmail(), '@wechat.com')) {
            $app->addRequestError('邮箱未设置');
            return $app->redirect($app->url('mypage_change'));
        }

        // check delivery address
        $addressCurrNum = count($Customer->getCustomerAddresses());
        if (0 == $addressCurrNum) {
            $app->addRequestError('配送地址未设置');
            return $app->redirect($app->url('mypage_delivery'));
        }

        // FRONT_CART_BUYSTEP_INITIALIZE
        $event = new EventArgs(
            array(),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::FRONT_CART_BUYSTEP_INITIALIZE, $event);

        $app['eccube.service.cart']->lock();
        $app['eccube.service.cart']->save();

        // FRONT_CART_BUYSTEP_COMPLETE
        $event = new EventArgs(
            array(),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::FRONT_CART_BUYSTEP_COMPLETE, $event);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        return $app->redirect($app->url('shopping'));
    }

    /**
     * Shopping Confirm
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function confirm(Application $app, Request $request)
    {
        log_info('//////////////////////// confirm start ///////////////////////////////////');
        $cartService = $app['eccube.service.cart'];
        $shoppingService = $app['eccube.service.shopping'];
        $stripePaymentService = $app['eccube.plugin.service.stripe_payment'];

        if (!$cartService->isLocked()) {
            return $app->redirect($app->url('cart'));
        }

        $order = $app['eccube.service.shopping']->getOrder($app['config']['order_processing']);

        if (!$order) {
            $app->addError('front.shopping.order.error');
            return $app->redirect($app->url('shopping_error'));
        }

        if ('POST' !== $request->getMethod()) {
            return $app->redirect($app->url('cart'));
        }

        // form
        $builder = $shoppingService->getShippingFormBuilder($order);

        $event = new EventArgs(
            array(
                'builder' => $builder,
                'Order' => $order,
            ),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::FRONT_SHOPPING_CONFIRM_INITIALIZE, $event);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->isValidPayment($app, $form)) {
            $data = $form->getData();
            $em = $app['orm.em'];
            $em->getConnection()->beginTransaction();
            try {
                $this->checkPaymentType($order, $data);
                $stripePaymentService->setFormData($order, $data);
                $stripePaymentService->processPurchase($order);
                $em->flush();
                $em->getConnection()->commit();
                $config = $app['eccube.plugin.repository.stripe_payment_config']->find(1);
                $url = $app['eccube.plugin.service.stripe_payment']->source($config, $app->url('shopping_charge'), $order->getPaymentTotal());
            } catch (ShoppingException $e) {
                $em->getConnection()->rollback();
                $app->log($e);
                $app->addError($e->getMessage());
                return $app->redirect($app->url('shopping_error'));
            } catch (StripePaymentException $e) {
                $em->getConnection()->rollback();
                $app->addError('front.shopping.payment.error');
                $app->addError($e->getMessage());
                return $app->redirect($app->url('shopping_error'));
            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                $app->log($e);
                $app->addError('front.shopping.system.error');
                return $app->redirect($app->url('shopping_error'));
            }
        }

        return $app->redirect($url);
    }

    /**
     * Shopping Charge
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function charge(Application $app, Request $request)
    {
        $id = $request->query->get('source');
        $liveMode = $request->query->get('livemode');
        $clientSecret = $request->query->get('client_secret');
        if (!$id) {
            $app->addError('front.shopping.payment.error');
            return $app->redirect($app->url('shopping_error'));
        }

        $config = $app['eccube.plugin.repository.stripe_payment_config']->find(1);

        try {
            $app['eccube.plugin.service.stripe_payment']->retrieve($config, $id);
        } catch (StripePaymentException $e) {
            $app->addError('front.shopping.payment.error');
            return $app->redirect($app->url('shopping_error'));
        }

        $cartService = $app['eccube.service.cart'];
        $stripePaymentService = $app['eccube.plugin.service.stripe_payment'];
        if (!$cartService->isLocked()) {
            return $app->redirect($app->url('cart'));
        }

        $order = $app['eccube.service.shopping']->getOrder($app['config']['order_processing']);

        if (!$order) {
            $app->addError('front.shopping.order.error');
            return $app->redirect($app->url('shopping_error'));
        }

        if ('GET' !== $request->getMethod()) {
            return $app->redirect($app->url('cart'));
        }

        $em = $app['orm.em'];
        $em->getConnection()->beginTransaction();
        try {
            $charge = $app['eccube.plugin.service.stripe_payment']->charge($config, $id, $order->getPaymentTotal());
            $stripePaymentService->processCharge($order);
            $em->flush();
            $em->getConnection()->commit();
        } catch (ShoppingException $e) {
            $em->getConnection()->rollback();
            $app->log($e);
            $app->addError($e->getMessage());
            return $app->redirect($app->url('shopping_error'));
        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            $app->log($e);
            $app->addError('front.shopping.system.error');
            return $app->redirect($app->url('shopping_error'));
        }

        $app['eccube.service.cart']->clear()->save();
        $event = new EventArgs(
            array(
                'form' => $form,
                'Order' => $order,
            ),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::FRONT_SHOPPING_CONFIRM_PROCESSING, $event);
        if ($event->getResponse() !== null) {
            return $event->getResponse();
        }

        $app['session']->set($this->sessionOrderKey, $order->getId());
        $mailHistory = $app['eccube.service.shopping']->sendOrderMail($order);
        $event = new EventArgs(
            array(
                'form' => $form,
                'Order' => $order,
                'MailHistory' => $mailHistory,
            ),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::FRONT_SHOPPING_CONFIRM_COMPLETE, $event);
        if ($event->getResponse() !== null) {
            return $event->getResponse();
        }
        return $app->redirect($app->url('shopping_complete'));
    }

    /**
     * check payment
     */
    private function isValidPayment(Application $app, $form)
    {
        $data = $form->getData();
        $paymentId = $data['payment']->getId();
        $shippings = $data['shippings'];
        $validCount = count($shippings);
        foreach ($shippings as $Shipping) {
            $payments = $app['eccube.repository.payment']->findPayments($Shipping->getDelivery());
            if($payments == null){
                continue;
            }
            foreach($payments as $payment){
                if($payment['id'] == $paymentId){
                    $validCount--;
                    continue;
                }
            }
        }
        if($validCount == 0){
            return true;
        }
        $form->get('payment')->addError(new FormError('front.shopping.payment.error'));
        return false;
    }

    /**
     *
     * @param $Order Order
     * @param $data array
     * @throws \Eccube\Exception\ShoppingException
     */
    private function checkPaymentType($order, $data)
    {
        $orderPaymentId = $order->getPayment()->getId();
        $formPaymentId = $data['payment']->getId();
        if (empty($orderPaymentId) || empty($formPaymentId)) {
            throw new ShoppingException('front.shopping.system.error');
        }
        if ($orderPaymentId != $formPaymentId) {
            throw new ShoppingException('front.shopping.system.error');
        }
    }

}
