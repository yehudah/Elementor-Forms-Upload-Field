jQuery( function( $ ) {
	elementor.hooks.addFilter( 'elementor_pro/forms/content_template/field/file', function( item, i, settings ) {
		var itemClasses = _.escape( item.css_classes ),
			required = '';

		if ( item.required ) {
			required = 'required';
			fieldGroupClasses += ' elementor-field-required';

			if ( settings.mark_required ) {
				fieldGroupClasses += ' elementor-mark-required';
			}
		}		

		return '<input size="1" type="file" class="elementor-field elementor-size-' + settings.input_size + ' ' + itemClasses + '" name="form_field_' + i + '" id="form_field_' + i + '" ' + required + ' >';
	});
});