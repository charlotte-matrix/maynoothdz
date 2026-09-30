/**
 * Admin: media library SVG picker + region color flags.
 */
(function ($) {
	'use strict';

	function bindUploader() {
		var $btn = $('#misvm_upload_btn');
		if (!$btn.length) {
			return;
		}

		var frame;

		$btn.on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Select SVG map',
				button: { text: 'Use this SVG' },
				library: { type: ['image', 'image/svg+xml'] },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#misvm_attachment_id').val(attachment.id);
				$('#misvm_clear_btn').prop('disabled', false);

				var url = attachment.url || '';
				var name = attachment.filename || '';
				$('#misvm_preview').html(
					'<img src="' + url + '" alt="" />' +
					(name ? '<p class="description">' + name + '</p>' : '')
				);
			});

			frame.open();
		});

		$('#misvm_clear_btn').on('click', function (e) {
			e.preventDefault();
			$('#misvm_attachment_id').val('');
			$('#misvm_preview').empty();
			$(this).prop('disabled', true);
		});
	}

	function bindRegionColors() {
		$('.misvm-regions-table').on('input change', '.misvm-region-color', function () {
			var $input = $(this);
			$input.siblings('.misvm-region-color-flag').val('1');
			$input.removeAttr('data-unset');
		});

		$('.misvm-regions-table').on('click', '.misvm-reset-color', function (e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			var $color = $row.find('.misvm-region-color');
			$color.val($color.data('default'));
			$color.attr('data-unset', '1');
			$row.find('.misvm-region-color-flag').val('0');
		});
	}

	$(function () {
		bindUploader();
		bindRegionColors();
	});
})(jQuery);
