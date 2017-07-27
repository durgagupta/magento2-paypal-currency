<?php

namespace Done\PayPalCurrency\Model\Magento\Paypal;

use Magento\Sales\Model\Order\Payment;
use Magento\Paypal\Model\Express\Checkout as ExpressCheckout;

class Express extends \Magento\Paypal\Model\Express {
	
	protected $_doneHelper;
	
	/**
     * Place an order with authorization or capture action
     *
     * @param Payment $payment
     * @param float $amount
     * @return $this
     */
    protected function _placeOrder(Payment $payment, $amount)
    {
    	
    	if (!$this->getHelper()->isEnable() ) {
    		
    		return parent::_placeOrder($payment, $amount);
    	}
    	
    	
        $order = $payment->getOrder();

        // prepare api call
        $token = $payment->getAdditionalInformation(ExpressCheckout::PAYMENT_INFO_TRANSPORT_TOKEN);

        $cart = $this->_cartFactory->create(['salesModel' => $order]);

        $api = $this->getApi()->setToken(
            $token
        )->setPayerId(
            $payment->getAdditionalInformation(ExpressCheckout::PAYMENT_INFO_TRANSPORT_PAYER_ID)
        )->setAmount(
            $amount* $this->getHelper()->getConvertRate()
        )->setPaymentAction(
            $this->_pro->getConfig()->getValue('paymentAction')
        )->setNotifyUrl(
            $this->_urlBuilder->getUrl('paypal/ipn/')
        )->setInvNum(
            $order->getIncrementId()
        )->setCurrencyCode(
        	$this->getHelper()->getCurrencyCode()
            //$order->getBaseCurrencyCode()
        )->setPaypalCart(
            $cart
        )->setIsLineItemsEnabled(
            $this->_pro->getConfig()->getValue('lineItemsEnabled')
        );
        if ($order->getIsVirtual()) {
            $api->setAddress($order->getBillingAddress())->setSuppressShipping(true);
        } else {
//             $api->setAddress($order->getShippingAddress());
//             $api->setBillingAddress($order->getBillingAddress());
        	$api->setAddress($order->getBillingAddress())->setSuppressShipping(true);
        }
        
        // condition for not to add more item
        $this->_registry->unregister('is_paypal_items');
        $this->_registry->register('is_paypal_items', 4);

        // call api and get details from it
        $api->callDoExpressCheckoutPayment();

        $this->_importToPayment($api, $payment);
        return $this;
    }
    
    
    public function getHelper() {
    	if (!$this->_doneHelper) {
    		
			$this->_doneHelper = \Magento\Framework\App\ObjectManager::getInstance()->get('\Done\PayPalCurrency\Helper\Data');
    	}
    
    	return $this->_doneHelper;
    }
}