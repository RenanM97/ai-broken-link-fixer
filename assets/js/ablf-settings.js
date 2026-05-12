(function($) {
	'use strict';

	// Global toast function — available to all plugin JS files.
	window.ablf_show_toast = function(message, type) {
		var toast = document.createElement('div');
		toast.className = 'ablf-toast' + (type === 'error' ? ' error' : '');
		toast.textContent = message;
		document.body.appendChild(toast);
		setTimeout(function() {
			toast.classList.add('fade-out');
			setTimeout(function() { toast.parentNode && toast.parentNode.removeChild(toast); }, 800);
		}, 3000);
	};

	// Convert any server-rendered .ablf-notice into a toast and remove from DOM.
	$(function() {
		$('.ablf-notice').each(function() {
			var text = $(this).find('p').text() || $(this).text();
			var type = $(this).hasClass('notice-error') ? 'error' : 'success';
			$(this).remove();
			window.ablf_show_toast(text.trim(), type);
		});
	});

	// Add redirect via AJAX — no page reload.
	var addForm = document.getElementById('ablf-add-redirect-form');
	if (addForm) {
		addForm.addEventListener('submit', function(e) {
			e.preventDefault();
			var from = document.getElementById('ablf-redirect-from').value.trim();
			var to   = document.getElementById('ablf-redirect-to').value.trim();
			if (!from || !to) return;
			$.post(ABLF.ajaxUrl, { action: 'ablf_add_redirect', from_url: from, to_url: to, nonce: ABLF.nonce })
				.done(function(resp) {
					if (!resp || !resp.success) {
						window.ablf_show_toast((resp && resp.data && resp.data.message) ? resp.data.message : 'Could not add redirect.', 'error');
						return;
					}
					var d = resp.data;
					var $tbody = $('.ablf-redirect-add').closest('.ablf-wrap').find('table tbody');
					// Remove empty-state row if present.
					$tbody.find('td[colspan]').closest('tr').remove();
					$tbody.append(
						'<tr>' +
						'<td class="ablf-url">' + $('<span>').text(d.from_url).html() + '</td>' +
						'<td class="ablf-url">' + $('<span>').text(d.to_url).html() + '</td>' +
						'<td>' + d.http_code + '</td>' +
						'<td>0</td>' +
						'<td>' + $('<span>').text(d.created_at).html() + '</td>' +
						'<td><button type="button" class="button-link-delete ablf-delete-redirect" data-id="' + d.id + '">Delete</button></td>' +
						'</tr>'
					);
					document.getElementById('ablf-redirect-from').value = '';
					document.getElementById('ablf-redirect-to').value   = '';
					window.ablf_show_toast('Redirect added.');
				})
				.fail(function(xhr) {
					var msg = 'Something went wrong. Status: ' + xhr.status;
					window.ablf_show_toast(msg, 'error');
				});
		});
	}

	// Delete redirect row without page refresh.
	$(document).on('click', '.ablf-delete-redirect', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var id   = $btn.data('id');
		$.post(ABLF.ajaxUrl, { action: 'ablf_delete_redirect', id: id, nonce: ABLF.nonce })
			.done(function(resp) {
				if (resp && resp.success) {
					$btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
					window.ablf_show_toast('Redirect deleted.');
				}
			});
	});

})(jQuery);
