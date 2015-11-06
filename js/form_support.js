;(function($) {
$(function(){
	$.validator.addMethod('validate-checkbox-oneormore',
		function (value) {
			alert(value);
			return $('.require-one:checked').size() !== 0;
		}, 'need a value');

	$('.ccm-block-type-form').each(function(){
		var _this = $(this);
		$(this).find("form").validationEngine();
		_this.find('.backbtn').on('click',function(){
			_this.find('.form_confirm').remove();
			_this.find('.form_entity').show();
			_this.find('.submit').val('確認');
			_this.find('.hidden_status').val('confirm');
			$(this).remove();

			var id = _this.attr('id');
			var position = $('#' + id).offset().top - 120;
			$('body,html').animate({scrollTop:position}, 300, 'swing');

			return false;
		});
	});
});
})(jQuery);