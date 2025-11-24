<?php

if (!is_user_logged_in()) {
	echo "<p>You must be logged in to view and create notes.</p>";
	return;
}

$user = wp_get_current_user();
$current_user_id = get_current_user_id();
$assigned_client = get_user_meta($current_user_id, 'assigned_client', true);
$user_roles = $user->roles;
$display_role = get_highest_priority_role($user_roles);
$display_name = $user->display_name;



if (current_user_can('manage_options')) echo '<p>Admin Debugging info -> User: ' . $display_name . ', Role: ' . $display_role . ', Client: ' . $assigned_client . '</p>'; 

/** 
if (!in_array($assigned_client, ['demo', 'mentorpro']) && !current_user_can('manage_options')) {
	echo '<p>The Notes tool is coming soon! If you would like to test it out before it goes live, <a href="mailto:support@mentorpro.com">send us an email and let us know!</a></p>';
	return;
}
**/

	echo '<div class="mpro-notes-container" style="display: flex; flex-direction: column; gap: 20px; background-color: #ECECEC; padding: 20px; border-radius: 8px;">';
	//echo '<div class="white-box">';


	$mentees = get_users([
		'role'       => 'mentee',
		'meta_query' => [
			[
				'key'     => 'assigned_client',
				'value'   => $assigned_client,
				'compare' => '='
			]
		],
		'orderby'    => 'display_name',
		'order'      => 'ASC',
		'fields'     => ['ID', 'display_name']
	]);
	
	$selected_mentee_id = isset($_POST['mentee_id']) ? intval($_POST['mentee_id']) : (isset($_GET['mentee_id']) ? intval($_GET['mentee_id']) : 0);
	
	$role = 'mentee';
	$select_id = 'user_select_' . $role;
	$button_id = 'select_all_' . $role;
	$hidden_input_id = 'share_with_all_' . $role . 's';
	$input_name = 'mentee_id';
	?>

	<?php
	// Show CSV download button for PMs and Admins
	if ($display_role === 'contract' || current_user_can('manage_options')) : ?>
		<div style="text-align: right; margin-bottom: 20px;">
			<form method="post" style="margin: 0; display: inline-block;">
				<?php wp_nonce_field('mpro_download_csv', 'mpro_csv_nonce'); ?>
				<button type="submit" name="mpro_download_notes_csv" style="background-color: #5B9B9F; color: white; border: none; padding: 12px 24px; cursor: pointer; border-radius: 6px; font-size: 15px;">
					Download all Notes
				</button>
			</form>
		</div>
	<?php endif; ?>

	<h3 style="font-style: italic; font-size: 20px; text-align: center;">Mentee notes can be created and viewed by all Mentors and PMs in a program.</h3>

	<!-- Hidden input to track Select All (even if not used here) -->
	<input type="hidden" id="<?php echo esc_attr($hidden_input_id); ?>" name="<?php echo esc_attr($hidden_input_id); ?>" value="0">

	<div>
		<form method="post">
			<label for="<?php echo esc_attr($select_id); ?>">Select a Mentee:</label>

			<!--
			<div style="margin-bottom: 10px;">
				<button type="button" id="<?php echo esc_attr($button_id); ?>" style="padding: 5px 10px;">
					Select All Mentees
				</button>
			</div>
		-->

			<select id="<?php echo esc_attr($select_id); ?>"
					name="<?php echo esc_attr($input_name); ?>"
					style="width: 100%;">
				<?php foreach ($mentees as $mentee) : ?>
					<option value="<?php echo esc_attr($mentee->ID); ?>" <?php echo ($selected_mentee_id == $mentee->ID) ? 'selected' : ''; ?>>
						<?php echo esc_html($mentee->display_name); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<div style="text-align: center; margin-top: 20px;">
				<input type="submit" value="View Mentee Notes" style="background-color: #3D5A6C; color: white; border: none; padding: 12px 24px; cursor: pointer; border-radius: 6px; font-size: 15px;">
			</div>
		</form>
	</div>

</div> <!-- end first mpro-notes-container -->

