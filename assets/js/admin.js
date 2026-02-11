(function($) {
	'use strict';

	var DTG = window.dtgConverter || {};

	// Tier 1 tags (will be converted to native Gutenberg blocks).
	var tier1Tags = [
		'vc_row', 'vc_row_inner', 'vc_column', 'vc_column_inner', 'vc_section',
		'vc_column_text', 'vc_custom_heading', 'vc_single_image', 'vc_btn',
		'vc_separator', 'vc_text_separator', 'vc_empty_space', 'vc_video',
		'vc_raw_html', 'vc_raw_js', 'vc_cta', 'vc_cta_button', 'vc_cta_button2',
		'vc_message', 'vc_icon', 'vc_copyright', 'vc_button', 'vc_button2',
		'mk_page_section', 'mk_fancy_title', 'mk_ornamental_title', 'mk_title_box',
		'mk_button', 'mk_button_gradient', 'mk_image', 'mk_padding_divider',
		'mk_divider', 'mk_blockquote', 'mk_custom_list', 'mk_highlight', 'mk_dropcaps'
	];

	/**
	 * Scan posts.
	 */
	$('#dtg-scan-btn').on('click', function() {
		var $btn = $(this);
		var $spinner = $('#dtg-scan-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(DTG.ajaxUrl, {
			action: 'dtg_scan_posts',
			nonce: DTG.nonce
		}, function(response) {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');

			if (!response.success) {
				alert('Scan failed: ' + (response.data || 'Unknown error'));
				return;
			}

			var data = response.data;
			$('#dtg-scan-results').show();
			$('#dtg-total-posts').text(data.total_posts);
			$('#dtg-vc-posts').text(data.posts_with_vc);
			$('#dtg-mk-posts').text(data.posts_with_mk);
			$('#dtg-converted-posts').text(data.already_converted);

			renderShortcodeInventory(data.shortcode_inventory);
			renderPostList(data.posts);
		}).fail(function() {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			alert('AJAX request failed');
		});
	});

	/**
	 * Render shortcode inventory table.
	 */
	function renderShortcodeInventory(inventory) {
		var html = '<table>';
		html += '<tr><th>Shortcode</th><th>Count</th><th>Tier</th></tr>';

		$.each(inventory, function(tag, count) {
			var isTier1 = tier1Tags.indexOf(tag) !== -1;
			var tierClass = isTier1 ? 'dtg-tag-tier1' : 'dtg-tag-tier2';
			var tierLabel = isTier1 ? 'Convert' : 'Keep as-is';

			html += '<tr>';
			html += '<td class="' + tierClass + '">[' + escHtml(tag) + ']</td>';
			html += '<td>' + count + '</td>';
			html += '<td>' + tierLabel + '</td>';
			html += '</tr>';
		});

		html += '</table>';
		$('#dtg-shortcode-inventory').html(html);
	}

	/**
	 * Render post list table.
	 */
	function renderPostList(posts) {
		var html = '<table>';
		html += '<tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Shortcodes</th><th>Converted</th><th>Action</th></tr>';

		$.each(posts, function(i, post) {
			var tags = Object.keys(post.shortcodes).join(', ');
			var convertedBadge = post.converted
				? '<span class="dtg-converted-badge">Yes</span>'
				: 'No';

			html += '<tr>';
			html += '<td>' + post.ID + '</td>';
			html += '<td>' + escHtml(post.post_title || '(no title)') + '</td>';
			html += '<td>' + escHtml(post.post_type) + '</td>';
			html += '<td>' + escHtml(post.post_status) + '</td>';
			html += '<td><small>' + escHtml(tags) + '</small></td>';
			html += '<td>' + convertedBadge + '</td>';
			html += '<td><button class="button button-small dtg-preview-post" data-id="' + post.ID + '">Preview</button></td>';
			html += '</tr>';
		});

		html += '</table>';
		$('#dtg-post-list').html(html);
	}

	/**
	 * Preview from post list button.
	 */
	$(document).on('click', '.dtg-preview-post', function() {
		var postId = $(this).data('id');
		$('#dtg-preview-post-id').val(postId);
		$('#dtg-preview-btn').trigger('click');
	});

	/**
	 * Preview conversion.
	 */
	$('#dtg-preview-btn').on('click', function() {
		var postId = $('#dtg-preview-post-id').val();
		var $spinner = $('#dtg-preview-spinner');

		if (!postId) {
			alert('Please enter a Post ID');
			return;
		}

		$spinner.addClass('is-active');

		$.post(DTG.ajaxUrl, {
			action: 'dtg_preview_conversion',
			nonce: DTG.nonce,
			post_id: postId
		}, function(response) {
			$spinner.removeClass('is-active');

			if (!response.success) {
				alert('Preview failed: ' + (response.data || 'Unknown error'));
				return;
			}

			var data = response.data;
			$('#dtg-preview-results').show();
			$('#dtg-preview-title').text('Preview: ' + data.post_title + ' (ID: ' + data.post_id + ')');
			$('#dtg-preview-original').text(data.original);
			$('#dtg-preview-converted').text(data.converted);

			// Scroll to preview.
			$('html, body').animate({
				scrollTop: $('#dtg-preview-results').offset().top - 50
			}, 300);
		}).fail(function() {
			$spinner.removeClass('is-active');
			alert('AJAX request failed');
		});
	});

	/**
	 * Run batch conversion.
	 */
	$('#dtg-convert-btn').on('click', function() {
		if (!confirm(DTG.i18n.confirm_convert)) {
			return;
		}

		var $btn = $(this);
		var $spinner = $('#dtg-convert-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$('#dtg-progress').show();
		$('#dtg-convert-log').show();
		$('#dtg-log-entries').html('');

		var totalProcessed = 0;
		var totalCount = parseInt($('#dtg-total-posts').text()) || 0;

		function processBatch(offset) {
			$.post(DTG.ajaxUrl, {
				action: 'dtg_run_batch',
				nonce: DTG.nonce,
				offset: offset,
				limit: 10
			}, function(response) {
				if (!response.success) {
					addLog('Batch failed: ' + (response.data || 'Unknown error'), 'failed');
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					return;
				}

				var data = response.data;
				totalProcessed += data.processed + data.skipped;

				// Update progress.
				var percent = totalCount > 0 ? Math.min(100, Math.round((totalProcessed / totalCount) * 100)) : 100;
				$('#dtg-progress-fill').css('width', percent + '%');
				$('#dtg-progress-text').text(totalProcessed + ' / ' + totalCount + ' posts processed (' + percent + '%)');

				// Log details.
				$.each(data.details, function(i, detail) {
					addLog(
						'[' + detail.status.toUpperCase() + '] ID ' + detail.ID + ' - ' + escHtml(detail.post_title) + ': ' + detail.message,
						detail.status
					);
				});

				// Continue if more.
				if (data.has_more) {
					processBatch(offset + 10);
				} else {
					addLog('--- Conversion complete! ---', 'success');
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$('#dtg-progress-fill').css('width', '100%');
				}
			}).fail(function() {
				addLog('AJAX request failed at offset ' + offset, 'failed');
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			});
		}

		processBatch(0);
	});

	/**
	 * Rollback single post.
	 */
	$('#dtg-rollback-single-btn').on('click', function() {
		var postId = $('#dtg-rollback-post-id').val();
		var $spinner = $('#dtg-rollback-spinner');

		if (!postId) {
			alert('Please enter a Post ID');
			return;
		}

		$spinner.addClass('is-active');

		$.post(DTG.ajaxUrl, {
			action: 'dtg_rollback_post',
			nonce: DTG.nonce,
			post_id: postId
		}, function(response) {
			$spinner.removeClass('is-active');

			var $results = $('#dtg-rollback-results');
			$results.show();

			if (response.success) {
				$results.html('<div class="notice notice-success inline"><p>' + escHtml(response.data) + '</p></div>');
			} else {
				$results.html('<div class="notice notice-error inline"><p>' + escHtml(response.data) + '</p></div>');
			}
		}).fail(function() {
			$spinner.removeClass('is-active');
			alert('AJAX request failed');
		});
	});

	/**
	 * Rollback all posts.
	 */
	$('#dtg-rollback-all-btn').on('click', function() {
		if (!confirm(DTG.i18n.confirm_rollback)) {
			return;
		}

		var $btn = $(this);
		var $spinner = $('#dtg-rollback-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(DTG.ajaxUrl, {
			action: 'dtg_rollback_all',
			nonce: DTG.nonce
		}, function(response) {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');

			var $results = $('#dtg-rollback-results');
			$results.show();

			if (response.success) {
				var data = response.data;
				$results.html(
					'<div class="notice notice-success inline"><p>Rollback complete: ' +
					data.restored + ' restored, ' + data.failed + ' failed (of ' + data.total + ' total).</p></div>'
				);
			} else {
				$results.html('<div class="notice notice-error inline"><p>Rollback failed.</p></div>');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			alert('AJAX request failed');
		});
	});

	/**
	 * Add entry to conversion log.
	 */
	function addLog(message, status) {
		var cssClass = 'dtg-log-' + (status || 'success');
		$('#dtg-log-entries').append('<div class="' + cssClass + '">' + message + '</div>');

		// Auto-scroll to bottom.
		var $log = $('#dtg-log-entries');
		$log.scrollTop($log[0].scrollHeight);
	}

	/**
	 * Escape HTML.
	 */
	function escHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

})(jQuery);
