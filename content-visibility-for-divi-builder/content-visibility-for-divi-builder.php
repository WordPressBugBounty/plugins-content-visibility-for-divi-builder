<?php
/**
 * @since             1.0.0
 * @package           content_visibility_for_divi_builder
 *
 * @wordpress-plugin
 * Plugin Name:       Content Visibility for Divi Builder
 * Plugin URI:        https://aod-tech.com/
 * Description:       Allows Sections and Modules to be displayed/hidden based on the outcome of a PHP boolean expression.
 * Version:           5.02
 * Author:            AoD Technologies LLC
 * Author URI:        https://aod-tech.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       content-visibility-for-divi-builder
 * Domain Path:       /languages
 *
 * Content Visibility for Divi Builder is free software: you
 * can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software
 * Foundation, either version 2 of the License, or any later version.
 *
 * Content Visibility for Divi Builder is distributed in the
 * hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Content Visibility for Divi Builder. If not, see
 * http://www.gnu.org/licenses/gpl-2.0.txt.
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
	die;
}

if ( !defined( 'CVDB_PLUGIN' ) ) {
	define( 'CVDB_PLUGIN', __FILE__ );
}

require_once __DIR__ . '/includes/global-eval-helper.php';
require_once __DIR__ . '/includes/plugin.class.php';
