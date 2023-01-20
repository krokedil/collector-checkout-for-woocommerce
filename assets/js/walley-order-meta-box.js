jQuery(function ($) {
	var walleyAdmin = {
		init: function () {
			$('body').on('click', '.sync-btn-walley', walleyAdmin.syncOrderBtn );

		},

		syncOrderBtn:function(e) {
			e.preventDefault();
			$('.sync-btn-walley').addClass( 'disabled' );
			$.ajax({
				type: 'POST',
				data: {
					id: walleyParams.order_id,
					nonce: walleyParams.walley_reauthorize_order_nonce,
				},
				dataType: 'json',
				url: walleyParams.walley_reauthorize_order,
				success: function (data) {
					console.log(data);
					if(data.success) {
						window.location.reload();
					} else {
						$('.sync-btn-walley').removeClass( 'disabled' );
						$('.walley_sync_wrapper').append( '<div><i>' + data.data + '</i></div>' );
					}
				},
				error: function (data) {
					console.log(data);
					console.log(data.statusText);
				},
				complete: function (data) {

				}
			});
		},
	}
	walleyAdmin.init();
});