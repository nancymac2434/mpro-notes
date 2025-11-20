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
		$note_content = wp_kses_post($_POST['note_content']);

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

// Handle CSV download for PMs
function mpro_handle_csv_download() {
	if (isset($_POST['mpro_download_notes_csv'])) {

		if (!isset($_POST['mpro_csv_nonce']) || !wp_verify_nonce($_POST['mpro_csv_nonce'], 'mpro_download_csv')) {
			wp_die("Security check failed.");
		}

		if (!is_user_logged_in()) {
			wp_die("You must be logged in to download notes.");
		}

		// Check if user is PM (contract) or admin
		$user_roles = wp_get_current_user()->roles;
		$user_role = get_highest_priority_role($user_roles);

		if ($user_role !== 'contract' && !current_user_can('manage_options')) {
			wp_die("You do not have permission to download notes.");
		}

		// Get all notes for the assigned client
		$current_user_id = get_current_user_id();
		$assigned_client = get_user_meta($current_user_id, 'assigned_client', true);

		// Debug output
		if (current_user_can('manage_options')) {
			error_log("CSV Download Debug - User ID: $current_user_id, Assigned Client: $assigned_client");
		}

		// Get all mentees for this client
		$mentee_ids = get_users([
			'role'       => 'mentee',
			'meta_query' => [
				[
					'key'     => 'assigned_client',
					'value'   => $assigned_client,
					'compare' => '='
				]
			],
			'fields'     => 'ID'
		]);

		// Debug output
		if (current_user_can('manage_options')) {
			error_log("CSV Download Debug - Found " . count($mentee_ids) . " mentees: " . implode(', ', $mentee_ids));
		}

		if (empty($mentee_ids)) {
			wp_die("No mentees found for your client ($assigned_client). Debug: User ID = $current_user_id");
		}

		// Get all notes for these mentees
		$args = [
			'post_type'   => 'wp_notes',
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_query'  => [
				[
					'key'     => 'related_mentee',
					'value'   => $mentee_ids,
					'compare' => 'IN'
				]
			],
			'orderby'     => 'date',
			'order'       => 'ASC'
		];

		$notes = get_posts($args);

		// Debug output
		if (current_user_can('manage_options')) {
			error_log("CSV Download Debug - Found " . count($notes) . " notes");
		}

		if (empty($notes)) {
			wp_die("No notes found for the mentees in your client ($assigned_client). Debug: Found " . count($mentee_ids) . " mentees");
		}

		// Generate CSV
		$filename = 'mentee-notes-' . $assigned_client . '-' . date('Y-m-d') . '.csv';

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $filename);

		$output = fopen('php://output', 'w');

		// Add BOM for proper Excel UTF-8 support
		fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

		// CSV Headers
		fputcsv($output, ['Mentee Name', 'Mentor Name', 'Date Created', 'Date Modified', 'Note Content']);

		// Add data rows
		foreach ($notes as $note) {
			$mentee_id = get_post_meta($note->ID, 'related_mentee', true);
			$mentee = get_userdata($mentee_id);
			$mentor = get_userdata($note->post_author);

			$date_created = get_the_date('Y-m-d', $note);
			$date_modified = get_the_modified_date('Y-m-d', $note);

			// If dates are the same, leave modified blank
			if ($date_created === $date_modified) {
				$date_modified = '';
			}

			fputcsv($output, [
				$mentee ? $mentee->display_name : 'Unknown',
				$mentor ? $mentor->display_name : 'Unknown',
				$date_created,
				$date_modified,
				$note->post_content
			]);
		}

		fclose($output);
		exit;
	}
}
add_action('init', 'mpro_handle_csv_download');
?>