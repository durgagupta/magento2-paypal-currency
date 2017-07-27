<?php
/**
 * @author dsgupta
 *
 */
namespace Done\PayPalCurrency\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper {
	
	protected $doneObjectManager;
	protected $scopeConfig;
	
	/**
	 * @param \Magento\Framework\App\Helper\Context $context
	 * @param \Magento\Framework\ObjectManagerInterface $doneObjectManager
	 */
	public function __construct(\Magento\Framework\App\Helper\Context $context, 
								\Magento\Framework\ObjectManagerInterface $doneObjectManager) {

		$this->doneObjectManager = $doneObjectManager;
		$this->scopeConfig = $this->doneObjectManager->create ('\Magento\Framework\App\Config\ScopeConfigInterface');
		parent::__construct ( $context );
	}
	
	
	/**
	 * @param unknown $path
	 * @return mixed
	 */
	public function getStoreConfig($path) {
		return $this->scopeConfig->getValue ( $path );
	}
	
	/**
	 *
	 * @return mixed
	 */
	public function isEnable() {
		
		return $this->getStoreConfig ( "donepaypal/general/enable");
	}
	
	
	/**
	 * @return mixed
	 */
	public function getCurrencyCode() {
		return $this->getStoreConfig ( "donepaypal/general/currency_symbol");
	}
	
	
	/**
	 * @return mixed
	 */
	public function getConvertRate() {
		return $this->getStoreConfig ( "donepaypal/general/conversion_rate" );
	}
}