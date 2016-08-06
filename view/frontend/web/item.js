define ([
	'df'
	,'Df_Checkout/js/data'
	,'Df_Payment/js/view/payment/cc-form'
	,'jquery'
], function(df, dfCheckout, parent, $) {'use strict'; return parent.extend({
	/**
	 * 2016-05-18
	 * @returns {String[]}
	 */
	getCardTypes: function() {return ['VI', 'MC', 'AE'];},
	/**
	 * 2016-07-16
	 * http://docs.checkout.com/getting-started/testing-and-simulating-charges#response-codes
	 * @override
	 * @see mage2pro/core/Payment/view/frontend/web/js/view/payment/mixin.js
	 * @returns {String}
	 */
	getDebugMessage: function() {
		if (df.undefined(this._debugMessage)) {
			/** @type {String} */
			var amountS = Math.round(100 * dfCheckout.grandTotal()).toString();
			/** @type {String} */
			var last2 = amountS.substring(amountS.length - 2);
			/** @type {?String} */
			var reason = ({
				'05': '	Declined - Do Not Honour'
				,'12': 'Invalid Transaction'
				,'14': 'Invalid Card Number'
				,'51': 'Insufficient Funds'
				,'62': 'Restricted Card'
				,'63': 'Security Violation'
			})[last2];
			this._debugMessage = !reason ? '' :
				('The transaction will <b><a href="{url}">fail</a></b> by the reason of «<b>{reason}</b>», '
				 + 'because the payment amount ends with «<b>{last2}</b>».')
					.replace('{url}', 'http://docs.checkout.com/getting-started/testing-and-simulating-charges#response-codes')
					.replace('{reason}', reason)
					.replace('{last2}', last2)
			;
		}
		return this._debugMessage;
	},
	/**
	 * 2016-03-02
	 * @return {Object}
	*/
	initialize: function() {
		this._super();
		/**
		 * 2016-06-01
		 * To note: anonymous user can change his email.
		 * We should then only initiate CheckoutKit
		 * when the buyer clicks the "Place Order" button
		 */
		// 2016-04-14
		// http://docs.checkout.com/getting-started/checkoutkit-js
		this.initDf();
		// 2016-03-09
		// «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?
		/** @type {String|Boolean} */
		var prefill = this.config('prefill');
		if ($.isPlainObject(prefill)) {
			this.creditCardNumber(prefill['number']);
			this.creditCardExpMonth(prefill['expiration-month']);
			this.creditCardExpYear(prefill['expiration-year']);
			this.creditCardVerificationNumber(prefill['cvv']);
		}
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
				 * This flag only triggers showing debugging messages in the console
				 *
				 * «Setting debugMode to true is highly recommended during the integration process;
				 * the browser’s console will display helpful information
				 * such as key events including event data and/or any issues found.»
				 * http://docs.checkout.com/getting-started/checkoutkit-js
				 *
				 * http://docs.checkout.com/reference/checkoutkit-js-reference/actions
				 * «The log action will only log messages on the console if debugMode is set to true.»
				 */
				debugMode: this.isTest()
				,publicKey: this.config('publishableKey')
				,ready: function(event) {deferred.resolve();}
				,apiError: function(event) {deferred.reject();}
			};
			/** @type {String} */
			var library = 'Dfe_CheckoutCom/API/' + (this.isTest() ? 'Sandbox' : 'Production');
			require.undef(library);
			delete window.CheckoutKit;
			// 2016-04-11
			// CheckoutKit не использует AMD и прикрепляет себя к window.
			require([library], function() {});
			this._initDf = deferred.promise();
		}
		return this._initDf;
	},
	/**
	 * 2016-08-06
	 * @override
	 * @see mage2pro/core/Payment/view/frontend/web/js/view/payment/mixin.js
	 * @used-by placeOrderInternal()
	 */
	onSuccess: function(redirectUrl) {
		/**
		 * 2016-05-04
		 * Redirect to do a 3D-Secure verification.
		 * Similar to: redirectOnSuccessAction.execute()
		 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Checkout/view/frontend/web/js/action/redirect-on-success.js#L19-L19
		 *
		 * 2016-05-09
		 * If 3D-Secure is not necessary,
		 * Method @see \Dfe\CheckoutCom\PlaceOrder::response() returns null:
		 * https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/f4acf4a3/PlaceOrder.php#L58
		 * which is then converted by
		 * @see \Magento\Framework\Webapi\ServiceOutputProcessor::process()
		 * to an empty array:
		 * «A Web API request returns an empty array for a null response»
		 * https://mage2.pro/t/1569
		 *
		 * When there is no need to do a 3D-Secure verification,
		 * the value of redirectUrl will be an empty array.
		 * So the correct test to be done is: if (redirectUrl),
		 * and if (redirectUrl.length)
		 * In all cases, we want to cope with the possibility of
		 * Magento core unexpectedly returning null.
		 */
		redirectUrl && redirectUrl.length
			? window.location.replace(redirectUrl)
			: this._super()
		;
	},
	/**
	 * @override
	 * @see https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Checkout/view/frontend/web/js/view/payment/default.js#L127-L159
	 * @used-by https://github.com/magento/magento2/blob/2.1.0/lib/web/knockoutjs/knockout.js#L3863
	 * @param {this} _this
	*/
	placeOrder: function(_this) {
		if (this.validate()) {
			this.initDf().done(function() {
				/**
				 * 2016-04-21
				 * http://docs.checkout.com/reference/checkoutkit-js-reference/actions#create-card-token
				 */
				CheckoutKit.createCardToken({
					cvv: _this.dfCardVerification()
					,expiryMonth: _this.dfCardExpirationMonth()
					,expiryYear: _this.dfCardExpirationYear()
					,number: _this.dfCardNumber()
					/**
					 * 2016-04-14
					 * «Charges Required-Field Matrix»
					 * http://developers.checkout.com/docs/server/integration-guide/charges#a1
					 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token
					 *
					 * 2016-04-17
					 * How to get the current customer's email on the frontend checkout screen?
					 * https://mage2.pro/t/1295
					 */
					,'email-address': dfCheckout.email()
				}, function(response) {
					if ('error' === response.type) {
						/**
						 * 2016-08-05
						 * We can get error messages from the response:
						 * response.title and response.description
						 * But they are not informative and contain a text like
						 * «Server Operation Failed»
						 * «The last server operation failed.»
						 */
						_this.showErrorMessage(
							'It looks like you have entered incorrect bank card data.'
						);
					}
					else {
						_this.token = response.id;
						_this.placeOrderInternal();
					}
				});
			});
		}
	}
});});
