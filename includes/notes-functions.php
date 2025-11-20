<?php

if (!defined('ABSPATH')) {
	exit;
}

// Handle note submission
function mpro_handle_note_submission() {
	if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mpro_add_note'])) {

		if (!isset($_POST['mpro_note_nonce']) || !wp_verify_nonce($_POST['mpro_note_nonce'], 'mpro_add_note')) {
			wp_die("Security check failed.");
		}

		if (!is_user_logged_in()) {
			wp_die("You must be logged in to add a note.");
		}

		$mentor_id = get_current_user_id();
		$mentee_id = isset($_POST['mentee_id']) ? intval($_POST['mentee_id']) : 0;
		$note_content = sanitize_textarea_field($_POST['note_content']);

		if (!$mentee_id || empty($note_content)) {
			wp_die("Invalid note submission.");
		}

		// Create Note Title
		$mentor_name = get_userdata($mentor_id)->display_name;
		$note_title = "Note by {$mentor_name} - " . current_time('Y-m-d');

		// Insert Note as Custom Post
		$post_id = wp_insert_post([
			'post_title'   => $note_title,
			'post_content' => $note_content,
			'post_status'  => 'publish',
			'post_type'    => 'wp_notes',
			'post_author'  => $mentor_id
		]);

		if ($post_id) {
			update_post_meta($post_id, 'related_mentee', $mentee_id);
		}

		// Redirect back with the mentee_id in the query string so the selected mentee stays loaded
		wp_redirect(add_query_arg('mentee_id', $mentee_id, $_SERVER['REQUEST_URI']));
		exit;
	}
}
add_action('init', 'mpro_handle_note_submission');

// Retrieve notes for a given mentee
function mpro_get_notes_for_mentee($mentee_id) {
	$args = [
		'post_type'   => 'wp_notes',
		'post_status' => 'publish',
		'numberposts' => -1, // Retrieve all notes
		'meta_query'  => [
			[
				'key'     => 'related_mentee',
				'value'   => $mentee_id,
				'compare' => '='
			]
		]
	];

	return get_posts($args);
}
?>