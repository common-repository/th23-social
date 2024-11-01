jQuery(document).ready(function($){

	// handle changes of screen options
	$('#th23-social-screen-options input').change(function() {
		var data = {
			action: 'th23_social_screen_options',
			nonce: $('#th23-social-screen-options-nonce').val(),
		};
		// add screen option fields to data dynamically
		$('#th23-social-screen-options input').each(function() {
			if($(this).attr('type') == 'checkbox') {
				var value = $(this).is(':checked');
			}
			else {
				var value = $(this).val();
			}
			if(typeof $(this).attr('name') != 'undefined') {
				data[$(this).attr('name')] = value;
			}
		});
		// saving user preference
		$.post(ajaxurl, data, function() {});
		// change live classes
		var classBase = $(this).attr('data-class');
		var classAdd = '';
		if($(this).attr('type') == 'checkbox') {
			if($(this).is(':checked')) {
				classAdd = classBase;
			}
		}
		else {
			classAdd = classBase + '-' + $(this).val().split(' ').join('_');
		}
		$("#th23-social-options").removeClass(function(index, className) {
			var regex = new RegExp('(^|\\s)' + classBase + '.*?(\\s|$)', 'g');
			return (className.match(regex) || []).join(' ');
		}).addClass(classAdd);
	});

	// handle show/hide of children options (up to 2 child levels deep)
	$('#th23-social-options input[data-childs]').change(function() {
		if($(this).attr('checked') == 'checked') {
			// loop through childs as selectors, for all that contain inputs with data-childs attribute, show this childs, if parent input is checked - and finally show ourselves as well
			$($(this).attr('data-childs')).each(function() {
				if($('input[data-childs]', this).attr('checked')) {
					$($('input[data-childs]', this).attr('data-childs')).show();
				}
			}).show();
		}
		else {
			// loop through childs as selectors, for all that contain inputs with data-childs attribute, hide this childs - and finally ourselves as well
			$($(this).attr('data-childs')).each(function() {
				$($('input[data-childs]', this).attr('data-childs')).hide();
			}).hide();
		}
	});

	// remove any "disabled" attributes from settings before submitting - to fetch/ perserve values
	$('.th23-social-options-submit').click(function() {
		$('#th23-social-options input[name="th23-social-options-do"]').val('submit');
		$('#th23-social-options :input').removeProp('disabled');
		$('#th23-social-options').submit();
	});

	// handle option template functionality - adding/ removing user defined lines
	$('#th23-social-options button[id^=template-add-]').click(function() {
		var option = $(this).val();
		// create "random" id based on microtime
		var id = 'm' + Date.now();
		// clone from template row, change ids and insert above invisible template row
		var row = $('#' + option + '-template').clone(true, true).attr('id', option + '-' + id);
		$('input', row).each(function(){
			$(this).attr('id', $(this).attr('id').replace('_template', '_' + id));
			$(this).attr('name', $(this).attr('name').replace('_template', '_' + id));
		});
		$('#template-remove-' + option + '-template', row).attr('id', 'template-remove-' + option + '-' + id).attr('data-element', id);
		$('#' + option + '-template').before(row);
		// add element to elements field
		var elements = $('#input_' + option + '_elements');
		elements.val(elements.val() + ',' + id);
	});
	$('#th23-social-options button[id^=template-remove-]').click(function() {
		var option = $(this).val();
		var id = $(this).attr('data-element');
		// remove row
		$('#' + option + '-' + id).remove();
		// remove element from elements field
		var elements = $('#input_' + option + '_elements');
		elements.val(elements.val().replace(',' + id, ''));
	});

	// toggle show / hide eg for longer descriptions
	// usage: <a href="" class="toggle-switch">switch</a><span class="toggle-show-hide" style="display: none;">show / hide</span>
	$('#th23-social-options .toggle-switch').click(function(e) {
		$(this).blur().next('.toggle-show-hide').toggle();
		e.preventDefault();
	});

	// handle professional extension upload
	$('#th23-social-pro-file').on('change', function(e) {
		$('#th23-social-options-submit').click();
	});

	// == customization: from here on plugin specific ==

	// crop - re-load social image thumbnails after cropping modal closes
	$('body').on('cropThumbnailModalClosed', function() {
		CROP_THUMBNAILS_DO_CACHE_BREAK($('.th23-social-image-preview'));
		$('.th23-social-message').remove();
	});

	// get entry_id required for add / change / remove social image actions
	var entry_id = $('#th23-social-image-container').data('entry');

	// add / change social image - via customized media modal
	$('#th23-social-image-container').on('click', '.th23-social-image-add, .th23-social-image-change', function(e) {
		e.preventDefault();
		if(!social_frame) {
			// setup default customized media modal - only showing images
			var social_frame = new wp.media.view.MediaFrame.Select({
				title: th23_social_js.social_image,
				multiple: false,
				library: {
					type: 'image',
				},
				button: {
					text: th23_social_js.save_social_image,
				},
			});
			// pre-select a previously chosen image upon opening the modal
			social_frame.on('open', function() {
				var image_id = $('#th23-social-image').data('image');
				if(image_id) {
					var selection =  social_frame.state().get('selection');
					var attachment = wp.media.attachment(image_id);
					attachment.fetch();
					selection.add( attachment ? [ attachment ] : [] );
				}
				$('.th23-social-message').remove();
			});
			// upon user confirmation of selection trigger AJAX request for update (individual post/ pages) and replacing image HTML with new selection (all)
			social_frame.on('select', function() {
				var data = {
					action: 'th23_social_update',
					nonce: th23_social_js.nonce,
					id: entry_id,
					image: social_frame.state().get('selection').first().id,
				};
				$.post(ajaxurl, data, function(r) {
					if(r.result == 'success') {
						$('#th23-social-image-container').html(r.html);
						// note: default social image selection on plugin options page is only saved into option value upon "save" for the whole plugin options page!
						$('#input_image_default').val(data.image + ' ' + $('#th23-social-image-container .th23-social-image-preview').attr('src'));
					}
					$('.th23-social-message').remove();
					if(r.msg != '') {
						$('#th23-social-image-container').after('<div class="th23-social-message th23-social-' + r.result + '">' + r.msg + '</div>');
					}
				});
			});
		}
		social_frame.open();
	});

	/* click on default image label in options page - open image selection modal, not highlight (hidden) input field */
	$('#th23-social-options label[for="input_image_default"]').click(function(e) {
		e.preventDefault();
		$('#th23-social-image-container .th23-social-image-add, #th23-social-image-container .th23-social-image-change').click();
	});

	// remove social image - triggering AJAX request for update (individual post/ pages) and replacing image HTML with placeholder (all)
	$('#th23-social-image-container').on('click', '.th23-social-image-remove', function(e) {
		e.preventDefault();
		var data = {
			action: 'th23_social_remove',
			nonce: th23_social_js.nonce,
			id: entry_id,
		};
		$.post(ajaxurl, data, function(r) {
			if(r.result == 'success') {
				$('#th23-social-image-container').html(r.html);
				// note: default social image deletion on plugin options page is only saved into option value upon "save" for the whole plugin options page!
				$('#input_image_default').val('');
			}
			$('.th23-social-message').remove();
			if(r.msg != '') {
				$('#th23-social-image-container').after(' <div class="th23-social-message th23-social-' + r.result + '">' + r.msg + '</div>');
			}
		});
	});

});
