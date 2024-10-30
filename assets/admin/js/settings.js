jQuery(document).ready(function($){
	var bbwp_fields_wp_uploader;
 // single file uploader
	$("body").on("click", ".otw_file_upload_button", function(e){
							inputobject = $(this).parent().find("input[type='text']");
							e.preventDefault();

							// If the media frame already exists, reopen it.
							if (bbwp_fields_wp_uploader) {
									bbwp_fields_wp_uploader.open();
									return;
							}

							// Create a new media frame
							bbwp_fields_wp_uploader = wp.media.frames.file_frame = wp.media({
									title: 'Choose File',
									button: {
											text: 'Choose File'
									},
									multiple: false
							});

							//When a file is selected, grab the URL and set it as the text field's value
							bbwp_fields_wp_uploader.on('select', function() {
									attachment = bbwp_fields_wp_uploader.state().get('selection').first().toJSON();
									if(inputobject.parent().find(".otw_single_image_preview").length >= 1){
											inputobject.parent().find(".otw_single_image_preview").html('<span><img src="'+attachment.url+'" /><a href="#" class="otw_dismiss_icon">&nbsp;</a></span>');
									}
									inputobject.val(attachment.url);
							});

							//Open the uploader dialog
							bbwp_fields_wp_uploader.open();
							return false;
			});

			$("body").on("click", ".otw_single_image_preview a", function(){
				$(this).parents(".otw_single_image_preview").parent().find("input[type='text']").val("");
				$(this).parent().remove();
				return false;
			});
});