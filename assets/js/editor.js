(function (wp) {
	'use strict';

	if (!wp || !window.JetFBActions || !window.JetFBHooks) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var compose = wp.compose.compose;
	var withSelect = wp.data.withSelect;
	var withDispatch = wp.data.withDispatch;
	var SelectControl = wp.components.SelectControl;
	var registerGateway = window.JetFBActions.registerGateway;
	var renderGateway = window.JetFBActions.renderGateway;
	var hooks = window.JetFBHooks;

	function objectAssign() {
		return Object.assign.apply(Object, arguments);
	}

	var CredFields = compose(
		withSelect(function () {
			return hooks.withSelectGateways.apply(null, arguments);
		}),
		withDispatch(function () {
			return hooks.withDispatchGateways.apply(null, arguments);
		})
	)(function (props) {
		var scenarioId = (props.gatewayScenario && props.gatewayScenario.id) || 'PAY_NOW';

		wp.element.useEffect(function () {
			props.setGatewayScenario({id: scenarioId});
			props.setGatewayRequest({id: scenarioId});
		}, [scenarioId]);

		return el(wp.element.Fragment, null,
			renderGateway('razorpay', {}, scenarioId)
		);
	});

	var PayNowFields = compose(
		withSelect(function () {
			return objectAssign(
				{},
				hooks.withSelectFormFields([], '--').apply(null, arguments),
				hooks.withSelectGateways.apply(null, arguments)
			);
		}),
		withDispatch(function () {
			return hooks.withDispatchGateways.apply(null, arguments);
		})
	)(function (props) {
		var general = props.gatewayGeneral || {};
		var specific = props.gatewaySpecific || {};
		var fields = props.formFields || [];
		var globalLabel = props.globalGatewayLabel;
		var setSpecific = props.setGatewaySpecific || function () {};

		function updateSpecific(key, value) {
			var next = {};
			next[key] = value;
			setSpecific(next);
		}

		function fieldSelect(label, key) {
			return el(SelectControl, {
				label: label,
				labelPosition: 'side',
				value: specific[key] || '',
				options: fields,
				onChange: function (value) {
					updateSpecific(key, value);
				}
			});
		}

		return el(wp.element.Fragment, null,
			el(SelectControl, {
				label: globalLabel('price_field'),
				labelPosition: 'side',
				value: general.price_field || '',
				options: fields,
				onChange: function (value) {
					props.setGateway({price_field: value});
				}
			}),
			el('hr', null),
			el('h3', null, __('JetFormBuilder Payment Details', 'jet-form-builder-razorpay-gateway')),
			el('p', {className: 'description'}, __('Optional. Map form fields here if you want Payer Info and Payment Shipping to appear inside JetFormBuilder Payments.', 'jet-form-builder-razorpay-gateway')),
			fieldSelect(__('Full Name Field', 'jet-form-builder-razorpay-gateway'), 'full_name_field'),
			fieldSelect(__('First Name Field', 'jet-form-builder-razorpay-gateway'), 'first_name_field'),
			fieldSelect(__('Last Name Field', 'jet-form-builder-razorpay-gateway'), 'last_name_field'),
			fieldSelect(__('Email Field', 'jet-form-builder-razorpay-gateway'), 'email_field'),
			fieldSelect(__('Phone Field', 'jet-form-builder-razorpay-gateway'), 'phone_field'),
			el('hr', null),
			el('h3', null, __('Payment Shipping', 'jet-form-builder-razorpay-gateway')),
			fieldSelect(__('Country Field', 'jet-form-builder-razorpay-gateway'), 'country_field'),
			fieldSelect(__('State / Province Field', 'jet-form-builder-razorpay-gateway'), 'state_field'),
			fieldSelect(__('City Field', 'jet-form-builder-razorpay-gateway'), 'city_field'),
			fieldSelect(__('Street Address Line 1 Field', 'jet-form-builder-razorpay-gateway'), 'address_line_1_field'),
			fieldSelect(__('Street Address Line 2 Field', 'jet-form-builder-razorpay-gateway'), 'address_line_2_field'),
			fieldSelect(__('ZIP / Postal Code Field', 'jet-form-builder-razorpay-gateway'), 'postal_code_field')
		);
	});

	registerGateway('razorpay', CredFields);
	registerGateway('razorpay', PayNowFields, 'PAY_NOW');

	wp.hooks.addFilter('jet.fb.gateways.getDisabledStateButton', 'jet-form-builder-razorpay-gateway', function (value, post, hasAction) {
		var gateway = post && post._jf_gateways ? post._jf_gateways.gateway : '';
		return 'razorpay' === gateway ? !hasAction('save_record') : value;
	});

	wp.hooks.addFilter('jet.fb.gateways.getDisabledInfo', 'jet-form-builder-razorpay-gateway', function (value, post) {
		var gateway = post && post._jf_gateways ? post._jf_gateways.gateway : '';
		if ('razorpay' !== gateway) {
			return value;
		}

		return el('p', null, __('Please add `Save Form Record` action.', 'jet-form-builder-razorpay-gateway'));
	});
})(window.wp);
