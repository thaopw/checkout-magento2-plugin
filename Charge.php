<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Charges\RequestModels\CardTokenChargeCreate;
use com\checkout\ApiServices\SharedModels\Address as CAddress;
use com\checkout\ApiServices\SharedModels\Phone as CPhone;
use com\checkout\ApiServices\SharedModels\Product as CProduct;
use Dfe\CheckoutCom\Settings as S;
use Dfe\CheckoutCom\Source\Metadata;
use libphonenumber\PhoneNumberUtil as PhoneParser;
use libphonenumber\PhoneNumber as ParsedPhone;
use Magento\Payment\Model\Info;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Store\Model\Store;
class Charge extends \Df\Core\O {
	/**
	 * 2016-05-06
	 * @return CardTokenChargeCreate
	 */
	private function _build() {
		/** @var CardTokenChargeCreate $result */
		$result = new CardTokenChargeCreate;
		/**
		 * 2016-04-21
		 * «Order tracking id generated by the merchant.
		 * Max length of 100 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 * 2016-05-03
		 * Не является обязательным, но в целом приятно,
		 * когда в графе «Track ID» значится номер заказа вместо «Unknown».
		 */
		$result->setTrackId($this->order()->getIncrementId());
		$result->setCustomerName($this->address()->getName());
		/**
		 * 2016-04-21
		 * «The authorised charge must captured within 7 days
		 * or the charge will be automatically voided by the system
		 * and the reserved funds will be released.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/capture-card-charge
		 *
		 * «Accepted values either 'y' or 'n'.
		 * Default is is set to 'y'.
		 * Defines if the charge will be authorised ('n') or captured ('y').
		 * Authorisations will expire in 7 days.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 * Несмотря на то, что в документации буквы 'y' и 'n' — прописные,
		 * в примерах везде используются заглавные.
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#request-example
		 */
		$result->setAutoCapture($this->needCapture() ? 'Y' : 'N');
		/**
		 * 2016-04-21
		 * «Delayed capture time in hours between 0 and 168 inclusive
		 * that corresponds to 7 days (7x24).
		 * E.g. 0.5 interpreted as 30 mins.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setAutoCapTime(0);
		/**
		 * 2016-04-21
		 * «A valid charge mode: 1 for No 3D, 2 for 3D, 3 Local Payment.
		 * Default is 1 if not provided.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 *
		 * 2016-05-03
		 * С настройками Личного кабинета по умолчанию
		 * 3D-Secure будет насильно использоваться для платежей размером не меньше 150 долларов.
		 */
		$result->setChargeMode(1);
		/**
		 * 2016-04-21
		 * How are an order's getCustomerEmail() and setCustomerEmail() methods
		 * implemented and used?
		 * https://mage2.pro/t/1308
		 *
		 * «The email address or customer id of the customer.»
		 * «Either email or customerId required.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setEmail($this->order()->getCustomerEmail());
		/**
		 * 2016-04-23
		 * Нельзя одновременно устанавливать и email, и customerId.
		 * Причём товары передаются только при указании email:
		 * https://github.com/CKOTech/checkout-php-library/blob/7c9312e9/com/checkout/ApiServices/Charges/ChargesMapper.php#L142
		 */
		/*if ($order->getCustomerId()) {
			$request->setCustomerId($order->getCustomerId());
		} */
		/**
		 * 2016-04-21
		 * «A description that can be added to this object.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setDescription(df_var(S::s()->description(), $this->metaVars()));
		/**
		 * 2016-04-21
		 * «Expressed as a non-zero positive integer
		 * (i.e. decimal figures not allowed).
		 * Divide Bahraini Dinars (BHD), Kuwaiti Dinars (KWD),
		 * Omani Rials (OMR) and Jordanian Dinars (JOD) into 1000 units
		 * (e.g. "value = 1000" is equivalent to 1 Bahraini Dinar).
		 * Divide all other currencies into 100 units
		 * (e.g. "value = 100" is equivalent to 1 US Dollar).
		 * Checkout.com will perform the proper conversions for currencies
		 * that do not support fractional values.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setValue($this->cAmount());
		/**
		 * 2016-04-21
		 * «Three-letter ISO currency code
		 * representing the currency in which the charge was made.
		 * (refer to currency codes and names)»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setCurrency($this->currencyCode());
		/**
		 * 2016-04-21
		 * «Transaction indicator. 1 for regular, 2 for recurring, 3 for MOTO.
		 * Defaults to 1 if not specified.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setTransactionIndicator(1);
		/**
		 * 2016-04-21
		 * «Customer/Card holder Ip.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setCustomerIp($this->order()->getRemoteIp());
		/**
		 * 2016-04-21
		 * «A valid card token (with prefix card_tok_)»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setCardToken($this->token());
		$this->setProducts($result);
		/**
		 * 2016-04-23
		 * «Shipping address details.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setShippingDetails($this->cAddress());
		/**
		 * 2016-04-23
		 * «A hash of FieldName and value pairs e.g. {'keys1': 'Value1'}.
		 * Max length of key(s) and value(s) is 100 each.
		 * A max. of 10 KVP are allowed.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setMetadata($this->metaData());
		return $result;
	}

	/**
	 * 2016-05-06
	 * @return OrderAddress
	 */
	private function address() {
		if (!isset($this->{__METHOD__})) {
			/** @var OrderAddress $result */
			$result = $this->order()->getShippingAddress();
			$this->{__METHOD__} = $result ? $result : $this->order()->getBillingAddress();
			df_assert($this->{__METHOD__});
		}
		return $this->{__METHOD__};
	}

	/** @return float */
	private function amount() {return $this[self::$P__AMOUNT];}

	/**
	 * 2016-05-06
	 * @return CAddress
	 */
	private function cAddress() {
		if (!isset($this->{__METHOD__})) {
			/** @var OrderAddress $a */
			$a = $this->address();
			/** @var CAddress $result */
			$result = new CAddress;
			/**
			 * 2016-04-23
			 * «Address field line 1. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setAddressLine1($a->getStreetLine(1));
			/**
			 * 2016-04-23
			 * «Address field line 2. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setAddressLine2($a->getStreetLine(2));
			/**
			 * 2016-04-23
			 * «Address postcode. Max. length of 50 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setPostcode($a->getPostcode());
			/**
			 * 2016-04-23
			 * «The country ISO2 code e.g. US.
			 * See provided list of supported ISO formatted countries.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setCountry($a->getCountryId());
			/**
			 * 2016-04-23
			 * «Address city. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setCity($a->getCity());
			/**
			 * 2016-04-23
			 * «Address state. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setState($a->getRegion());
			/**
			 * 2016-04-23
			 * «Contact phone object for the card holder.
			 * If provided, it will contain the countryCode and number properties
			 * e.g. 'phone':{'countryCode': '44' , 'number':'12345678'}.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setPhone($this->cPhone());
			/**
			 * 2016-04-23
			 * «Shipping address details.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-06
	 * @param float|null $amount
	 * @return int
	 */
	private function cAmount($amount = null) {
		return Method::amount($this->payment(), $amount ? $amount : $this->amount());
	}

	/**
	 * 2016-05-06
	 * @return CPhone
	 */
	private function cPhone() {
		if (!isset($this->{__METHOD__})) {
			/**
			 * 2016-05-03
			 * https://github.com/giggsey/libphonenumber-for-php#quick-examples
			 * @var PhoneParser $phoneParser
			 */
			$phoneParser = PhoneParser::getInstance();
			/** @var CPhone $result */
			$result = new CPhone;
			try {
				/** @var ParsedPhone $parsedPhone */
			    $parsedPhone = $phoneParser->parse($a->getTelephone(), $a->getCountryId());
				/**
				 * 2016-04-23
				 * «Contact phone number for the card holder.
				 * Its length should be between 6 and 25 characters.
				 * Allowed characters are: numbers, +, (,) ,/ and ' '.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$result->setNumber($parsedPhone->getNationalNumber());
				/**
				 * 2016-04-23
				 * «Country code for the phone number of the card holder
				 * e.g. 44 for United Kingdom.
				 * Please refer to Country ISO and Code section
				 * in the Other Codes menu option.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$result->setCountryCode($parsedPhone->getCountryCode());
			} catch (\libphonenumber\NumberParseException $e) {}
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-06
	 * @param OrderItem $item
	 * @return CProduct
	 */
	private function cProduct(OrderItem $item) {
		/** @var CProduct $result */
		$result = new CProduct;
		/**
		 * 2016-04-23
		 * «Name of product. Max of 100 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		// Простые варианты имеют имена типа «New Very Prive-36-Almond»,
		// нам удобнее видеть имена простыми,
		// как у настраиваемого товара: «New Very Prive»).
		$result->setName(
			$item->getParentItem()
			? $item->getParentItem()->getName()
			: $item->getName()
		);
		$result->setProductId($item->getProductId());
		/**
		 * 2016-04-23
		 * «Description of the product.Max of 500 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setDescription($item->getDescription());
		/**
		 * 2016-04-23
		 * «Stock Unit Identifier.
		 * Unique product identifier.
		 * Max length of 100 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setSku($item->getSku());
		/**
		 * 2016-04-23
		 * «Product price per unit. Max. of 6 digits.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 *
		 * 2016-05-03
		 * Не используем здесь @see \Dfe\CheckoutCom\Method::amount(),
		 * потому что нам в данном случае нужно передавать цену в рублях,
		 * а не в копейках (в документации об э\том не сказано,
		 * проверял посредством личного кабинета checkout.com).
		 */
		$result->setPrice(df_order_item_price($item));
		/**
		 * 2016-04-23
		 * «Units of the product to be shipped. Max length of 3 digits.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setQuantity($item->getQtyOrdered());
		/**
		 * 2016-04-23
		 * «image link to product on merchant website.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setImage(df_product_image_url($item->getProduct()));
		return $result;
	}

	/** @return string */
	private function currencyCode() {return $this->order()->getBaseCurrencyCode();}

	/** @return array(string => string) */
	private function metaData() {
		return array_combine(
			dfa_select(Metadata::s()->map(), S::s()->metadata())
			,dfa_select($this->metaVars(), S::s()->metadata())
		);
	}

	/**
	 * 2016-05-06
	 * @return array(string => string)
	 */
	private function metaVars() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = Metadata::vars($this->store(), $this->order());
		}
		return $this->{__METHOD__};
	}

	/** @return Order */
	private function order() {return $this->payment()->getOrder();}

	/** @return InfoInterface|Info|OrderPayment */
	private function payment() {return $this[self::$P__PAYMENT];}

	/** @return bool */
	private function needCapture() {return $this[self::$P__NEED_CAPTURE];}

	/**
	 * 2016-05-06
	 * @param CardTokenChargeCreate $request
	 * @return void
	 */
	private function setProducts(CardTokenChargeCreate $request) {
		foreach ($this->order()->getItems() as $item) {
			/** @var OrderItem $item */
			/**
			 * 2016-03-24
			 * Если товар является настраиваемым, то
			 * @uses \Magento\Sales\Model\Order::getItems()
			 * будет содержать как настраиваемый товар, так и его простой вариант.
			 */
			if (!$item->getChildrenItems()) {
				/**
				 * 2016-04-23
				 * «An array of Product details»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setProducts($this->cProduct($item));
			}
		}
	}

	/** @return Store */
	private function store() {return $this->order()->getStore();}

	/** @return string */
	private function token() {return $this[self::$P__TOKEN];}

	/**
	 * 2016-05-06
	 * @override
	 * @return void
	 */
	protected function _construct() {
		parent::_construct();
		$this
			->_prop(self::$P__AMOUNT, RM_V_FLOAT)
			->_prop(self::$P__NEED_CAPTURE, RM_V_BOOL, false)
			->_prop(self::$P__PAYMENT, InfoInterface::class)
			->_prop(self::$P__TOKEN, RM_V_STRING_NE)
		;
	}

	/** @var string */
	private static $P__AMOUNT = 'amount';
	/** @var string */
	private static $P__NEED_CAPTURE = 'need_capture';
	/** @var string */
	private static $P__PAYMENT = 'payment';
	/** @var string */
	private static $P__TOKEN = 'token';

	/**
	 * 2016-05-06
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param string $token
	 * @param float|null $amount [optional]
	 * @param bool $capture [optional]
	 * @return CardTokenChargeCreate
	 */
	public static function build(InfoInterface $payment, $token, $amount = null, $capture = true) {
		return (new self([
			self::$P__AMOUNT => $amount ? $amount : $payment->getBaseAmountOrdered()
			, self::$P__NEED_CAPTURE => $capture
			, self::$P__PAYMENT => $payment
			, self::$P__TOKEN => $token
		]))->_build();
	}

	/** @return $this */
	public static function s() {static $r; return $r ? $r : $r = new self;}
}