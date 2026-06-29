(function ($) {
	$(function () {
		var cfg = window.ppSitePulse || {};

		$('#pp-sp-preview').on('click', function () {
			var $btn = $(this).prop('disabled', true).text('Loading…');
			$('#pp-sp-test-result').text('');
			$.post(cfg.ajax_url, { action: 'pp_site_pulse_preview', nonce: cfg.nonce })
				.done(function (res) {
					if (res && res.success) {
						$('#pp-sp-preview-wrap').show();
						var frame = document.getElementById('pp-sp-preview-frame');
						frame.srcdoc = res.data.html;
					} else {
						$('#pp-sp-test-result').text((res && res.data && res.data.message) || 'Preview failed.');
					}
				})
				.fail(function () { $('#pp-sp-test-result').text('Preview request failed.'); })
				.always(function () { $btn.prop('disabled', false).text('Preview client email'); });
		});

		$('#pp-sp-send-test').on('click', function () {
			if (!window.confirm('Send a test review email to your review address now?')) { return; }
			var $btn = $(this).prop('disabled', true).text('Sending…');
			$('#pp-sp-test-result').text('');
			$.post(cfg.ajax_url, { action: 'pp_site_pulse_send_test', nonce: cfg.nonce })
				.done(function (res) {
					var msg = (res && res.data && res.data.message) || (res && res.success ? 'Sent.' : 'Failed.');
					$('#pp-sp-test-result').text(msg);
				})
				.fail(function () { $('#pp-sp-test-result').text('Send request failed.'); })
				.always(function () { $btn.prop('disabled', false).text('Send test to me'); });
		});
	});
})(jQuery);
