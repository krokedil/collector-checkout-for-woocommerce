jQuery(function ($) {
	var walleyAdmin = {
		init: function () {
			$('body').on('click', '.sync-btn-walley', walleyAdmin.syncOrderBtn );

		},

		syncOrderBtn:function(e) {
			e.preventDefault();
			$('.sync-btn-walley').addClass( 'disabled' );
			$('.sync-btn-walley').prop('disabled', true);
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
						$('.sync-btn-walley').prop('disabled', false);
						$('.walley_sync_wrapper').append( '<div><i>' + data.data + '</i></div>' );
						alert( data.data );
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