<?php

/**
 * payment method: Alipay
 */
namespace Plugin\StripePayment\Service;

use Eccube\Application;
use Eccube\Entity\Order;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Exception\ShoppingException;
use Plugin\StripePayment\Entity\StripePaymentConfig;
use Plugin\StripePayment\Exception\StripePaymentException;

class StripePaymentService
{
    /** @var \Eccube\Application */
    public $app;

    /** @var \Eccube\Service\ShoppingService */
    protected $shoppingService;


    public function __construct(Application $app, $shoppingService)
    {
        $this->app = $app;
        $this->shoppingService = $shoppingService;
    }

    /**
     * @param Order $Order
     * @param array $data
     */
    public function setFormData(Order $order, array $data)
    {
        $this->shoppingService->setFormData($order, $data);
    }

    /**
     * @param Order $Order
     * @throws ShoppingException
     */
    public function processPurchase(Order $order)
    {
        $em = $this->app['orm.em'];
        $this->shoppingService->calculatePrice($order);
        $check = $this->shoppingService->isOrderProduct($em, $order);
        if (!$check) {
            throw new ShoppingException('front.shopping.stock.error');
        }
        $order = $this->shoppingService->calculateDeliveryFee($order);
    }

    /**
     * @param Order $Order
     * @throws ShoppingException
     */
    public function processCharge(Order $order)
    {
        $em = $this->app['orm.em'];
        $order->setOrderDate(new \DateTime());
        $orderStatus = $this->app['eccube.repository.order_status']->find($this->app['config']['order_new']);
        $order->setOrderStatus($orderStatus);
        $event = new EventArgs(
            array(
                'Order' => $order,
            ),
            null
        );
        $this->app['eccube.event.dispatcher']->dispatch(EccubeEvents::SERVICE_SHOPPING_ORDER_STATUS, $event);

        $this->shoppingService->setStockUpdate($em, $order);

        $this->shoppingService->setCustomerUpdate($order, $this->app->user());
    }

    /**
     * Create Source
     */
    public function source(StripePaymentConfig $config, $url, $amount)
    {
        $publicKey = $config->getTestPublicKey();
        \Stripe\Stripe::setApiKey($publicKey);
        $source = \Stripe\Source::create(array(
            "type" => "alipay",
            "currency" => "jpy",
            "amount" => $amount,
            "redirect" => array(
                "return_url" => $url
            )
        ));
        log_info($source);
        if ($source['redirect']['url']) {
            return $source['redirect']['url'];
        } else {
            throw new StripePaymentException('front.shopping.payment.error');
        } 
    }

    public function retrieve(StripePaymentConfig $config, $source)
    {
        $secretKey = $config->getTestSecretKey();
        \Stripe\Stripe::setApiKey($secretKey);
        $retrieve = \Stripe\Source::retrieve($source);
        log_info($retrieve);
        if (!$retrieve['status']==='chargeable') {
            throw new StripePaymentException('front.shopping.payment.error');
        }
    }

    /**
     *
     */
    public function charge(StripePaymentConfig $config, $source, $amount)
    {
        $secretKey = $config->getTestSecretKey();
        \Stripe\Stripe::setApiKey($secretKey);
        $charge = \Stripe\Charge::create(array(
            "amount" => $amount,
            "currency" => "jpy",
            "source" => $source,
        ));
        log_info($charge);
        if (!$charge['status']==='succeeded') {
            throw new StripePaymentException('front.shopping.payment.error');
        }
    }
}
