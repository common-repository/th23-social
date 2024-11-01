( function( wp ) {

	const { __ } = wp.i18n;
	const { registerBlockType } = wp.blocks;
	const { createElement, Fragment } = wp.element;
	const { InspectorControls } = wp.editor;
	const { TextControl, SelectControl, ServerSideRender } = wp.components;
	const { getCurrentPostId } = wp.data.select("core/editor");

	// Add and handle social block to Gutenberg editor
	registerBlockType( 'th23-social/block-bar', {
	    title: 'th23 Social',
		description: __( 'Add a social bar', 'th23-social' ),
	    icon: 'share', // any WordPress dashicon - https://developer.wordpress.org/resource/dashicons/
	    category: 'common',

		edit( props ) {

			// add post ID - important for request to server side render
			props.attributes.entry_id = getCurrentPostId();

			return [

				// show block specific sidebar section
				props.isSelected &&
				createElement( InspectorControls, {}, [
					// heading
					createElement( 'h2', {}, 'Settings' ),
					// type
					createElement( SelectControl, {
						label: __('Type', 'th23-social'),
						value: props.attributes.type,
						onChange: function( value ) { props.setAttributes( { type: value } ); },
						options: [
							{ value: 'share', label: __( 'Share', 'th23-social' ) },
							{ value: 'follow', label: __( 'Follow', 'th23-social' ) },
						],
					} ),
					// claim
					createElement( TextControl, {
						label: __( 'Claim', 'th23-social' ),
						placeholder: __( 'Leave empty for default', 'th23-social' ),
						value: props.attributes.claim,
						onChange: function( value ) { props.setAttributes( { claim: value } ); },
					} ),
				] ),

				// render social bar on server - to show it, as it will look like on the frontend
				// todo / future: render client side based on template / dummy created on server side upon editor init
				createElement( ServerSideRender, {
					block: props.name,
					attributes: props.attributes,
	            } ),

			];
		},

		// save is done automatically, but the function must be present
		save() {
			return null;
		},

	} );

} ) ( window.wp );
