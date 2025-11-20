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

	echo '<div style="display: flex; flex-direction: column; gap: 20px; background-color: #F0F0F0;">';
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
	
	<h3 style="font-style: italic;">Mentee notes can be created and viewed by all Mentors and PMs in a program.</h3>
	
	<!-- Hidden input to track Select All (even if not used here) -->
	<input type="hidden" id="<?php echo esc_attr($hidden_input_id); ?>" name="<?php echo esc_attr($hidden_input_id); ?>" value="0">
	
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
	
		<br><br>
		<input type="submit" value="View Notes" style="background-color: #2B4D59; color: white; border: none; padding: 10px; cursor: pointer;">
	</form>




<?php
	echo '<div class="white-box">';

// If a mentee is selected, show notes
if ($selected_mentee_id) { ?>
	
	<!-- Notes Form -->
	<form method="post">
		<?php wp_nonce_field('mpro_add_note', 'mpro_note_nonce'); ?>
		
		<input type="hidden" name="mentee_id" value="<?php echo esc_attr($selected_mentee_id); ?>">
	
		<textarea name="note_content" placeholder="Write a note..." required style="width:100%; height:100px;"></textarea>
	
		<input type="submit" name="mpro_add_note" value="Save Note" style="width:15%; background-color: #2B4D59; color: white; border: none; padding: 10px; cursor: pointer;">
	</form>
	
	<?php
	$notes = mpro_get_notes_for_mentee($selected_mentee_id);
	//error_log( 'Notes ' . json_encode($notes));

	echo '</div><div class="white-box">';


	echo '<input type="text" id="notes-search" placeholder="Search notes..." style="margin-bottom: 20px; width: 100%; padding: 8px;">';
	if ($notes) {
		echo "<h3>Notes for " . esc_html(get_userdata($selected_mentee_id)->display_name) . ":";
		echo "</h3>";
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
			echo nl2br(esc_html($note->post_content));
			echo '</div>';

			if ($is_owner_or_admin) { ?>		
				<button type="button" class="edit-note-button" data-note-id="<?php echo esc_attr($note->ID); ?>" style="margin-right: 5px;">Edit</button>
							
				<!-- Inline Edit Form (initially hidden) -->
				<div class="table-responsive">
				<div id="edit-note-form-<?php echo esc_attr($note->ID); ?>" style="display:none; margin-top:10px;">
				<form method="post">
					<?php echo wp_nonce_field('mpro_edit_note', 'mpro_edit_note_nonce', true, false); ?>
					<input type="hidden" name="note_id" value="<?php echo esc_attr($note->ID); ?>">
					<textarea name="note_content" rows="5" cols="60"><?php echo esc_textarea($note->post_content); ?></textarea>
					<br>
					<button type="submit" name="edit_note">Save Changes</button>
				</form>
				</div>	
				</div>
			<?php 
			}


			echo '</details>';
		}
		echo "</div>";
	} else {
		echo "<h3>No notes found for " . esc_html(get_userdata($selected_mentee_id)->display_name) . ".";

	}
	echo '</div>';	
echo '</div>';	


}
 
?>