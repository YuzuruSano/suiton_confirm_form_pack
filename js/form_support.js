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
			_this.find('.delete').show();
			_this.find('.submit').val('確認');
			_this.find('.hidden_status').val('confirm');
			$(this).remove();

			var id = _this.attr('id');
			var position = $('#' + id).offset().top;
			//position値の増減で移動先の調整可。固定ヘッダー時の調整などに
			$('body,html').animate({scrollTop:position}, 300, 'swing');

			return false;
		});
	});

	/* ===============================================
	ファイルの削除はJSで行う
	=============================================== */
	$('input[type=file],.form_confirm_file').each(function(){
		var elem = '<button style="display:none;" class="delete" value="' + $(this).attr('id') + '">このファイルを削除</button>';
		$(this).after(elem);
	});

	check_file_input();

	$('.delete').on('click',function(){
		var this_val = $(this).val();
		var _class = $(id).attr('class');
		var id = '#' + this_val;
		$(id).val('');
		$('#' + 'hidden_name_tmp_name_'+this_val).remove();
		$('#' + 'hidden_name_'+this_val).remove();

		$(id).replaceWith('<input id="'+ $(this).val() +'" class="form-control" type="file" name="'+ $(this).val() +'">');
		$(this).hide();

		check_file_input();

		return false;
	});

	function check_file_input(){
		$('input[type=file]').change(function() {
			var file = $(this).prop('files')[0];
			if(file){
				$(this).next('.delete').show();
			}
		}).change();
	}
});
})(jQuery);
/* ===============================================
jQuery varidation engineの追加ルール
checkboxに一つ以上の値にチェックが入っているか
=============================================== */
function st_checkbox_rule(field, rules, i, options){
	var container = $(field).closest('.checkboxList');
	if($('input:checked',container).length === 0){
		options.showArrow = false;
		options.showArrowOnRadioAndCheckbox = true;
		return '一つ以上の値にチェックを入れてください';
	}
}