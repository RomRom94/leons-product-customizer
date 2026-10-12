(function ($) {
	'use strict';

	var settings = window.lpcViewImages || {};
	var i18n = settings.i18n || {};

	function setCell(cell, id, thumb) {
		var img = cell.querySelector('img');
		cell.querySelector('[data-lpc-input]').value = id ? String(id) : '';
		img.src = thumb || '';
		img.hidden = !id;
		cell.querySelector('.lpc-view-images__add').hidden = !!id;
		cell.querySelector('[data-lpc-remove]').hidden = !id;
		cell.classList.toggle('is-filled', !!id);
	}

	// Statut de chaque couleur et résumé, recalculés après chaque modification de la grille.
	function refreshStatus(grid) {
		var rows = grid.querySelectorAll('tr[data-lpc-color]');
		var missing = 0;
		rows.forEach(function (row) {
			var complete = Array.prototype.every.call(row.querySelectorAll('[data-lpc-input]'), function (input) {
				return input.value !== '';
			});
			var status = row.querySelector('[data-lpc-status]');
			status.textContent = complete ? i18n.ok : i18n.missing;
			status.classList.toggle('is-ok', complete);
			status.classList.toggle('is-missing', !complete);
			if (!complete) {
				missing++;
			}
		});
		var summary = grid.querySelector('[data-lpc-summary]');
		if (summary) {
			summary.textContent = missing
				? i18n.someMissing.replace('%1$d', missing).replace('%2$d', rows.length)
				: i18n.allOk;
		}
	}

	function openPicker(grid, cell) {
		var frame = wp.media({
			title: i18n.frameTitle,
			button: { text: i18n.frameButton },
			library: { type: 'image' },
			multiple: false,
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var sizes = attachment.sizes || {};
			var thumb = (sizes.thumbnail || sizes.medium || sizes.full || {}).url || attachment.url;
			setCell(cell, attachment.id, thumb);
			refreshStatus(grid);
		});
		frame.open();
	}

	function matchByFilename(grid, button) {
		var result = grid.querySelector('[data-lpc-match-result]');
		button.disabled = true;
		result.textContent = '…';
		$.post(settings.ajaxUrl, {
			action: settings.action,
			nonce: settings.nonce,
			product_id: button.getAttribute('data-product'),
		})
			.done(function (response) {
				var found = (response && response.success && response.data) || {};
				var count = 0;
				Object.keys(found).forEach(function (color) {
					var row = grid.querySelector('tr[data-lpc-color="' + color + '"]');
					if (!row) {
						return;
					}
					Object.keys(found[color]).forEach(function (view) {
						var cell = row.querySelector('[data-lpc-cell][data-view="' + view + '"]');
						if (cell) {
							setCell(cell, found[color][view].id, found[color][view].thumb);
							count++;
						}
					});
				});
				refreshStatus(grid);
				result.textContent = count ? i18n.matched.replace('%d', count) : i18n.noMatch;
			})
			.fail(function () {
				result.textContent = i18n.error;
			})
			.always(function () {
				button.disabled = false;
			});
	}

	$(function () {
		var grid = document.querySelector('[data-lpc-view-images]');
		if (!grid) {
			return;
		}
		grid.addEventListener('click', function (event) {
			var pick = event.target.closest('[data-lpc-pick]');
			var remove = event.target.closest('[data-lpc-remove]');
			var match = event.target.closest('[data-lpc-match]');
			if (pick) {
				event.preventDefault();
				openPicker(grid, pick.closest('[data-lpc-cell]'));
			} else if (remove) {
				event.preventDefault();
				setCell(remove.closest('[data-lpc-cell]'), 0, '');
				refreshStatus(grid);
			} else if (match) {
				event.preventDefault();
				matchByFilename(grid, match);
			}
		});
	});
})(jQuery);
