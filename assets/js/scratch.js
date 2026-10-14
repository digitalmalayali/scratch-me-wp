/* global SW_Data, jQuery */
(function ($) {
	'use strict';

	var i18n = (window.SW_Data && SW_Data.i18n) || {};

	// ------------------------------------------------------------------
	// Nonce handling. Pages may be served from a full-page cache, in which
	// case the nonce printed in the page can be days old. Fetch a fresh one
	// in the background, and retry once if the server says it expired.
	// ------------------------------------------------------------------
	var nonce = window.SW_Data ? SW_Data.nonce : '';
	var noncePromise = null;

	function refreshNonce() {
		noncePromise = $.ajax({
			url: SW_Data.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			cache: false,
			data: { action: 'sw_get_nonce' }
		}).then(function (res) {
			if (res && res.success && res.data && res.data.nonce) {
				nonce = res.data.nonce;
			}
			return nonce;
		}, function () {
			return nonce; // Keep the one from the page.
		});
		return noncePromise;
	}

	function postEntry(payload, isRetry) {
		var send = function () {
			return $.ajax({
				url: SW_Data.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: $.extend({}, payload, { nonce: nonce })
			});
		};
		var request = noncePromise ? noncePromise.then(send, send) : send();

		return request.then(null, function (xhr) {
			var body = xhr && xhr.responseJSON;
			var expired = xhr && xhr.status === 403 &&
				(!body || body === -1 || (body.data && body.data.code === 'bad_nonce'));
			if (expired && !isRetry) {
				return refreshNonce().then(function () {
					return postEntry(payload, true);
				});
			}
			return $.Deferred().reject(xhr).promise();
		});
	}

	function errorMessage(xhr) {
		var body = xhr && xhr.responseJSON;
		if (body && body.data && body.data.message) {
			return body.data.message;
		}
		return i18n.genericError || 'Something went wrong. Please try again.';
	}

	// ------------------------------------------------------------------
	// Custom questions. These checks mirror SW_Questions::validate() on the
	// server, which always has the final say.
	// ------------------------------------------------------------------
	var qMsg = (window.SW_Data && SW_Data.qMsg) || {};

	function fmt(str, value) {
		return String(str || '').replace(/%(\d\$)?[sd]/, String(value));
	}

	// Pick the singular message when the number is 1.
	function fmtCount(key, n) {
		return fmt((n === 1 && qMsg[key + '1']) ? qMsg[key + '1'] : qMsg[key], n);
	}

	function charCount(text) {
		// Count characters the way the server does (emoji = 1).
		return Array.from ? Array.from(text).length : text.length;
	}

	function testPattern(pattern, value) {
		var sources = {
			letters: "^[\\p{L}\\p{M}\\s.'-]+$",
			digits: '^[0-9]+$',
			alnum: '^[\\p{L}\\p{M}\\p{N}\\s]+$'
		};
		if (!sources[pattern]) { return true; }
		try {
			return new RegExp(sources[pattern], 'u').test(value);
		} catch (e) {
			return true; // Very old browser: leave it to the server.
		}
	}

	function validDate(value) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
		if (!m) { return false; }
		var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
		return d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3];
	}

	/**
	 * Read and check one question. Returns { value, error }.
	 */
	function checkQuestion($q) {
		var type = $q.data('type');
		var required = String($q.data('required')) === '1';
		var num = function (key) {
			var v = $q.attr('data-' + key);
			return (v === undefined || v === '') ? null : parseFloat(v);
		};

		if (type === 'checkbox') {
			var picked = $q.find('input:checked').map(function () { return this.value; }).get();
			var minS = num('min-select');
			var maxS = num('max-select');
			if (!picked.length) {
				if (required || (minS && minS > 0)) {
					return { value: picked, error: (minS && minS > 1) ? fmt(qMsg.minSelect, minS) : qMsg.selectOne };
				}
				return { value: picked, error: '' };
			}
			if (minS && picked.length < minS) { return { value: picked, error: fmt(qMsg.minSelect, minS) }; }
			if (maxS && picked.length > maxS) { return { value: picked, error: fmtCount('maxSelect', maxS) }; }
			return { value: picked, error: '' };
		}

		if (type === 'radio') {
			var $checked = $q.find('input:checked');
			var rv = $checked.length ? $checked.val() : '';
			return { value: rv, error: (!rv && required) ? qMsg.chooseOne : '' };
		}

		var $input = $q.find('.sw-q-input').first();
		var el = $input[0];

		if (type === 'number' && el && el.validity && el.validity.badInput) {
			return { value: '', error: qMsg.number };
		}

		var value = $.trim($input.val() || '');

		if (value === '') {
			if (required) {
				return { value: value, error: type === 'select' ? qMsg.chooseOne : qMsg.required };
			}
			return { value: value, error: '' };
		}

		switch (type) {
			case 'text':
			case 'textarea':
				var len = charCount(value);
				var minL = num('min-length');
				var maxL = num('max-length');
				if (minL !== null && len < minL) { return { value: value, error: fmtCount('minLength', minL) }; }
				if (maxL !== null && len > maxL) { return { value: value, error: fmtCount('maxLength', maxL) }; }
				var pattern = $q.data('pattern');
				if (!testPattern(pattern, value)) { return { value: value, error: qMsg[pattern] || qMsg.required }; }
				break;

			case 'number':
				if (!/^-?\d+(\.\d+)?$/.test(value)) { return { value: value, error: qMsg.number }; }
				if (String($q.data('integer')) === '1' && value.indexOf('.') !== -1) { return { value: value, error: qMsg.integer }; }
				var n = parseFloat(value);
				var min = num('min');
				var max = num('max');
				if (min !== null && n < min) { return { value: value, error: fmt(qMsg.min, $q.attr('data-min')) }; }
				if (max !== null && n > max) { return { value: value, error: fmt(qMsg.max, $q.attr('data-max')) }; }
				break;

			case 'email':
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) { return { value: value, error: qMsg.email }; }
				break;

			case 'date':
				if (!validDate(value)) { return { value: value, error: qMsg.date }; }
				var minD = $q.attr('data-min-date');
				var maxD = $q.attr('data-max-date');
				if (minD && value < minD) { return { value: value, error: fmt(qMsg.minDate, $q.attr('data-min-date-text') || minD) }; }
				if (maxD && value > maxD) { return { value: value, error: fmt(qMsg.maxDate, $q.attr('data-max-date-text') || maxD) }; }
				break;

			case 'select':
				var allowed = $input.find('option').map(function () { return this.value; }).get();
				if (allowed.indexOf(value) === -1) { return { value: value, error: qMsg.chooseValid }; }
				break;
		}
		return { value: value, error: '' };
	}

	function showQuestionError($q, message) {
		$q.toggleClass('sw-has-error', !!message);
		$q.find('.sw-q-input').attr('aria-invalid', message ? 'true' : 'false');
		$q.find('.sw-q-error').text(message || '').toggle(!!message);
	}

	/**
	 * Check every question in a form. Returns { answers, ok, first }.
	 */
	function collectAnswers($form) {
		var answers = {};
		var ok = true;
		var $first = null;
		$form.find('.sw-question').each(function () {
			var $q = $(this);
			var result = checkQuestion($q);
			$q.attr('data-touched', '1');
			showQuestionError($q, result.error);
			if (result.error) {
				ok = false;
				if (!$first) { $first = $q; }
			}
			answers[$q.data('qid')] = result.value;
		});
		return { answers: answers, ok: ok, first: $first };
	}

	function updateCounter($q) {
		var $counter = $q.find('.sw-q-counter');
		if (!$counter.length) { return; }
		var max = parseInt($q.attr('data-max-length'), 10);
		var len = charCount($q.find('.sw-q-input').val() || '');
		var min = parseInt($q.attr('data-min-length'), 10);
		$counter.text(len + ' / ' + max);
		// Warn when close to the limit — but not for fixed-length answers
		// (e.g. a 6-digit PIN), where reaching the limit is exactly right.
		$counter.toggleClass('sw-q-counter-near', !isNaN(max) && min !== max && len >= max * 0.9);
	}

	// Live feedback: counters update as you type; once a field has shown an
	// error (or the form was submitted), it re-checks itself on every change.
	$(document).on('input change', '.sw-question .sw-q-input', function () {
		var $q = $(this).closest('.sw-question');
		updateCounter($q);
		if ($q.attr('data-touched') === '1') {
			showQuestionError($q, checkQuestion($q).error);
			clearFixMessage($q.closest('form'));
		}
	});

	// Once every answer is fixed, remove the "check the highlighted answers" note.
	function clearFixMessage($form) {
		if ($form.find('.sw-question.sw-has-error').length) { return; }
		var $error = $form.nextAll('.sw-error').first();
		if ($error.is(':visible') && $error.text() === qMsg.fixErrors) {
			$error.hide().text('');
		}
	}
	$(document).on('blur', '.sw-question .sw-q-input', function () {
		var $q = $(this).closest('.sw-question');
		// Mark touched only once the visitor has typed or picked something.
		if (checkQuestion($q).value.length) {
			$q.attr('data-touched', '1');
			showQuestionError($q, checkQuestion($q).error);
		}
	});

	// ------------------------------------------------------------------
	// One scratch card
	// ------------------------------------------------------------------
	function initScratchCard(container) {
		var $container = $(container);
		var campaignId = $container.data('campaign-id');
		var requireBefore = String($container.data('require-before')) === '1';
		var scratchImage = $container.data('scratch-image');

		var $gateBefore = $container.find('.sw-phone-gate-before');
		var $gateAfter = $container.find('.sw-phone-gate-after');
		var $scratchWrapper = $container.find('.sw-scratch-wrapper');
		var $hint = $container.find('.sw-scratch-hint');
		var $resultBox = $container.find('.sw-result');
		var $resultMessage = $container.find('.sw-result-message');
		var $resultDescription = $container.find('.sw-result-description');
		var $resultCoupon = $container.find('.sw-result-coupon');
		var $resultImage = $container.find('.sw-result-image');
		var $returningBadge = $container.find('.sw-returning-badge');
		var $prizeLabel = $container.find('.sw-prize-label');
		var $prizeImage = $container.find('.sw-prize-image');

		var pendingResult = null; // server result when the phone is captured before the reveal
		var revealed = false;
		var submitting = false;

		// Digits only, max 10.
		$container.find('.sw-phone-input').on('input', function () {
			var digits = this.value.replace(/\D/g, '');
			// Allow pasting "+91 98765 43210" or "098765 43210".
			if (digits.length === 12 && digits.indexOf('91') === 0) { digits = digits.slice(2); }
			if (digits.length === 11 && digits.charAt(0) === '0') { digits = digits.slice(1); }
			this.value = digits.slice(0, 10);
		});

		function submitPhone($form, onSuccess) {
			if (submitting) { return; }

			var $input = $form.find('.sw-phone-input');
			var digits = $input.val().replace(/\D/g, '');
			var $error = $form.nextAll('.sw-error').first();
			$error.hide().text('');

			var phoneOk = /^[6-9]\d{9}$/.test(digits);
			var collected = collectAnswers($form);

			if (!phoneOk) {
				$error.text(i18n.invalidPhone || 'Please enter a valid 10-digit mobile number.').show();
				$input.trigger('focus');
				return;
			}
			if (!collected.ok) {
				$error.text(qMsg.fixErrors || '').show();
				collected.first.find('.sw-q-input').first().trigger('focus');
				return;
			}

			var $btn = $form.find('button[type="submit"]');
			submitting = true;
			$btn.prop('disabled', true);

			postEntry({
				action: 'sw_submit_phone',
				campaign_id: campaignId,
				raw_input: digits,
				answers: collected.answers,
				sw_hp: $form.find('.sw-hp-input').val() || ''
			}, false).then(function (response) {
				submitting = false;
				$btn.prop('disabled', false);
				if (response && response.success) {
					onSuccess(response.data);
				} else {
					var data = (response && response.data) || {};
					$error.text(data.message || i18n.genericError || '').show();
					if (data.field_errors) {
						var $firstBad = null;
						var missing = false;
						$.each(data.field_errors, function (qid, message) {
							var $q = $form.find('.sw-question').filter(function () { return String($(this).data('qid')) === String(qid); });
							if (!$q.length) { missing = true; return; }
							$q.attr('data-touched', '1');
							showQuestionError($q, message);
							if (!$firstBad) { $firstBad = $q; }
						});
						if (missing) {
							// The questions changed after this page was loaded (e.g. an
							// old copy from a page cache). Ask for a reload.
							$error.text(qMsg.reload || i18n.genericError || '').show();
						}
						if ($firstBad) { $firstBad.find('.sw-q-input').first().trigger('focus'); }
					}
				}
			}, function (xhr) {
				submitting = false;
				$btn.prop('disabled', false);
				$error.text(errorMessage(xhr)).show();
			});
		}

		// --- Flow: phone required BEFORE scratching ---
		$gateBefore.find('.sw-phone-form').on('submit', function (e) {
			e.preventDefault();
			submitPhone($(this), function (data) {
				$gateBefore.hide();
				if (data.returning_visitor) {
					// Skip the card entirely — show their existing result right away.
					showResult(data);
					return;
				}
				pendingResult = data;
				$prizeLabel.text(data.prize);
				if (data.prize_image) {
					$prizeImage.attr('src', data.prize_image).show();
				}
				$scratchWrapper.show();
				$hint.show();
				setupCanvas();
			});
		});

		// --- Flow: phone required AFTER scratching ---
		$gateAfter.find('.sw-phone-form').on('submit', function (e) {
			e.preventDefault();
			submitPhone($(this), showResult);
		});

		if (!requireBefore) {
			// The real prize is only drawn once the phone number is entered.
			$prizeLabel.text('🎁 ' + (i18n.mystery || ''));
			setupCanvas();
		}

		function showResult(data) {
			$gateAfter.hide();
			$scratchWrapper.hide();
			$hint.hide();

			$returningBadge.toggle(!!data.returning_visitor);
			$resultMessage.text(data.message || '');

			if (data.prize_description) {
				$resultDescription.text(data.prize_description).show();
			} else {
				$resultDescription.hide();
			}

			if (data.coupon_code) {
				$resultCoupon.text((i18n.couponLabel || 'Coupon code:') + ' ' + data.coupon_code).show();
			} else {
				$resultCoupon.hide();
			}

			if (data.prize_image) {
				$resultImage.attr('src', data.prize_image).show();
			} else {
				$resultImage.hide();
			}

			$container.addClass('sw-is-' + (data.result === 'win' ? 'win' : 'lose'));
			$resultBox.show();
		}

		function setupCanvas() {
			var canvas = $container.find('.sw-scratch-canvas')[0];
			if (!canvas || canvas.getAttribute('data-initialized')) { return; }
			canvas.setAttribute('data-initialized', '1');

			var ctx = canvas.getContext('2d');
			var width = canvas.width;
			var height = canvas.height;
			var hasScratched = false;
			var isDrawing = false;
			var lastPos = null;

			function drawDefaultLayer() {
				ctx.globalCompositeOperation = 'source-over';
				var gradient = ctx.createLinearGradient(0, 0, width, height);
				gradient.addColorStop(0, '#c9c9c9');
				gradient.addColorStop(0.5, '#e8e8e8');
				gradient.addColorStop(1, '#b7b7b7');
				ctx.fillStyle = gradient;
				ctx.fillRect(0, 0, width, height);
				ctx.fillStyle = '#888';
				ctx.font = 'bold 18px sans-serif';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.fillText(i18n.scratchHere || 'SCRATCH HERE', width / 2, height / 2);
			}

			// Always paint the default layer first so the prize is never visible
			// while a custom image loads; swap in the image only if the visitor
			// has not started scratching yet.
			drawDefaultLayer();
			if (scratchImage) {
				var img = new Image();
				img.onload = function () {
					if (hasScratched) { return; }
					ctx.globalCompositeOperation = 'source-over';
					ctx.drawImage(img, 0, 0, width, height);
				};
				img.src = scratchImage;
			}

			function getPos(e) {
				var rect = canvas.getBoundingClientRect();
				var point = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]) || e;
				return {
					x: (point.clientX - rect.left) * (canvas.width / rect.width),
					y: (point.clientY - rect.top) * (canvas.height / rect.height)
				};
			}

			function scratchTo(pos) {
				ctx.globalCompositeOperation = 'destination-out';
				ctx.lineWidth = 36;
				ctx.lineCap = 'round';
				ctx.lineJoin = 'round';
				ctx.beginPath();
				if (lastPos) {
					ctx.moveTo(lastPos.x, lastPos.y);
					ctx.lineTo(pos.x, pos.y);
					ctx.stroke();
				} else {
					ctx.arc(pos.x, pos.y, 18, 0, Math.PI * 2);
					ctx.fill();
				}
				lastPos = pos;
			}

			function checkCompletion() {
				var ratio = 0;
				try {
					var data = ctx.getImageData(0, 0, width, height).data;
					var transparent = 0;
					var sampled = 0;
					for (var i = 3; i < data.length; i += 16) { // every 4th pixel
						sampled++;
						if (data[i] === 0) { transparent++; }
					}
					ratio = sampled ? transparent / sampled : 0;
				} catch (err) {
					ratio = 1; // Canvas unreadable (e.g. cross-origin image) — just reveal.
				}
				if (ratio >= 0.5 && !revealed) {
					revealed = true;
					finishReveal();
				}
			}

			function finishReveal() {
				canvas.style.transition = 'opacity 0.4s ease';
				canvas.style.opacity = '0';
				canvas.style.pointerEvents = 'none';
				$hint.hide();

				setTimeout(function () {
					if (requireBefore && pendingResult) {
						showResult(pendingResult);
					} else if (!requireBefore) {
						$gateAfter.show();
						$gateAfter.find('.sw-phone-input').trigger('focus');
					}
				}, 700); // Let the visitor see the revealed card for a moment.
			}

			function handleStart(e) {
				if (revealed) { return; }
				if (e.cancelable) { e.preventDefault(); }
				isDrawing = true;
				hasScratched = true;
				lastPos = null;
				scratchTo(getPos(e));
			}
			function handleMove(e) {
				if (!isDrawing) { return; }
				if (e.cancelable) { e.preventDefault(); }
				scratchTo(getPos(e));
			}
			function handleEnd() {
				if (!isDrawing) { return; }
				isDrawing = false;
				lastPos = null;
				checkCompletion();
			}

			canvas.addEventListener('mousedown', handleStart);
			canvas.addEventListener('mousemove', handleMove);
			window.addEventListener('mouseup', handleEnd);

			canvas.addEventListener('touchstart', handleStart, { passive: false });
			canvas.addEventListener('touchmove', handleMove, { passive: false });
			canvas.addEventListener('touchend', handleEnd);
			canvas.addEventListener('touchcancel', handleEnd);
		}
	}

	$(function () {
		var $cards = $('.sw-container');
		if (!$cards.length || !window.SW_Data) { return; }
		refreshNonce();
		$cards.each(function () {
			initScratchCard(this);
		});
	});
})(jQuery);
