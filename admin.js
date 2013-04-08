(function ($) {
	if (typeof $ == "undefined") return;
	$(document).ready(function () {
		var length = edButtons.length;
		var $toolbar = $('#ed_toolbar');
		$.each(['markdown', 'gfm'], function(index, sh){
			var id = 'ed_' + sh;
			var eb = new edButton(
				id,
				sh,
				'[' + sh + ']\n',
				'[/' + sh + ']\n',
				-1
			);
			edButtons.push(eb);
		});
	});
})(jQuery)
