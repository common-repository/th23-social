<?php
/*
Plugin Name: th23 Social
Description: Social sharing and following buttons via blocks, auto-inserts, shortcodes and widgets - without external resources loading, including follower and share counting.
Version: 1.2.0
Author: Thorsten Hartmann (th23)
Author URI: http://th23.net/
Text Domain: th23-social
Domain Path: /lang
License: GPLv2 only
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2019-2020, Thorsten Hartmann (th23)
http://th23.net/

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2, as published by the Free Software Foundation. You may NOT assume that you can use any other version of the GPL.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
This license and terms apply for the Basic part of this program as distributed, but NOT for the separately distributed Professional add-on!
*/

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_social {

	// Initialize class-wide variables
	public $plugin = array(); // plugin (setup) information
	public $options = array(); // plugin options (user defined, changable)
	public $data = array(); // data exchange between plugin functions

	function __construct() {

		// Setup basics
		$this->plugin['file'] = __FILE__;
		$this->plugin['basename'] = plugin_basename($this->plugin['file']);
		$this->plugin['dir_url'] = plugin_dir_url($this->plugin['file']);
		$this->plugin['version'] = '1.2.0';

    	// Load plugin options
		$this->options = (array) get_option('th23_social_options');

		// Localization
		load_plugin_textdomain('th23-social', false, dirname($this->plugin['basename']) . '/lang');

		// == customization: from here on plugin specific ==

		// Provide option to dynamically register additional services, eg subscribe via th23 Subscribe plugin
		// note: apply filter twice 1) early for count and redirect information and 2) late to allow filter to pass in user specific information
		add_action('plugins_loaded', array(&$this, 'filter_services'));
		add_action('init', array(&$this, 'filter_services'), 20);

		// Register social image size
		add_action('after_setup_theme', array(&$this, 'register_image_size'));

		// Gather plugin related parameters and remove them from request URI - should not be part of URLs generated
		$gets = array('follow', 'share');
		$this->data['gets'] = array();
		foreach($gets as $get) {
			if(isset($_GET[$get])) {
				$this->data['gets'][$get] = sanitize_text_field($_GET[$get]);
				unset($_GET[$get]);
			}
		}
		$_SERVER['REQUEST_URI'] = remove_query_arg($gets);

		// Trigger link initiated actions (follow, share)
		add_action('init', array(&$this, 'trigger_actions'));

		// Prepare JS and CSS
		add_action('init', array(&$this, 'register_js_css'));
		add_action('template_redirect', array(&$this, 'load_js_css'));

		// Insert social meta data - see PRO file

		// Register shortcodes
		$follow_shortcodes = (!empty($this->options['follow_shortcodes'])) ? explode(' ', $this->options['follow_shortcodes']) : array();
		foreach($follow_shortcodes as $shortcode) {
			add_shortcode(trim(str_replace(array('[', ']'), '', $shortcode)), array(&$this, 'follow_shortcode'));
		}
		$share_shortcodes = (!empty($this->options['share_shortcodes'])) ? explode(' ', $this->options['share_shortcodes']) : array();
		foreach($share_shortcodes as $shortcode) {
			add_shortcode(trim(str_replace(array('[', ']'), '', $shortcode)), array(&$this, 'share_shortcode'));
		}

		// Register Gutenberg blocks
		add_action('init', array(&$this, 'add_gutenberg'));

		// Insert follow and share after content
		add_filter('the_content', array(&$this, 'follow_insert_content'), 20);
		add_filter('the_content', array(&$this, 'share_insert_content'), 20);

		// Insert follow into lists
		add_action('get_template_part', array(&$this, 'follow_insert_lists'));

		// count number of occurances in content area of page - excluding in widgets (sidebar and footer)
		$this->data['follow_bars_content'] = 0;
		$this->data['share_bars_content'] = array(); // to store per entry as ID => count

		// count entries looped in a list (eg index, archive, search) - to insert follow bar
		$this->data['entries_looped'] = 0;

	}

	// Ensure PHP <5 compatibility
	function th23_social() {
		self::__construct();
	}

	// Error logging
	function log($msg) {
		if(!empty(WP_DEBUG) && !empty(WP_DEBUG_LOG)) {
			if(empty($this->plugin['data'])) {
				$plugin_data = get_file_data($this->plugin['file'], array('Name' => 'Plugin Name'));
				$plugin_name = $plugin_data['Name'];
			}
			else {
				$plugin_name = $this->plugin['data']['Name'];
			}
			error_log($plugin_name . ': ' . print_r($msg, true));
		}
	}

	// == customization: from here on plugin specific ==

	// === COMMON ===

	// Provide option to dynamically register additional services, eg subscribe via th23 Subscribe plugin
	function filter_services() {
		$this->options['services'] = apply_filters('th23_social_services', $this->options['services']);
	}

	// Register social image size - so it is created by default accessible upon cropping
	function register_image_size() {
		add_image_size('th23-social', $this->options['image_width'], $this->options['image_height'], true);
	}

	// Create social image size - and return according image data
	function create_social_image_size($image_id, $width_height = null) {
		$image = false;
		// Option to "force" new image dimensions not yet saved as default (see admin)
		if(empty($width_height)) {
			$width_height = array(
				'width' => (int) $this->options['image_width'],
				'height' => (int) $this->options['image_height'],
			);
		}
		// Following image creation code based on Optimize Images Resizing plugin - https://wordpress.org/plugins/optimize-images-resizing/
		$attachment_path = get_attached_file($image_id);
		$image_editor = wp_get_image_editor($attachment_path);
		if(!is_wp_error($image_editor)) {
			// Logic for calculation is based on http://stackoverflow.com/questions/8541380/max-crop-area-for-a-given-image-and-aspect-ratio
			$ar = ($width_height['width'] / $width_height['height']);
			$source_image_size = $image_editor->get_size();
			if ($ar < 1) { // "tall" crop
				$cropWidth = min(round($source_image_size['height'] * $ar), $source_image_size['width']);
				$cropHeight = min(round($cropWidth / $ar), $source_image_size['height']);
			}
			else { // "wide" or square crop
				$cropHeight = min(round($source_image_size['width'] / $ar), $source_image_size['height']);
				$cropWidth = min(round($cropHeight * $ar), $source_image_size['width']);
			}
			$startX = floor(($source_image_size['width'] / 2) - ($cropWidth / 2));
			$startY = floor(($source_image_size['height'] / 2) - ($cropHeight / 2));
			// Use crop mode of image editor, as only this can upscale images, if required
			$image_editor->crop($startX, $startY, $cropWidth, $cropHeight, $width_height['width'], $width_height['height']);
			$result_image_size = $image_editor->get_size();
			$filename = $image_editor->generate_filename($result_image_size['width'] . 'x' . $result_image_size['height']);
			$image_editor->save($filename);
			// Update image attachment data
			$image_meta = wp_get_attachment_metadata($image_id);
			$image_meta['sizes']['th23-social'] = array(
				'file'      => wp_basename($filename),
				'width'     => $result_image_size['width'],
				'height'    => $result_image_size['height'],
				'mime-type' => get_post_mime_type($image_id),
			);
			wp_update_attachment_metadata($image_id, $image_meta);
			// Fetch our newly created own size
			$image = image_get_intermediate_size($image_id, 'th23-social');
		}
		return $image;
	}

	// Trigger link initiated actions (follow, share)
	function trigger_actions() {

		if(isset($this->data['gets']['follow'])) {
			$action = 'follow';
			$this->data['service_id'] = $this->data['gets']['follow'];
		}
		elseif(isset($this->data['gets']['share'])) {
			$action = 'share';
			$this->data['service_id'] = $this->data['gets']['share'];
		}
		if(isset($action) && !is_admin()) {
			if(!isset($this->options['services'][$this->data['service_id']])) {
				unset($action, $this->data['service_id']);
			}
			// Handle user request to follow
			elseif('follow' == $action) {
				$this->follow_redirect();
			}
			// Handle user request to share - needs post specific data, can only do at "wp" stage
			elseif('share' == $action) {
				add_action('wp', array(&$this, 'share_redirect'));
			}
		}

	}

	// === FRONTEND ===

	// Register JS and CSS
	function register_js_css() {
		wp_register_style('th23-social-css', $this->plugin['dir_url'] . 'th23-social.css', array(), $this->plugin['version']);
	}

	// Load JS and CSS
	function load_js_css() {
		wp_enqueue_style('th23-social-css');
	}

	// Add connector to URL
	// note: ensure "/" before "?" due to some mail programs / browsers otherwise breaking line and not recognize a URL
	function add_connector($url) {
		if(strpos($url, '?') !== false) {
			return $url . '&';
		}
		elseif(substr($url, -1) == '/') {
			return $url . '?';
		}
		else {
			return $url . '/?';
		}
	}

	// Get current URL - with or without connector
	function get_current_url() {
		$current_url = (is_ssl() ? 'https' : 'http').'://';
		$current_url .= ($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') ? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'] : $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		if(strpos(get_option('home'), '://www.') === false) {
			$current_url = str_replace('://www.', '://', $current_url);
		}
		else {
			if(strpos($current_url, '://www.') === false) {
				$current_url = str_replace('://', '://www.', $current_url);
			}
		}
		return $current_url;
	}

	// Get default social image ID and URL
	// default storage format in option: "ID https://domain.tld/path/image-name-widthxsize.jpg"
	function social_image_default($id_url = '') {
		$default = array('id' => 0, 'url' => '');
		if(empty($id_url)) {
			$id_url = $this->options['image_default'];
		}
		if(!empty($id_url)) {
			$id_url = explode(' ', $id_url, 2);
			$default['id'] = (int) $id_url[0];
			if(!empty($id_url[1])) {
				$default['url'] = $id_url[1];
			}
		}
		if(!empty($default['id']) && empty($default['url'])) {
			$social_image = $this->create_social_image_size($default['id']);
			$default['url'] = (!empty($social_image['url'])) ? $social_image['url'] : '';
		}
		return $default;
	}

	// Get social image URL
	function social_image($entry_id) {
		$entry_img = '';
		// defined social image
		if(!empty($social_image = get_post_meta($entry_id, 'th23_social_image', true))) {
			$social_image = maybe_unserialize($social_image);
			if(!empty($social_image['id']) && empty($social_image['url'])) {
				$social_image = $this->create_social_image_size($social_image['id']);
			}
			$entry_img = (!empty($social_image['url'])) ? $social_image['url'] : '';
		}
		// th23 Featured image
		if(empty($entry_img)) {
			global $th23_featured;
			if(isset($th23_featured) && !empty($featured_image = $th23_featured->get($entry_id))) {
				if(!empty($featured_image['image_id'])) {
					// get featured image in the right social size
					$social_image = array('id' => $featured_image['image_id'], 'url' => wp_get_attachment_image_url($featured_image['image_id'], 'th23-social'));
					if(empty($social_image['url'])) {
						$social_image = $this->create_social_image_size($social_image['id']);
					}
					$entry_img = (!empty($social_image['url'])) ? $social_image['url'] : '';
				}
			}
		}
		// post thumbnail
		if(empty($entry_img)) {
			if(!empty($thumbnail_id = get_post_thumbnail_id($entry_id))) {
				$social_image = array('id' => $thumbnail_id, 'url' => get_the_post_thumbnail_url($entry_id, 'th23-social'));
				if(empty($social_image['url'])) {
					$social_image = $this->create_social_image_size($social_image['id']);
				}
				$entry_img = (!empty($social_image['url'])) ? $social_image['url'] : '';
			}
		}
		// first image attached to entry (ATTENTION: Just inserting an image does NOT attach it to a post - and positioning it first does NOT make it first attached!)
		if(empty($entry_img)) {
			if(!empty($first_attachment = get_children(array('post_parent' => $entry_id, 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'ASC', 'numberposts' => 1)))) {
				$first_attachment = current($first_attachment);
				$social_image = array('id' => $first_attachment->ID, 'url' => wp_get_attachment_image_url($first_attachment->ID, 'th23-social'));
				if(empty($social_image['url'])) {
					$social_image = $this->create_social_image_size($social_image['id']);
				}
				$entry_img = (!empty($social_image['url'])) ? $social_image['url'] : '';
			}
		}
		// default social image
		if(empty($entry_img)) {
			$default_image = $this->social_image_default();
			$entry_img = (!empty($default_image['url'])) ? $default_image['url'] : '';
		}
		// let't take what we found as image in the correct social size
		return $entry_img;
	}

	// Replace URL placeholders - always adding connector for parameters on local URLs
	function url_replacements($url, $service = '', $entry_id = 0) {

		$replacements = array();

		// WordPress home URL, including proper connector for parameters
		$replacements['%home_url%'] = $this->add_connector(get_home_url());

		// Current URL visited, including proper connector for parameters
		$replacements['%current_url%'] = $this->add_connector($this->get_current_url());

		// URL to RSS feed, including proper connector for parameters
		$replacements['%feed_url%'] = $this->add_connector(get_feed_link());

		// URL to registration page, including proper connector for parameters
		$replacements['%registration_url%'] = $this->add_connector(wp_registration_url());

		// optional username of content author at the service (do NOT include leading @ on Twitter)
		$own_account = (!empty($this->options['services'][$service]['own_account'])) ? $this->options['services'][$service]['own_account'] : '';
		$replacements['%own_account%'] = urlencode($own_account);

		// URL to current entry / post
		$entry_url = get_the_permalink($entry_id);
		$replacements['%entry_url%'] = (!empty($entry_url)) ? urlencode($entry_url) : '';

		// Title of the current entry / post
		$entry_title = get_the_title($entry_id);
		$replacements['%entry_title%'] = (!empty($entry_title)) ? urlencode(wp_strip_all_tags($entry_title)) : '';

		// List of hashtags assigned to current entry / post - separated by commata, without leading # character
		$tags = get_the_tags($entry_id);
		$tags_list = '';
		if(!empty($tags) && is_array($tags)) {
			foreach($tags as $tag) {
				$tags_list .= ',' . urlencode($tag->name);
			}
			$tags_list = substr($tags_list, 1);
		}
		$replacements['%entry_tags%'] = $tags_list;

		// URL to image presented to social services for this entry / post - in the defined social image size
		$replacements['%entry_img%'] = urlencode($this->social_image($entry_id));

		return str_replace(array_keys($replacements), $replacements, $url);

	}

	// === FOLLOW ===

	// Handle user request to follow at a service - count and redirect accordingly
	function follow_redirect() {
		// check if following is enabled for this service - and a follow URL exists
		if(!empty($this->options['services'][$this->data['service_id']]['follow_active']) && !empty($this->options['services'][$this->data['service_id']]['follow_url'])) {
			// get unfiltered options before increasing the counters (as "services" can be altered at runtime by plugins)
			$options_unfiltered = (array) get_option('th23_social_options');
			// increase counter for service & total counter in option value
			$options_unfiltered['services'][$this->data['service_id']]['follow_count']++;
			$options_unfiltered['follow_total_count']++;
			update_option('th23_social_options', $options_unfiltered);
			// filter URL and execute redirect
			$url = $this->url_replacements($this->options['services'][$this->data['service_id']]['follow_url'], $this->data['service_id']);
			wp_redirect($url);
			exit;
		}
	}

	// Create follow buttons HTML
	function follow_buttons($test = false) {
		$html = '';
		foreach($this->options['services'] as $service_id => $service) {
			if(!empty($service['follow_active']) && !empty($service['follow_url']) && !empty($service['name'])) {
				$css = (!empty($service['css_class'])) ? ' ' . $service['css_class'] . '-button' : '';
				// set target _blank, if follow URL is not on own server
				$blank = (substr(ltrim($this->url_replacements($service['follow_url'], $service_id)), 0, strlen(get_home_url())) !== get_home_url()) ? ' target="_blank"' : '';
				// show follower number for service, if required minimum is reached
				$follower = (!empty($this->options['follow_show_per_service']) && (int) $service['follow_count'] >= (int) $this->options['follow_show_per_service_min']) ? '<span class="service-count">' . (int) $service['follow_count'] . '</span>' : '';
				// test parameter will prevent counting eg upon clicks in Gutenberg editor
				$test = (!empty($test)) ? '&test' : '';
				// first character of name is default button letter - but can be hidden/ replaced by theme CSS eg with Genericon icon
				$html .= '<a href="' . $this->add_connector($this->get_current_url()) . 'follow=' . esc_attr($service_id) . $test . '" class="button' . $css . '"' . $blank . ' rel="nofollow"><span class="button-letter">' . esc_html(substr($service['name'], 0, 1)) . '</span><span class="button-text">' . esc_html($service['name']) . '</span>' . $follower . '</a>';
			}
		}
		if(!empty($html)) {
			$html = '<span class="buttons">' . $html . '</span>';
			// show total follower number, if required minimum is reached
			if(!empty($this->options['follow_show_total']) && (int) $this->options['follow_total_count'] >= (int) $this->options['follow_show_total_min']) {
				$html .= '<span class="total-count"><span class="count-follower">' . sprintf(__('Join %s follower', 'th23-social'), '<span class="count">' . (int) $this->options['follow_total_count'] . '</span>') . '</span><span class="count-only">' . (int) $this->options['follow_total_count'] . '</span></span>';
			}
		}
		return $html;
	}

	// Translate follow shortcode
	function follow_shortcode($atts = array()) {
		// remove shortcode, if only auto-insert in content is set
		if(!empty($this->options['follow_insert_content']) && !empty($this->options['follow_insert_content_shortcodes']) && $this->options['follow_insert_content_shortcodes'] == 'only_insert') {
			return '';
		}
		// count number of shortcodes/ blocks replaced
		$this->data['follow_bars_content']++;
		// create html
		$claim = (!empty($atts['claim'])) ? $atts['claim'] : $this->options['follow_claim'];
		$claim = (!empty($claim)) ? '<span class="claim">' . $claim . '</span>' : '';
		$html = '<div class="th23-social follow">' . $claim;
		$html .= $this->follow_buttons();
		$html .= '</div>';
		return $html;
	}

	// Insert follow after content
	function follow_insert_content($content) {
		// never insert into search results (maybe truncated in the middle) or feed (no proper CSS styling)
		if(is_search() || is_feed()) {
			return $content;
		}
		// check auto-insert in content is enabled
		if(empty($this->options['follow_insert_content'])) {
			return $content;
		}
		if(!empty($this->options['follow_insert_content_limit'])) {
			// check for singular entry (post, page, attachment) if limited
			if($this->options['follow_insert_content_limit'] == 'singles' && !is_singular()) {
				return $content;
			}
			// check for singule post if limited
			elseif($this->options['follow_insert_content_limit'] == 'single_posts' && !is_single()) {
				return $content;
			}
		}
		// check for previous handled follow shortcode if limited to once within an entry
		if(!empty($this->options['follow_insert_content_shortcodes']) && $this->options['follow_insert_content_shortcodes'] == 'only_once' && !empty($this->data['follow_bars_content'])) {
			return $content;
		}
		// create html
		$claim = $this->options['follow_claim'];
		$claim = (!empty($claim)) ? '<span class="claim">' . $claim . '</span>' : '';
		$html = '<div class="th23-social follow">' . $claim;
		$html .= $this->follow_buttons();
		$html .= '</div>';
		return $content . $html;
	}

	// Insert follow into lists
	function follow_insert_lists($slug = '') {
		// check if we show a list ie index overview, an archive (category, date, tag, author, ...) or search results
		if(!is_home() && !is_archive() && !is_search()) {
			return;
		}
		// check if we include a "content" template part
		if(substr($slug, -7) !== 'content') {
			return;
		}
		// check if inserting in lists is enabled
		if(empty($this->options['follow_insert_lists'])) {
			return;
		}
		// check how many entries we already showed - and if its time to insert a follow bar
		if(!empty($this->options['follow_insert_lists_entries']) && (int) $this->data['entries_looped'] >= (int) $this->options['follow_insert_lists_entries']) {
			// create and insert html
			$claim = $this->options['follow_claim'];
			$claim = (!empty($claim)) ? '<span class="claim">' . $claim . '</span>' : '';
			$html = '<article class="hentry entry th23-social"><div class="th23-social-list entry-content"><div class="th23-social follow">' . $claim;
			$html .= $this->follow_buttons();
			$html .= '</div></div></article>';
			echo $html;
			// reset counter, with first entry now already done
			$this->data['entries_looped'] = 1;
		}
		else {
			$this->data['entries_looped']++;
		}
	}

	// === SHARE ===

	// Handle user request to share post at a service - count and redirect accordingly
	function share_redirect() {
		// check if sharing is enabled for this service, a share URL exists and the entry is not password-protected
		if(!empty($this->options['services'][$this->data['service_id']]['share_active']) && !empty($this->options['services'][$this->data['service_id']]['share_url']) && !post_password_required()) {
			// sharing requires a valid entry
			$entry_id = get_the_ID();
			if(!empty($entry_id)) {
				// no counting for tests eg upon clicks in Gutenberg blocks
				if(!isset($_REQUEST['test'])) {
					// get share counts
					$entry_meta = get_post_meta($entry_id, 'th23_social_counts', true);
					$counts = (!empty($entry_meta)) ? maybe_unserialize($entry_meta) : array();
					// update service and total share counts
					$counts[$this->data['service_id']] = (!empty($counts[$this->data['service_id']])) ? $counts[$this->data['service_id']] + 1 : 1;
					$counts['total'] = (!empty($counts['total'])) ? $counts['total'] + 1 : 1;
					update_post_meta($entry_id, 'th23_social_counts', serialize($counts));
				}
				// filter URL and execute redirect
				$url = $this->url_replacements($this->options['services'][$this->data['service_id']]['share_url'], $this->data['service_id'], $entry_id);
				wp_redirect($url);
				exit;
			}
		}
	}

	// Create share buttons HTML
	function share_buttons($entry_id, $test = false) {
		// get counts from entry meta
		$entry_meta = get_post_meta($entry_id, 'th23_social_counts', true);
		if(!empty($entry_meta)) {
			$counts = maybe_unserialize($entry_meta);
		}
		$html = '';
		foreach($this->options['services'] as $service_id => $service) {
			if(!empty($service['share_active']) && !empty($service['share_url']) && !empty($service['name'])) {
				$css = (!empty($service['css_class'])) ? ' ' . $service['css_class'] . '-button' : '';
				// set target _blank, if share URL is not on own server
				$blank = (substr(ltrim($this->url_replacements($service['share_url'], $service_id, $entry_id)), 0, strlen(get_home_url())) !== get_home_url()) ? ' target="_blank"' : '';
				// show number of shares for service, if required minimum is reached
				$count = (!empty($counts[$service_id])) ? (int) $counts[$service_id] : 0;
				$shares = (!empty($this->options['share_show_per_service']) && $count >= (int) $this->options['share_show_per_service_min']) ? '<span class="service-count">' . $count . '</span>' : '';
				// test parameter will prevent counting eg upon clicks in Gutenberg editor
				$test = (!empty($test)) ? '&test' : '';
				// first character of name is default button letter - but can be hidden/ replaced by theme CSS eg with Genericon icon
				$html .= '<a href="' . $this->add_connector(get_permalink($entry_id)) . 'share=' . esc_attr($service_id) . $test . '" class="button' . $css . '"' . $blank . ' rel="nofollow"><span class="button-letter">' . esc_html(substr($service['name'], 0, 1)) . '</span><span class="button-text">' . esc_html($service['name']) . '</span>' . $shares . '</a>';
			}
		}
		if(!empty($html)) {
			$html = '<span class="buttons">' . $html . '</span>';
			// show number of total shares, if required minimum is reached
			$count = (!empty($counts['total'])) ? (int) $counts['total'] : 0;
			if(!empty($this->options['share_show_total']) && $count >= (int) $this->options['share_show_total_min']) {
				$html .= '<span class="total-count"><span class="count-shares">' . sprintf(__('Already shared %s times', 'th23-social'), '<span class="count">' . $count . '</span>') . '</span><span class="count-only">' . $count . '</span></span>';
			}
		}
		return $html;
	}

	// Translate share shortcode
	function share_shortcode($atts = array()) {
		// remove shortcode, if only auto-insert in content is set
		if(!empty($this->options['share_insert_content']) && !empty($this->options['share_insert_content_shortcodes']) && $this->options['share_insert_content_shortcodes'] == 'only_insert') {
			return '';
		}
		// sharing requires a valid entry and a not password-protected entry
		$entry_id = get_the_ID();
		if(empty($entry_id) || post_password_required()) {
			return;
		}
		// count number of share shortcodes/ blocks replaced/ social sharing bars inserted
		$this->data['share_bars_content'][$entry_id] = (!empty($this->data['share_bars_content'][$entry_id])) ? $this->data['share_bars_content'][$entry_id] + 1 : 1;
		// create html
		$claim = (!empty($atts['claim'])) ? $atts['claim'] : $this->options['share_claim'];
		$claim = (!empty($claim)) ? '<span class="claim">' . $claim . '</span>' : '';
		$html = '<div class="th23-social share">' . $claim;
		$html .= $this->share_buttons($entry_id);
		$html .= '</div>';
		return $html;
	}

	// Insert share after content
	function share_insert_content($content) {
		// never insert into search results (maybe truncated in the middle) or feed (no proper CSS styling)
		if(is_search() || is_feed()) {
			return $content;
		}
		// check auto-insert in content is enabled
		if(empty($this->options['share_insert_content'])) {
			return $content;
		}
		if(!empty($this->options['share_insert_content_limit'])) {
			// check for singular entry (post, page, attachment) if limited
			if($this->options['share_insert_content_limit'] == 'singles' && !is_singular()) {
				return $content;
			}
			// check for singule post if limited
			elseif($this->options['share_insert_content_limit'] == 'single_posts' && !is_single()) {
				return $content;
			}
		}
		// sharing requires a valid entry and a not password-protected entry
		$entry_id = get_the_ID();
		if(empty($entry_id) || post_password_required()) {
			return $content;
		}
		// check for previous handled share shortcode if limited to once within an entry
		if(!empty($this->options['share_insert_content_shortcodes']) && $this->options['share_insert_content_shortcodes'] == 'only_once' && !empty($this->data['share_bars_content'][$entry_id])) {
			return $content;
		}
		// count number of share shortcodes/ blocks replaced/ social sharing bars inserted
		$this->data['share_bars_content'][$entry_id] = (!empty($this->data['share_bars_content'][$entry_id])) ? $this->data['share_bars_content'][$entry_id] + 1 : 1;
		// create html
		$claim = $this->options['share_claim'];
		$claim = (!empty($claim)) ? '<span class="claim">' . $claim . '</span>' : '';
		$html = '<div class="th23-social share">' . $claim;
		$html .= $this->share_buttons($entry_id);
		$html .= '</div>';
		return $content . $html;
	}

	// Register Gutenberg blocks - for editor JS/CSS see admin
	function add_gutenberg() {
		register_block_type( 'th23-social/block-bar', array(
			'render_callback' => array(&$this, 'gutenberg_block_callback'),
			'attributes' => array(
				// note: due to non-deactivatable "Advanced" / "Additional CSS class" input field defined by WP, needs to be allowed as attribute to avoid invalid blocks - NOT used by this plugin, doesn't influence styling
				'className' => array(
					'type' => 'string',
					'default' => '',
				),
				// entry ID passed to server side rendering when in the editor
				'entry_id' => array(
					'type' => 'number',
					'default' => 0,
				),
				// type of block - "follow" or "share"
				'type' => array(
					'type' => 'string',
					'default' => 'share',
				),
				// claim shown above the buttons
				'claim' => array(
					'type' => 'string',
					'default' => '',
				),
			),
		) );
	}

	// Render Gutenberg block - frontend and editor
	function gutenberg_block_callback( $atts ) {

		// type of block?
		if(empty($atts['type']) || $atts['type'] != 'follow') {
			$atts['type'] = 'share';
		}

		// sharing requires a valid entry and a not password-protected entry
		$entry_id = get_the_ID();
		// identify call from editor and get entry ID via attributes
		$editor_call = false;
		if(empty($entry_id) && !empty($atts['entry_id'])) {
			$editor_call = true;
			$entry_id = $atts['entry_id'];
		}
		if($atts['type'] == 'share' && (empty($entry_id) || post_password_required())) {
			return '';
		}

		// only enforce limitations on frontend - not in editor
		if(empty($editor_call)) {
			// follow block limitations and counting
			if($atts['type'] == 'follow') {
				// don't render follow block, if only auto-insert in content is set
				if(!empty($this->options['follow_insert_content']) && !empty($this->options['follow_insert_content_shortcodes']) && $this->options['follow_insert_content_shortcodes'] == 'only_insert') {
					return '';
				}
				// count number of follow shortcodes/ blocks replaced
				$this->data['follow_bars_content']++;
			}
			// share block limitations and counting
			else {
				// don't render share block, if only auto-insert in content is set
				if(!empty($this->options['share_insert_content']) && !empty($this->options['share_insert_content_shortcodes']) && $this->options['share_insert_content_shortcodes'] == 'only_insert') {
					return '';
				}
				// count number of share shortcodes/ blocks replaced/ social sharing bars inserted
				$this->data['share_bars_content'][$entry_id] = (!empty($this->data['share_bars_content'][$entry_id])) ? $this->data['share_bars_content'][$entry_id] + 1 : 1;
			}
		}

		// user defined or default claim?
		$default_claim = ($atts['type'] == 'follow') ? $this->options['follow_claim'] : $this->options['share_claim'];
		$claim = (!empty($atts['claim'])) ? $atts['claim'] : $default_claim;
		$claim = (!empty($claim)) ? '<span class="claim">' . $claim . '</span>' : '';

		// build html
		$html = '<div class="th23-social ' . $atts['type'] . '">' . $claim;
		$html .= ($atts['type'] == 'follow') ? $this->follow_buttons($editor_call) : $this->share_buttons($entry_id, $editor_call);
		$html .= '</div>';
		return $html;

	}

}

