<?php
/*
th23 Social
Professional extension - Language strings

Copyright 2019-2020, Thorsten Hartmann (th23)
http://th23.net
*/

// This file should not be executed - but only be read by the gettext parser to prepare for translations
die();

// Function to extract i18n calls from PRO file
$file = file_get_contents('th23-social-pro.php');
preg_match_all("/__\\(.*?'\\)|_n\\(.*?'\\)|\\/\\* translators:.*?\\*\\//s", $file, $matches);
foreach($matches[0] as $match) {
	echo $match . ";\n";
}

__('Upload Professional extension?', 'th23-social');
__('Go to plugin settings page for upload...', 'th23-social');
/* translators: 1: "Professional" as name of the version, 2: "...-pro.php" as file name, 3: version number of the PRO file, 4: version number of main file, 5: link to WP update page, 6: link to "th23.net" plugin download page, 7: link to "Go to plugin settings page to upload..." page or "Upload updated Professional extension?" link */;
__('The version of the %1$s extension (%2$s, version %3$s) does not match with the overall plugin (version %4$s). Please make sure you update the overall plugin to the latest version via the <a href="%5$s">automatic update function</a> and get the latest version of the %1$s extension from %6$s. %7$s', 'th23-social');
__('Error', 'th23-social');

?>