<?php
// If a mentee is selected, show notes
if ($selected_mentee_id) {
	$mentee_name = get_userdata($selected_mentee_id)->display_name;
	$notes = mpro_get_notes_for_mentee($selected_mentee_id);
	?>

<div class="mpro-notes-container" style="display: flex; flex-direction: column; gap: 20px; background-color: #ECECEC; padding: 20px; border-radius: 8px; margin-top: 60px;">

	<!-- Notes Section Header -->
	<div style="text-align: center; padding: 15px;">
		<h2 style="margin: 0; font-size: 24px;">Notes For: <?php echo esc_html($mentee_name); ?></h2>
	</div>

	<!-- Add New Note Section -->
	<div>
		<h3 style="margin-top: 0; margin-bottom: 10px;">Add a New Note</h3>
		<form method="post">
			<?php wp_nonce_field('mpro_add_note', 'mpro_note_nonce'); ?>

			<input type="hidden" name="mentee_id" value="<?php echo esc_attr($selected_mentee_id); ?>">

			<?php
			wp_editor('', 'note_content', [
				'textarea_name' => 'note_content',
				'media_buttons' => false,
				'textarea_rows' => 8,
				'teeny' => false,
				'quicktags' => true,
				'tinymce' => [
					'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink',
					'toolbar2' => ''
				]
			]);
			?>

			<div style="text-align: center; margin-top: 10px;">
				<input type="submit" name="mpro_add_note" value="Save Note" style="background-color: #5B9B9F; color: white; border: none; padding: 12px 24px; cursor: pointer; border-radius: 6px; font-size: 15px;">
			</div>
		</form>
	</div>

	<!-- View Notes Section -->
	<div>
		<h3 style="margin-top: 0; margin-bottom: 10px;">View Published Notes</h3>
		<input type="text" id="notes-search" placeholder="Search notes..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px;">

	<?php
	if ($notes) {
		echo '<div id="notes-container">'; // 🔧 START CONTAINER FOR JS SEARCH

		foreach ($notes as $note) {
			$published = get_the_date( 'Y-m-d', $note );
			$modified  = get_the_modified_date( 'Y-m-d', $note );
			$title     = $note->post_title;
			
			if ( $modified !== $published ) {
				// Append an asterisk or "edited" label.
				$title .= ' (edited '.$modified.')';
			}
			
//			error_log( 'mod ' . $modified . ' pub ' . $published);

						
			echo '<details class="accordion">';
			echo '<summary style="display: flex; justify-content: space-between; align-items: center;">';
			echo '<span style="padding-left: 15px;">' . esc_html($title) . '</span>';
			
			$is_owner_or_admin = ($note->post_author == $current_user_id) || current_user_can('manage_options');
//	error_log('Owner: ' . $note->post_author . ' current user ' . $current_user_id);
			if ($is_owner_or_admin) {			
//			if ($is_owner_or_admin || ($user_role === 'contract')) {			
				
				// Capture the nonce field output.
				$nonce_field = wp_nonce_field('wpd_delete_document', 'wpd_delete_nonce', true, false);
				$nonce_field = str_replace('id="wpd_delete_nonce"', '', $nonce_field);
				// Wrap the form in a span to keep it inline.
				echo '<span>';
				echo '<form method="post" style="margin: 0; padding: 0; display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this note?\');">';
				echo $nonce_field;
				echo '<input type="hidden" name="delete_document_id" value="' . esc_attr($note->ID) . '">';
				echo '<button type="submit" name="delete_document" style="color: red; border: none; background: none; cursor: pointer;">Delete</button>';
				echo '</form>';
				echo '</span>';
			}
			echo '</summary>'; 


			echo '<div class="content">';
			echo wp_kses_post($note->post_content);
			echo '</div>';

			if ($is_owner_or_admin) { ?>		
				<div style="text-align: right; margin: 10px;">
				<button type="button" class="edit-note-button" data-note-id="<?php echo esc_attr($note->ID); ?>">Edit</button>
			</div>
							
				<!-- Inline Edit Form (initially hidden) -->
				<div class="table-responsive">
				<div id="edit-note-form-<?php echo esc_attr($note->ID); ?>" style="display:none; margin-top:10px;">
				<form method="post">
					<?php echo wp_nonce_field('mpro_edit_note', 'mpro_edit_note_nonce', true, false); ?>
					<input type="hidden" name="note_id" value="<?php echo esc_attr($note->ID); ?>">
					<?php
					wp_editor($note->post_content, 'note_content_edit_' . $note->ID, [
						'textarea_name' => 'note_content',
						'media_buttons' => false,
						'textarea_rows' => 8,
						'teeny' => false,
						'quicktags' => true,
						'tinymce' => [
							'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink',
							'toolbar2' => ''
						]
					]);
					?>
					<div style="text-align: right; margin: 10px;">
						<button type="button" onclick="jQuery('#edit-note-form-<?php echo esc_attr($note->ID); ?>').hide();" style="background-color: #6c757d; margin-right: 10px;">Cancel</button>
						<button type="submit" name="edit_note">Save Changes</button>
					</div>
				</form>
				</div>	
				</div>
			<?php 
			}


			echo '</details>';
		}
		echo "</div>"; // end notes-container
	} else {
		echo "<p style='color: #666; font-style: italic;'>No notes have been added yet.</p>";
	}
	?>
	</div> <!-- end View Notes white-box -->

</div> <!-- end second mpro-notes-container -->

<?php
}
?>