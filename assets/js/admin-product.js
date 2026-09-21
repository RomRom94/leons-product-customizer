(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var button = document.getElementById('lpc_load_default_template');
		var textarea = document.getElementById('lpc_config_textarea');

		if (!button || !textarea || !window.lpcAdminProduct || !window.lpcAdminProduct.defaultJson) {
			return;
		}

		button.addEventListener('click', function () {
			var label = window.lpcAdminProduct.templateLabel || 'défaut';
			if (textarea.value.trim() !== '' && !window.confirm('Remplacer la configuration actuelle par le modèle ' + label + ' ?')) {
				return;
			}
			textarea.value = window.lpcAdminProduct.defaultJson;
		});
	});
})();
