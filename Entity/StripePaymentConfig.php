<?php

namespace Plugin\StripePayment\Entity;

use Doctrine\ORM\Mapping as ORM;

class StripePaymentConfig extends \Eccube\Entity\AbstractEntity
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $live_public_key;

    /**
     * @var string
     */
    private $live_secret_key;

    /**
     * @var string
     */
    private $test_public_key;

    /**
     * @var string
     */
    private $test_secret_key;

    /**
     * @var integer
     */
    private $payment_id;

    /**
     * Set id
     *
     * @param integer $id
     * @return StripePaymentConfig
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set live_public_key
     *
     * @param string $livePublicKey
     * @return StripePaymentConfig
     */
    public function setLivePublicKey($livePublicKey)
    {
        $this->live_public_key = $livePublicKey;
        return $this;
    }

    /**
     * Get live_public_key
     *
     * @return string
     */
    public function getLivePublicKey()
    {
        return $this->live_public_key;
    }

    /**
     * Set live_secret_key
     *
     * @param string $liveSecretKey
     * @return StripePaymentConfig
     */
    public function setLiveSecretKey($liveSecretKey)
    {
        $this->live_secret_key = $liveSecretKey;
        return $this;
    }

    /**
     * Get live_secret_key
     *
     * @return string
     */
    public function getLiveSecretKey()
    {
        return $this->live_secret_key;
    }

    /**
     * Set test_public_key
     *
     * @param string $testPublicKey
     * @return StripePaymentConfig
     */
    public function setTestPublicKey($testPublicKey)
    {
        $this->test_public_key = $testPublicKey;
        return $this;
    }

    /**
     * Get test_public_key
     *
     * @return string
     */
    public function getTestPublicKey()
    {
        return $this->test_public_key;
    }

    /**
     * Set test_secret_key
     *
     * @param string $testSecretKey
     * @return StripePaymentConfig
     */
    public function setTestSecretKey($testSecretKey)
    {
        $this->test_secret_key = $testSecretKey;
        return $this;
    }

    /**
     * Get test_secret_key
     *
     * @return string
     */
    public function getTestSecretKey()
    {
        return $this->test_secret_key;
    }

    /**
     * Set payment_id
     *
     * @param integer $paymentId
     * @return Stripe
     */
    public function setPaymentId($paymentId)
    {
        $this->payment_id = $paymentId;
        return $this;
    }

    /**
     * Get payment_id
     *
     * @return integer
     */
    public function getPaymentId()
    {
        return $this->payment_id;
    }
}
