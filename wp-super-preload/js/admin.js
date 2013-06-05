jQuery(function($){
	function super_preload_ajax(id, mode) {
		// `WPSP` is enqueued by wp_localize_script()
		$.post(WPSP.url, {
			action: WPSP.action,
			token: WPSP.token,
			mode: mode
		}).done(function (data, textStatus, jqXHR) {
			$(id).text('0' !== data ? data : 'deactivated');
		}).fail(function (jqXHR, textStatus, errorThrown) {
			$(id).text(jqXHR.responseText);
		});
	}

	$(function () {
		// Estimate the number of pages to be preloaded
		super_preload_ajax('#preload_msg', 'no-fetch');

		// Preload now button
		$('#preload_now').click(function () {
			$('#preload_msg').text("Requesting ...");
			super_preload_ajax('#preload_msg', 'fetch-now');
		});

		// Select the event of garbage collection
		$('#select_gc_event').bind('change', function () {
			$('#super_preload_settings_synchronize_gc').val($(this).val());
		});
	});
});