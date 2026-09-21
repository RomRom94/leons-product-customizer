(function ($) {
	'use strict';

	function formatMoney(amount) {
		var cfg = window.lpcCustomizer || {};
		var decimals = typeof cfg.decimals === 'number' ? cfg.decimals : 2;
		var decSep = cfg.decimalSep || ',';
		var thouSep = cfg.thousandSep || ' ';
		var symbol = cfg.currencySymbol || '€';
		var fixed = amount.toFixed(decimals);
		var parts = fixed.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thouSep);
		return parts.join(decSep) + '\u00a0' + symbol;
	}

	function formatPrice(amount) {
		if (window.lpcCustomizer && window.lpcCustomizer.formattedZero && amount <= 0) {
			return window.lpcCustomizer.formattedZero;
		}
		return formatMoney(amount);
	}

	function LpcCustomizer(root, config) {
		this.root = root;
		this.config = config || {};
		this.zones = this.config.zones || {};
		this.faceImage = root.querySelector('[data-lpc-target="faceImage"]');
		this.backImage = root.querySelector('[data-lpc-target="backImage"]');
		this.faceCanvas = root.querySelector('[data-lpc-target="faceCanvas"]');
		this.backCanvas = root.querySelector('[data-lpc-target="backCanvas"]');
		this.addonsTotalEl = root.querySelector('[data-lpc-addons-total]');
		this.zoneInputs = root.querySelectorAll('[data-lpc-zone]');
		this.fontSelect = root.querySelector('[data-lpc-global="font"]');
		this.colorInputs = root.querySelectorAll('[data-lpc-global="color"]');
		this.fileInputs = root.querySelectorAll('input[type="file"][data-lpc-zone]');
		this.removeButtons = root.querySelectorAll('[data-lpc-zone-remove]');
		this.imageCache = {};
		this.pairs = [];
	}

	LpcCustomizer.prototype.getGlobalFont = function () {
		return (this.fontSelect && this.fontSelect.value) || 'Slicker';
	};

	LpcCustomizer.prototype.getGlobalColor = function () {
		var checked = null;
		this.colorInputs.forEach(function (input) {
			if (input.checked) {
				checked = input;
			}
		});
		return (checked && checked.getAttribute('data-hex')) || '#000000';
	};

	LpcCustomizer.prototype.init = function () {
		var self = this;

		if (this.backImage && this.backCanvas) {
			this.pairs.push({
				img: this.backImage,
				canvas: this.backCanvas,
				view: 'back',
				draw: function (ctx, w, h) {
					self.drawView(ctx, w, h, 'back');
				},
			});
		}

		if (this.faceImage && this.faceCanvas) {
			this.pairs.push({
				img: this.faceImage,
				canvas: this.faceCanvas,
				view: 'front',
				draw: function (ctx, w, h) {
					self.drawView(ctx, w, h, 'front');
				},
			});
		}

		this.pairs.forEach(function (pair) {
			self.setupPair(pair);
		});

		this.zoneInputs.forEach(function (input) {
			input.addEventListener('input', function () {
				self.render();
				self.updateAddonsTotal();
			});
		});

		if (this.fontSelect) {
			this.fontSelect.addEventListener('change', function () {
				self.render();
			});
		}

		this.colorInputs.forEach(function (input) {
			input.addEventListener('change', function () {
				self.render();
			});
		});

		this.fileInputs.forEach(function (input) {
			input.addEventListener('change', function () {
				self.onFileZoneChange(input);
			});
			self.updateRemoveButtonVisibility(input);
		});

		this.removeButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				self.clearFileZone(button.getAttribute('data-lpc-zone-remove'));
			});
		});

		this.bindVariations();
		this.loadFonts().then(function () {
			self.render();
		});
		this.updateAddonsTotal();
	};

	LpcCustomizer.prototype.loadFonts = function () {
		var fonts = new Set(['Slicker']);
		Object.keys(this.zones).forEach(function (zoneId) {
			var zone = this.zones[zoneId];
			if (zone.style && zone.style.font) {
				fonts.add(zone.style.font);
			}
		}, this);

		if (this.fontSelect) {
			Array.prototype.forEach.call(this.fontSelect.options, function (option) {
				if (option.value) {
					fonts.add(option.value);
				}
			});
		}

		var promises = [];
		fonts.forEach(function (font) {
			if (document.fonts && document.fonts.load) {
				promises.push(document.fonts.load("normal 100px '" + font + "'"));
			}
		});
		return Promise.all(promises);
	};

	LpcCustomizer.prototype.setupPair = function (pair) {
		var self = this;
		var sync = function () {
			self.syncCanvasSize(pair);
		};

		if (pair.img.complete && pair.img.naturalWidth > 0) {
			sync();
		} else {
			pair.img.addEventListener('load', sync, { once: true });
		}

		if (window.ResizeObserver) {
			pair.observer = new ResizeObserver(sync);
			pair.observer.observe(pair.img);
		}
	};

	LpcCustomizer.prototype.getZoneValue = function (zoneId) {
		var input = this.root.querySelector('[data-lpc-zone="' + zoneId + '"]');
		return input ? input.value : '';
	};

	LpcCustomizer.prototype.drawView = function (ctx, w, h, viewName) {
		ctx.clearRect(0, 0, w, h);
		var self = this;

		Object.keys(this.zones).forEach(function (zoneId) {
			var zone = self.zones[zoneId];
			if (zone.view !== viewName) {
				return;
			}

			if (zone.type === 'image') {
				self.drawImageZone(ctx, w, h, zoneId, zone);
				return;
			}

			if (zone.type !== 'text') {
				return;
			}
			var value = self.getZoneValue(zoneId).trim().toUpperCase();
			if (!value) {
				return;
			}
			var pos = zone.position || {};
			var style = zone.style || {};
			var x = w * (typeof pos.x === 'number' ? pos.x : 0.5);
			var y;
			// y_without_name : position alternative quand la zone "name" est vide (rétrocompat maillot)
			if (typeof pos.y_without_name === 'number') {
				y = self.getZoneValue('name').trim()
					? h * (typeof pos.y === 'number' ? pos.y : 0.5)
					: h * pos.y_without_name;
			} else {
				y = h * (typeof pos.y === 'number' ? pos.y : 0.5);
			}
			var baseSize = Math.round(w * (style.base_size_ratio || 0.05));
			var maxWidth = w * (style.max_width_ratio || 0.85);
			var font = self.getGlobalFont();
			var color = self.getGlobalColor();
			var fontSize = self.fitFontSize(ctx, value, maxWidth, baseSize, font);
			self.drawText(ctx, value, x, y, fontSize, {
				font: font,
				color: color,
				stroke: style.stroke,
			});
		});
	};

	LpcCustomizer.prototype.drawImageZone = function (ctx, w, h, zoneId, zone) {
		var img = this.imageCache[zoneId];
		if (!img) {
			return;
		}
		var pos = zone.position || {};
		var boxW = w * (typeof pos.width === 'number' ? pos.width : 0.2);
		var boxH = h * (typeof pos.height === 'number' ? pos.height : 0.2);
		var boxX = w * (typeof pos.x === 'number' ? pos.x : 0.5) - boxW / 2;
		var boxY = h * (typeof pos.y === 'number' ? pos.y : 0.5) - boxH / 2;

		var scale = Math.min(boxW / img.naturalWidth, boxH / img.naturalHeight);
		var drawW = img.naturalWidth * scale;
		var drawH = img.naturalHeight * scale;
		var drawX = boxX + (boxW - drawW) / 2;
		var drawY = boxY + (boxH - drawH) / 2;

		ctx.drawImage(img, drawX, drawY, drawW, drawH);
	};

	LpcCustomizer.prototype.onFileZoneChange = function (input) {
		var self = this;
		var zoneId = input.getAttribute('data-lpc-zone');
		var file = input.files && input.files[0];

		this.updateRemoveButtonVisibility(input);

		if (!file) {
			delete this.imageCache[zoneId];
			this.render();
			return;
		}

		var reader = new FileReader();
		reader.onload = function (event) {
			var img = new Image();
			img.onload = function () {
				self.imageCache[zoneId] = img;
				self.render();
			};
			img.src = event.target.result;
		};
		reader.readAsDataURL(file);
	};

	LpcCustomizer.prototype.updateRemoveButtonVisibility = function (input) {
		var zoneId = input.getAttribute('data-lpc-zone');
		var button = this.root.querySelector('[data-lpc-zone-remove="' + zoneId + '"]');
		if (!button) {
			return;
		}
		button.hidden = !(input.files && input.files.length);
	};

	LpcCustomizer.prototype.clearFileZone = function (zoneId) {
		var input = this.root.querySelector('input[type="file"][data-lpc-zone="' + zoneId + '"]');
		if (!input) {
			return;
		}
		input.value = '';
		this.onFileZoneChange(input);
		this.updateAddonsTotal();
	};

	LpcCustomizer.prototype.fitFontSize = function (ctx, text, maxWidth, baseFontSize, font) {
		var size = baseFontSize;
		ctx.font = "normal " + size + "px '" + font + "', sans-serif";
		while (size > 8 && ctx.measureText(text).width > maxWidth) {
			size -= 1;
			ctx.font = "normal " + size + "px '" + font + "', sans-serif";
		}
		return size;
	};

	LpcCustomizer.prototype.drawText = function (ctx, text, x, y, fontSize, style) {
		var font = style.font || 'Slicker';
		ctx.font = "normal " + fontSize + "px '" + font + "', sans-serif";
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.lineWidth = fontSize * 0.06;
		ctx.strokeStyle = style.stroke || '#000000';
		ctx.strokeText(text, x, y);
		ctx.fillStyle = style.color || '#FFFFFF';
		ctx.fillText(text, x, y);
	};

	LpcCustomizer.prototype.syncCanvasSize = function (pair) {
		var w = pair.img.clientWidth;
		var h = pair.img.clientHeight;
		if (!w || !h || !pair.img.naturalWidth) {
			return;
		}
		// Résolution du canvas = taille d'affichage réelle × densité de pixels de
		// l'écran (jusqu'à 2x), pas la taille native (souvent petite) de l'image
		// source : sinon le texte dessiné est étiré/flou une fois affiché en grand.
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		pair.logicalWidth = w;
		pair.logicalHeight = h;
		pair.canvas.width = Math.round(w * dpr);
		pair.canvas.height = Math.round(h * dpr);
		this.render();
	};

	LpcCustomizer.prototype.render = function () {
		var self = this;
		this.pairs.forEach(function (pair) {
			if (!pair.logicalWidth || !pair.logicalHeight) {
				return;
			}
			pair.draw(pair.canvas.getContext('2d'), pair.canvas.width, pair.canvas.height);
		});
	};

	LpcCustomizer.prototype.updateAddonsTotal = function () {
		if (!this.addonsTotalEl) {
			return;
		}
		var total = 0;
		this.zoneInputs.forEach(function (input) {
			var value = input.value.trim();
			if (!value) {
				return;
			}
			var price = parseFloat(input.getAttribute('data-lpc-zone-price') || '0', 10);
			if (!isNaN(price)) {
				total += price;
			}
		});
		if (window.accounting && window.woocommerce_addons_params) {
			this.addonsTotalEl.innerHTML = accounting.formatMoney(total, {
				symbol: woocommerce_addons_params.currency_format_symbol,
				decimal: woocommerce_addons_params.currency_format_decimal_sep,
				thousand: woocommerce_addons_params.currency_format_thousand_sep,
				precision: woocommerce_addons_params.currency_format_num_decimals,
				format: woocommerce_addons_params.currency_format,
			});
		} else {
			this.addonsTotalEl.textContent = formatPrice(total);
		}
	};

	LpcCustomizer.prototype.updateImage = function (img, data) {
		if (!img) {
			return;
		}
		var placeholder = img.getAttribute('data-placeholder-src') || img.src;
		if (!data || !data.src) {
			img.src = placeholder;
			img.removeAttribute('srcset');
			img.removeAttribute('sizes');
			return;
		}
		var self = this;
		img.addEventListener(
			'load',
			function () {
				self.pairs.forEach(function (pair) {
					if (pair.img === img) {
						self.syncCanvasSize(pair);
					}
				});
			},
			{ once: true }
		);
		img.src = data.src;
		if (data.srcset) {
			img.srcset = data.srcset;
		} else {
			img.removeAttribute('srcset');
		}
		if (data.sizes) {
			img.sizes = data.sizes;
		} else {
			img.removeAttribute('sizes');
		}
	};

	LpcCustomizer.prototype.applyVariation = function (variation) {
		if (!variation) {
			return;
		}

		var faceData =
			variation.image && variation.image.src ? variation.image : null;

		if (this.faceImage && faceData) {
			this.updateImage(this.faceImage, faceData);
		}

		var backData = null;
		if (variation.lpc_back_image && variation.lpc_back_image.src) {
			backData = variation.lpc_back_image;
		} else {
			var backSrc =
				variation.lpc_back_image_src || variation.leons_dos_image_src || '';
			if (backSrc) {
				backData = { src: backSrc };
			} else if (faceData) {
				backData = faceData;
			}
		}

		if (this.backImage && backData) {
			this.updateImage(this.backImage, backData);
		}
	};

	LpcCustomizer.prototype.resetPreviewImages = function () {
		var placeholder =
			(this.faceImage && this.faceImage.getAttribute('data-placeholder-src')) || '';
		if (this.faceImage && placeholder) {
			this.updateImage(this.faceImage, { src: placeholder });
		}
		if (this.backImage && placeholder) {
			this.updateImage(this.backImage, { src: placeholder });
		}
		this.render();
	};

	LpcCustomizer.prototype.bindVariations = function () {
		var self = this;
		this.onFoundVariation = function (event, variation) {
			self.applyVariation(variation);
		};
		this.onResetVariation = function () {
			self.resetPreviewImages();
		};
		$(document.body).on('found_variation', '.variations_form', this.onFoundVariation);
		$(document.body).on('reset_data', '.variations_form', this.onResetVariation);
	};

	LpcCustomizer.prototype.destroy = function () {
		this.pairs.forEach(function (pair) {
			if (pair.observer) {
				pair.observer.disconnect();
			}
		});
		$(document.body).off('found_variation', '.variations_form', this.onFoundVariation);
		$(document.body).off('reset_data', '.variations_form', this.onResetVariation);
	};

	// Le bloc de personnalisation est rendu hors du <form> "Ajouter au panier"
	// (voir content-single-product.php du thème), en pleine largeur sous la
	// galerie/résumé. On relie ses champs au formulaire via l'attribut HTML
	// `form` : le navigateur les inclut alors dans la soumission (POST natif
	// ou FormData/serialize()) au même titre que s'ils étaient imbriqués
	// dedans, quelle que soit leur position réelle dans le DOM.
	function linkFieldsToCartForm(root) {
		var form = document.querySelector('.summary form.cart');
		if (!form) {
			return;
		}
		if (!form.id) {
			form.id = 'lpc-add-to-cart-form';
		}
		root.querySelectorAll('input, select, textarea').forEach(function (field) {
			field.setAttribute('form', form.id);
		});
	}

	$(function () {
		if (!window.lpcCustomizer || !window.lpcCustomizer.config) {
			return;
		}
		var root = document.querySelector('[data-lpc-customizer]');
		if (!root) {
			return;
		}
		linkFieldsToCartForm(root);
		var instance = new LpcCustomizer(root, window.lpcCustomizer.config);
		instance.init();
		root.lpcCustomizerInstance = instance;
	});
})(jQuery);
