<?php
/*
Plugin Name: Options Framework
Plugin URI: http://www.wptheming.com
Description: A framework for building theme options.
Version: 0.1
Author: Devin Price
Author URI: http://www.wptheming.com
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/* Basic plugin definitions */

define('OPTIONS_FRAMEWORK_VERSION', '0.1');
define('OPTIONS_FRAMEWORK_URL', plugin_dir_url( __FILE__ ));

/* Make sure we don't expose any info if called directly */

if ( !function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a little plugin, don't mind me.";
	exit;
}

/* If the user can't edit theme options, no use running this plugin */

function optionsframework_rolescheck () {
	if ( current_user_can('edit_theme_options') ) {
		// If the user can edit theme options, let the fun begin!
		add_action('admin_menu', 'optionsframework_add_page');
		add_action('admin_init', 'optionsframework_init' );
		add_action( 'admin_init', 'optionsframework_mlu_init' );
		add_action('after_setup_theme', 'optionsframework_helpers');
	}
}
add_action('init', 'optionsframework_rolescheck' );

/* Might add an activation message on install */

register_activation_hook(__FILE__,'optionsframework_activation_hook'); 
function optionsframework_activation_hook() {
	// But for now, nothing
}

/* When uninstalled, deletes options */

register_uninstall_hook( __FILE__, 'optionsframework_delete_options' );
function optionsframework_delete_options() {
	delete_option('of_theme_options');
}

/* 
 * Creates the settings in the database by looping through the array
 * we supplied in options.php.  This is a neat way to do it since
 * we won't have to save settings for headers, descriptions, or arguments-
 * and it makes it a little easier to change and set up in my opinion.
 *
 * Read more about the Settings API in the WordPress codex:
 * http://codex.wordpress.org/Settings_API
 *
 */

function optionsframework_init() {

	// Include the required files
	require_once dirname( __FILE__ ) . '/options-interface.php';
	require_once dirname( __FILE__ ) . '/options-medialibrary-uploader.php';
	
	// Loads the options array from the theme
	if ( $optionsfile = locate_template( array('options.php') ) ) {
		require_once($optionsfile);
	}
	else if (file_exists( dirname( __FILE__ ) . '/options.php' ) ) {
		require_once dirname( __FILE__ ) . '/options.php';
	}
	
	register_setting('of_theme_options', 'of_theme_options', 'optionsframework_validate' );
	
	// I'm not sure this is working perfectly yet, will need to test more
	optionsframework_setdefaults();
}

/* 
 * Add default options to the database if they aren't already present.
 * May update this later to load only on plugin activation, or theme
 * activation since most people won't be editing the options.php
 * on a regular basis.
 *
 * http://codex.wordpress.org/Function_Reference/add_option
 *
 */

function optionsframework_setdefaults() {
	
	// Grab the default options data from the array in options.php
	$options = of_options();
		
	// If the options haven't been added to the database yet, it gets added now
	foreach ($options as $option) {
		if ( ($option['type'] != 'heading') && ($option['type'] != 'info') ) {
			$opt_id = preg_replace("/\W/", "", strtolower($option['id']) );
			// Excluding arrays for the moment since wp_filter_post_kses accepts only strings
			if ( isset($option['std' ]) && !is_array($option['std' ]) ) {
				$value = wp_filter_post_kses($option['std']);
			} else {
				$value = '';
			}
			$values[$opt_id] = $value;
		}
	}
	add_option('of_theme_options', $values);
}

/* Add a subpage called "Theme Options" to the appearance menu. */

if ( !function_exists( 'optionsframework_add_page' ) ) {
function optionsframework_add_page() {

	$of_page = add_submenu_page('themes.php', 'Theme Options', 'Theme Options', 'edit_theme_options', 'options-framework','optionsframework_page');
	
	// Loads the required css and javascripts
	add_action("admin_print_styles-$of_page",'optionsframework_load_styles');
	add_action("admin_print_scripts-$of_page", 'optionsframework_load_scripts');
	
}
}

/* Load the required CSS */

function optionsframework_load_styles() {
	wp_enqueue_style('admin-style', OPTIONS_FRAMEWORK_URL.'css/admin-style.css');
	wp_enqueue_style('color-picker', OPTIONS_FRAMEWORK_URL.'css/colorpicker.css');
}	

/* 
 * Loads the javascripts required to make those sweet fading effects.
 * You'll notice the inline script called by of_admin_head is actually
 * hanging out with the rest of the party in options-interface.php.
 *
 */

function optionsframework_load_scripts() {

	// Loads inline scripts in options-interface.php
	add_action('admin_head', 'of_admin_head');
	
	// Loads enqueued scripts
	wp_enqueue_script('jquery-ui-core');
	wp_register_script('jquery-input-mask', OPTIONS_FRAMEWORK_URL.'js/jquery.maskedinput-1.2.2.js', array( 'jquery' ));
	wp_enqueue_script('jquery-input-mask');
	wp_enqueue_script('color-picker', OPTIONS_FRAMEWORK_URL.'js/colorpicker.js', array('jquery'));
}

