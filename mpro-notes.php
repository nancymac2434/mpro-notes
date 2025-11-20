<?php
/**
 * Plugin Name: MPro Notes
 * Description: A note-taking system for mentors to document notes about their mentees.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Register Notes Custom Post Type
function mpro_register_notes_cpt() {
	$args = array(
		'public' => false,
		'show_ui' => true,
		'label'  => 'Notes',
		'supports' => ['editor', 'author'],
		'capability_type' => 'post',
		'capabilities' => [
			'create_posts' => 'do_not_allow', // Prevent manual note creation from dashboard
		],
		'map_meta_cap' => true
	);
	register_post_type('wp_notes', $args);
}
add_action('init', 'mpro_register_notes_cpt');

// Include functions
include_once plugin_dir_path(__FILE__) . 'includes/notes-functions.php';
include_once plugin_dir_path(__FILE__) . 'includes/delete-handler2.php';

// Add shortcode
function mpro_notes_shortcode() {
	ob_start();
	include plugin_dir_path(__FILE__) . 'includes/notes-form.php';
	return ob_get_clean();
}
add_shortcode('mpro_notes', 'mpro_notes_shortcode');

function my_plugin_enqueue_styles() {
	if ( is_page('mentee-notes') ) { // Adjust the condition as needed
		wp_enqueue_style(
			'mpro-notes-style',
			plugins_url('assets/css/style.css', __FILE__),
			array(),
			'1.0.0'
		);
	}
}
add_action('wp_enqueue_scripts', 'my_plugin_enqueue_styles');

function my_plugin_enqueue_scripts() {
	wp_enqueue_script(
		'mpro-notes-scripts', // Unique handle
		plugins_url('assets/js/script.js', __FILE__), // Path to your JS file
		array(), // Dependencies if any (e.g., array('jquery') if using jQuery)
		'1.0.0', // Version number
		true // Load in footer
	);
}
add_action('wp_enqueue_scripts', 'my_plugin_enqueue_scripts');

add_action('wp_footer', function(){
	?>
	<script>
	jQuery(document).ready(function($) {
		$('.edit-note-button').on('click', function(){
			var noteID = $(this).data('note-id');
			$('#edit-note-form-' + noteID).toggle();
		});
	});
	</script>
	<?php
});


add_action('init', 'mpro_process_edit_note');
function mpro_process_edit_note() {
	if ( isset($_POST['edit_note']) ) {
		// Verify nonce
		if ( ! isset($_POST['mpro_edit_note_nonce']) || ! wp_verify_nonce($_POST['mpro_edit_note_nonce'], 'mpro_edit_note') ) {
			wp_die('Security check failed.');
		}
		
		// Retrieve and sanitize inputs
		$note_id = isset($_POST['note_id']) ? absint($_POST['note_id']) : 0;
		$new_content = isset($_POST['note_content']) ? sanitize_textarea_field($_POST['note_content']) : '';
		
		if ( $note_id && $new_content !== '' ) {
			// Update the note (assuming it's stored as a post)
			$updated = wp_update_post([
				'ID'           => $note_id,
				'post_content' => $new_content,
			]);
			
			if ( is_wp_error( $updated ) ) {
				// Log error or handle it as needed
				error_log('Error updating note: ' . $updated->get_error_message());
			} else {
				// Optionally, you can set a query parameter to indicate success.
				$redirect_url = add_query_arg('note_updated', 'true', wp_get_referer());
				wp_redirect($redirect_url);
				exit;
			}
		}
	}
}

function mpro_notes_enqueue_custom_scripts() {
	
	// Enqueue your custom scripts file with proper dependencies.
	wp_enqueue_script(
		'mpro-custom-scripts',
		plugin_dir_url( __FILE__ ) . 'assets/js/scripts.js',
		array('jquery', 'select2'),
		'1.0',
		true
	);
	
}
add_action('wp_enqueue_scripts', 'mpro_notes_enqueue_custom_scripts');