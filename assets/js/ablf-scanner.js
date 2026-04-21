(function($) {
	'use strict';

	var pollTimer = null;

	function ajax(action, data) {
		return $.post(ABLF.ajaxUrl, $.extend({ action: action, nonce: ABLF.nonce }, data || {}));
	}

	function renderProgress(p) {
		var $wrap = $('#ablf-scan-progress');
		if (!$wrap.length) return;
		$wrap.show();
		var percent = p.percent || 0;
		var done    = (p.done || 0) + (p.failed || 0);
		var total   = p.total || 0;
		$wrap.find('.ablf-progress-fill').css('width', percent + '%');
		if ($wrap.find('.ablf-progress-percent').length === 0) {
			$wrap.find('.ablf-progress-bar').append('<span class="ablf-progress-percent"></span>');
		}
		$wrap.find('.ablf-progress-percent').text(percent + '%');
		$wrap.find('.ablf-progress-label').text(
			'Checking ' + done + ' of ' + total + ' URLs — ' + percent + '% complete'
		);
	}

	function startPolling() {
		if (pollTimer) clearInterval(pollTimer);
		var seenWork = false; // true once we've seen at least 1 queued/processing/done item
		pollTimer = setInterval(function() {
			ajax('ablf_scan_progress').done(function(resp) {
				if (!resp || !resp.success) return;
				var p = resp.data;
				var total = (p.total || 0);
				if (total > 0) seenWork = true;
				renderProgress(p);
				// Only finish when we've seen real work AND the queue is now empty.
				if (seenWork && (p.queued || 0) === 0 && (p.processing || 0) === 0) {
					clearInterval(pollTimer);
					pollTimer = null;
					setTimeout(function() { window.location.reload(); }, 1000);
				}
			});
		}, 2000);
	}

	$(document).on('click', '#ablf-start-scan', function(e) {
		e.preventDefault();
		var $btn = $(this);
		$btn.prop('disabled', true).text(ABLF.i18n.scanning);

		// Show 0% immediately so the bar appears while cron queues up.
		renderProgress({ queued: 1, processing: 0, done: 0, failed: 0, total: 1, percent: 0 });

		ajax('ablf_start_scan').done(function(resp) {
			if (!resp || !resp.success) {
				$btn.prop('disabled', false).text('Scan Now');
				$('#ablf-scan-progress').hide();
				window.ablf_show_toast(ABLF.i18n.error, 'error');
				return;
			}
			// Don't read progress from the start response — cron hasn't run yet.
			// Just begin polling; cron will update the queue in the background.
			startPolling();
		}).fail(function() {
			$btn.prop('disabled', false).text('Scan Now');
			$('#ablf-scan-progress').hide();
			window.ablf_show_toast(ABLF.i18n.error, 'error');
		});
	});

	$(function() {
		var $wrap = $('#ablf-scan-progress');
		if ($wrap.length && $wrap.is(':visible')) {
			startPolling();
		}
	});

})(jQuery);
