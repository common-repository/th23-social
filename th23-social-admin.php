<?php
/*
th23 Social
Admin area

Copyright 2019-2020, Thorsten Hartmann (th23)
http://th23.net/
*/

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_social_admin extends th23_social_pro {

	function __construct() {

		parent::__construct();

		// Setup basics (additions for backend)
		$this->plugin['settings_base'] = 'options-general.php';
		$this->plugin['settings_handle'] = 'th23-social';
		$this->plugin['settings_permission'] = 'manage_options';
		$this->plugin['extendable'] = __('<p>Improve <strong>presentation of shared content</strong> on social services, by automatically embedding dedicated HTML tags to posts and pages.</p><p>Define a <strong>dedicated image in optimized size</strong> for each post / page, to ensure consistency and good visiblity upon shares on social media.</p>', 'th23-social');
		// icon: "square" 48 x 48px (footer) / "horizontal" 36px height (header, width irrelevant) / both (resized if larger)
		$this->plugin['icon'] = array('square' => 'img/th23-social-square.png', 'horizontal' => 'img/th23-social-horizontal.png');
		$this->plugin['extension_files'] = array('th23-social-pro.php');
		$this->plugin['download_url'] = 'https://th23.net/th23-social/';
		$this->plugin['support_url'] = 'https://th23.net/th23-social-support/';
		$this->plugin['requirement_notices'] = array();

		// Install/ uninstall
		add_action('activate_' . $this->plugin['basename'], array(&$this, 'install'));
		add_action('deactivate_' . $this->plugin['basename'], array(&$this, 'uninstall'));

		// Update
		add_action('upgrader_process_complete', array(&$this, 'pre_update'), 10, 2);
		add_action('plugins_loaded', array(&$this, 'post_update'));

		// Requirements
		add_action('plugins_loaded', array(&$this, 'requirements'));
		add_action('admin_notices', array(&$this, 'admin_notices'));

		// Modify plugin overview page
		add_filter('plugin_action_links_' . $this->plugin['basename'], array(&$this, 'settings_link'), 10);
		add_filter('plugin_row_meta', array(&$this, 'contact_link'), 10, 2);

		// Add admin page and JS/ CSS
		add_action('admin_init', array(&$this, 'register_admin_js_css'));
		add_action('admin_menu', array(&$this, 'add_admin'));
		add_action('wp_ajax_th23_social_screen_options', array(&$this, 'set_screen_options'));

		// == customization: from here on plugin specific ==

		// Provide option to modify services defaults, eg adding a (non user-deletable/ -changable) service to subscribe via th23 Subscribe plugin
		// note: services filtered in will only be saved to options upon first save in admin area
		add_action('plugins_loaded', array(&$this, 'filter_services_defaults'));

		// Protect meta values from being edited "raw" by user
		add_filter('is_protected_meta', array(&$this, 'set_protected_meta'), 10, 3);

		// Prevent auto-creation of own image size upon upload - this will be taken care of upon selection as social image
		add_filter('intermediate_image_sizes_advanced', array(&$this, 'prevent_auto_image_resizing'));

		// Load additional JS and CSS upon creating / editing posts and pages
		add_action('admin_print_scripts-post.php', array(&$this, 'load_admin_js'));
		add_action('admin_print_scripts-post-new.php', array(&$this, 'load_admin_js'));
		add_action('admin_print_styles-post.php', array(&$this, 'load_admin_css'));
		add_action('admin_print_styles-post-new.php', array(&$this, 'load_admin_css'));

		// Update social image and shares per service on edit post / page screen - via classic metabox / in Gutenberg sidebar panel
		add_action('add_meta_boxes', array(&$this, 'add_entry_meta_box'));
		add_action('save_post', array(&$this, 'save_entry_meta'));
		// Handle respective AJAX requests for the social image
		add_action('wp_ajax_th23_social_update', array(&$this, 'ajax_update_image'));
		add_action('wp_ajax_th23_social_remove', array(&$this, 'ajax_remove_image'));

		// Add Gutenberg block (social sharing bar)
		add_action('enqueue_block_editor_assets', array(&$this, 'add_gutenberg_edit'));

		// Ensure cropping thumbnails is activated on plugin option page
		add_filter('crop_thumbnails_activat_on_adminpages', function($value) {
			$screen = get_current_screen();
			return $value || $screen->id == 'settings_page_th23-social';
		});

		// Reset cached meta for raw excerpts - upon content update
		add_action('save_post', array(&$this, 'excerpt_raw_reset'));

		// Settings: Screen options
		// note: default can handle boolean, integer or string
		$this->plugin['screen_options'] = array(
			'hide_description' => array(
				'title' => __('Hide settings descriptions', 'th23-social'),
				'default' => false,
			),
		);

		// Settings: Help
		// note: use HTML formatting within content and help_sidebar text eg always wrap in "<p>", use "<a>" links, etc
		$this->plugin['help_tabs'] = array(
			'th23_social_help_overview' => array(
				'title' => __('Settings and support', 'th23-social'),
				'content' => __('<p>You can find video tutorials explaning the plugin settings for on <a href="https://www.youtube.com/channel/UCS3sNYFyxhezPVu38ESBMGA">my YouTube channel</a>.</p><p>More details and explanations are available on <a href="https://th23.net/th23-social-support/">my Frequently Asked Questions (FAQ) page</a> or the <a href="https://wordpress.org/support/plugin/th23-social/">plugin support section on WordPress.org</a>.</p>', 'th23-social'),
			),
		);
		$this->plugin['help_sidebar'] = __('<p>Support me by <a href="https://wordpress.org/support/plugin/th23-social/reviews/#new-post">leaving a review</a> or check out some of <a href="https://wordpress.org/plugins/search/th23/">my other plugins</a> <strong>:-)</strong></p>', 'th23-social');

		// Settings: Prepare (multi-line) option descriptions - Service
		$services_description = __('List of available social services to share content and follow this website', 'th23-social');
		$services_description .= '<br /><a href="" class="toggle-switch">' . __('Details about allowed URL placeholders', 'th23-social') . '</a><span class="toggle-show-hide" style="display: none;">';
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%home_url% = WordPress home URL, including proper connector for parameters', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%current_url% = Current URL visited, including proper connector for parameters', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%feed_url% = URL to RSS feed, including proper connector for parameters', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%registration_url% = URL to registration page, including proper connector for parameters', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%own_account% = Username of content author at the service (do NOT include leading @ on Twitter)', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%entry_url% = URL to current entry / post', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%entry_img% = URL to image of entry / post - either th23 Social image, defined th23 Featured image, set post thumbnail image, or first image in entry', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%entry_title% = Title of the current entry / post', 'th23-social');
		/* translators: don't translate the part within the % signs */
		$services_description .= '<br />' . __('%entry_tags% = List of hashtags assigned to current entry / post - separated by commata, without leading # character', 'th23-social') . '</span>';

		// Prepare (multi-line) option descriptions - Section description (image sizes)
		$section_description = __('It is possible to best suit the different sizes recommended by various social services with one image of 1280 x 960 pixels, keeping a 160 pixel padding around the core content.', 'th23-social');
		$section_description .= '<br />' . __('<strong>Warning</strong>: Changing this settings will force re-creation of all social images with new size - resulting in loss of any manual crops done previously!', 'th23-social');
		$section_description .= '<br /><a href="" class="toggle-switch">' . __('Detailed image about optimal size and cropping', 'th23-social') . '</a>';
		$section_description .= '<span class="toggle-show-hide" style="display: none;"><br /><img class="th23-social-image-size" src="' . esc_url($this->plugin['dir_url'] . 'img/th23-social-image-size.png') . '" /></span>';

		// Settings: Define plugin options
		// note: ensure all are at least defined in general admin module to ensure settings are kept upon updates
		$this->plugin['options'] = array(
			'services' => array( // stores all available services (filterable for other plugins, not user-editable)
				'title' => __('Social Services', 'th23-social'),
				'description' => $services_description,
				'default' => array(
					'template' => array(
						'name' => array( // eg "Facebook"
							'title' => __('Name', 'th23-social'),
							'description' => __('Required', 'th23-social'),
							'default' => '',
						),
						'css_class' => array( // eg "f"
							'title' => __('CSS class', 'th23-social'),
							'default' => '',
						),
						'order' => array(
							'title' => __('Order', 'th23-social'),
							'description' => __('Order in which the services show up (same numbers result in order as shown here)', 'th23-social'),
							'default' => 0,
							'attributes' => array(
								'class' => 'small-text',
							),
						),
						'own_account' => array( // eg "whereverwetravel"
							'title' => __('Account', 'th23-social'),
							'description' => __('Own account at external service', 'th23-social'),
							'default' => '',
						),
						'follow_url' => array( // eg "https://fb.com/" - can include placeholders in the format %placeholder%, eg %current_url% = current url of page shown, %home_url% = url of homepage/ root
							'title' => __('Follow URL', 'th23-social'),
							'default' => '',
						),
						'follow_active' => array( // enable/disable service to be followable on the frontend
							'title' => __('Followable', 'th23-social'),
							'description' => __('Requires Follow URL', 'th23-social'),
							'element' => 'checkbox',
							'default' => array(
								'single' => 0,
								0 => '',
								1 => ' ', // note: not empty to show this checkbox option
							),
						),
						'follow_count' => array( // stores count of followers at this service
							'title' => __('Current followers', 'th23-social'),
							'description' => __('Option to manually set the current number of followers - it is NOT advisable to change this unless you are sure what you are doing', 'th23-social'),
							'default' => 0,
						),
						'share_url' => array( // eg "https://fb.com/" - can include placeholders in the format %placeholder%, eg %current_url% = current url of page shown, %home_url% = url of homepage/ root
							'title' => __('Share URL', 'th23-social'),
							'default' => '',
						),
						'share_active' => array( // enable/disable possibility to share entries on this service via the frontend
							'title' => __('Shareable', 'th23-social'),
							'description' => __('Requires Share URL', 'th23-social'),
							'element' => 'checkbox',
							'default' => array(
								'single' => 0,
								0 => '',
								1 => ' ', // note: not empty to show this checkbox option
							),
						),
					),
					// Default services - for pre-fills by service (not changable by user) see $this->plugin['presets'] below
					// Facebook
					'facebook' => array(
						'css_class' => 'f',
						'follow_url' => 'https://www.facebook.com/%own_account%',
						'share_url' => 'https://www.facebook.com/sharer.php?u=%entry_url%',
					),
					// Twitter
					'twitter' => array(
						'css_class' => 't',
						'follow_url' => 'https://twitter.com/%own_account%',
						'share_url' => 'https://twitter.com/share?url=%entry_url%&text=%entry_title%&via=%own_account%&hashtags=%entry_tags%',
					),
					// LinkedIn
					'linkedin' => array(
						'css_class' => 'l',
						'follow_url' => 'https://www.linkedin.com/in/%own_account%',
						'share_url' => 'https://www.linkedin.com/shareArticle?url=%entry_url%&title=%entry_title%',
					),
					// Xing
					'xing' => array(
						'css_class' => 'x',
						'follow_url' => 'https://www.xing.com/profile/%own_account%',
						'share_url' => 'https://www.xing.com/spi/shares/new?url=%entry_url%&follow_url=%own_account%',
					),
					// Instagram
					'instagram' => array(
						'css_class' => 'i',
						'follow_url' => 'https://www.instagram.com/%own_account%',
						'share_url' => '',
					),
					// Pinterest - currently not using &is_video=%is_video% in URL with option for videos
					'pinterest' => array(
						'css_class' => 'p',
						'follow_url' => 'https://www.pinterest.com/%own_account%',
						'share_url' => 'https://pinterest.com/pin/create/bookmarklet/?media=%entry_img%&url=%entry_url%&description=%entry_title%',
					),
					// RSS
					'rss' => array(
						'css_class' => 'r',
						'own_account' => '',
						'follow_url' => '%feed_url%',
						'share_url' => '',
					),
					// Possible additional services - note: currently no replacement for %entry_desc% implemented on frontend
					/* Share URLs - https://github.com/bradvin/social-share-urls
					* WhatsApp	https://wa.me/?text=[entry_title] [entry_url]
					* Tumblr	https://www.tumblr.com/share/link?url=[entry_url]&name=[entry_title]&description=[entry_desc]
					* Wordpress	https://wordpress.com/press-this.php?u=[entry_url]&t=[entry_title]&s=[entry_desc]&i=[entry_img]
					*/
				),
				'extendable' => true,
			),
			'follow_claim' => array(
				'section' => __('Follow Options', 'th23-social'),
				'title' => __('Follow claim', 'th23-social'),
				'description' => __('Short sentence shown above follow buttons inserted by shortcodes, blocks or content insert - for widgets specify in each widgets settings', 'th23-social'),
				'default' => __('Don\'t miss any updates! <strong>Follow us</strong> via', 'th23-social'),
				'attributes' => array(
					'class' => 'large-text',
				),
			),
			'follow_shortcodes' => array(
				'title' => __('Follow shortcodes', 'th23-social'),
				'description' => __('Shortcodes to replace by social follow bar - separate multiple ones by spaces', 'th23-social'),
				'default' => '[th23-social-follow]',
				'attributes' => array(
					'class' => 'large-text',
				),
			),
			'follow_insert_content' => array(
				'title' => __('Content insert', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Insert follow bar automatically after content in each entry', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-follow_insert_content_limit,.option-follow_insert_content_shortcodes',
				),
			),
			'follow_insert_content_limit' => array(
				'title' => __('Limit content types', 'th23-social'),
				'description' => __('Singles inserts upon displaying a single post or page, but not on lists like archives - single post is similar, but does not insert on pages', 'th23-social'),
				'element' => 'dropdown',
				'default' => array(
					'single' => 'always',
					'always' => __('No limitation - always insert', 'th23-social'),
					'singles' => __('Only insert on singles', 'th23-social'),
					'single_posts' => __('Only insert on single posts', 'th23-social'),
				),
			),
			'follow_insert_content_shortcodes' => array(
				'title' => __('Limit shortcodes / blocks', 'th23-social'),
				'description' => __('Disabling shortcodes and blocks prevents them from being rendered, regardless of limit content type setting', 'th23-social'),
				'element' => 'dropdown',
				'default' => array(
					'single' => 'always',
					'always' => __('Insert regardless of previous follow shortcodes and blocks', 'th23-social'),
					'only_once' => __('Only insert, if not inserted via follow shortcode or block before', 'th23-social'),
					'only_insert' => __('Insert and disable follow shortcodes and blocks', 'th23-social'),
				),
			),
			'follow_insert_lists' => array(
				'title' => __('List insert', 'th23-social'),
				'description' => __('Lists are the homepage, archives and search results - but not feeds', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Insert follow bar automatically between entries in lists', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-follow_insert_lists_entries',
				),
			),
			'follow_insert_lists_entries' => array(
				'title' => __('Frequency', 'th23-social'),
				'description' => __('Number of entries after which a follow bar is inserted, if another entry is following', 'th23-social'),
				'default' => 4,
				/* translators: part of "Insert every x entries" where "x" is user input in an input field */
				'unit' => __('every x entries', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'follow_show_total' => array(
				'title' => __('Total followers', 'th23-social'),
				'description' => __('Sum across all services', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 1,
					0 => '',
					1 => __('Show total number of followers', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-follow_show_total_min,.option-follow_total_count',
				),
			),
			'follow_show_total_min' => array(
				'title' => __('Minimum to show', 'th23-social'),
				'description' => __('Show total follower count only, if minimum amount of followers is reached', 'th23-social'),
				'default' => 5,
				/* translators: after user input field, explaining content "Minimum x followers in total to show" */
				'unit' => __('followers in total', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'follow_total_count' => array(
				'title' => __('Current total followers', 'th23-social'),
				'description' => __('Option to manually set the current number of total followers - it is NOT advisable to change this unless you are sure what you are doing', 'th23-social'),
				'default' => 0,
			),
			'follow_show_per_service' => array(
				'title' => __('Followers per service', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Show follower count per service', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-follow_show_per_service_min',
				),
			),
			'follow_show_per_service_min' => array(
				'title' => __('Minimum to show', 'th23-social'),
				'description' => __('Show follower count per service only, if minimum amount of followers is reached', 'th23-social'),
				'default' => 5,
				/* translators: after user input field, explaining content "Minimum x followers per service to show" */
				'unit' => __('followers per service', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'share_claim' => array(
				'section' => __('Share Options', 'th23-social'),
				'title' => __('Share claim', 'th23-social'),
				'description' => __('Short sentence shown above share buttons inserted by shortcodes, blocks or content insert - for widgets specify in each widgets settings', 'th23-social'),
				'default' => __('Do you enjoy this post? <strong>Share it</strong> with others via', 'th23-social'),
				'attributes' => array(
					'class' => 'large-text',
				),
			),
			'share_shortcodes' => array(
				'title' => __('Share shortcodes', 'th23-social'),
				'description' => __('Shortcodes to replace by social sharing bar - separate multiple ones by spaces', 'th23-social'),
				'default' => '[th23-social-share]',
				'attributes' => array(
					'class' => 'large-text',
				),
			),
			'share_insert_content' => array(
				'title' => __('Content insert', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Insert share bar automatically after content in each entry', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-share_insert_content_limit,.option-share_insert_content_shortcodes',
				),
			),
			'share_insert_content_limit' => array(
				'title' => __('Limit content types', 'th23-social'),
				'description' => __('Singles inserts upon displaying a single post or page, but not on lists like archives - single post is similar, but does not insert on pages', 'th23-social'),
				'element' => 'dropdown',
				'default' => array(
					'single' => 'always',
					'always' => __('No limitation - always insert', 'th23-social'),
					'singles' => __('Only insert on singles', 'th23-social'),
					'single_posts' => __('Only insert on single posts', 'th23-social'),
				),
			),
			'share_insert_content_shortcodes' => array(
				'title' => __('Limit shortcodes / blocks', 'th23-social'),
				'description' => __('Disabling shortcodes and blocks prevents them from being rendered, regardless of limit content type setting', 'th23-social'),
				'element' => 'dropdown',
				'default' => array(
					'single' => 'always',
					'always' => __('Insert regardless of previous share shortcodes and blocks', 'th23-social'),
					'only_once' => __('Only insert, if not inserted via share shortcode or block before', 'th23-social'),
					'only_insert' => __('Insert and disable share shortcodes and blocks', 'th23-social'),
				),
			),
			'share_show_total' => array(
				'title' => __('Total shares', 'th23-social'),
				'description' => __('Sum across all services', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 1,
					0 => '',
					1 => __('Show total number of shares', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-share_show_total_min',
				),
			),
			'share_show_total_min' => array(
				'title' => __('Minimum to show', 'th23-social'),
				'description' => __('Show total share count only, if minimum amount of shares is reached', 'th23-social'),
				'default' => 5,
				/* translators: after user input field, explaining content "Minimum x shares in total to show" */
				'unit' => __('shares in total', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'share_show_per_service' => array(
				'title' => __('Shares per service', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Show share count per service', 'th23-social'),
				),
				'attributes' => array(
					'data-childs' => '.option-share_show_per_service_min',
				),
			),
			'share_show_per_service_min' => array(
				'title' => __('Minimum to show', 'th23-social'),
				'description' => __('Show share count per service only, if minimum amount of shares is reached', 'th23-social'),
				'default' => 5,
				/* translators: after user input field, explaining content "Minimum x shares per service to show" */
				'unit' => __('shares per service', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'image_width' => array(
				'section' => __('Social Image', 'th23-social'),
				'section_description' => $section_description,
				'title' => __('Width', 'th23-social'),
				'description' => __('Width of image promoted to social services eg upon sharing, in pixels', 'th23-social'),
				'default' => 1280,
				/* translators: "px" unit symbol / shortcut for pixels eg after input field */
				'unit' => __('px', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'image_height' => array(
				'title' => __('Height', 'th23-social'),
				'description' => __('Height of image promoted to social services eg upon sharing, in pixels', 'th23-social'),
				'default' => 960,
				/* translators: "px" unit symbol / shortcut for pixels eg after input field */
				'unit' => __('px', 'th23-social'),
				'attributes' => array(
					'class' => 'small-text',
				),
			),
			'image_default' => array(
				'title' => __('Default', 'th23-social'),
				'description' => __('Selected default image will be promoted to social services on overview pages and individual posts / pages without a specific social image defined', 'th23-social'),
				'render' => 'option_image_default',
				'default' => '',
				'element' => 'hidden',
			),
			'cache_reset' => array(
				'section' => __('Cache', 'th23-social'),
				'title' => __('Reset auto-excerpts', 'th23-social'),
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Delete cached auto-excerpts for entries - will be recreated automatically, when needed', 'th23-social'),
				),
			),
		);

		// Settings: Define presets of social services (changable by user, eg names used on the frontend)
		$this->plugin['presets'] = array(
			'services' => array(
				'facebook' => array(
					'name' => 'facebook',
				),
				'twitter' => array(
					'name' => 'Twitter',
				),
				'linkedin' => array(
					'name' => 'LinkedIn',
				),
				'xing' => array(
					'name' => 'Xing',
				),
				'instagram' => array(
					'name' => 'Instagram',
				),
				'pinterest' => array(
					'name' => 'Pinterest',
				),
				'rss' => array(
					'name' => 'RSS',
				),
			),
		);

	}

	// Ensure PHP <5 compatibility
	function th23_social_admin() {
		self::__construct();
	}

	// Plugin versions
	// Note: Any CSS styling needs to be "hardcoded" here as plugin CSS might not be loaded (e.g. on plugin overview page)
	function plugin_professional($highlight = false) {
		$title = '<i>Professional</i>';
		return ($highlight) ? '<span style="font-weight: bold; color: #336600;">' . $title . '</span>' : $title;
	}
	function plugin_basic() {
		return '<i>Basic</i>';
	}
	function plugin_upgrade($highlight = false) {
		/* translators: "Professional" as name of the version */
		$title = sprintf(__('Upgrade to %s version', 'th23-social'), $this->plugin_professional());
		return ($highlight) ? '<span style="font-weight: bold; color: #CC3333;">' . $title . '</span>' : $title;
	}

	// Get validated plugin options
	function get_options($options = array(), $html_input = false) {
		$checked_options = array();
		foreach($this->plugin['options'] as $option => $option_details) {
			$default = $option_details['default'];
			// default array can be template or allowing multiple inputs
			$default_value = $default;
			$type = '';
			if(is_array($default)) {
				$default_value = reset($default);
				$type = key($default);
			}

			// if we have a template, pass all values for each element through the check against the template defaults
			if($type == 'template') {
				unset($default['template']);
				// create complete list of all elements - those from previous settings (re-activation), overruled by (most recent) defaults and merged with any possible user input
				$elements = array_keys($default);
				if($html_input && !empty($option_details['extendable']) && !empty($_POST['input_' . $option . '_elements'])) {
					$elements = array_merge($elements, explode(',', $_POST['input_' . $option . '_elements']));
				}
				else {
					$elements = array_merge(array_keys($options[$option]), $elements);
				}
				$elements = array_unique($elements);
				// loop through all elements - and validate previous / user values
				$checked_options[$option] = array();
				$sort_elements = array();
				foreach($elements as $element) {
					$checked_options[$option][$element] = array();
					// loop through all (sub-)options
					foreach($default_value as $sub_option => $sub_option_details) {
						$sub_default = $sub_option_details['default'];
						$sub_default_value = $sub_default;
						$sub_type = '';
						if(is_array($sub_default)) {
							$sub_default_value = reset($sub_default);
							$sub_type = key($sub_default);
						}
						unset($value);
						// force pre-set options for elements given in default
						if(isset($default[$element][$sub_option])) {
							$value = $default[$element][$sub_option];
						}
						// html input
						elseif($html_input) {
							if(isset($_POST['input_' . $option . '_' . $element . '_' . $sub_option])) {
								// if only single value allowed, only take first element from value array for validation
								if($sub_type == 'single' && is_array($_POST['input_' . $option . '_' . $element . '_' . $sub_option])) {
									$value = reset($_POST['input_' . $option . '_' . $element . '_' . $sub_option]);
								}
								else {
									$value = stripslashes($_POST['input_' . $option . '_' . $element . '_' . $sub_option]);
								}
							}
							// avoid empty items filled with default - will be filled with default in case empty/0 is not allowed for single by validation
							elseif($sub_type == 'multiple') {
								$value = array();
							}
							elseif($sub_type == 'single') {
								$value = '';
							}
						}
						// previous value
						elseif(isset($options[$option][$element][$sub_option])) {
							$value = $options[$option][$element][$sub_option];
						}
						// in case no value is given, take default
						if(!isset($value)) {
							$value = $sub_default_value;
						}
						// verify and store value
						$value = $this->get_valid_option($sub_default, $value);
						$checked_options[$option][$element][$sub_option] = $value;
						// prepare sorting
						if($sub_option == 'order') {
							$sort_elements[$element] = $value;
						}
					}
				}
				// sort verified elements according to order field (after validation to sort along valid order values)
				if(isset($default_value['order'])) {
					asort($sort_elements);
					$sorted_elements = array();
					foreach($sort_elements as $element => $null) {
						$sorted_elements[$element] = $checked_options[$option][$element];
					}
					$checked_options[$option] = $sorted_elements;
				}
			}
			// normal input fields
			else {
				unset($value);
				// html input
				if($html_input) {
					if(isset($_POST['input_' . $option])) {
						// if only single value allowed, only take first element from value array for validation
						if($type == 'single' && is_array($_POST['input_' . $option])) {
							$value = reset($_POST['input_' . $option]);
						}
						elseif($type == 'multiple' && is_array($_POST['input_' . $option])) {
							$value = array();
							foreach($_POST['input_' . $option] as $key => $val) {
								$value[$key] = stripslashes($val);
							}
						}
						else {
							$value = stripslashes($_POST['input_' . $option]);
						}
					}
					// avoid empty items filled with default - will be filled with default in case empty/0 is not allowed for single by validation
					elseif($type == 'multiple') {
						$value = array();
					}
					elseif($type == 'single') {
						$value = '';
					}
				}
				// previous value
				elseif(isset($options[$option])) {
					$value = $options[$option];
				}
				// in case no value is given, take default
				if(!isset($value)) {
					$value = $default_value;
				}
				// check value defined by user
				$checked_options[$option] = $this->get_valid_option($default, $value);
			}
		}
		return $checked_options;
	}

	// Validate / type match value against default
	function get_valid_option($default, $value) {
		if(is_array($default)) {
			$default_value = reset($default);
			$type = key($default);
			unset($default[$type]);
			if($type == 'multiple') {
				// note: multiple selections / checkboxes can be empty
				$valid_value = array();
				foreach($value as $selected) {
					// force allowed type - determined by first default element / no mixed types allowed
					if(gettype($default_value[0]) != gettype($selected)) {
						settype($selected, gettype($default_value[0]));
					}
					// check against allowed values - including type check
					if(isset($default[$selected])) {
						$valid_value[] = $selected;
					}
				}
			}
			else {
				// force allowed type - determined default value / no mixed types allowed
				if(gettype($default_value) != gettype($value)) {
					settype($value, gettype($default_value));
				}
				// check against allowed values
				if(isset($default[$value])) {
					$valid_value = $value;
				}
				// single selections (radio buttons, dropdowns) should have a valid value
				else {
					$valid_value = $default_value;
				}
			}
		}
		else {
			// force allowed type - determined default value
			if(gettype($default) != gettype($value)) {
				settype($value, gettype($default));
			}
			$valid_value = $value;
		}
		return $valid_value;
	}

	// Install
	function install() {
		// Prefill values in an option template, keeping them user editable (and therefore not specified in the default value itself)
		// need to check, if items exist(ed) before and can be reused - so we dont' overwrite them (see uninstall with delete_option inactive)
		if(isset($this->plugin['presets'])) {
			if(!isset($this->options) || !is_array($this->options)) {
				$this->options = array();
			}
			$this->options = array_merge($this->plugin['presets'], $this->options);
		}
		// Set option values
		update_option('th23_social_options', $this->get_options($this->options));
		$this->options = (array) get_option('th23_social_options');
	}

	// Uninstall
	function uninstall() {

		// NOTICE: To keep all settings etc in case the plugin is reactivated, return right away - if you want to remove previous settings and data, comment out the following line!
		return;

		// Delete option values
		delete_option('th23_social_options');

	}

	// Update - store previous version before plugin is updated
	// note: this function is still run by the old version of the plugin, ie before the update
	function pre_update($upgrader_object, $options) {
		if('update' == $options['action'] && 'plugin' == $options['type'] && !empty($options['plugins']) && is_array($options['plugins']) && in_array($this->plugin['basename'], $options['plugins'])) {
			set_transient('th23_social_update', $this->plugin['version']);
			if(!empty($this->plugin['pro'])) {
				set_transient('th23_social_update_pro', $this->plugin['pro']);
			}
		}
	}

	// Update - check for previous update and trigger requird actions
	function post_update() {

		// previous Professional extension - remind to update/re-upload
		if(!empty(get_transient('th23_social_update_pro')) && empty($this->plugin['pro'])) {
			add_action('th23_social_requirements', array(&$this, 'post_update_missing_pro'));
		}

		if(empty($previous = get_transient('th23_social_update'))) {
			return;
		}

		/* execute required update actions, optionally depending on previously installed version
		if(version_compare($previous, '1.2.0', '<')) {
			// action required
		}
		*/

		// upon successful update, delete transient (update only executed once)
		delete_transient('th23_social_update');

	}
	// previous Professional extension - remind to update/re-upload
	function post_update_missing_pro($context) {
		if('plugin_settings' == $context) {
			$missing = '<label for="th23-social-pro-file"><strong>' . __('Upload Professional extension?', 'th23-social') . '</strong></label>';
		}
		else {
			$missing = '<a href="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '"><strong>' . __('Go to plugin settings page for upload...', 'th23-social') . '</strong></a>';
		}
		/* translators: 1: "Professional" as name of the version, 2: link to "th23.net" plugin download page, 3: link to "Go to plugin settings page to upload..." page or "Upload updated Professional extension?" link */
		$notice = sprintf(__('Due to an update the previously installed %1$s extension is missing. Please get the latest version of the %1$s extension from %2$s. %3$s', 'th23-social'), $this->plugin_professional(), '<a href="' . esc_url($this->plugin['download_url']) . '" target="_blank">th23.net</a>', $missing);
		$this->plugin['requirement_notices']['missing_pro'] = '<strong>' . __('Error', 'th23-social') . '</strong>: ' . $notice;
	}

	// Requirements - checks
	function requirements() {

		// check requirements only on relevant admin pages
		global $pagenow;
		if(empty($pagenow)) {
			return;
		}
		if('index.php' == $pagenow) {
			// admin dashboard
			$context = 'admin_index';
		}
		elseif('plugins.php' == $pagenow) {
			// plugins overview page
			$context = 'plugins_overview';
		}
		elseif($this->plugin['settings_base'] == $pagenow && !empty($_GET['page']) && $this->plugin['settings_handle'] == $_GET['page']) {
			// plugin settings page
			$context = 'plugin_settings';
		}
		else {
			return;
		}

		// Check - plugin not designed for multisite setup
		if(is_multisite()) {
			$this->plugin['requirement_notices']['multisite'] = '<strong>' . __('Warning', 'th23-social') . '</strong>: ' . __('Your are running a multisite installation - the plugin is not designed for this setup and therefore might not work properly', 'th23-social');
		}

		// allow further checks by Professional extension (without re-assessing $context)
		do_action('th23_social_requirements', $context);

	}

	// Requirements - show requirement notices on admin dashboard
	function admin_notices() {
		global $pagenow;
		if(!empty($pagenow) && 'index.php' == $pagenow && !empty($this->plugin['requirement_notices'])) {
			echo '<div class="notice notice-error">';
			echo '<p style="font-size: 14px;"><strong>' . $this->plugin['data']['Name'] . '</strong></p>';
			foreach($this->plugin['requirement_notices'] as $notice) {
				echo '<p>' . $notice . '</p>';
			}
			echo '</div>';
		}
	}

	// Add settings link to plugin actions in plugin overview page
	function settings_link($links) {
		$links['settings'] = '<a href="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '">' . __('Settings', 'th23-social') . '</a>';
		return $links;
	}

	// Add supporting information (eg links and notices) to plugin row in plugin overview page
	// Note: Any CSS styling needs to be "hardcoded" here as plugin CSS might not be loaded (e.g. when plugin deactivated)
	function contact_link($links, $file) {
		if($this->plugin['basename'] == $file) {
			// Use internal version number and expand version details
			if(!empty($this->plugin['pro'])) {
				/* translators: parses in plugin version number (optionally) together with upgrade link */
				$links[0] = sprintf(__('Version %s', 'th23-social'), $this->plugin['version']) . ' ' . $this->plugin_professional(true);
			}
			elseif(!empty($this->plugin['extendable'])) {
				/* translators: parses in plugin version number (optionally) together with upgrade link */
				$links[0] = sprintf(__('Version %s', 'th23-social'), $this->plugin['version']) . ' ' . $this->plugin_basic() . ((empty($this->plugin['requirement_notices']) && !empty($this->plugin['download_url'])) ? ' - <a href="' . esc_url($this->plugin['download_url']) . '">' . $this->plugin_upgrade(true) . '</a>' : '');
			}
			// Add support link
			if(!empty($this->plugin['support_url'])) {
				$links[] = '<a href="' . esc_url($this->plugin['support_url']) . '">' . __('Support', 'th23-social') . '</a>';
			}
			// Show warning, if installation requirements are not met - add it after/ to last link
			if(!empty($this->plugin['requirement_notices'])) {
				$notices = '';
				foreach($this->plugin['requirement_notices'] as $notice) {
					$notices .= '<div style="margin: 1em 0; padding: 5px 10px; background-color: #FFFFFF; border-left: 4px solid #DD3D36; box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);">' . $notice . '</div>';
				}
				$last = array_pop($links);
				$links[] = $last . $notices;
			}
		}
		return $links;
	}

	// Register admin JS and CSS
	function register_admin_js_css() {
		wp_register_script('th23-social-admin-js', $this->plugin['dir_url'] . 'th23-social-admin.js', array('jquery'), $this->plugin['version'], true);
		wp_register_style('th23-social-admin-css', $this->plugin['dir_url'] . 'th23-social-admin.css', array(), $this->plugin['version']);
	}

	// Register admin page in admin menu/ prepare loading admin JS and CSS/ trigger screen options and help
	function add_admin() {
		$this->plugin['data'] = get_plugin_data($this->plugin['file']);
		$page = add_submenu_page($this->plugin['settings_base'], $this->plugin['data']['Name'], $this->plugin['data']['Name'], $this->plugin['settings_permission'], $this->plugin['settings_handle'], array(&$this, 'show_admin'));
		add_action('admin_print_scripts-' . $page, array(&$this, 'load_media_js'));
		add_action('admin_print_scripts-' . $page, array(&$this, 'load_admin_js'));
		add_action('admin_print_styles-' . $page, array(&$this, 'load_admin_css'));
		if(!empty($this->plugin['screen_options'])) {
			add_action('load-' . $page, array(&$this, 'add_screen_options'));
		}
		if(!empty($this->plugin['help_tabs'])) {
			add_action('load-' . $page, array(&$this, 'add_help'));
		}
	}

	// Load media JS - but only on plugin admin page
	function load_media_js() {
		wp_enqueue_media();
	}

	// Load admin JS
	function load_admin_js() {
		wp_enqueue_script('th23-social-admin-js');
		// customization: localization is plugin specific
		wp_localize_script('th23-social-admin-js', 'th23_social_js', array(
			'nonce' => wp_create_nonce('th23-social-nonce'),
			'social_image' => __('Social image', 'th23-social'),
			'save_social_image' => __('Save social image', 'th23-social'),
		));
	}

	// Load admin CSS
	function load_admin_css() {
		wp_enqueue_style('th23-social-admin-css');
	}

	// Handle screen options
	function add_screen_options() {
		add_filter('screen_settings', array(&$this, 'show_screen_options'), 10, 2);
	}
	function show_screen_options($html, $screen) {
		$html .= '<div id="th23-social-screen-options">';
		$html .= '<input type="hidden" id="th23-social-screen-options-nonce" value="' . wp_create_nonce('th23-social-screen-options-nonce') . '" />';
		$html .= $this->get_screen_options(true);
		$html .= '</div>';
		return $html;
	}
	function get_screen_options($html = false) {
		if(empty($this->plugin['screen_options'])) {
			return array();
		}
		if(empty($user = get_user_meta(get_current_user_id(), 'th23_social_screen_options', true))) {
			$user = array();
		}
		$screen_options = ($html) ? '' : array();
		foreach($this->plugin['screen_options'] as $option => $details) {
			$type = gettype($details['default']);
			$value = (isset($user[$option]) && gettype($user[$option]) == $type) ? $user[$option] : $details['default'];
			if($html) {
				$name = 'th23_social_screen_options_' . $option;
				$class = 'th23-social-screen-option-' . $option;
				if('boolean' == $type) {
					$checked = (!empty($value)) ? ' checked="checked"' : '';
					$screen_options .= '<fieldset class="' . $name . '"><label><input name="' . $name .'" id="' . $name .'" value="1" type="checkbox"' . $checked . ' data-class="' . $class . '">' . esc_html($details['title']) . '</label></fieldset>';
				}
				elseif('integer' == $type) {
					$min_max = (isset($details['range']['min'])) ? ' min="' . $details['range']['min'] . '"' : '';
					$min_max .= (isset($details['range']['max'])) ? ' max="' . $details['range']['max'] . '"' : '';
					$screen_options .= '<fieldset class="' . $name . '"><label for="' . $name . '">' . esc_html($details['title']) . '</label><input id="' . $name . '" name="' . $name . '" type="number"' . $min_max . ' value="' . $value . '" data-class="' . $class . '" /></fieldset>';
				}
				elseif('string' == $type) {
					$screen_options .= '<fieldset class="' . $name . '"><label for="' . $name . '">' . esc_html($details['title']) . '</label><input id="' . $name . '" name="' . $name . '" type="text" value="' . esc_attr($value) . '" data-class="' . $class . '" /></fieldset>';
				}
			}
			else {
				$screen_options[$option] = $value;
			}
		}
		return $screen_options;
	}
	// update user preference for screen options via AJAX
	function set_screen_options() {
		if(!empty($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'th23-social-screen-options-nonce')) {
			$screen_options = $this->get_screen_options();
			$new = array();
			foreach($screen_options as $option => $value) {
				$name = 'th23_social_screen_options_' . $option;
				if('boolean' == gettype($value)) {
					if(empty($_POST[$name])) {
						$screen_options[$option] = $value;
					}
					elseif('true' == $_POST[$name]) {
						$screen_options[$option] = true;
					}
					else {
						$screen_options[$option] = false;
					}
				}
				else {
					settype($_POST[$name], gettype($value));
					$screen_options[$option] = $_POST[$name];
				}
			}
			update_user_meta(get_current_user_id(), 'th23_social_screen_options', $screen_options);
		}
		wp_die();
	}

	// Add help
	function add_help() {
		$screen = get_current_screen();
		foreach($this->plugin['help_tabs'] as $id => $details) {
			$screen->add_help_tab(array(
				'id' => $id,
				'title' => $details['title'],
				'content' => $details['content'],
			));
		}
		if(!empty($this->plugin['help_sidebar'])) {
			$screen->set_help_sidebar($this->plugin['help_sidebar']);
		}
	}

	// Show admin page
	function show_admin() {

		global $wpdb;
		$form_classes = array();

		// Open wrapper and show plugin header
		echo '<div class="wrap th23-social-options">';

		// Header - logo / plugin name
		echo '<h1>';
		if(!empty($this->plugin['icon']['horizontal'])) {
			echo '<img class="icon" src="' . esc_url($this->plugin['dir_url'] . $this->plugin['icon']['horizontal']) . '" alt="' . esc_attr($this->plugin['data']['Name']) . '" />';
		}
		else {
			echo $this->plugin['data']['Name'];
		}
		echo '</h1>';

		// Get screen options, ie user preferences - and build CSS class
		if(!empty($this->plugin['screen_options'])) {
			$screen_options = $this->get_screen_options();
			foreach($screen_options as $option => $value) {
				if($value === true) {
					$form_classes[] = 'th23-social-screen-option-' . $option;
				}
				elseif(!empty($value)) {
					$form_classes[] = 'th23-social-screen-option-' . $option . '-' . esc_attr(str_replace(' ', '_', $value));
				}
			}
		}

		// start form
		echo '<form method="post" enctype="multipart/form-data" id="th23-social-options" action="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '" class="' . implode(' ', $form_classes) . '">';

		// Show warnings, if requirements are not met
		if(!empty($this->plugin['requirement_notices'])) {
			foreach($this->plugin['requirement_notices'] as $notice) {
				echo '<div class="notice notice-error"><p>' . $notice . '</p></div>';
			}
		}

		// Do update of plugin options if required
		if(!empty($_POST['th23-social-options-do'])) {
			check_admin_referer('th23_social_settings', 'th23-social-settings-nonce');
			$new_options = $this->get_options($this->options, true);

			// customization: check for "manual" request to delete all saved raw excerpts for entries, eg after changing default length or filters to be taken into account
			// note: reset to 0 to prevent this from triggering an option update and to ensure the checkbox is always unchecked upon page load
			if(!empty($new_options['cache_reset'])) {
				delete_post_meta_by_key('th23_social_excerpt_raw');
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-social') . '</strong>: ' . __('Cache cleared', 'th23-social') . '</p><button class="notice-dismiss" type="button"></button></div>';
			}
			$new_options['cache_reset'] = 0;

			// check against unfiltered options stored (as "services" can be altered at runtime by plugins)
			$options_unfiltered = (array) get_option('th23_social_options');
			if($new_options != $options_unfiltered) {

				// customization: check for changed image_width / image_height options, if a default social image is selected - re-create default social image selected and include (new) default URL in options to save
				if(!empty($new_options['image_default']) && ($options_unfiltered['image_width'] != $new_options['image_width'] || $options_unfiltered['image_height'] != $new_options['image_height'])) {
					// note: default function here ONLY used to separate the ID from an attached URL string - can't rely (yet) on the new URL due to changed size parameters not yet saved!
					$default_image = $this->social_image_default($new_options['image_default']);
					if(!empty($default_image['id'])) {
						$default_image_id = $default_image['id'];
						$default_image = $this->create_social_image_size($default_image_id, array('width' => $new_options['image_width'], 'height' => $new_options['image_height']));
						$default_image_url = (!empty($default_image['url'])) ? $default_image['url'] : '';
						$new_options['image_default'] = $default_image_id . ' ' . $default_image_url;
					}
				}

				update_option('th23_social_options', $new_options);
				$this->options = $new_options;

				// customization: re-apply filters to "services" option (as possible changes by plugins need to be shown in options table)
				$this->filter_services();

				// customization: check for changed image_width / image_height options - re-create cropped images for all selected on individual posts / pages as social image
				if($options_unfiltered['image_width'] != $this->options['image_width'] || $options_unfiltered['image_height'] != $this->options['image_height']) {
					global $wpdb;
					$social_image_entries = $wpdb->get_results('SELECT post_id, meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = "th23_social_image"', ARRAY_A);
					foreach($social_image_entries as $social_image_entry) {
						// accessing the DB directly...it's double serialized (once by plugin and once more as string by wpdb)
						$social_image_meta = maybe_unserialize(maybe_unserialize($social_image_entry['meta_value']));
						// attempt to re-create social image according to new dimensions and update entry meta data accordingly
						$image = $this->create_social_image_size($social_image_meta['id']);
						if(!empty($image)) {
							update_post_meta($social_image_entry['post_id'], 'th23_social_image', serialize(array('id' => (int) $social_image_meta['id'], 'url' => $image['url'])));
						}
					}
				}

				echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-social') . '</strong>: ' . __('Settings saved', 'th23-social') . '</p><button class="notice-dismiss" type="button"></button></div>';
			}
		}

		// Handle Profesional extension upload and show upgrade information
		if(empty($this->pro_upload()) && empty($this->plugin['pro']) && empty($this->plugin['requirement_notices']) && !empty($this->plugin['extendable']) && !empty($this->plugin['download_url'])) {
			echo '<div class="th23-social-admin-about">';
			echo '<p>' . $this->plugin['extendable'] . '</p>';
			echo '<p><a href="' . esc_url($this->plugin['download_url']) . '">' . $this->plugin_upgrade(true) . '</a></p>';
			echo '</div>';
		}

		// Show plugin settings
		// start table
		echo '<table class="form-table"><tbody>';

		// collect all children options - and the no shows
		$child_list = '';
		$sub_child_list = '';
		$no_show_list = '';

		// loop through all options
		foreach($this->plugin['options'] as $option => $option_details) {

			// add children options and no shows
			if(isset($option_details['element']) && $option_details['element'] == 'checkbox' && !empty($option_details['attributes']['data-childs'])) {
				// if the current option itself is on the child list, then the options in data-childs are sub childs
				if(strpos($child_list, 'option-' . $option . ',') !== false) {
					$sub_child_list .= $option_details['attributes']['data-childs'] . ',';
				}
				// otherwise we have first level children
				else {
					$child_list .= $option_details['attributes']['data-childs'] . ',';
				}
				if(empty($this->options[$option]) || strpos($no_show_list, 'option-' . $option . ',') !== false) {
					$no_show_list .= $option_details['attributes']['data-childs'] . ',';
				}
			}
			// assign proper child or sub-child class - for proper indent
			$child_class = '';
			if(strpos($child_list, 'option-' . $option . ',') !== false) {
				$child_class = ' child';
			}
			elseif(strpos($sub_child_list, 'option-' . $option . ',') !== false) {
				$child_class = ' sub-child';
			}
			// prepare show/hide style for current element
			$no_show_style = (strpos($no_show_list, 'option-' . $option . ',') !== false) ? ' style="display: none;"' : '';

			$key = '';
			if(is_array($option_details['default'])) {
				$default_value = reset($option_details['default']);
				$key = key($option_details['default']);
				unset($option_details['default'][$key]);
				if($key == 'template') {

					echo '</tbody></table>';
					echo '<div class="option option-template option-' . $option . $child_class . '"' . $no_show_style . '>';
					echo '<h2>' . $option_details['title'] . '</h2>';
					if(!empty($option_details['description'])) {
						echo '<p class="section-description">' . $option_details['description'] . '</p>';
					}
					echo '<table class="option-template"><tbody>';

					// create template headers
					echo '<tr>';
					foreach($default_value as $sub_option => $sub_option_details) {
						$hint_open = '';
						$hint_close = '';
						if(isset($sub_option_details['description'])) {
							$hint_open = '<span class="hint" title="' . esc_attr($sub_option_details['description']) . '">';
							$hint_close = '</span>';
						}
						echo '<th class="' . $sub_option . '">' . $hint_open . $sub_option_details['title'] . $hint_close . '</th>';
					}
					// show add button, if template list is user editable
					if(!empty($option_details['extendable'])) {
						echo '<td class="template-actions"><button type="button" id="template-add-' . $option . '" value="' . $option . '">' . __('+', 'th23-social') . '</button></td>';
					}
					echo '</tr>';

					// get elements for rows - and populate hidden input (adjusted by JS for adding/ deleting rows)
					$elements = array_keys(array_merge($this->options[$option], $option_details['default']));
					// sort elements array according to order field
					if(isset($default_value['order'])) {
						$sorted_elements = array();
						foreach($elements as $element) {
							$sorted_elements[$element] = (isset($this->options[$option][$element]['order'])) ? $this->options[$option][$element]['order'] : 0;
						}
						asort($sorted_elements);
						$elements = array_keys($sorted_elements);
					}

					// add list of elements and empty row as source for user inputs - filled with defaults
					if(!empty($option_details['extendable'])) {
						echo '<input id="input_' . $option . '_elements" name="input_' . $option . '_elements" value="' . implode(',', $elements) . '" type="hidden" />';
						$elements[] = 'template';
					}

					// show template rows
					foreach($elements as $element) {
						echo '<tr id="' . $option . '-' . $element . '">';
						foreach($default_value as $sub_option => $sub_option_details) {
							echo '<td>';
							// get sub value default - and separate any array to show as sub value
							$sub_key = '';
							if(is_array($sub_option_details['default'])) {
								$sub_default_value = reset($sub_option_details['default']);
								$sub_key = key($sub_option_details['default']);
								unset($sub_option_details['default'][$sub_key]);
							}
							else {
								$sub_default_value = $sub_option_details['default'];
							}
							// force current value to be default and disable input field for preset elements / fields (not user changable / editable)
							if(isset($option_details['default'][$element][$sub_option])) {
								// set current value to default (not user-changable)
								$this->options[$option][$element][$sub_option] = $option_details['default'][$element][$sub_option];
								// disable input field
								if(!isset($sub_option_details['attributes']) || !is_array($sub_option_details['attributes'])) {
									$sub_option_details['attributes'] = array();
								}
								$sub_option_details['attributes']['disabled'] = 'disabled';
								// show full value in title, as field is disabled and thus sometimes not scrollable
								$sub_option_details['attributes']['title'] = esc_attr($this->options[$option][$element][$sub_option]);
							}
							// set to template defined default, if not yet set (eg options added via filter before first save)
							elseif(!isset($this->options[$option][$element][$sub_option])) {
								$this->options[$option][$element][$sub_option] = $sub_default_value;
							}
							// build and show input field
							$html = $this->build_input_field($option . '_' . $element . '_' . $sub_option, $sub_option_details, $sub_key, $sub_default_value, $this->options[$option][$element][$sub_option]);
							if(!empty($html)) {
								echo $html;
							}
							echo '</td>';
						}
						// show remove button, if template list is user editable and element is not part of the default set
						if(!empty($option_details['extendable'])) {
							$remove = (empty($this->plugin['options'][$option]['default'][$element]) || $element == 'template') ? '<button type="button" id="template-remove-' . $option . '-' . $element . '" value="' . $option . '" data-element="' . $element . '">' . __('-', 'th23-social') . '</button>' : '';
							echo '<td class="template-actions">' . $remove . '</td>';
						}
						echo '</tr>';
					}

					echo '</tbody></table>';
					echo '</div>';
					echo '<table class="form-table"><tbody>';

					continue;

				}
			}
			else {
				$default_value = $option_details['default'];
			}

			// separate option sections - break table(s) and insert heading
			if(!empty($option_details['section'])) {
				echo '</tbody></table>';
				echo '<h2 class="option option-section option-' . $option . $child_class . '"' . $no_show_style . '>' . $option_details['section'] . '</h2>';
				if(!empty($option_details['section_description'])) {
					echo '<p class="section-description">' . $option_details['section_description'] . '</p>';
				}
				echo '<table class="form-table"><tbody>';
			}

			// Build input field and output option row
			if(!isset($this->options[$option])) {
				// might not be set upon fresh activation
				$this->options[$option] = $default_value;
			}
			$html = $this->build_input_field($option, $option_details, $key, $default_value, $this->options[$option]);
			if(!empty($html)) {
				echo '<tr class="option option-' . $option . $child_class . '" valign="top"' . $no_show_style . '>';
				$option_title = $option_details['title'];
				if(!isset($option_details['element']) || ($option_details['element'] != 'checkbox' && $option_details['element'] != 'radio')) {
					$brackets = (isset($option_details['element']) && ($option_details['element'] == 'list' || $option_details['element'] == 'dropdown')) ? '[]' : '';
					$option_title = '<label for="input_' . $option . $brackets . '">' . $option_title . '</label>';
				}
				echo '<th scope="row">' . $option_title . '</th>';
				echo '<td><fieldset>';
				// Rendering additional field content via callback function
				// passing on to callback function as parameters: $default_value = default value, $this->options[$option] = current value
				if(!empty($option_details['render']) && method_exists($this, $option_details['render'])) {
					$render = $option_details['render'];
					echo $this->$render($default_value, $this->options[$option]);
				}
				echo $html;
				if(!empty($option_details['description'])) {
					echo '<span class="description">' . $option_details['description'] . '</span>';
				}
				echo '</fieldset></td>';
				echo '</tr>';
			}

		}

		// end table
		echo '</tbody></table>';
		echo '<br/>';

		// submit
		echo '<input type="hidden" name="th23-social-options-do" value=""/>';
		echo '<input type="button" id="th23-social-options-submit" class="button-primary th23-social-options-submit" value="' . esc_attr(__('Save Changes', 'th23-social')) . '"/>';
		wp_nonce_field('th23_social_settings', 'th23-social-settings-nonce');

		echo '<br/>';

		// Plugin information
		echo '<div class="th23-social-admin-about">';
		if(!empty($this->plugin['icon']['square'])) {
			echo '<img class="icon" src="' . esc_url($this->plugin['dir_url'] . $this->plugin['icon']['square']) . '" alt="' . esc_attr($this->plugin['data']['Name']) . '" /><p>';
		}
		else {
			echo '<p><strong>' . $this->plugin['data']['Name'] . '</strong>' . ' | ';
		}
		if(!empty($this->plugin['pro'])) {
			/* translators: parses in plugin version number (optionally) together with upgrade link */
			echo sprintf(__('Version %s', 'th23-social'), $this->plugin['version']) . ' ' . $this->plugin_professional(true);
		}
		else {
			/* translators: parses in plugin version number (optionally) together with upgrade link */
			echo sprintf(__('Version %s', 'th23-social'), $this->plugin['version']);
			if(!empty($this->plugin['extendable'])) {
				echo ' ' . $this->plugin_basic();
				if(empty($this->plugin['requirement_notices']) && !empty($this->plugin['download_url'])) {
					echo ' - <a href="' . esc_url($this->plugin['download_url']) . '">' . $this->plugin_upgrade(true) . '</a> (<label for="th23-social-pro-file">' . __('Upload upgrade', 'th23-social') . ')</label>';
				}
			}
		}
		// embed upload for Professional extension
		if(!empty($this->plugin['extendable'])) {
			echo '<input type="file" name="th23-social-pro-file" id="th23-social-pro-file" />';
		}
		/* translators: parses in plugin author name */
		echo ' | ' . sprintf(__('By %s', 'th23-social'), $this->plugin['data']['Author']);
		if(!empty($this->plugin['support_url'])) {
			echo ' | <a href="' . esc_url($this->plugin['support_url']) . '">' . __('Support', 'th23-social') . '</a>';
		}
		elseif(!empty($this->plugin['data']['PluginURI'])) {
			echo ' | <a href="' . $this->plugin['data']['PluginURI'] . '">' . __('Visit plugin site', 'th23-social') . '</a>';
		}
		echo '</p></div>';

		// Close form and wrapper
		echo '</form>';
		echo '</div>';

	}

	// Handle Profesional extension upload
	function pro_upload() {

		if(empty($_FILES['th23-social-pro-file']) || empty($pro_upload_name = $_FILES['th23-social-pro-file']['name'])) {
			return;
		}

		global $th23_social_path;
		$files = array();
		$try_again = '<label for="th23-social-pro-file">' . __('Try again?', 'th23-social') . '</label>';

		// zip archive
		if('.zip' == substr($pro_upload_name, -4)) {
			// check required ZipArchive class (core component of most PHP installations)
			if(!class_exists('ZipArchive')) {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-social') . '</strong>: ';
				/* translators: parses in "Try again?" link */
				echo sprintf(__('Your server can not handle zip files. Please extract it locally and try again with the individual files. %s', 'th23-social'), $try_again) . '</p></div>';
				return;
			}
			// open zip file
			$zip = new ZipArchive;
			if($zip->open($_FILES['th23-social-pro-file']['tmp_name']) !== true) {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-social') . '</strong>: ';
				/* translators: parses in "Try again?" link */
				echo sprintf(__('Failed to open zip file. %s', 'th23-social'), $try_again) . '</p></div>';
				return;
			}
			// check zip contents
			for($i = 0; $i < $zip->count(); $i++) {
			    $zip_file = $zip->statIndex($i);
				$files[] = $zip_file['name'];
			}
			if(!empty(array_diff($files, $this->plugin['extension_files']))) {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-social') . '</strong>: ';
				/* translators: parses in "Try again?" link */
				echo sprintf(__('Zip file seems to contain files not belonging to the Professional extension. %s', 'th23-social'), $try_again) . '</p></div>';
				return;
			}
			// extract zip to plugin folder (overwrites existing files by default)
			$zip->extractTo($th23_social_path);
			$zip->close();
		}
		// (invalid) individual file
		elseif(!in_array($pro_upload_name, $this->plugin['extension_files'])) {
			echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-social') . '</strong>: ';
			/* translators: parses in "Try again?" link */
			echo sprintf(__('This does not seem to be a proper Professional extension file. %s', 'th23-social'), $try_again) . '</p></div>';
			return;
		}
		// idividual file
		else {
			move_uploaded_file($_FILES['th23-social-pro-file']['tmp_name'], $th23_social_path . $pro_upload_name);
			$files[] = $pro_upload_name;
		}

		// ensure proper file permissions (as done by WP core function "_wp_handle_upload" after upload)
		$stat = stat($th23_social_path);
		$perms = $stat['mode'] & 0000666;
		foreach($files as $file) {
			chmod($th23_social_path . $file, $perms);
		}

		// check for missing extension files
		$missing_file = false;
		foreach($this->plugin['extension_files'] as $file) {
			if(!is_file($th23_social_path . $file)) {
				$missing_file = true;
				break;
			}
		}

		// upload success message
		if($missing_file) {
			$missing = '<label for="th23-social-pro-file">' . __('Upload missing file(s)!', 'th23-social') . '</label>';
			echo '<div class="notice notice-warning"><p><strong>' . __('Done', 'th23-social') . '</strong>: ';
			/* translators: parses in "Upload missing files!" link */
			echo sprintf(__('Professional extension file uploaded. %s', 'th23-social'), $missing) . '</p></div>';
			return true;
		}
		else {
			$reload = '<a href="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '">' . __('Reload page to see Professional settings!', 'th23-social') . '</a>';
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-social') . '</strong>: ';
			/* translators: parses in "Reload page to see Professional settings!" link */
			echo sprintf(__('Professional extension file uploaded. %s', 'th23-social'), $reload) . '</p><button class="notice-dismiss" type="button"></button></div>';
			return true;
		}

	}

	// Create admin input field
	// note: uses the chance to point out any invalid combinations for element and validation options
	function build_input_field($option, $option_details, $key, $default_value, $current_value) {

		if(!isset($option_details['element'])) {
			$option_details['element'] = 'input';
		}
		$element_name = 'input_' . $option;
		$element_attributes = array();
		if(!isset($option_details['attributes']) || !is_array($option_details['attributes'])) {
			$option_details['attributes'] = array();
		}
		$element_attributes_suggested = array();
		$valid_option_field = true;
		if($option_details['element'] == 'checkbox') {
			// exceptional case: checkbox allows "single" default to handle (yes/no) checkbox
			if(empty($key) || ($key == 'multiple' && !is_array($default_value)) || ($key == 'single' && is_array($default_value))) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['type'] = 'checkbox';
		}
		elseif($option_details['element'] == 'radio') {
			if(empty($key) || $key != 'single' || is_array($default_value)) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['type'] = 'radio';
		}
		elseif($option_details['element'] == 'list') {
			if(empty($key) || $key != 'multiple' || !is_array($default_value)) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['multiple'] = 'multiple';
			$element_attributes_suggested['size'] = '5';
		}
		elseif($option_details['element'] == 'dropdown') {
			if(empty($key) || $key != 'single' || is_array($default_value)) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['size'] = '1';
		}
		elseif($option_details['element'] == 'hidden') {
			if(!empty($key)) {
				$valid_option_field = false;
			}
			$element_attributes['type'] = 'hidden';
		}
		else {
			if(!empty($key)) {
				$valid_option_field = false;
			}
			$element_attributes_suggested['type'] = 'text';
			$element_attributes_suggested['class'] = 'regular-text';
		}
		// no valid option field, due to missmatch of input field and default value
		if(!$valid_option_field) {
			$support_open = '';
			$support_close = '';
			if(!empty($this->plugin['support_url'])) {
				$support_open = '<a href="' . esc_url($this->plugin['support_url']) . '">';
				$support_close = '</a>';
			}
			elseif(!empty($this->plugin['data']['PluginURI'])) {
				$support_open = '<a href="' . $this->plugin['data']['PluginURI'] . '">';
				$support_close = '</a>';
			}
			echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-social') . '</strong>: ';
			/* translators: 1: option name, 2: opening a tag of link to support/ plugin page, 3: closing a tag of link */
			echo sprintf(__('Invalid combination of input field and default value for "%1$s" - please %2$scontact the plugin author%3$s', 'th23-social'), $option, $support_open, $support_close);
			echo '</p></div>';
			return '';
		}

		$html = '';

		// handle repetitive elements (checkboxes and radio buttons)
		if($option_details['element'] == 'checkbox' || $option_details['element'] == 'radio') {
			$html .= '<div>';
			// special handling for single checkboxes (yes/no)
			$checked = ($option_details['element'] == 'radio' || $key == 'single') ? array($current_value) : $current_value;
			foreach($option_details['default'] as $value => $text) {
				// special handling for yes/no checkboxes
				if(!empty($text)){
					$html .= '<div><label><input name="' . $element_name . '" id="' . $element_name . '_' . $value . '" value="' . $value . '" ';
					foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
						$html .= $attr . '="' . $attr_value . '" ';
					}
					$html .= (in_array($value, $checked)) ? 'checked="checked" ' : '';
					$html .= '/>' . $text . '</label></div>';
				}
			}
			$html .= '</div>';
		}
		// handle repetitive elements (dropdowns and lists)
		elseif($option_details['element'] == 'list' || $option_details['element'] == 'dropdown') {
			$html .= '<select name="' . $element_name . '" id="' . $element_name . '" ';
			foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
				$html .= $attr . '="' . $attr_value . '" ';
			}
			$html .= '>';
			$selected = ($option_details['element'] == 'dropdown') ? array($current_value) : $current_value;
			foreach($option_details['default'] as $value => $text) {
				$html .= '<option value="' . $value . '"';
				$html .= (in_array($value, $selected)) ? ' selected="selected"' : '';
				$html .= '>' . $text . '</option>';
			}
			$html .= '</select>';
			if($option_details['element'] == 'dropdown' && !empty($option_details['unit'])) {
				$html .= '<span class="unit">' . $option_details['unit'] . '</span>';
			}
		}
		// textareas
		elseif($option_details['element'] == 'textarea') {
			$html .= '<textarea name="' . $element_name . '" id="' . $element_name . '" ';
			foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
				$html .= $attr . '="' . $attr_value . '" ';
			}
			$html .= '>' . stripslashes($current_value) . '</textarea>';
		}
		// simple (self-closing) inputs
		else {
			$html .= '<input name="' . $element_name . '" id="' . $element_name . '" ';
			foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
				$html .= $attr . '="' . $attr_value . '" ';
			}
			$html .= 'value="' . stripslashes($current_value) . '" />';
			if(!empty($option_details['unit'])) {
				$html .= '<span class="unit">' . $option_details['unit'] . '</span>';
			}
		}

		return $html;

	}

	// == customization: from here on plugin specific ==

	// Provide option to modify services defaults, eg adding a (non user-deletable/ -changable) service to subscribe via th23 Subscribe plugin - but make sure we preserve the template
	function filter_services_defaults() {
		$services_defaults = $this->plugin['options']['services']['default'];
		$template = $services_defaults['template'];
		unset($services_defaults['template']);
		// make sure template is preserved as the first element in defaults
		$this->plugin['options']['services']['default'] = array('template' => $template) + apply_filters('th23_social_services_defaults', $services_defaults);
	}

	// Render additional field content for default social image option via callback function
	function option_image_default($default_image = null, $current_image = null) {
		return '<div id="th23-social-image-container" data-entry="default">' . $this->social_image_html($this->social_image_default()) . '</div>';
	}

	// Protect meta values from being edited "raw" by user on edit post / page
	function set_protected_meta($protected, $meta_key, $meta_type) {
		if(in_array($meta_key, array('th23_social_image', 'th23_social_counts', 'th23_social_excerpt_raw'))) {
			return true;
		}
		return $protected;
	}

	// Prevent auto-creation of own image size upon upload - this will be taken care of upon selection as featured image
	function prevent_auto_image_resizing($sizes) {
		unset($sizes['th23-social']);
		return $sizes;
	}

	// Update social image and shares per service on edit post / page screen - via classic metabox / in Gutenberg sidebar panel
	function add_entry_meta_box() {
		add_meta_box('th23-social-meta-box', __('Social', 'th23-social'), array(&$this, 'meta_box_content'), array('post', 'page'), 'side');
	}
	function meta_box_content() {

		// get ID of the entry (post/page) edited
		$entry_id = get_the_ID();

		// show social image for this entry - or placeholder
		echo __('Image promoted to social services upon sharing this entry', 'th23-social');
		echo '<div id="th23-social-image-container" data-entry="' . (int) $entry_id . '">';
		if(!empty($social_image = get_post_meta($entry_id, 'th23_social_image', true))) {
			$social_image = maybe_unserialize($social_image);
		}
		if(empty($social_image) || !is_array($social_image)) {
			$social_image = array('id' => 0, 'url' => '');
		}
		echo $this->social_image_html($social_image);
		echo '</div>';

		// get share counts
		$entry_meta = get_post_meta($entry_id, 'th23_social_counts', true);
		$counts = (!empty($entry_meta)) ? maybe_unserialize($entry_meta) : array();

		echo __('Manually set current number of shares per service and total for this entry - it is NOT advisable to change this unless you are sure what you are doing', 'th23-social');
		// loop through services
		echo '<table>';
		foreach($this->options['services'] as $service_id => $service) {
			$service_count = (!empty($counts[$service_id])) ? (int) $counts[$service_id] : 0;
			echo '<tr><td>' . esc_html($service['name']) . '</td><td><input id="th23_social_service_count_' . $service_id . '" name="th23_social_service_count_' . $service_id . '" type="text" size="10" value="' . $service_count . '" /></td></tr>';
		}
		$total_count = (!empty($counts['total'])) ? (int) $counts['total'] : 0;
		echo '<tr><td><strong>' . __('Total', 'th23-social') . '</strong></td><td><input id="th23_social_service_count_total" name="th23_social_service_count_total" type="text" size="10" value="' . $total_count . '" /></td></tr>';
		echo '</table>';

	}
	function save_entry_meta($entry_id) {

		$counts = array();

		// get count per service
		foreach($this->options['services'] as $service_id => $service) {
			$counts[$service_id] = (!empty($_POST['th23_social_service_count_' . $service_id])) ? (int) $_POST['th23_social_service_count_' . $service_id] : 0;
		}

		// get total count
		$counts['total'] = (!empty($_POST['th23_social_service_count_total'])) ? (int) $_POST['th23_social_service_count_total'] : 0;

		// save as entry meta
		update_post_meta($entry_id, 'th23_social_counts', serialize($counts));

	}

	// Build social image or placeholder HTML - for metabox and AJAX requests
	function social_image_html($social_image) {
		if(empty($social_image['id'])) {
			$html = '<div id="th23-social-image" class="th23-social-image th23-social-image-add" data-image="0"><div class="dashicons-before dashicons-plus"><br />' . esc_html__('Add social image', 'th23-social') . '</div></div>';
		}
		else {
			$html = '<div id="th23-social-image" class="th23-social-image" data-image="' . (int) $social_image['id'] . '">';
			$html .= '<img class="th23-social-image-preview" src="' . esc_attr($social_image['url']) . '" alt="" />';
			$html .= '<div class="th23-social-image-overlay">';
			// Check for Crop Thumbnails plugin (http://wordpress.org/extend/plugins/crop-thumbnails/)
			// Note: For older versions of the basis plugin checking $cptSettings and for newer ones check $cptSettingsScreen
			global $cptSettings, $cptSettingsScreen;
			$crop = (isset($cptSettings) || isset($cptSettingsScreen)) ? true : false;
			if($crop) {
				$html .= '<div class="th23-social-image-crop dashicons-before dashicons-image-crop cropThumbnailBox cropThumbnailsLink" data-cropthumbnail="' . esc_attr('{"image_id":' . (int) $social_image['id'] . ',"viewmode":"single"}') . '" title="' . esc_attr__('Crop Social Image', 'th23-social') . '"><br />' . esc_html__('Crop', 'th23-social') . '</div>';
			}
			$html .= '<div class="th23-social-image-change dashicons-before dashicons-plus"><br />' . esc_html__('Change', 'th23-social') . '</div>';
			$html .= '<div class="th23-social-image-remove dashicons-before dashicons-minus"><br />' . esc_html__('Remove', 'th23-social') . '</div>';
			$html .= '</div></div>';
		}
		return $html;
	}

	// Handle respective AJAX requests for the social image

	// AJAX: Update social image
	function ajax_update_image() {

		// Check request
		$this->ajax_validate_request();

		// Get entry and image ID
		if(empty($_POST['id']) || empty($_POST['image'])) {
			$this->ajax_send_response(array('result' => 'error', 'msg' => __('Error: Invalid ID!', 'th23-social')));
		}
		// entry ID can be "default" on plugin options page
		$entry_id = ($_POST['id'] == 'default') ? 'default' : (int) $_POST['id'];
		$image_id = (int) $_POST['image'];

		// Check if social image size exists and size options have not changed since when it was created - if not, try to create it!
		if(empty($image = image_get_intermediate_size($image_id, 'th23-social')) || $image['width'] != $this->options['image_width'] || $image['height'] != $this->options['image_height']) {
			$image = $this->create_social_image_size($image_id);
		}

		// If we could not get a properly sized image by now, throw an error
		if(!$image) {
			$this->ajax_send_response(array('result' => 'error', 'msg' => __('Error: Could not generate required image size!', 'th23-social')));
		}

		// Update entry meta and generate new image HTML
		$social_image = array('id' => (int) $image_id, 'url' => $image['url']);
		if($entry_id == 'default' || update_post_meta($entry_id, 'th23_social_image', serialize($social_image))) {
			$response = array('result' => 'success', 'msg' => '', 'html' => $this->social_image_html($social_image));
		}
		else {
			$response = array('result' => 'error', 'msg' => __('Error: Could not update social image!', 'th23-social'));
		}

		// Send response
		$this->ajax_send_response($response);

	}

	// AJAX: Remove social image
	function ajax_remove_image() {

		// Check request
		$this->ajax_validate_request();

		// Get entry ID
		if(empty($_POST['id'])) {
			$this->ajax_send_response(array('result' => 'error', 'msg' => __('Error: Invalid ID!', 'th23-social')));
		}
		// entry ID can be "default" on plugin options page
		$entry_id = ($_POST['id'] == 'default') ? 'default' : (int) $_POST['id'];

		// Remove social image and generate placeholder HTML
		if($entry_id == 'default' || delete_post_meta($entry_id, 'th23_social_image')) {
			$response = array('result' => 'success', 'msg' => '', 'html' => $this->social_image_html(array('id' => 0, 'url' => '')));
		}
		else {
			$response = array('result' => 'error', 'msg' => __('Error: Could not delete social image!', 'th23-social'));
		}

		// Send response
		$this->ajax_send_response($response);

	}

	// AJAX: Validate request
	function ajax_validate_request() {

		// Check correct "nonce"
		if(!wp_verify_nonce($_POST['nonce'], 'th23-social-nonce')) {
			$this->ajax_send_response(array('result' => 'error', 'id' => (int) $_POST['id'], 'msg' => __('Error: Invalid request!', 'th23-social')));
		}

		// Check user permission
		if(!current_user_can('edit_posts')) {
			$this->ajax_send_response(array('result' => 'error', 'id' => (int) $_POST['id'], 'msg' => __('Error: No permission!', 'th23-social')));
		}

	}

	// AJAX: Send response
	function ajax_send_response($response) {
		header( "Content-Type: application/json" );
		echo wp_json_encode($response);
		wp_die();
	}

	// Add social block on post and page admin screens (Gutenberg) - for block registration see main/ frontend file
	function add_gutenberg_edit() {
		// enqueue block JS
		wp_enqueue_script( 'th23-social-blocks-share-js', $this->plugin['dir_url'] . 'blocks/share.js', array( 'wp-i18n', 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-data' ), $this->plugin['version'], true );
		// enqueue block CSS - here normal frontend style due to server side rendered block (alternatively have seperate frontend CSS for blocks in "blocks/share.css" or load specific backend CSS for block from "blocks/share-editor.css" with dependency "array('wp-edit-blocks')")
		wp_enqueue_style( 'th23-social-blocks-share-css', $this->plugin['dir_url'] . 'th23-social.css', array(), $this->plugin['version'] );
	}

	// Reset cached meta for raw excerpts - upon content update
	function excerpt_raw_reset($entry_id) {
		delete_post_meta($entry_id, 'th23_social_excerpt_raw');
	}

}

?>
