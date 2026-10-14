/* global jQuery, wp, SW_Admin */
jQuery(function ($) {
	'use strict';

	var i18n = (window.SW_Admin && SW_Admin.i18n) || {};
	var $table = $('#sw-prizes-table');
	var $tbody = $table.find('tbody');

	// Unique key for each new prize row, so every row's fields (including its
	// checkboxes) post together as prizes[<key>][...].
	var rowCounter = $tbody.find('tr').length + 1000;

	// ------------------------------------------------------------------
	// Media uploader — campaign fields (data-target = input id) and per-row
	// prize images (data-target-class, since rows repeat).
	// ------------------------------------------------------------------
	$(document).on('click', '.sw-media-upload', function (e) {
		e.preventDefault();
		if (!window.wp || !wp.media) { return; }

		var button = $(this);
		var targetId = button.data('target');
		var targetClass = button.data('target-class');

		var frame = wp.media({
			title: i18n.mediaTitle || 'Select or Upload Image',
			button: { text: i18n.mediaButton || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			if (targetId) {
				$('#' + targetId).val(attachment.url);
			} else if (targetClass) {
				var $cell = button.closest('td');
				$cell.find('.' + targetClass).val(attachment.url);
				$cell.find('.sw-prize-thumb').attr('src', attachment.url).show();
				$cell.find('.sw-remove-image').show();
			}
		});

		frame.open();
	});

	$(document).on('click', '.sw-remove-image', function (e) {
		e.preventDefault();
		var $cell = $(this).closest('td');
		$cell.find('.sw-prize-image-field').val('');
		$cell.find('.sw-prize-thumb').attr('src', '').hide();
		$(this).hide();
	});

	// ------------------------------------------------------------------
	// Prize rows
	// ------------------------------------------------------------------
	function addRow(values) {
		var tmpl = document.getElementById('sw-prize-row-template');
		if (!tmpl) { return null; }
		var html = tmpl.innerHTML.replace(/__INDEX__/g, String(rowCounter++));
		var $row = $($.parseHTML($.trim(html))).filter('tr');
		$tbody.append($row);

		if (values) {
			$row.find('.sw-prize-label').val(values.label || '');
			$row.find('.sw-prize-coupon').val(values.coupon || '');
			$row.find('.sw-prize-description').val(values.description || '');
			$row.find('.sw-chance-input').val(values.chance || 0);
			$row.find('.sw-prize-stock').val(values.stock === null || values.stock === undefined ? '' : values.stock);
			$row.find('.sw-prize-active').prop('checked', values.active !== false);
			$row.find('.sw-prize-winning').prop('checked', values.winning !== false);
			var $type = $row.find('.sw-prize-type');
			if (values.type && $type.find('option').filter(function () { return this.value === values.type; }).length) {
				$type.val(values.type);
			}
		}
		syncRowType($row);
		return $row;
	}

	$('#sw-add-prize').on('click', function () {
		var $row = addRow(null);
		if ($row) { $row.find('.sw-prize-label').trigger('focus'); }
		updateTotalChance();
	});

	$(document).on('click', '.sw-remove-prize', function () {
		$(this).closest('tr').remove();
		updateTotalChance();
	});

	// "No Prize" rows can never be a win, and their Chance is not used:
	// visitors lose whenever no winning prize is drawn.
	function syncRowType($row) {
		var isNoPrize = $row.find('.sw-prize-type').val() === 'No Prize';
		var $win = $row.find('.sw-prize-winning');
		if (isNoPrize) {
			$win.prop('checked', false);
		}
		$win.prop('disabled', isNoPrize);
		$row.find('.sw-chance-input').prop('readonly', isNoPrize).css('opacity', isNoPrize ? 0.5 : 1);
	}

	$(document).on('change', '.sw-prize-type', function () {
		syncRowType($(this).closest('tr'));
		updateTotalChance();
	});

	$tbody.find('tr').each(function () { syncRowType($(this)); });

	// Live Total Chance = overall win rate (active, winning prizes only).
	function updateTotalChance() {
		var total = 0;
		$tbody.find('tr').each(function () {
			var $row = $(this);
			if (!$row.find('.sw-prize-active').is(':checked') || !$row.find('.sw-prize-winning').is(':checked')) {
				return;
			}
			var val = parseFloat($row.find('.sw-chance-input').val());
			if (!isNaN(val)) { total += val; }
		});
		var $total = $('#sw-total-chance');
		$total.text(total.toFixed(2));
		$total.css('color', total > 100.001 ? '#b32d2e' : '#1a7f37');
	}

	$(document).on('input change', '.sw-chance-input, .sw-prize-active, .sw-prize-winning', updateTotalChance);
	updateTotalChance();

	// ------------------------------------------------------------------
	// CSV import (same columns as the sample file). Rows are added to the
	// table for review; nothing is saved until "Save Campaign" is clicked.
	// ------------------------------------------------------------------
	function parseCsv(text) {
		var rows = [];
		var row = [];
		var field = '';
		var inQuotes = false;
		text = text.replace(/^﻿/, '');

		for (var i = 0; i < text.length; i++) {
			var c = text.charAt(i);
			if (inQuotes) {
				if (c === '"') {
					if (text.charAt(i + 1) === '"') { field += '"'; i++; } else { inQuotes = false; }
				} else {
					field += c;
				}
			} else if (c === '"') {
				inQuotes = true;
			} else if (c === ',') {
				row.push(field); field = '';
			} else if (c === '\n' || c === '\r') {
				if (c === '\r' && text.charAt(i + 1) === '\n') { i++; }
				row.push(field); field = '';
				rows.push(row); row = [];
			} else {
				field += c;
			}
		}
		if (field !== '' || row.length) { row.push(field); rows.push(row); }
		return rows.filter(function (r) { return r.join('').trim() !== ''; });
	}

	function yes(value, fallback) {
		var v = String(value || '').trim().toLowerCase();
		if (v === '') { return fallback; }
		return ['yes', 'y', 'true', '1', 'on'].indexOf(v) !== -1;
	}

	function fmt(str, n) { return String(str || '').replace('%d', n); }

	$('#sw-import-file').on('change', function () {
		var file = this.files && this.files[0];
		var input = this;
		var $status = $('#sw-import-status');
		if (!file) { return; }

		var reader = new FileReader();
		reader.onerror = function () { $status.text(i18n.importError || ''); };
		reader.onload = function () {
			var rows = parseCsv(String(reader.result || ''));
			input.value = '';
			if (!rows.length) { $status.text(i18n.importEmpty || ''); return; }

			// Map columns by header name so column order doesn't matter.
			var header = rows[0].map(function (h) { return h.trim().toLowerCase(); });
			var col = function (names) {
				for (var n = 0; n < names.length; n++) {
					var idx = header.indexOf(names[n]);
					if (idx !== -1) { return idx; }
				}
				return -1;
			};
			var cTitle = col(['prize title', 'title', 'prize', 'label']);
			if (cTitle === -1) { $status.text(i18n.importEmpty || ''); return; }
			var cType = col(['prize type', 'type']);
			var cCoupon = col(['coupon code', 'coupon']);
			var cDesc = col(['description']);
			var cChance = col(['chance (%)', 'chance', 'chance %']);
			var cStock = col(['stock/count', 'stock', 'count']);
			var cWin = col(['is winning', 'winning', 'is win']);
			var cActive = col(['active', 'is active']);

			var prizes = [];
			rows.slice(1).forEach(function (r) {
				var get = function (idx) { return idx === -1 ? '' : String(r[idx] || '').trim(); };
				var label = get(cTitle);
				if (!label) { return; }
				var type = get(cType) || 'Common Coupon';
				var stock = get(cStock).replace(/[^0-9]/g, '');
				prizes.push({
					label: label,
					type: type,
					coupon: get(cCoupon),
					description: get(cDesc),
					chance: Math.max(0, Math.min(100, parseFloat(get(cChance).replace('%', '')) || 0)),
					stock: stock === '' ? null : parseInt(stock, 10),
					winning: type === 'No Prize' ? false : yes(get(cWin), true),
					active: yes(get(cActive), true)
				});
			});

			if (!prizes.length) { $status.text(i18n.importEmpty || ''); return; }

			var hasRows = $tbody.find('.sw-prize-label').filter(function () { return $.trim(this.value) !== ''; }).length > 0;
			if (hasRows && window.confirm(fmt(i18n.importReplace, prizes.length))) {
				$tbody.empty();
			}
			prizes.forEach(function (p) { addRow(p); });
			updateTotalChance();
			$status.text(fmt(i18n.importDone, prizes.length));
		};
		reader.readAsText(file);
	});

	// ------------------------------------------------------------------
	// Question builder
	// ------------------------------------------------------------------
	var $qList = $('#sw-questions-list');
	var qCounter = $qList.children('.sw-question-card').length + 1000;
	var maxQuestions = parseInt($qList.data('max'), 10) || 20;

	function syncQuestionCard($card) {
		var type = $card.find('.sw-qc-type').val();
		$card.attr('data-type', type);
		$card.find('[data-show-for]').each(function () {
			var types = String($(this).data('show-for')).split(' ');
			$(this).toggle(types.indexOf(type) !== -1);
		});
		// Max characters placeholder follows the type's hard limit.
		var $maxl = $card.find('input[name$="[max_length]"]');
		$maxl.attr('placeholder', type === 'textarea' ? '5000' : '1000');
		$maxl.attr('max', type === 'textarea' ? '5000' : '1000');
	}

	function refreshQuestions() {
		var $cards = $qList.children('.sw-question-card');
		$cards.each(function (i) {
			$(this).find('.sw-qc-number').text('Q' + (i + 1));
			$(this).find('.sw-qc-up').prop('disabled', i === 0);
			$(this).find('.sw-qc-down').prop('disabled', i === $cards.length - 1);
		});
		$('.sw-q-empty').toggle($cards.length === 0);
		var full = $cards.length >= maxQuestions;
		$('#sw-add-question').prop('disabled', full);
		$('.sw-q-limit').toggle(full);
	}

	$qList.children('.sw-question-card').each(function () { syncQuestionCard($(this)); });
	refreshQuestions();

	$('#sw-add-question').on('click', function () {
		if ($qList.children('.sw-question-card').length >= maxQuestions) { return; }
		var tmpl = document.getElementById('sw-question-template');
		if (!tmpl) { return; }
		var html = tmpl.innerHTML.replace(/__QKEY__/g, String(qCounter++));
		var $card = $($.parseHTML($.trim(html))).filter('.sw-question-card');
		$qList.append($card);
		syncQuestionCard($card);
		refreshQuestions();
		$card.find('.sw-qc-label').trigger('focus');
	});

	$qList.on('change', '.sw-qc-type', function () {
		syncQuestionCard($(this).closest('.sw-question-card'));
	});

	$qList.on('click', '.sw-qc-remove', function () {
		var $card = $(this).closest('.sw-question-card');
		var label = $.trim($card.find('.sw-qc-label').val());
		if (label && !window.confirm(fmtText(i18n.deleteQuestion, label))) { return; }
		$card.remove();
		refreshQuestions();
	});

	$qList.on('click', '.sw-qc-up', function () {
		var $card = $(this).closest('.sw-question-card');
		$card.prev('.sw-question-card').before($card);
		refreshQuestions();
		$(this).trigger('focus');
	});

	$qList.on('click', '.sw-qc-down', function () {
		var $card = $(this).closest('.sw-question-card');
		$card.next('.sw-question-card').after($card);
		refreshQuestions();
		$(this).trigger('focus');
	});

	function fmtText(str, value) { return String(str || '').replace('%s', value); }

	// Check the questions before the campaign is saved, and point at the
	// first problem instead of silently saving something that won't work.
	function questionProblem($card) {
		var label = $.trim($card.find('.sw-qc-label').val());
		var type = $card.find('.sw-qc-type').val();
		var num = function (sel) {
			var v = $.trim($card.find(sel).val());
			return v === '' ? null : parseFloat(v);
		};
		if (!label) {
			// An untouched empty card is simply ignored on save.
			var touched = $card.find('textarea, input[type="text"], input[type="number"], input[type="date"]').filter(function () {
				return $.trim(this.value) !== '';
			}).length > 0;
			return touched ? { el: '.sw-qc-label', msg: i18n.qNeedsLabel } : null;
		}
		if (type === 'select' || type === 'radio' || type === 'checkbox') {
			var opts = $.trim($card.find('.sw-qc-options').val()).split(/\r?\n/).filter(function (o) { return $.trim(o) !== ''; });
			if (!opts.length) { return { el: '.sw-qc-options', msg: i18n.qNeedsOptions }; }
			if (type === 'checkbox') {
				var minS = num('input[name$="[min_select]"]');
				var maxS = num('input[name$="[max_select]"]');
				if (minS !== null && minS > opts.length) { return { el: 'input[name$="[min_select]"]', msg: i18n.qMinSelect }; }
				if (minS !== null && maxS !== null && minS > maxS) { return { el: 'input[name$="[min_select]"]', msg: i18n.qMinMax }; }
			}
		}
		if (type === 'text' || type === 'textarea') {
			var minL = num('input[name$="[min_length]"]');
			var maxL = num('input[name$="[max_length]"]');
			if (minL !== null && maxL !== null && minL > maxL) { return { el: 'input[name$="[min_length]"]', msg: i18n.qMinMax }; }
		}
		if (type === 'number') {
			var minV = num('input[name$="[min]"]');
			var maxV = num('input[name$="[max]"]');
			if (minV !== null && maxV !== null && minV > maxV) { return { el: 'input[name$="[min]"]', msg: i18n.qMinMax }; }
		}
		if (type === 'date') {
			var minD = $card.find('input[name$="[min_date]"]').val();
			var maxD = $card.find('input[name$="[max_date]"]').val();
			if (minD && maxD && minD > maxD) { return { el: 'input[name$="[min_date]"]', msg: i18n.qMinMax }; }
		}
		return null;
	}

	// Stop before submitting if the server would drop part of the form.
	var maxVars = (window.SW_Admin && parseInt(SW_Admin.maxInputVars, 10)) || 0;
	$('input[name="sw_form_end"]').closest('form').on('submit', function (e) {
		if (!maxVars) { return; }
		var count = 0;
		$(this).find('input, select, textarea').each(function () {
			if (!this.name || this.disabled) { return; }
			if ((this.type === 'checkbox' || this.type === 'radio') && !this.checked) { return; }
			if (this.type === 'submit' || this.type === 'button' || this.type === 'file') { return; }
			count++;
		});
		if (count > maxVars) {
			e.preventDefault();
			e.stopImmediatePropagation();
			window.alert(String(i18n.tooManyFields || '').replace('%1$d', count).replace('%2$d', maxVars));
		}
	});

	$qList.closest('form').on('submit', function (e) {
		var stop = false;
		$qList.find('.sw-qc-problem').remove();
		$qList.children('.sw-question-card').each(function (i) {
			var problem = questionProblem($(this));
			if (!problem) { return; }
			var $field = $(this).find(problem.el).first();
			$('<p class="sw-qc-problem" role="alert"></p>').text('Q' + (i + 1) + ': ' + problem.msg).appendTo($(this));
			if (!stop) {
				stop = true;
				e.preventDefault();
				$field.trigger('focus');
				this.scrollIntoView({ block: 'center' });
			}
		});
	});

	// ------------------------------------------------------------------
	// Leads page: confirm before permanently deleting via Bulk actions.
	// ------------------------------------------------------------------
	$('#sw-leads-form').on('submit', function (e) {
		var submitter = e.originalEvent && e.originalEvent.submitter;
		if (submitter && submitter.name === 'sw_empty_trash') { return; } // has its own confirm
		var a1 = $(this).find('select[name="bulk_action"]').val();
		var a2 = $(this).find('select[name="bulk_action2"]').val();
		if ((a1 === 'delete' || a2 === 'delete') &&
			!window.confirm(i18n.confirmDelete || 'Permanently delete the selected leads?')) {
			e.preventDefault();
		}
	});
});
