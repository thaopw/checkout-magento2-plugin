<?php
namespace Dfe\CheckoutCom;
use Dfe\CheckoutCom\Settings as S;
/**
 * 2016-07-17
 * A sample failure response:
	{
		"id": "charge_test_153AF6744E5J7A98E1D9",
		"responseMessage": "40144 - Threshold Risk - Decline",
		"responseAdvancedInfo": null,
		"responseCode": "40144",
		"status": "Declined",
		"authCode": "00000"
		...
	}
 */
class Exception extends \Df\Payment\Exception {
	/**
	 * 2016-07-17
	 * @override
	 * @see Df\Core\Exception::__construct()
	 * @param Response $response
	 */
	public function __construct(Response $response) {
		parent::__construct();
		$this->_r = $response;
	}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Core\Exception::getMessageForCustomer()
	 * @return string
	 */
	public function getMessageForCustomer() {return $this->_r->messageForCustomer();}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Core\Exception::getMessageRm()
	 * @return string
	 */
	public function getMessageRm() {return df_dump($this->_r->a([
		'status', 'responseMessage', 'id', 'responseCode', 'authCode', 'responseAdvancedInfo'
	]));}

	/**
	 * 2016-07-17
	 * @var Response
	 */
	private $_r;
}