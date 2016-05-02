define ([
	'Magento_Payment/js/view/payment/cc-form'
	,'jquery'
	, 'df'
	, 'Df_Checkout/js/data'
	, 'mage/translate'
	, 'underscore'
	/**
	 * 2016-04-17
	 * How to get the customer's data on the frontend checkout page's client side (with JavaScript)
	 * using the «Magento_Customer/js/model/customer» object?
	 * https://mage2.pro/t/1245
	 *
	 * The «Magento_Customer/js/model/customer» JavaScript object interface
	 * https://mage2.pro/t/1252
	 */
	, 'Magento_Customer/js/model/customer'
	/**
	 * 2016-04-17
	 * How is the «Magento_Customer/js/customer-data» object implemented and used?
	 * https://mage2.pro/t/1246
	 */
	, 'Magento_Customer/js/customer-data'
	/**
	 * 2016-04-17
	 * How is the «Magento_Checkout/js/checkout-data» object implemented and used?
	 * https://mage2.pro/t/1293
	 *
	 * How to get the checkout data on the frontend checkout page's client side (with JavaScript)?
	 * https://mage2.pro/t/1292
	 *
	 * https://mage2.pro/t/1294
	 * The «Magento_Checkout/js/checkout-data» JavaScript object interface and its implementation
	 */
	, 'Magento_Checkout/js/checkout-data'
], function(Component, $, df, dfCheckout, $t, _, customer, customerData, checkoutData) {
	'use strict';
	return Component.extend({
		defaults: {
			active: false
			,clientConfig: {id: 'dfe-checkout-com'}
			,code: 'dfe_checkout_com'
			,template: 'Dfe_CheckoutCom/item'
		},
		imports: {onActiveChange: 'active'},
		/**
		 * 2016-03-02
		 * @param {?String} key
		 * @returns {Object}|{*}
	 	 */
		config: function(key) {
			/** @type {Object} */
			var result =  window.checkoutConfig.payment[this.getCode()];
			return !key ? result : result[key];
		},
		/**
		 * 2016-03-01
		 * 2016-03-08
		 * Раньше реализация была такой:
		 * return _.keys(this.getCcAvailableTypes())
		 *
		 * https://support.stripe.com/questions/which-cards-and-payment-types-can-i-accept-with-stripe
		 * «Which cards and payment types can I accept with Stripe?
		 * With Stripe, you can charge almost any kind of credit or debit card:
		 * U.S. businesses can accept
		  		Visa, MasterCard, American Express, JCB, Discover, and Diners Club.
		 * Australian, Canadian, European, and Japanese businesses can accept
		 * 		Visa, MasterCard, and American Express.»
		 *
		 * Не стал делать реализацию на сервере, потому что там меня не устраивал
		 * порядок следования платёжных систем (первой была «American Express»)
		 * https://github.com/magento/magento2/blob/cf7df72/app/code/Magento/Payment/etc/payment.xml#L10-L44
		 * А изменить этот порядок коротко не получается:
		 * https://github.com/magento/magento2/blob/487f5f45/app/code/Magento/Payment/Model/CcGenericConfigProvider.php#L105-L124
		 *
		 * @returns {String[]}
	 	 */
		getCardTypes: function() {
			return ['VI', 'MC', 'AE'];
		},
		/** @returns {String} */
		getCode: function() {return this.code;},
		/**
		 * 2016-03-06
   		 * @override
   		 */
		getData: function () {
			return {
				method: this.item.method,
				additional_data: {token: this.token}
			};
		},
		/**
		 * 2016-03-08
		 * @return {String}
		*/
		getTitle: function() {
			var result = this._super();
			return result + (!this.isTest() ? '' : ' [<b>Checkout.com TEST MODE</b>]');
		},
		/**
		 * 2016-03-02
		 * @return {Object}
		*/
		initialize: function() {
			this._super();
			// 2016-04-14
			// http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js
			this.initDf();
			/*console.log(dfCheckout.email());
			console.log(customer);
			console.log(customerData);
			console.log(window.customerData);
			console.log(checkoutData); */
			//CheckoutKit.setPublishableKey(this.config('publishableKey'));
			this.creditCardNumber('5436031030606378');
			this.creditCardExpMonth(6);
			this.creditCardExpYear(2017);
			this.creditCardVerificationNumber(257);
			return this;
		},
		/**
		 * 2016-03-08
		 * @return {Promise}
		*/
		initDf: function() {
			if (df.undefined(this._initDf)) {
				/** @type {Deferred} */
				var deferred = $.Deferred();
				var _this = this;
				window.CKOConfig = {
					/**
					 * 2016-04-20
					 * Этот флаг только включает запись диагностических сообщений в консоль.
					 *
					 * «Setting debugMode to true is highly recommended during the integration process;
					 * the browser’s console will display helpful information
					 * such as key events including event data and/or any issues found.»
					 * http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js
					 *
					 * http://developers.checkout.com/docs/browser/reference/actions/checkoutkit-js
					 * «The log action will only log messages on the console if debugMode is set to true.»
					 */
					debugMode: this.isTest()
					,publicKey: this.config('publishableKey')
					/**
					 * 2016-04-14
					 * «Charges Required-Field Matrix»
					 * http://developers.checkout.com/docs/server/integration-guide/charges#a1
					 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token
					 *
					 * 2016-04-17
					 * How to get the current customer's email on the frontend checkout screen?
					 * https://mage2.pro/t/1295
					 */
					,customerEmail: dfCheckout.email()
					,ready: function(event) {
						console.log("CheckoutKit.js is ready");
						// 2016-04-14
						// http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js/charge-via-card-token#step-2-capture-and-send-credit-card-details
						//CheckoutKit.monitorForm('form.dfe-checkout-com', CheckoutKit.CardFormModes.CARD_TOKENISATION);
						/**
						 * 2016-04-20
						 * http://developers.checkout.com/docs/browser/reference/handlers/checkoutkit-js
						 * @type {*|Array}
						 */
						var ev = CheckoutKit.Events;
						/**
						 * 2016-04-20
						 * «If you do not want the <form> to be submitted automatically,
						 * you can add an event listener to receive the card token.»
						 * http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js/charge-via-card-token#step-2-capture-and-send-credit-card-details
						 *
						 * http://developers.checkout.com/docs/browser/reference/handlers/checkoutkit-js
						 * CARD_TOKENISED
						 * After a card is tokenised.
						 * The event object will contain the card token.
						 * Example: {id: 'card_tok_111'}
						 */
						CheckoutKit.addEventHandler(ev.CARD_TOKENISED, function(event) {
						    console.log('card token', event.data.id);
							_this.token = event.data.id;
							_this.placeOrder();
						});
						/**
						 * 2016-04-20
						 * http://developers.checkout.com/docs/browser/reference/handlers/checkoutkit-js
						 */
						CheckoutKit.addEventHandler(ev.CARD_TOKENISATION_FAILED, function(event) {
							_this.messageContainer.addErrorMessage({
								'message': $t('The card tokenisation fails.')
							});
						});
						deferred.resolve();
					}
					,apiError: function (event) {deferred.reject();}
				};
				/** @type {String} */
				var library = 'Dfe_CheckoutCom/API/' + (this.isTest() ? 'Sandbox' : 'Production');
				// 2016-04-11
				// CheckoutKit не использует AMD и прикрепляет себя к window.
				require([library], function() {
				});
				this._initDf = deferred.promise();
			}
			return this._initDf;
		},
		/**
		 * 2016-04-11
		 * @return {Boolean}
		*/
		isTest: function() {return this.config('isTest');},
		pay: function() {
			var _this = this;
			this.initDf().done(function() {
				var $form = $('form.dfe-checkout-com');
				/**
				 * 2016-04-21
				 * http://developers.checkout.com/docs/browser/reference/actions/checkoutkit-js#create-card-token
				 */
				debugger;
				CheckoutKit.createCardToken({
				    number: $('[data-checkout="card-number"]', $form).val()
					,expiryMonth: $('[data-checkout="expiry-month"]', $form).val()
					,'expiryYear': $('[data-checkout="expiry-year"]', $form).val()
					,cvv: $('[data-checkout="cvv"]', $form).val()
				}, function(response) {
					debugger;
					console.log(response.id);
					_this.token = response.id;
					_this.placeOrder();
				});

			});
		}
	});
});
