;(function($) {
$(function(){
	_.each($('.ccm-block-type-form'),function(item){
		var _this = $(item);

		_this.find("form").validationEngine('attach', {
			promptPosition: "topLeft:130",
			onFieldFailure:function(field){
				if(field.hasClass('input-checkbox')){
					$(field).closest('label').before(field.prev());
				}
			},
			onValidationComplete: function(form, status){
				if (status === true){
					return true;
				}else{
					_.each($('.checkboxList'),function(item){
						var first = $(item).find('.formError').first();
						$(item).find('.formError').remove().end()
						.find('label').first().before(first);
					});
				}
			}
		});

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

function st_checkbox_rule(field, rules, i, options){
	var container = $(field).closest('.checkboxList');
	if($('input:checked',container).length === 0){
		options.showArrow = false;
		options.showArrowOnRadioAndCheckbox = true;
		return '一つ以上の値にチェックを入れてください';
	}
}