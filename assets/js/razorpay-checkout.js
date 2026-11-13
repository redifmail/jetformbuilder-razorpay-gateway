(function ($) {
	'use strict';

	var CHECKOUT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';

	function getSettings() {
		return window.JFBRazorpaySettings || {};
	}

	function hasCheckoutData(response) {
		return response && response.razorpay && response.razorpay.order_id && response.razorpay.key;
	}

	function hasCheckoutToken(response) {
		return response && response.razorpay && response.razorpay.token;
	}

	function getMessage(key, fallback) {
		var settings = getSettings();
		var i18n = settings.i18n || {};

		return i18n[key] || fallback;
	}

	function loadCheckout(callback) {
		var retries = 0;
		var script;

		function done() {
			if ('function' === typeof window.Razorpay) {
				callback();
				return;
			}

			window.alert(getMessage('checkout_failed', 'Razorpay checkout could not be opened. Please try again.'));
		}

		function waitForGlobal() {
			if ('function' === typeof window.Razorpay) {
				callback();
				return;
			}

			retries += 1;

			if (retries > 40) {
				done();
				return;
			}

			window.setTimeout(waitForGlobal, 100);
		}

		if ('function' === typeof window.Razorpay) {
			callback();
			return;
		}

		script = document.querySelector('script[src*="checkout.razorpay.com/v1/checkout.js"]');

		if (script) {
			waitForGlobal();
			return;
		}

		script = document.createElement('script');
		script.src = CHECKOUT_SRC;
		script.async = true;
		script.onload = waitForGlobal;
		script.onerror = done;
		document.head.appendChild(script);
	}

	function submitReturn(url, fields) {
		var form = document.createElement('form');
		form.method = 'POST';
		form.action = url;
		form.style.display = 'none';

		Object.keys(fields || {}).forEach(function (name) {
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = fields[name] || '';
			form.appendChild(input);
		});

		document.body.appendChild(form);
		form.submit();
	}

	function openCheckout(response) {
		var data = response.razorpay;

		loadCheckout(function () {
			var options = {
				key: data.key,
				amount: data.amount,
				currency: data.currency || 'INR',
				name: data.name || document.title,
				description: data.description || '',
				order_id: data.order_id,
				prefill: data.prefill || {},
				handler: function (paymentResponse) {
					submitReturn(data.return_url, {
						razorpay_order_id: paymentResponse.razorpay_order_id,
						razorpay_payment_id: paymentResponse.razorpay_payment_id,
						razorpay_signature: paymentResponse.razorpay_signature
					});
				},
				modal: {
					ondismiss: function () {
						$(document).trigger('jet-form-builder/razorpay/dismissed', [response]);
					}
				}
			};

			var checkout = new window.Razorpay(options);

			checkout.on('payment.failed', function (failedResponse) {
				var error = failedResponse && failedResponse.error ? failedResponse.error : {};
				submitReturn(data.return_url, {
					razorpay_order_id: data.order_id,
					razorpay_payment_failed: 1,
					razorpay_error_code: error.code || '',
					razorpay_error_description: error.description || ''
				});
			});

			checkout.open();
		});
	}

	function fetchCheckout(token) {
		var settings = getSettings();
		var endpoint = settings.checkout_endpoint || '';

		if (!endpoint) {
			window.alert(getMessage('checkout_failed', 'Razorpay checkout could not be opened. Please try again.'));
			return;
		}

		$.ajax({
			url: endpoint,
			method: 'GET',
			dataType: 'json',
			data: {
				token: token
			}
		}).done(function (data) {
			if (!data || !data.key || !data.order_id) {
				window.alert(getMessage('checkout_failed', 'Razorpay checkout could not be opened. Please try again.'));
				return;
			}

			openCheckout({
				razorpay: data
			});
		}).fail(function (xhr) {
			var response = xhr && xhr.responseJSON ? xhr.responseJSON : {};
			window.alert(response.message || getMessage('checkout_expired', 'Razorpay checkout session has expired. Please submit the form again.'));
		});
	}

	function handleCheckoutResponse(response) {
		if (hasCheckoutData(response)) {
			openCheckout(response);
			return;
		}

		if (hasCheckoutToken(response)) {
			fetchCheckout(response.razorpay.token);
		}
	}

	function getNestedParam(params, key) {
		return params.get('razorpay[' + key + ']') || '';
	}

	function getCheckoutTokenFromUrl() {
		var params = new URLSearchParams(window.location.search || '');
		var token = getNestedParam(params, 'token');

		if (!token) {
			return null;
		}

		return {
			razorpay: {
				token: token
			}
		};
	}

	function cleanCheckoutParams() {
		var url = new URL(window.location.href);
		var changed = false;
		var removeKeys = [];

		url.searchParams.forEach(function (value, key) {
			if (0 === key.indexOf('razorpay[')) {
				removeKeys.push(key);
			}
		});

		removeKeys.forEach(function (key) {
			if (url.searchParams.has(key)) {
				url.searchParams.delete(key);
				changed = true;
			}
		});

		if (changed && window.history && window.history.replaceState) {
			window.history.replaceState({}, document.title, url.toString());
		}
	}

	$(document).on('jet-form-builder/ajax/on-success', function (event, response) {
		handleCheckoutResponse(response);
	});

	$(function () {
		var response = getCheckoutTokenFromUrl();

		if (!hasCheckoutToken(response)) {
			return;
		}

		cleanCheckoutParams();
		handleCheckoutResponse(response);
	});
})(jQuery);
