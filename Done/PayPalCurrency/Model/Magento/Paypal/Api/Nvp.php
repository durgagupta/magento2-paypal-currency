<?php 

namespace  Done\PayPalCurrency\Model\Magento\Paypal\Api;

use function Zend\Mvc\Controller\params;

class Nvp extends \Magento\Paypal\Model\Api\Nvp
{
	
	
	protected $_doneHelper;
	
	
	/**
	 * Prepare line items request
	 *
	 * Returns true if there were line items added
	 *
	 * @param array &$request
	 * @param int $i
	 * @return true|null
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	protected function _exportLineItems(array &$request, $i = 0)
	{
		
		if (!$this->getHelper()->isEnable() ) {
			return parent::_exportLineItems($request, $i);
		}
		
		
		if (!$this->_cart) {
            return;
        }
        $this->_cart->setTransferDiscountAsItem();
	
		// always add cart totals, even if line items are not requested
		if ($this->_lineItemTotalExportMap) {
			foreach ($this->_cart->getAmounts() as $key => $total) {
				if (isset($this->_lineItemTotalExportMap[$key])) {
					// !empty($total)
					$privateKey = $this->_lineItemTotalExportMap[$key];
					$request[$privateKey] = $this->formatPrice($total * $this->getHelper()->getConvertRate());
				}
			}
		}
	
		// add cart line items
		$items = $this->_cart->getAllItems();
		if (empty($items) || !$this->getIsLineItemsEnabled()) {
			return;
		}
		$result = null;
		foreach ($items as $item) {
			foreach ($this->_lineItemExportItemsFormat as $publicKey => $privateFormat) {
				$result = true;
				$value = $item->getDataUsingMethod($publicKey);
				if (isset($this->_lineItemExportItemsFilters[$publicKey])) {
					$callback = $this->_lineItemExportItemsFilters[$publicKey];
					$value = call_user_func([$this, $callback], $value);
				}
				if (is_float($value)) {
					$value = $this->formatPrice($value * $this->getHelper()->getConvertRate());
				}
				$request[sprintf($privateFormat, $i)] = $value;
			}
			$i++;
		}
		return $result;
	}
	
	
	/**
	 * DoCapture call
	 *
	 * @return void
	 * @link https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_DoCapture
	 */
	public function callDoCapture()
	{
		if (!$this->getHelper()->isEnable() ) {
			parent::callDoCapture();
		}
		
		$this->setCompleteType($this->_getCaptureCompleteType());
		$request = $this->_exportToRequest($this->_doCaptureRequest);
		
		if (isset($request['AMT'])) 
			$request['AMT'] = $this->formatPrice(($request['AMT']) * $this->getHelper()->getConvertRate());
		
		if (isset($request['CURRENCYCODE']))
			$request['CURRENCYCODE'] = $this->getHelper()->getCurrencyCode();
		
		$response = $this->call(self::DO_CAPTURE, $request);
		$this->_importFromResponse($this->_paymentInformationResponse, $response);
		$this->_importFromResponse($this->_doCaptureResponse, $response);
	}
	
	
	public function getHelper() {
		if (!$this->_doneHelper) {

			$this->_doneHelper = \Magento\Framework\App\ObjectManager::getInstance()->get('\Done\PayPalCurrency\Helper\Data');
		}
		
		return $this->_doneHelper;
	}
	
}
?>