/* 
 * Let's build out the options panel.
 *
 * If we were using the Settings API as it was likely intended
 * we would call do_settings_sections here.  But as we don't want the
 * settings wrapped in a table, we'll call our own custom
 * optionsframework_fields.  Saunter over to options-interface.php
 * if you want to see how each individual field is generated.
 *
 * Nonces are provided using the settings_fields()
 *
 */

if ( !function_exists( 'optionsframework_page' ) ) {
function optionsframework_page() {

	// Get the theme name so we can display it up top
	$themename = get_theme_data(STYLESHEETPATH . '/style.css');
	$themename = $themename['Name'];
	$message = '';
	
	// Reset isn't working yet, just testing
	if ( isset( $_GET['reset'] ) )
		$message = __( 'Options reset' );
		
	if ( isset( $_GET['updated'] ) )
		$message = __( 'Options saved' );
	?>
    
	<div class="wrap">
    <?php screen_icon( 'themes' ); ?>
	<h2><?php _e('Theme Options'); ?></h2>
    
    <?php if ($message) { ?>
    	<div id="message" class="updated fade"><p><strong><?php echo $message; ?></strong></p></div>
    <?php } ?>
    
    <div id="of_container">
       <form action="options.php" method="post">
	  <?php settings_fields('of_theme_options'); ?>

        <div id="header">
          <div class="logo">
            <h2><?php echo $themename; ?></h2>
          </div>
          <div class="clear"></div>
        </div>
        <div id="main">
        <?php $return = optionsframework_fields(); ?>
          <div id="of-nav">
            <ul>
              <?php echo $return[1]; ?>
            </ul>
          </div>
          <div id="content">
            <?php echo $return[0]; /* Settings */ ?>
          </div>
          <div class="clear"></div>
        </div>
        <div class="of_admin_bar">
			<input type="submit" class="button-primary" name="of_theme_options[update]" value="<?php _e( 'Save Options' ); ?>" />
            </form>
            
            <input type="submit" class="reset-button" name="of_theme_options[reset]" value="<?php _e('Reset to Default')?>" onclick="return confirm('Click OK to reset. Any theme settings will be lost! NOTE: This is not working yet.');"/>
		</div>
<div class="clear"></div>
</div> <!-- / #container -->  
</div> <!-- / .wrap -->

<?php
}
}

/* 
 * Data sanitization!
 *
 * This runs after the submit button has been clicked and checks
 * the fields for stuff that's not supposed to be there.
 *
 */

if ( !function_exists( 'optionsframework_validate' ) ) {
function optionsframework_validate($input) {

	// Get the options array we have defined in options.php
	$options = of_options();
	
	foreach ($options as $option) {
		// Verify that the option has an id
		if ( isset ($option['id']) ) {
			// Verify that there's a value in the $input
			if (isset ($input[($option['id'])]) ) {
		
				switch ( $option['type'] ) {
				
				// If it's a checkbox, make sure it's either null or checked
				case ($option['type'] == 'checkbox'):
					if ( !empty($input[($option['id'])]) )
						$input[($option['id'])] = 'true';
				break;
				
				// If it's a multicheck
				case ($option['type'] == 'multicheck'):
					$i = 0;
					foreach ($option['options'] as $key) {
						// Make sure the key is lowercase and without spaces
						$key = ereg_replace("[^A-Za-z0-9]", "", strtolower($key));
						// Check that the option isn't null
						if (!empty($input[($option['id']. '_' . $key)])) {
							// If it's not null, make sure it's true, add it to an array
							if ( $input[($option['id']. '_' . $key)] ) {
								$input[($option['id']. '_' . $key)] = 'true';
								$checkboxarray[$i] = $key;
								$i++;
							}
						}
					}
					// Take all the items that were checked, and set them as the main option
					if (!empty($checkboxarray)) {
						$input[($option['id'])] = $checkboxarray;
					}
				break;
				
				// If it's a typography option
				case ($option['type'] == 'typography') :
					$typography_id = $option['id'];
					$input[$typography_id] = array('size' => $input[$typography_id .'_size'],
												  'face' => $input[$typography_id .'_face'],
												  'style' => $input[$typography_id .'_style'],
												  'color' => $input[$typography_id .'_color']);
				break;
				
				// If it's a select make sure it's in the array we supplied
				case ($option['type'] == 'select' || $option['type'] == 'select2') :
					if ( ! in_array( $input[($option['id'])], $option['options'] ) )
						$input[($option['id'])] = null;
				break;
				
				// For the remaining options, strip any tags that aren't allowed in posts
				default:
					$input[($option['id'])] = wp_filter_post_kses( $input[($option['id'])] );
				
				}
			}
		}
	
	}
	
	return $input; // Return validated input
	
}
}

/* Loads after theme_setup, so it can be overridden */

function optionsframework_helpers() {

	/* 
	 * Helper function to return the theme option.
	 * If no value has been saved, returns $default.
	 *
	 */
	
	if ( !function_exists( 'of_get_option' ) ) {
	function of_get_option($name, $default) {
		if ( get_option('of_theme_options') ) {
			$options = get_option('of_theme_options');
		}
		
		if ( !empty($options[$name]) ) {
			return $options[$name];
		} else {
			return $default;
		}
	}
	}
}