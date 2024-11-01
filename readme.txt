=== th23 Social ===
Contributors: th23
Donate link: http://th23.net/th23-social
Tags: social, follow, following, follower, share, sharing, shares, facebook, twitter, linkedin, xing, pinterest, counter, count, buttons, Gutenberg
Requires at least: 4.2
Tested up to: 5.4
Stable tag: 1.2.0
Requires PHP: 5.6.32
License: GPLv2 only
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Social sharing and following buttons via block, auto-insert, shortcode and widget - without external resources, including follower and share counting

== Description ==

Provide your users the option to **follow you on social networks** and enable them to **share your posts / pages**. But keep as much control about your and your users data as possible.

The plugin offers you various options to show social sharing / following buttons:

* Decide to show the social bars always or only on single posts / pages
* Embed social follow bars **after each x entries** in overviews like archives and search results
* Auto-insert a social sharing bar **at the end of each entry**
* Use **shortcodes** to manually embed social bars wherever you want - including the option to specify additional shortcodes (you might have used earlier) to be translated

Counting of followers and shares is done **on your own server**, while the current count can be changed manually to be in sync with already existing numbers. It is possible to show the number of shares / followers as total and/or per social network.

Strictly no loading of external scripts or resources - making a **GDPR (DSGVO)** compliant usage easier.

= Social networks =

Out-of-the-box supported social networks:

* Facebook
* Twitter
* LinkedIn
* Xing
* Instagram (only following)
* Pinterest
* RSS (only following)

Manual extension with **additional social services / networks is possible** in the admin area. Selective enabling / disabling for sharing / following is possible per social service.

= Professional extension =

