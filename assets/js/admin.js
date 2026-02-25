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
		'mk_divider', 'mk_blockquote', 'mk_custom_list', 'mk_highlight', 'mk_dropcaps',
		'mk_custom_box'
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
	 * Render post list table with checkboxes and per-post mode selection.
	 */
	function renderPostList(posts) {
		// Bulk controls toolbar.
		var html = '<div class="dtg-bulk-controls">';
		html += '<button type="button" class="button button-small" id="dtg-select-all-btn">Select All</button> ';
		html += '<button type="button" class="button button-small" id="dtg-deselect-all-btn">Deselect All</button>';
		html += '<span class="dtg-bulk-separator">|</span>';
		html += '<label for="dtg-bulk-mode">Set mode for selected: </label>';
		html += '<select id="dtg-bulk-mode" class="dtg-mode-select">';
		html += '<option value="hybrid">Hybrid</option>';
		html += '<option value="native">Native</option>';
		html += '</select> ';
		html += '<button type="button" class="button button-small" id="dtg-apply-bulk-mode">Apply</button>';
		html += '<span class="dtg-bulk-separator">|</span>';
		html += '<span id="dtg-selected-count" class="dtg-selected-count">0 selected</span>';
		html += '</div>';

		// Post table.
		html += '<table class="widefat dtg-post-table">';
		html += '<thead><tr>';
		html += '<th class="dtg-col-check"><input type="checkbox" id="dtg-select-all-check" /></th>';
		html += '<th class="dtg-col-id">ID</th>';
		html += '<th>Title</th>';
		html += '<th class="dtg-col-type">Type</th>';
		html += '<th>Shortcodes</th>';
		html += '<th class="dtg-col-status">Converted</th>';
		html += '<th class="dtg-col-mode">Mode</th>';
		html += '<th class="dtg-col-action">Action</th>';
		html += '</tr></thead><tbody>';

		$.each(posts, function(i, post) {
			var tags = Object.keys(post.shortcodes).join(', ');
			var isConverted = post.converted;
			var convertedBadge = isConverted
				? '<span class="dtg-converted-badge">Yes</span>'
				: 'No';

			var checkboxHtml = '<input type="checkbox" class="dtg-post-check" value="' + post.ID + '"';
			if (!isConverted) {
				checkboxHtml += ' checked="checked"';
			}
			checkboxHtml += ' />';

			var modeHtml;
			if (isConverted) {
				modeHtml = '<span class="dtg-mode-na">&mdash;</span>';
			} else {
				modeHtml = '<select class="dtg-post-mode dtg-mode-select" data-post-id="' + post.ID + '">';
				modeHtml += '<option value="hybrid" selected>Hybrid</option>';
				modeHtml += '<option value="native">Native</option>';
				modeHtml += '</select>';
			}

			var rowClass = isConverted ? 'dtg-row-converted' : '';

			html += '<tr class="' + rowClass + '" data-post-id="' + post.ID + '">';
			html += '<td class="dtg-col-check">' + checkboxHtml + '</td>';
			html += '<td class="dtg-col-id">' + post.ID + '</td>';
			html += '<td>' + escHtml(post.post_title || '(no title)') + '</td>';
			html += '<td class="dtg-col-type">' + escHtml(post.post_type) + '</td>';
			html += '<td><small>' + escHtml(tags) + '</small></td>';
			html += '<td class="dtg-col-status">' + convertedBadge + '</td>';
			html += '<td class="dtg-col-mode">' + modeHtml + '</td>';
			html += '<td class="dtg-col-action"><button class="button button-small dtg-preview-post" data-id="' + post.ID + '">Preview</button></td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		$('#dtg-post-list').html(html);

		// Initial count update.
		updateSelectedCount();
	}

	/**
	 * Update the selected post count display.
	 */
	function updateSelectedCount() {
		var count = $('.dtg-post-check:checked').length;
		$('#dtg-selected-count').text(count + ' selected');
	}

	/**
	 * Select All button.
	 */
	$(document).on('click', '#dtg-select-all-btn', function() {
		$('.dtg-post-check').prop('checked', true);
		$('#dtg-select-all-check').prop('checked', true);
		updateSelectedCount();
	});

	/**
	 * Deselect All button.
	 */
	$(document).on('click', '#dtg-deselect-all-btn', function() {
		$('.dtg-post-check').prop('checked', false);
		$('#dtg-select-all-check').prop('checked', false);
		updateSelectedCount();
	});

	/**
	 * Header checkbox — toggle all.
	 */
	$(document).on('change', '#dtg-select-all-check', function() {
		var isChecked = $(this).prop('checked');
		$('.dtg-post-check').prop('checked', isChecked);
		updateSelectedCount();
	});

	/**
	 * Individual checkbox change — update count and header checkbox.
	 */
	$(document).on('change', '.dtg-post-check', function() {
		updateSelectedCount();
		var totalChecks = $('.dtg-post-check').length;
		var checkedCount = $('.dtg-post-check:checked').length;
		$('#dtg-select-all-check').prop('checked', checkedCount === totalChecks);
	});

	/**
	 * Apply bulk mode to all checked posts.
	 */
	$(document).on('click', '#dtg-apply-bulk-mode', function() {
		var mode = $('#dtg-bulk-mode').val();
		$('.dtg-post-check:checked').each(function() {
			var postId = $(this).val();
			$('select.dtg-post-mode[data-post-id="' + postId + '"]').val(mode);
		});
	});

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

			// Show generated CSS if any.
			if (data.css) {
				$('#dtg-preview-css-wrap').show();
				$('#dtg-preview-css').text(data.css);
			} else {
				$('#dtg-preview-css-wrap').hide();
			}

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
	 * Run batch conversion — processes selected posts with per-post modes.
	 */
	$('#dtg-convert-btn').on('click', function() {
		// 1. Collect selected posts with their modes.
		var hybridIds = [];
		var nativeIds = [];

		$('.dtg-post-check:checked').each(function() {
			var postId = parseInt($(this).val());
			var mode = $('select.dtg-post-mode[data-post-id="' + postId + '"]').val() || 'hybrid';
			if (mode === 'hybrid') {
				hybridIds.push(postId);
			} else {
				nativeIds.push(postId);
			}
		});

		var totalSelected = hybridIds.length + nativeIds.length;

		if (totalSelected === 0) {
			alert('Please select at least one post to convert.');
			return;
		}

		var summary = 'Convert ' + totalSelected + ' post(s)?';
		if (hybridIds.length > 0) {
			summary += '\n- ' + hybridIds.length + ' in Hybrid mode';
		}
		if (nativeIds.length > 0) {
			summary += '\n- ' + nativeIds.length + ' in Native mode';
		}
		summary += '\n\nMake sure you have a database backup.';

		if (!confirm(summary)) {
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

		// Update progress bar.
		function updateProgress() {
			var percent = totalSelected > 0 ? Math.min(100, Math.round((totalProcessed / totalSelected) * 100)) : 100;
			$('#dtg-progress-fill').css('width', percent + '%');
			$('#dtg-progress-text').text(totalProcessed + ' / ' + totalSelected + ' posts processed (' + percent + '%)');
		}

		// Process a batch of post IDs with a specific mode.
		function processSelectedBatch(postIds, mode, offset, onComplete) {
			if (offset >= postIds.length) {
				onComplete();
				return;
			}

			var batch = postIds.slice(offset, offset + 10);

			$.post(DTG.ajaxUrl, {
				action: 'dtg_convert_selected_batch',
				nonce: DTG.nonce,
				post_ids: batch,
				mode: mode
			}, function(response) {
				if (!response.success) {
					var errData = response.data;
					if (errData && errData.requirements) {
						addLog('Hybrid mode requirements not met:', 'failed');
						$.each(errData.requirements, function(i, req) {
							var icon = req.status === 'ok' ? 'OK' : 'MISSING';
							var line = '[' + icon + '] ' + escHtml(req.name);
							if (req.hint) {
								line += ' — ' + escHtml(req.hint);
							}
							addLog(line, req.status === 'ok' ? 'success' : 'failed');
						});
					} else {
						addLog('Batch failed: ' + (errData && errData.message ? errData.message : errData || 'Unknown error'), 'failed');
					}
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					return;
				}

				var data = response.data;
				totalProcessed += data.processed + data.skipped;
				updateProgress();

				// Log details.
				$.each(data.details, function(i, detail) {
					addLog(
						'[' + detail.status.toUpperCase() + '] ID ' + detail.ID + ' - ' + escHtml(detail.post_title) + ': ' + detail.message,
						detail.status
					);
				});

				// Mark converted posts in the table.
				$.each(data.details, function(i, detail) {
					if (detail.status === 'success') {
						var $row = $('tr[data-post-id="' + detail.ID + '"]');
						$row.addClass('dtg-row-converted');
						$row.find('.dtg-post-check').prop('checked', false);
						$row.find('.dtg-col-status').html('<span class="dtg-converted-badge">Yes</span>');
						$row.find('.dtg-col-mode').html('<span class="dtg-mode-na">&mdash;</span>');
					}
				});
				updateSelectedCount();

				// Continue with next batch.
				processSelectedBatch(postIds, mode, offset + 10, onComplete);
			}).fail(function() {
				addLog('AJAX request failed for ' + mode + ' batch at offset ' + offset, 'failed');
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			});
		}

		// 2. Process hybrid posts first, then native.
		if (hybridIds.length > 0) {
			addLog('--- Processing ' + hybridIds.length + ' post(s) in Hybrid mode ---', 'success');
		}

		processSelectedBatch(hybridIds, 'hybrid', 0, function() {
			if (nativeIds.length > 0) {
				addLog('--- Processing ' + nativeIds.length + ' post(s) in Native mode ---', 'success');
			}

			processSelectedBatch(nativeIds, 'native', 0, function() {
				addLog('--- Conversion complete! ---', 'success');
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
				$('#dtg-progress-fill').css('width', '100%');
			});
		});
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
	 * Regenerate CSS file.
	 */
	$('#dtg-regenerate-css-btn').on('click', function() {
		var $btn = $(this);
		var $spinner = $('#dtg-css-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(DTG.ajaxUrl, {
			action: 'dtg_regenerate_css',
			nonce: DTG.nonce
		}, function(response) {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');

			var $results = $('#dtg-css-results');
			$results.show();

			if (response.success) {
				var data = response.data;
				$results.html(
					'<div class="notice notice-success inline"><p>CSS file regenerated. ' +
					data.post_count + ' posts, file size: ' + escHtml(data.file_size) + '</p></div>'
				);
			} else {
				$results.html('<div class="notice notice-error inline"><p>Failed: ' + escHtml(response.data) + '</p></div>');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			alert('AJAX request failed');
		});
	});

	/**
	 * Convert a single post.
	 */
	$('#dtg-convert-single-btn').on('click', function() {
		var postId = $('#dtg-convert-single-post-id').val();
		var $btn = $(this);
		var $spinner = $('#dtg-convert-single-spinner');
		var $results = $('#dtg-convert-single-results');
		var conversionMode = $('#dtg-convert-single-mode').val() || 'hybrid';

		if (!postId) {
			alert('Please enter a Post ID');
			return;
		}

		if (!confirm('Convert post ID ' + postId + ' using ' + conversionMode + ' mode?')) {
			return;
		}

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$results.html('<em>Converting...</em>').show();

		$.post(DTG.ajaxUrl, {
			action: 'dtg_convert_single',
			nonce: DTG.nonce,
			post_id: postId,
			mode: conversionMode
		}, function(response) {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');

			if (!response.success) {
				var errMsg = response.data;
				if (errMsg && errMsg.requirements) {
					var html = '<div class="notice notice-error inline"><p><strong>Requirements not met:</strong></p><ul>';
					$.each(errMsg.requirements, function(i, req) {
						if (req.status === 'missing') {
							html += '<li>' + escHtml(req.name) + ' — ' + escHtml(req.hint) + '</li>';
						}
					});
					html += '</ul></div>';
					$results.html(html);
				} else {
					$results.html('<div class="notice notice-error inline"><p>Failed: ' + escHtml(errMsg) + '</p></div>');
				}
				return;
			}

			var data = response.data;
			var cssInfo = data.css ? ' CSS generated: ' + data.css.length + ' chars.' : '';
			$results.html(
				'<div class="notice notice-success inline"><p>' +
				escHtml(data.message) + cssInfo +
				' <a href="' + escHtml(DTG.siteUrl || '') + '/?p=' + postId + '" target="_blank">View post</a>' +
				'</p></div>'
			);
		}).fail(function() {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			$results.html('<div class="notice notice-error inline"><p>AJAX request failed.</p></div>');
		});
	});

	/**
	 * Check hybrid requirements (preflight).
	 */
	$('#dtg-check-requirements-btn').on('click', function() {
		var $btn = $(this);
		var $results = $('#dtg-requirements-results');

		$btn.prop('disabled', true);
		$results.html('<em>Checking...</em>').show();

		$.post(DTG.ajaxUrl, {
			action: 'dtg_check_hybrid_requirements',
			nonce: DTG.nonce
		}, function(response) {
			$btn.prop('disabled', false);

			if (!response.success) {
				$results.html('<span style="color:#d63638;">Check failed.</span>');
				return;
			}

			var data = response.data;
			var html = '<table style="width:100%; border-collapse:collapse; margin-top:8px;">';

			$.each(data.requirements, function(i, req) {
				var isOk = req.status === 'ok';
				var icon = isOk ? '<span style="color:#00a32a;">&#10004;</span>' : '<span style="color:#d63638;">&#10008;</span>';
				var hint = (!isOk && req.hint) ? ' <small style="color:#666;">— ' + escHtml(req.hint) + '</small>' : '';

				html += '<tr>';
				html += '<td style="padding:3px 8px 3px 0; white-space:nowrap;">' + icon + '</td>';
				html += '<td style="padding:3px 0;">' + escHtml(req.name) + hint + '</td>';
				html += '</tr>';
			});

			html += '</table>';

			if (data.ready) {
				html += '<p style="color:#00a32a; margin:8px 0 0;"><strong>All requirements met. Ready for hybrid conversion.</strong></p>';
			} else {
				html += '<p style="color:#d63638; margin:8px 0 0;"><strong>Some requirements are missing. Please install the missing plugins first.</strong></p>';
			}

			$results.html(html);
		}).fail(function() {
			$btn.prop('disabled', false);
			$results.html('<span style="color:#d63638;">AJAX request failed.</span>');
		});
	});

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
