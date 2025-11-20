<?php
if (!defined('ABSPATH')) {
	exit;
}

// note- same exact function as used in document manager. Could be put into another plugin, or saved somewhere and loaded...

function wpd_handle_document_delete2() {
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
		if (!isset($_POST['wpd_delete_nonce']) || !wp_verify_nonce($_POST['wpd_delete_nonce'], 'wpd_delete_document')) {
			die('Security check failed.');
		}

		if (!is_user_logged_in()) {
			die('You must be logged in to delete notes.');
		}

		$document_id = intval($_POST['delete_document_id']);
		if (!$document_id) {
			die('Invalid note ID.');
		}

		$document = get_post($document_id);
		$current_user_id = get_current_user_id();

		// Only allow deletion if the user is the owner or an admin or a PM (contract)
		$user_roles = wp_get_current_user()->roles;
		$user_role = get_highest_priority_role($user_roles);
		//$assigned_client = get_user_meta($user_id, 'assigned_client', true);

		if ($document && ($document->post_author == $current_user_id || current_user_can('manage_options') || $user_role === 'contract')) {
			// Get the file path
			$file_url = get_post_meta($document_id, 'document_url', true);
			$upload_dir = wp_upload_dir();
			$file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

			// Delete file from the server
			if (file_exists($file_path)) {
				unlink($file_path);
			}

			// Delete post and associated metadata
			wp_delete_post($document_id, true);

			// ✅ Redirect to prevent resubmission
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		} else {
			die('You do not have permission to delete this note.');
		}
	}
}
add_action('init', 'wpd_handle_document_delete2');