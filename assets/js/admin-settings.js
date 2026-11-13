(function (wp) {
	'use strict';

	if (!wp || !wp.hooks || !wp.i18n) {
		return;
	}

	var __ = wp.i18n.__;
	var adminSettings = window.JFBRazorpayAdminSettings || {};
	var labels = {
		key_id: __('Key ID', 'jet-form-builder-razorpay-gateway'),
		secret: __('Key Secret', 'jet-form-builder-razorpay-gateway'),
		webhook_secret: __('Webhook Secret', 'jet-form-builder-razorpay-gateway')
	};

	var RazorpayTab = {
		title: __('Razorpay Gateway API', 'jet-form-builder-razorpay-gateway'),
		component: {
			name: 'razorpay',
			props: {
				incoming: {
					type: Object,
					default: function () {
						return {};
					}
				}
			},
			data: function () {
				return {
					label: labels,
					storage: Object.assign({
						key_id: '',
						secret: '',
						webhook_secret: ''
					}, JSON.parse(JSON.stringify(this.incoming || {})))
				};
			},
			methods: {
				changeVal: function (key, value) {
					this.$set(this.storage, key, value);
				},
				getRequestOnSave: function () {
					return {data: Object.assign({}, this.storage)};
				},
				input: function (h, key, type) {
					var self = this;
					return h('cx-vui-input', {
						attrs: {
							name: key,
							label: self.label[key],
							'wrapper-css': ['equalwidth'],
							size: 'fullwidth',
							type: type || 'text',
							value: self.storage[key] || ''
						},
						on: {
							input: function (value) {
								self.changeVal(key, value);
							}
						}
					});
				}
			},
			render: function (h) {
				var children = [
					this.input(h, 'key_id'),
					this.input(h, 'secret', 'password'),
					h('p', {class: 'description'}, __('Optional. Add this only when you configure Razorpay webhooks as backup payment confirmation for JetFormBuilder forms.', 'jet-form-builder-razorpay-gateway')),
					this.input(h, 'webhook_secret', 'password')
				];

				if (adminSettings.webhook_endpoint) {
					children.push(h('p', {class: 'description'}, [
						__('Webhook URL: ', 'jet-form-builder-razorpay-gateway'),
						h('code', adminSettings.webhook_endpoint)
					]));
				}

				return h('section', children);
			}
		}
	};

	wp.hooks.addFilter('jet.fb.register.gateways', 'jet-form-builder-razorpay-gateway', function (gateways) {
		gateways.push(RazorpayTab);
		return gateways;
	});
})(window.wp);