Further functionality is available as [Professional extension](https://th23.net/th23-social/):

* Improve **presentation of shared content** on social services, by automatically embedding dedicated HTML tags to posts and pages
* Define a **dedicated image in optimized size** for each post / page, to ensure consistency and good visiblity upon shares on social media

= Special opportunity =

If you are **interested in trying out the Professional version** for free, write a review for the plugin and in return get a year long license including updates, please [register at my website](https://th23.net/user-management/?register) and [contact me](https://th23.net/contact/). First come, first serve - limited opportunity for the first 10 people!

= Integration with other plugins =

For an even better user experience this **plugin integrates** with the following plugins:

* **th23 Subscribe** showing a subscription button within follow bars, manageable via th23 Social settings in the admin area - find this plugin in the [WP plugin repository](https://wordpress.org/plugins/th23-subscribe/) or the [plugins website](https://th23.net/th23-subscribe/) for more details and its Professional version with even more features
* **Crop Thumbnails** allowing selective (manual) cropping of images to be presented on social media upon shares via integration with [Crop Thumbnails](https://wordpress.org/plugins/crop-thumbnails/) plugin

For **more information** on the plugin or to get the Professional extension visit the [authors website](http://th23.net/th23-social/).

== Installation ==

To install th23 Social follow these easy steps:

1. Upload the plugin files to the `/wp-content/plugins/th23-social` directory, or install the plugin through the WordPress plugins screen
1. Activate the plugin through the 'Plugins' screen in the WordPress admin area
1. Use the 'Settings' -> 'th23 Social' screen to configure the plugin
1. Especially add your accounts for the social services and check which services should be available to your users for following or sharing
1. To add the th23 Social widget to your sidebar or footer, go to 'Appearance' -> 'Widgets' in the WordPress admin area, drag the 'th23 Social' widget from 'Available Widgets' on the left to a selected 'Widget Area' on the right
1. To define a social image for an entry or modify the share counts, go to the post / page edit screen and access the 'th23 Social' sidebar / metabox

That is it - your users will now have the option to follow you or share your content on various social services!

== Frequently Asked Questions ==

= How to show social service icons? =

To style the social buttons showing Genericon icons instead of first letter and service name, insert the following into your themes style CSS.
Note: Requires Genericons font being available! Inserting manual updates into the theme CSS file might be needed again after theme updates!

`
/* th23 Social: Inserts in entries */
.entry-content .th23-social {
	border-top: 2px dashed rgba(51,51,51,.3);
	margin-top: 3em;
	padding-top: 2em;
}

/* th23 Social: General buttons */
.th23-social .button {
	font-size: 1.2em;
}

/* th23 Social: style th23 Subscribe button */
.th23-social .th23-subscribe-button {
	color: #820000;
}
.th23-social .th23-subscribe-button:hover,
.th23-social .th23-subscribe-button:focus,
.th23-social .th23-subscribe-button:active {
	background-color: #820000;
}

/* th23 Social: enable Genericons for services they exist for */
.th23-social .f-button .button-letter,
.th23-social .t-button .button-letter,
.th23-social .l-button .button-letter,
.th23-social .i-button .button-letter,
.th23-social .p-button .button-letter,
.th23-social .r-button .button-letter,
.th23-social .th23-subscribe-button .button-letter {
	display: none;
}
.th23-social .f-button:before,
.th23-social .t-button:before,
.th23-social .l-button:before,
.th23-social .i-button:before,
.th23-social .p-button:before,
.th23-social .r-button:before,
.th23-social .th23-subscribe-button:before {
	-moz-osx-font-smoothing: grayscale;
	-webkit-font-smoothing: antialiased;
	display: inline-block;
	font-family: "Genericons";
	font-size: 100%;
	font-style: normal;
	font-weight: normal;
	font-variant: normal;
	line-height: calc(1.8em - 2px);
	speak: none;
	text-align: center;
	text-decoration: inherit;
	text-transform: none;
	vertical-align: unset;
}

/* th23 Social: Facebook */
.th23-social .f-button:before {
	content: "\f204";
}

/* th23 Social: Twitter */
.th23-social .t-button:before {
	content: "\f202";
}

/* th23 Social: LinkedIn */
.th23-social .l-button:before {
	content: "\f207";
}

/* th23 Social: Xing - NOT existing in Genericons set */

/* th23 Social: Instagram */
.th23-social .i-button:before {
	content: "\f215";
}

/* th23 Social: Pinterest */
.th23-social .p-button:before {
	content: "\f209";
}

/* th23 Social: RSS */
.th23-social .r-button:before {
	content: "\f413";
}

/* th23 Social: Subscribe */
.th23-social .th23-subscribe-button:before {
	content: "\f410";
}

/* th23 Social: Follow widget */
.th23-social .total-count {
	white-space: nowrap;
}

/* th23 Social: Follow widget */
.th23-social-widget .follow .total-count {
	display: block;
}
`

= Why does the "by" profile link in Facebook not show up upon sharing? =

There seem to be ongoing changes by Facebook on if and how they use these Open Graph tags.

It currently looks like authors have to give permission to a publication or website (and the associated FB page) in order to be cited as author in the "byline" for shared content.

To do so and have your profile linked you need to login to your Facebook profile, go to Settings (for your profile), and click "Linked Publications" (for a direct link once logged in [click here](https://www.facebook.com/settings?tab=author_publisher)). On this page you need to add your websites Facebook page as a "Linked Publication".

For some details and further links see https://stackoverflow.com/questions/46658129/facebook-stopped-displaying-articleauthor

== Screenshots ==

1. Auto-insert sharing bar after the content in posts
2. Follow (or share) widget, flexible claim
3. Showing follow bar every x entries in an overview page / archive
4. Easy to use Gutenberg blocks to share or follow
5. Admin area offering relevant settings next to entry edit page in sidebar / metabox
6. Extensive set of options, including extension of social services by user

== Changelog ==

= 1.2.0 =
* [enhancement, Basic/Pro] enable Professional extension - providing additional functionality
* [enhancement, Basic/Pro] major update for plugin settings area, easy upload of Professional extension files via plugin settings, adding screen options, adding unit descriptions, simplified display (hide/show examples), improved error logging
* [enhancement, Basic/Pro] optimize parameter gathering upon loading plugin
* [enhancement, Basic/Pro] enhanced security preventing direct call to plugin files
* [fix, Basic/Pro] - various small fixes for style, wording, etc

= v1.0.1 =
* [fix] Prevent auto-creation of own image size upon upload - this will be taken care of upon selection as social image
* [fix] Remove call to deprecated PHP function "create_function" upon widget initialization

= v1.0.0 =
* [enhancement] German translation
* [fix] approach to include social bars now compatible with more themes (different hook allowing for more flexibility in theme file names supported)
* [fix] current URL and connector determination now also works in edge-cases and is using the most simple form allowed
* [fix] author fields only available / used for type "article" in OpenGraph / Facebook
* [fix] add rel="nofollow" to social links to avoid search engines indexing
* [fix] only load JS files in admin when required
* [fix] new WP default theme (2019) uses styling via "entry" instead of "hentry" class - catering now for both...
* [fix] ensure proper building of links, preventing some mail programs / browsers otherwise breaking line and not recognize URL
* [fix, PRO] ensure read more extension is really "raw" format and not HTML entity

= v0.2.0 =
* [enhancement] Introduce default social image - as fallback option and for overview pages
* [fix] do not insert social bars into search results (maybe truncated in the middle) or feeds (no proper CSS styling)
* [fix] ensure white color on service background color on hover - didn't get priority on some themes
* [fix] add title attribute, picked up as modal title by Corp Thumbnail plugin

= v0.1.3 (first public release) =
* n/a

== Upgrade Notice ==

= 1.2.0 =
* Get the full potential of social sharing and following with the Professional extension

= v0.1.3 (first public release) =
* n/a
