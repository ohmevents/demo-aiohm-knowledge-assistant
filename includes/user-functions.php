<?php
/**
 * User-related functions for the AIOHM KB Assistant plugin.
 * This includes functions for managing user-specific data like projects,
 * conversations, and messages.
 *
 * @package AIOHM_KB_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Creates a new project for a user.
 *
 * @param int    $user_id      The ID of the user.
 * @param string $project_name The name of the new project.
 * @return int|false The ID of the newly created project, or false on failure.
 */
function aiohm_create_project( $user_id, $project_name ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'aiohm_projects';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Project creation with cache invalidation
	$inserted = $wpdb->insert(
		$table_name,
		array(
			'user_id'       => $user_id,
			'project_name'  => $project_name,
			'creation_date' => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s' )
	);

	if ( ! $inserted ) {
		return false;
	}

	// Clear user-related project caches
	wp_cache_delete( 'aiohm_user_projects_' . $user_id, 'aiohm_user_functions' );
	wp_cache_delete( 'aiohm_all_projects', 'aiohm_user_functions' );

	return $wpdb->insert_id;
}

/**
 * Creates a new conversation associated with a project.
 *
 * @param int    $user_id    The ID of the user.
 * @param int    $project_id The ID of the project.
 * @param string $title      The title of the conversation.
 * @return int|false The ID of the new conversation, or false on failure.
 */
function aiohm_create_conversation( $user_id, $project_id, $title ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'aiohm_conversations';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Conversation creation with cache invalidation
	$result = $wpdb->insert(
		$table_name,
		array(
			'user_id'    => $user_id,
			'project_id' => $project_id,
			'title'      => $title,
			'created_at' => current_time( 'mysql', 1 ),
			'updated_at' => current_time( 'mysql', 1 ),
		),
		// FIX: Added correct data formats for the insert query.
		array(
			'%d', // user_id
			'%d', // project_id
			'%s', // title
			'%s', // created_at
			'%s', // updated_at
		)
	);

	// FIX: Check if the insert failed.
	if ( ! $result ) {
		return false;
	}

	// Clear conversation-related caches
	wp_cache_delete( 'aiohm_user_conversations_' . $user_id, 'aiohm_user_functions' );
	wp_cache_delete( 'aiohm_project_conversations_' . $project_id, 'aiohm_user_functions' );

	return $wpdb->insert_id;
}

/**
 * Adds a message to a conversation.
 *
 * @param int    $conversation_id The ID of the conversation.
 * @param string $sender          The sender of the message ('user' or 'ai').
 * @param string $content         The message content.
 * @return bool True on success, false on failure.
 */
function aiohm_add_message_to_conversation( $conversation_id, $sender, $content ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'aiohm_messages';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Message creation with cache invalidation
	$result = $wpdb->insert(
		$table_name,
		array(
			'conversation_id' => $conversation_id,
			'sender'          => $sender,
			'content'         => $content,
			'created_at'      => current_time( 'mysql', 1 ),
		),
		// FIX: Added correct data formats for the insert query.
		array(
			'%d', // conversation_id
			'%s', // sender
			'%s', // content
			'%s', // created_at
		)
	);

	// Also update the 'updated_at' timestamp of the parent conversation
    if ($result) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conversation timestamp update with cache clearing
        $wpdb->update(
            $wpdb->prefix . 'aiohm_conversations',
            ['updated_at' => current_time('mysql', 1)],
            ['id' => $conversation_id],
            ['%s'],
            ['%d']
        );
        
        // Clear message and conversation caches
        wp_cache_delete( 'aiohm_conversation_messages_' . $conversation_id, 'aiohm_user_functions' );
        wp_cache_delete( 'aiohm_conversation_' . $conversation_id, 'aiohm_user_functions' );
    }
	
	// FIX: Return true or false to indicate if the save was successful.
	return $result !== false;
}