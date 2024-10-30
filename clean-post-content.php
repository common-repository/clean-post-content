<?php 
/**
 * Plugin Name: Clean Post Content
 * Description: This plugin allows you to remove shortcodes that may appear in your posts and pages.
 * Version: 1.0.0
 * Requires PHP: 5.6.39
 * Author: Matthew Sudekum
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: clean-post-content
 */

 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

add_action('admin_enqueue_scripts', 'cpcwp_enqueue_scripts');
function cpcwp_enqueue_scripts() {
	wp_enqueue_script('jquery');

}

add_action( 'admin_menu', 'cpcwp_menu_setup' );
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'cpcwp_add_tool_link' );

function cpcwp_menu_setup() {
    add_submenu_page( 'tools.php', 'Clean Post Content', 'Clean Post Content','manage_options', 'clean-post-content', 'cpcwp_display' );
}

function cpcwp_add_tool_link( $settings ) {
   $settings[] = '<a href="'. get_admin_url(null, 'tools.php?page=clean-post-content') .'"><b>Use Tool</b></a>';
   return array_reverse($settings);
}

function cpcwp_display() {
    ?>
	
    <h1>Clean Post Content Options</h1>
    <br>
    <h2>Stored Shortcodes</h2>
    <?php cpcwp_present_data(); ?>
    <form method="post">
        <input type="submit" class="button button-primary" name="reset" value="Reset" />
    </form>
    <?php cpcwp_reset_data(); ?>
    <br>
    <h2>New Shortcodes To Replace</h2>
    <p>List shortcodes you want to remove from your post content, separated by commas. (Include the square brackets)</p>
    <form method="post">
        <textarea type='text-area' style='margin-bottom:20px; word-wrap:normal; min-height: 200px; max-height: 200px; min-width: 200px; max-width: 200px; resize: none; overflow-wrap: break-word;'
         id='shortcodes' name='shortcodes' placeholder='[example-1],[example-2]'></textarea>
        <br>
        <input type="submit" class="button button-primary" name="add" value="Add"/>
    </form>
    <?php 
	cpcwp_store_data();
	$post_types = get_post_types(array(
		'public'   => true,
		'show_ui'  => true,
	),"objects");
	?>
    <h2>Clean The Post Content</h2>
    <form method="post">
		<select name="post-type" id="post-type">
			<?php 
			foreach($post_types as $key => $post_type){
				echo '<option value="'. esc_html($post_type->name) .'">'. esc_html($post_type->label) .'</option>';
			}
			?>
		</select>
        <input type="submit" class="button button-primary" name="cleanse" value="Cleanse">
    </form>
    <?php
    if(isset($_POST['cleanse'])) {
        cpcwp_update_product_descriptions();
    }

}

function cpcwp_present_data() {
    $json = cpcwp_get_data();
    $codes = '';
    echo '<div id="reloadable"><ul style="margin-left: 20px; list-style: disc;">';
    foreach($json as $code) {
        $code = cpcwp_sanitize_code($code);
        echo '<li>'.esc_html($code['code']).'</li>';
    }
    echo '</ul></div>';
}

function cpcwp_get_data() {
    $jsonString = file_get_contents(plugin_dir_path( __FILE__ ).'codes.json');
    $data = json_decode($jsonString, true);
    return $data;
}

function cpcwp_store_data() {
    if(isset($_POST['add'])){
        if(!empty($_POST['shortcodes'])){
            $codes = cpcwp_sanitize_code($_POST['shortcodes']);
            //Find and replace any spaces
            $codes = str_replace(' ', '', $codes);
            //Divide up shortcodes
            $codes = explode(',',$codes);
			$codes = array_unique($codes);
            //Format for json insertion
            $codes_separated = [];
            foreach($codes as $code){
                $arr = array('code' => $code);
                array_push($codes_separated, $arr);
            }
            //Get current json data and append new data
            $json = cpcwp_get_data();
            foreach($codes_separated as $code) {
                array_push($json, $code);
            }
            //Encode appended data
            $jsonString = wp_json_encode($json);
            file_put_contents(plugin_dir_path( __FILE__ ).'codes.json', $jsonString);
            echo '<p style="color:green; margin-top: 0px;">Shortcodes were added successfully!</p>';
            echo wp_get_inline_script_tag('jQuery("#reloadable").load(location.href + " #reloadable");');
        }
        else {
            echo '<p style="color:red; margin-top: 0px;">Input shortcode(s) to add to the list.</p>';
        }
    }
}

function cpcwp_sanitize_code($code){
    return preg_replace('/[^\[\]a-zA-Z0-9_\/,-]/', '', $code);
}

function cpcwp_reset_data() {
    if(isset($_POST['reset'])) {
        $data = [];
        $jsonString = wp_json_encode($data);
        file_put_contents(plugin_dir_path( __FILE__ ).'codes.json', $jsonString);
        echo '<p style="color:green; margin: 0px auto -13px auto;">Shortcodes were reset successfully!</p>';
        echo wp_get_inline_script_tag('jQuery("#reloadable").load(location.href + " #reloadable");');
    }
}

function cpcwp_update_product_descriptions() {
    $data = cpcwp_get_data();
    $shortcodes = []; //Array holding shortcodes to look to replace
    foreach($data as $code) {
        array_push($shortcodes, $code['code']);
    }
    cpcwp_iterate_update($shortcodes);
    
}

function cpcwp_iterate_update($shortcodes){
	if(isset($_POST['post-type'])){
		$args = array(
			'post_type' => sanitize_text_field($_POST['post-type']),
			'posts_per_page' => -1,
		);
		$posts = new WP_Query($args);
		if($posts->have_posts()) {
			while($posts->have_posts()) {
				$posts->the_post();
				$post_id = get_the_ID();
				$content = get_the_content();
				$cleaned_content = str_replace($shortcodes, '', $content);
				
				$post_data = array(
					'ID'           => $post_id,
					'post_content' => $cleaned_content,
				);
				wp_update_post($post_data);
			}
		}
		wp_reset_postdata();
		echo '<p style="color: green; margin-top: 0px;">Success! The content has been cleansed.</p>';
	}
	else{
		echo '<p style="color:red; margin-top: 0px;">Something went wrong.</p>';
	}
}

?>