// === WIDGETS ===

// Add social widget - can be added multiple times with different settings
class th23_social_widget extends WP_Widget {

	function __construct() {
		parent::__construct(false, $name = 'th23 Social', array('description' => __('Displays social sharing links, eg to Facebook', 'th23-social')));
	}

	// Ensure PHP <5 compatibility
	function th23_social_widget() {
		self::__construct();
	}

	// Show widget
	function widget($args, $instance) {
		extract($args);

		// show widget?
		if(empty($instance['show'])) {
			$instance['show'] = 'always';
		}
		if(($instance['show'] == 'singular' && !is_singular()) || ($instance['show'] == 'posts' && !is_single())) {
			return;
		}

		// type of widget?
		if(empty($instance['type'])) {
			$instance['type'] = 'follow';
		}

		// sharing requires a valid entry and a not password-protected entry
		$entry_id = get_the_ID();
		if($instance['type'] == 'share' && (empty($entry_id) || post_password_required())) {
			return;
		}

		// make main plugin class in widget available
		global $th23_social;

		echo $before_widget . '<div class="th23-social-widget">';

		// title
		if(!empty($instance['title'])) {
			echo $before_title . apply_filters('widget_title', $instance['title']) . $after_title;
		}

		// description
		if(!empty($instance['description'])) {
			echo '<p class="widget-description">' . esc_html($instance['description']) . '</p>';
		}

		// print out widget content
		echo '<div class="th23-social ' . $instance['type'] . '">';
		echo ($instance['type'] == 'share') ? $th23_social->share_buttons($entry_id) : $th23_social->follow_buttons();
		echo '</div>';

		echo '</div>' . $after_widget;

	}

