(function($) {
	'use strict';

	function ajax(action, data) {
		return $.post(ABLF.ajaxUrl, $.extend({ action: action, nonce: ABLF.nonce }, data || {}));
	}

	/* ---------- Bulk action options — context-aware per tab ---------- */
	function updateBulkActions(tab) {
		var select    = document.getElementById('ablf-bulk-action');
		var bulkBar   = document.querySelector('.ablf-bulk-bar');
		var selectAll = document.getElementById('ablf-select-all');

		if (tab === 'fixed' || tab === 'allowlist') {
			// Hide bulk bar, header checkbox, and every row checkbox on these tabs.
			if (bulkBar)   bulkBar.style.display      = 'none';
			if (selectAll) selectAll.style.visibility = 'hidden';
			document.querySelectorAll('.ablf-row-check').forEach(function(el) { el.style.visibility = 'hidden'; });
			return;
		}

		// All other tabs — restore visibility.
		if (bulkBar)   bulkBar.style.display      = 'flex';
		if (selectAll) selectAll.style.visibility = 'visible';
		document.querySelectorAll('.ablf-row-check').forEach(function(el) { el.style.visibility = 'visible'; });

		if (!select) return;
		select.innerHTML = '<option value="">Bulk actions</option>';
		if (tab === 'broken' || tab === 'all') {
			select.innerHTML +=
				'<option value="ignore">Ignore Selected</option>' +
				'<option value="ask_pathfinder">Ask Pathfinder for Selected</option>' +
				'<option value="add_to_allowlist">Add to Allowlist</option>';
		} else if (tab === 'ignored') {
			select.innerHTML += '<option value="restore">Restore Selected</option>';
		}
		// Force the placeholder to be active — defeats browser form-state restoration.
		select.value = '';
	}

	/* Read active tab from the .current link in the filter bar. */
	function activeTab() {
		var $current = $('.ablf-filters a.current');
		if (!$current.length) return 'broken';
		var href = $current.attr('href') || '';
		var match = href.match(/status=([a-z]+)/);
		return match ? match[1] : 'broken';
	}

	/* Update on page load — tabs do a full reload so this is the only call needed. */
	$(function() {
		updateBulkActions(activeTab());
	});

	/* ---------- Tab counts ---------- */
	function updateTabCount(status, delta) {
		var $tab = $('.ablf-filters a[href*="status=' + status + '"]');
		var $count = $tab.find('.count');
		var current = parseInt($count.text().replace(/[()]/g, ''), 10) || 0;
		$count.text('(' + Math.max(0, current + delta) + ')');
	}

	function maybeShowEmptyState() {
		if ($('.ablf-links-table tbody .ablf-row').length === 0) {
			$('.ablf-links-table tbody').html(
				'<tr><td colspan="8">' + (ABLF.i18n.allClean || 'No broken links found. Your site is clean! \uD83C\uDF89') + '</td></tr>'
			);
		}
	}

	$(document).on('click', '.ablf-ask-pathfinder', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		var $row = $('.ablf-suggestion-row[data-for="' + id + '"]');
		var $cell = $row.find('.ablf-suggestion-cell');
		$cell.html('<span class="ablf-spinner"></span>' + ABLF.i18n.thinking);
		$row.show();

		ajax('ablf_get_suggestions', { id: id }).done(function(resp) {
			if (!resp || !resp.success) {
				$cell.text((resp && resp.data && resp.data.message) ? resp.data.message : ABLF.i18n.error);
				return;
			}
			$cell.html(resp.data.html);
		}).fail(function() {
			$cell.text(ABLF.i18n.error);
		});
	});

	$(document).on('click', '.ablf-fix-suggestion', function(e) {
		e.preventDefault();
		if (!window.confirm(ABLF.i18n.confirmFix)) return;
		var $btn = $(this);
		var id = $btn.data('id');
		var sid = $btn.data('suggestion');
		var url = $btn.data('url');

		$btn.prop('disabled', true);
		ajax('ablf_fix_link', { id: id, suggestion_id: sid, replacement_url: url }).done(function(resp) {
			if (!resp || !resp.success) {
				window.ablf_show_toast((resp && resp.data && resp.data.message) ? resp.data.message : ABLF.i18n.error, 'error');
				$btn.prop('disabled', false);
				return;
			}
			var $row = $('.ablf-row[data-id="' + id + '"]');
			$row.find('.ablf-status').removeClass().addClass('ablf-status ablf-status-fixed').text('fixed');
			$row.find('.ablf-actions').html('<span class="description">fixed</span>');
			$('.ablf-suggestion-row[data-for="' + id + '"]').hide();
			updateTabCount('broken', -1);
			updateTabCount('fixed', 1);
		}).fail(function() {
			$btn.prop('disabled', false);
			window.ablf_show_toast(ABLF.i18n.error, 'error');
		});
	});

	$(document).on('click', '.ablf-ignore', function(e) {
		e.preventDefault();
		if (!window.confirm(ABLF.i18n.confirmIgnore)) return;
		var id = $(this).data('id');
		ajax('ablf_ignore_link', { id: id }).done(function(resp) {
			if (resp && resp.success) {
				$('.ablf-row[data-id="' + id + '"]').fadeOut(300, function() {
					$(this).remove();
					maybeShowEmptyState();
				});
				$('.ablf-suggestion-row[data-for="' + id + '"]').remove();
				updateTabCount('broken', -1);
				updateTabCount('ignored', 1);
			}
		});
	});

	$(document).on('click', '.ablf-ignore-from-suggestion', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		ajax('ablf_ignore_link', { id: id }).done(function() {
			$('.ablf-row[data-id="' + id + '"]').fadeOut(300, function() {
				$(this).remove();
				maybeShowEmptyState();
			});
			$('.ablf-suggestion-row[data-for="' + id + '"]').remove();
			updateTabCount('broken', -1);
			updateTabCount('ignored', 1);
		});
	});

	$(document).on('click', '.ablf-restore', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		ajax('ablf_restore_link', { id: id }).done(function(resp) {
			if (resp && resp.success) {
				$('.ablf-row[data-id="' + id + '"]').fadeOut(300, function() {
					$(this).remove();
					maybeShowEmptyState();
				});
				$('.ablf-suggestion-row[data-for="' + id + '"]').remove();
				updateTabCount('ignored', -1);
				updateTabCount('broken', 1);
			}
		});
	});

	// Mark a fixed link back as broken (Fixed tab → Broken tab).
	$(document).on('click', '.ablf-reopen', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		ajax('ablf_restore_link', { id: id }).done(function(resp) {
			if (resp && resp.success) {
				$('.ablf-row[data-id="' + id + '"]').fadeOut(300, function() {
					$(this).remove();
					maybeShowEmptyState();
				});
				$('.ablf-suggestion-row[data-for="' + id + '"]').remove();
				updateTabCount('fixed', -1);
				updateTabCount('broken', 1);
			}
		});
	});

	/* Bulk */
	$(document).on('change', '#ablf-select-all', function() {
		$('.ablf-row-check').prop('checked', this.checked).trigger('change');
	});
	$(document).on('change', '.ablf-row-check', function() {
		var n = $('.ablf-row-check:checked').length;
		$('.ablf-bulk-count').text(n ? n + ' selected' : '');
	});
	$(document).on('click', '#ablf-bulk-apply', function() {
		var action = $('#ablf-bulk-action').val();
		var ids = $('.ablf-row-check:checked').map(function() { return this.value; }).get();
		if (!action || !ids.length) return;

		if (action === 'ask_pathfinder') {
			ids.forEach(function(id) {
				var $row = $('.ablf-suggestion-row[data-for="' + id + '"]');
				var $cell = $row.find('.ablf-suggestion-cell');
				$cell.html('<span class="ablf-spinner"></span>' + ABLF.i18n.thinking);
				$row.show();
				ajax('ablf_get_suggestions', { id: id }).done(function(resp) {
					if (!resp || !resp.success) {
						$cell.text((resp && resp.data && resp.data.message) ? resp.data.message : ABLF.i18n.error);
						return;
					}
					$cell.html(resp.data.html);
				}).fail(function() {
					$cell.text(ABLF.i18n.error);
				});
			});
			$('.ablf-row-check').prop('checked', false);
			$('#ablf-select-all').prop('checked', false);
			$('.ablf-bulk-count').text('');
			return;
		}

		ajax('ablf_bulk_action', { bulk_action: action, ids: ids }).done(function(resp) {
			if (!resp || !resp.success) return;
			var doneAction = resp.data.action;
			ids.forEach(function(id) {
				$('.ablf-row[data-id="' + id + '"]').fadeOut(300, function() {
					$(this).remove();
					maybeShowEmptyState();
				});
				$('.ablf-suggestion-row[data-for="' + id + '"]').remove();
			});
			if (doneAction === 'ignore') {
				updateTabCount('broken', -ids.length);
				updateTabCount('ignored', ids.length);
			} else if (doneAction === 'restore') {
				updateTabCount('ignored', -ids.length);
				updateTabCount('broken', ids.length);
			} else if (doneAction === 'add_to_allowlist') {
				updateTabCount('broken', -ids.length);
				updateTabCount('allowlist', ids.length);
				window.ablf_show_toast(ids.length + ' domain(s) added to allowlist.', 'success');
			}
			$('.ablf-row-check').prop('checked', false);
			$('#ablf-select-all').prop('checked', false);
			$('.ablf-bulk-count').text('');
		});
	});

	/* ---------- Allowlist tab: add entry ---------- */
	$(document).on('click', '#ablf-allowlist-add-btn', function() {
		var pattern = $.trim($('#ablf-allowlist-pattern').val());
		var type    = $('#ablf-allowlist-type').val();
		var note    = $.trim($('#ablf-allowlist-note').val());
		var $fb     = $('.ablf-allowlist-feedback');

		if (!pattern) {
			$fb.text('Please enter a pattern.').css('color', '#d63638');
			return;
		}
		$fb.text('').css('color', '');

		ajax('ablf_add_allowlist', { pattern: pattern, pattern_type: type, note: note }).done(function(resp) {
			if (!resp || !resp.success) {
				$fb.text((resp && resp.data && resp.data.message) ? resp.data.message : ABLF.i18n.error).css('color', '#d63638');
				return;
			}
			var d  = resp.data;
			var now = new Date();
			var mon = now.toLocaleString('default', { month: 'short' });
			var dateStr = mon + ' ' + now.getDate() + ', ' + now.getFullYear();

			// Remove empty-state row if present.
			$('#ablf-allowlist-tbody .ablf-allowlist-empty').remove();

			var row = '<tr class="ablf-allowlist-row" data-id="' + d.id + '">' +
				'<td><code>' + $('<span>').text(d.pattern).html() + '</code></td>' +
				'<td>' + (d.pattern_type === 'domain' ? 'Domain' : 'URL') + '</td>' +
				'<td>' + $('<span>').text(d.note).html() + '</td>' +
				'<td>' + dateStr + '</td>' +
				'<td><button type="button" class="button ablf-allowlist-remove" data-id="' + d.id + '">Remove</button></td>' +
				'</tr>';
			$('#ablf-allowlist-tbody').prepend(row);

			$('#ablf-allowlist-pattern').val('');
			$('#ablf-allowlist-note').val('');
			updateTabCount('allowlist', 1);
			window.ablf_show_toast('Added to allowlist.', 'success');
		}).fail(function() {
			$fb.text(ABLF.i18n.error).css('color', '#d63638');
		});
	});

	/* ---------- Allowlist tab: remove entry ---------- */
	$(document).on('click', '.ablf-allowlist-remove', function() {
		var id   = $(this).data('id');
		var $row = $('.ablf-allowlist-row[data-id="' + id + '"]');
		ajax('ablf_remove_allowlist', { id: id }).done(function(resp) {
			if (!resp || !resp.success) return;
			$row.fadeOut(300, function() {
				$(this).remove();
				if ($('#ablf-allowlist-tbody .ablf-allowlist-row').length === 0) {
					$('#ablf-allowlist-tbody').html(
						'<tr class="ablf-allowlist-empty"><td colspan="5">No allowlist entries yet. Add URLs or domains to prevent them from being flagged as broken.</td></tr>'
					);
				}
			});
			updateTabCount('allowlist', -1);
		});
	});

})(jQuery);
