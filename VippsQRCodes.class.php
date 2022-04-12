<?php


if ( ! function_exists('register_vipps_qr_code') ) {

// Register Custom Post Type
function register_vipps_qr_code() {

	$labels = array(
		'name'                  => _x( 'Vipps QR Codes', 'Post Type General Name', 'woo-vipps' ),
		'singular_name'         => _x( 'Vipps QR Code', 'Post Type Singular Name', 'woo-vipps' ),
		'menu_name'             => __( 'QR Codes', 'woo-vipps' ),
		'name_admin_bar'        => __( 'Vipps QR Codes', 'woo-vipps' ),
		'archives'              => __( 'Item Archives', 'woo-vipps' ),
		'attributes'            => __( 'Item Attributes', 'woo-vipps' ),
		'parent_item_colon'     => __( 'Parent Item:', 'woo-vipps' ),
		'all_items'             => __( 'QR Codes', 'woo-vipps' ),
		'add_new_item'          => __( 'Add New QR Code', 'woo-vipps' ),
		'add_new'               => __( 'Add New', 'woo-vipps' ),
		'new_item'              => __( 'New QR Code', 'woo-vipps' ),
		'edit_item'             => __( 'Edit QR Code', 'woo-vipps' ),
		'update_item'           => __( 'Update QR Code', 'woo-vipps' ),
		'view_item'             => __( 'View QR Code', 'woo-vipps' ),
		'view_items'            => __( 'View QR Codes', 'woo-vipps' ),
		'search_items'          => __( 'Search Item', 'woo-vipps' ),
		'not_found'             => __( 'Not found', 'woo-vipps' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'woo-vipps' ),
		'featured_image'        => __( 'Featured Image', 'woo-vipps' ),
		'set_featured_image'    => __( 'Set featured image', 'woo-vipps' ),
		'remove_featured_image' => __( 'Remove featured image', 'woo-vipps' ),
		'use_featured_image'    => __( 'Use as featured image', 'woo-vipps' ),
		'insert_into_item'      => __( 'Insert into item', 'woo-vipps' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'woo-vipps' ),
		'items_list'            => __( 'Items list', 'woo-vipps' ),
		'items_list_navigation' => __( 'Items list navigation', 'woo-vipps' ),
		'filter_items_list'     => __( 'Filter items list', 'woo-vipps' ),
	);
	$capabilities = array(
		'edit_post'             => 'edit_post',
		'read_post'             => 'read_post',
		'delete_post'           => 'delete_post',
		'edit_posts'            => 'edit_posts',
		'edit_others_posts'     => 'edit_others_posts',
		'publish_posts'         => 'publish_posts',
		'read_private_posts'    => 'read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Vipps QR Code', 'woo-vipps' ),
		'description'           => __( 'Vipps QR Codes', 'woo-vipps' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'custom-fields' ),
		'taxonomies'            => array( 'post', 'tag' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 10,
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => false,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => true,
		'capabilities'          => $capabilities,
		'show_in_rest'          => true,
	);
	register_post_type( 'vipps_qr_code', $args );

}
add_action( 'init', 'register_vipps_qr_code', 0 );

}