	// Admin: Validate widget settings
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['type'] = (empty($new_instance['type']) || !in_array($new_instance['type'], array('follow', 'share'))) ? 'follow' : $new_instance['type'];
		$instance['show'] = (empty($new_instance['show']) || !in_array($new_instance['show'], array('always', 'singular', 'posts'))) ? 'always' : $new_instance['show'];
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['description'] = $new_instance['description'];
		return $instance;
	}

	// Admin: Show widget settings
	function form($instance) {

		// defaults upon first adding the widget
		$instance = wp_parse_args((array) $instance, array(
			'title' => __('Social', 'th23-social'),
		));

		// type
		$type_radios = array(
			'follow' => __('Follow', 'th23-social'),
			'share' => __('Share', 'th23-social'),
		);
		echo '<p>';
		$type = (!empty($instance['type']) && isset($type_radios[$instance['type']])) ? $instance['type'] : 'follow';
		foreach($type_radios as $id => $name) {
			echo '<div><label for="' . $this->get_field_id('type') . '_' . $id . '"><input type="radio" id="' . $this->get_field_id('type') . '_' . $id . '" name="' . $this->get_field_name('type') . '"' . (($type == $id) ? ' checked="checked"' : '') . ' value="' . $id . '" /> ' . $name . '</label></div>';
		}
		echo '</p>';

		// show
		$show_options = array(
			'always' => __('Always', 'th23-social'),
			'singular' => __('Only on Singles', 'th23-social'),
			'posts' => __('Only on Posts', 'th23-social'),
		);
		$show = (!empty($instance['show']) && isset($type_radios[$instance['show']])) ? $instance['show'] : 'always';
		echo '<p><label for="' . $this->get_field_id('show') . '">' . __('Show widget', 'th23-social') . '</label><select class="widefat" id="' . $this->get_field_id('show') . '" name="' . $this->get_field_name('show') . '" size="1">';
		foreach($show_options as $id => $name) {
			echo '<option id="' . $this->get_field_id('show') . '_' . $id . '" name="' . $this->get_field_name('show') . '"' . (($show == $id) ? ' selected="selected"' : '') . ' value="' . $id . '">' . $name . '</option>';
		}
		echo '</select></p>';

		// title
		$title = !empty($instance['title']) ? esc_attr($instance['title']) : '';
		echo '<p><label for="' . $this->get_field_id('title') . '">' . __('Title', 'th23-social') . '</label><input class="widefat" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="' . $title . '" /></p>';

		// description
		$description = !empty($instance['description']) ? esc_attr($instance['description']) : '';
		echo '<p><label for="' . $this->get_field_id('description') . '">' . __('Description', 'th23-social') . '</label><textarea class="widefat" id="' . $this->get_field_id('description') . '" name="' . $this->get_field_name('description') . '" size="3">' . $description . '</textarea></p>';

	}

}
add_action('widgets_init', function() { return register_widget('th23_social_widget'); });

// === INITIALIZATION ===

$th23_social_path = plugin_dir_path(__FILE__);

// Load additional PRO class, if it exists
if(file_exists($th23_social_path . 'th23-social-pro.php')) {
	require($th23_social_path . 'th23-social-pro.php');
}
// Mimic PRO class, if it does not exist
if(!class_exists('th23_social_pro')) {
	class th23_social_pro extends th23_social {
		function __construct() {
			parent::__construct();
		}
		// Ensure PHP <5 compatibility
		function th23_social_pro() {
			self::__construct();
		}
	}
}

// Load additional admin class, if required...
if(is_admin() && file_exists($th23_social_path . 'th23-social-admin.php')) {
	require($th23_social_path . 'th23-social-admin.php');
	$th23_social = new th23_social_admin();
}
// ...or initiate plugin via (mimiced) PRO class
else {
	$th23_social = new th23_social_pro();
}

?>
