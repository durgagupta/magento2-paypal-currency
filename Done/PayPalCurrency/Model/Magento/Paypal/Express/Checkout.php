<?php

namespace Done\PayPalCurrency\Model\Magento\Paypal\Express;



use Magento\Paypal\Model\Config as PaypalConfig;
use Magento\Quote\Model\Quote\Address;
use Magento\Paypal\Model\Cart as PaypalCart;
use Braintree\Exception;


class Checkout extends \Magento\Paypal\Model\Express\Checkout
{
	
	protected $_doneHelper;
	
	 /**
     * Reserve order ID for specified quote and start checkout on PayPal
     *
     * @param string $returnUrl
     * @param string $cancelUrl
     * @param bool|null $button
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function start($returnUrl, $cancelUrl, $button = null)
    {
    	
    	if (!$this->getHelper()->isEnable() ) {
    		
    		return parent::start($returnUrl, $cancelUrl, $button);
    	}
    	
        $this->_quote->collectTotals();

        if (!$this->_quote->getGrandTotal()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'PayPal can\'t process orders with a zero balance due. '
                    . 'To finish your purchase, please go through the standard checkout process.'
                )
            );
        }

        $this->_quote->reserveOrderId();
        $this->quoteRepository->save($this->_quote);
        // prepare API
        $this->_getApi();
        
        $solutionType = $this->_config->getMerchantCountry() == 'DE'
            ? \Magento\Paypal\Model\Config::EC_SOLUTION_TYPE_MARK
            : $this->_config->getValue('solutionType');
        $this->_api->setAmount($this->_quote->getBaseGrandTotal() * $this->getHelper()->getConvertRate())
            //->setCurrencyCode($this->_quote->getBaseCurrencyCode())
            ->setCurrencyCode($this->getHelper()->getCurrencyCode())
            ->setInvNum($this->_quote->getReservedOrderId())
            ->setReturnUrl($returnUrl)
            ->setCancelUrl($cancelUrl)
            ->setSolutionType($solutionType)
            ->setPaymentAction($this->_config->getValue('paymentAction'));
        if ($this->_giropayUrls) {
            list($successUrl, $cancelUrl, $pendingUrl) = $this->_giropayUrls;
            $this->_api->addData(
                [
                    'giropay_cancel_url' => $cancelUrl,
                    'giropay_success_url' => $successUrl,
                    'giropay_bank_txn_pending_url' => $pendingUrl,
                ]
            );
        }

        if ($this->_isBml) {
            $this->_api->setFundingSource('BML');
        }

        $this->_setBillingAgreementRequest();

        if ($this->_config->getValue('requireBillingAddress') == PaypalConfig::REQUIRE_BILLING_ADDRESS_ALL) {
            $this->_api->setRequireBillingAddress(1);
        }

        // suppress or export shipping address
        $address = null;
        if ($this->_quote->getIsVirtual()) {
            if ($this->_config->getValue('requireBillingAddress')
                == PaypalConfig::REQUIRE_BILLING_ADDRESS_VIRTUAL
            ) {
                $this->_api->setRequireBillingAddress(1);
            }
            $this->_api->setSuppressShipping(true);
        } else {
        	
//             $address = $this->_quote->getShippingAddress();
//             $isOverridden = 0;
//             if (true === $address->validate()) {
//                 $isOverridden = 1;
//                 $this->_api->setAddress($address);
//             }
//             $this->_quote->getPayment()->setAdditionalInformation(
//                 self::PAYMENT_INFO_TRANSPORT_SHIPPING_OVERRIDDEN,
//                 $isOverridden
//             );
//             $this->_quote->getPayment()->save();
        	$this->_api->setSuppressShipping(true);
        }

        /** @var $cart \Magento\Payment\Model\Cart */
        $cart = $this->_cartFactory->create(['salesModel' => $this->_quote]);

        $this->_api->setPaypalCart($cart);

        if (!$this->_taxData->getConfig()->priceIncludesTax()) {
            $this->setShippingOptions($cart, $address);
        }

        $this->_config->exportExpressCheckoutStyleSettings($this->_api);

        /* Temporary solution. @TODO: do not pass quote into Nvp model */
        $this->_api->setQuote($this->_quote);
        $this->_api->callSetExpressCheckout();

        $token = $this->_api->getToken();

        $this->_setRedirectUrl($button, $token);

        $payment = $this->_quote->getPayment();
        $payment->unsAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT);
        // Set flag that we came from Express Checkout button
        if (!empty($button)) {
            $payment->setAdditionalInformation(self::PAYMENT_INFO_BUTTON, 1);
        } elseif ($payment->hasAdditionalInformation(self::PAYMENT_INFO_BUTTON)) {
            $payment->unsAdditionalInformation(self::PAYMENT_INFO_BUTTON);
        }
        $payment->save();

        return $token;
    }
    
    
    /**
     * Set shipping options to api
     * @param \Magento\Paypal\Model\Cart $cart
     * @param \Magento\Quote\Model\Quote\Address|null $address
     * @return void
     */
    protected function setShippingOptions(PaypalCart $cart, Address $address = null)
    {
    	
    	if (!$this->getHelper()->isEnable() ) {
    		
    		parent::setShippingOptions($cart, $address);
    	}
    	// for included tax always disable line items (related to paypal amount rounding problem)
    	$this->_api->setIsLineItemsEnabled($this->_config->getValue(PaypalConfig::TRANSFER_CART_LINE_ITEMS));
    
    	// add shipping options if needed and line items are available
    	$cartItems = $cart->getAllItems();
    	if ($this->_config->getValue(PaypalConfig::TRANSFER_CART_LINE_ITEMS)
    			&& $this->_config->getValue(PaypalConfig::TRANSFER_SHIPPING_OPTIONS)
    			&& !empty($cartItems)
    			) {
    				if (!$this->_quote->getIsVirtual()) {
    					$options = $this->_prepareShippingOptions($address, true);
    					if ($options) {
    						$this->_api->setShippingOptionsCallbackUrl(
    								$this->_coreUrl->getUrl(
    										'*/*/shippingOptionsCallback',
    										['quote_id' => $this->_quote->getId()]
    										)
    								)->setShippingOptions($options);
    					}
    				}
    			}
    }
    
    /**
     * 
     */
    public function getHelper() {
    	if (!$this->_doneHelper) {
    		
    		$this->_doneHelper = \Magento\Framework\App\ObjectManager::getInstance()->get('\Done\PayPalCurrency\Helper\Data');
    	}
    
    	return $this->_doneHelper;
    }
	
}
	
	