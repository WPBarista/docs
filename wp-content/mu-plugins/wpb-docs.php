<?php
/**
 * Plugin Name: WPB Docs
 * Description: docs library for WPB themes & code, install in wp-content/mu-plugins
 * Author: wpbarista.com 
 * Version: 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! is_admin() ) {
	return;
}

/* -------------------------------------------------------------------------
 * 1. The 'doc' CPT
 * ---------------------------------------------------------------------- */

add_action( 'init', 'wpb_register_doc_cpt' );

function wpb_admin_only_capabilities() {
	return array(
		'edit_posts'             => 'manage_options',
		'edit_others_posts'      => 'manage_options',
		'publish_posts'          => 'manage_options',
		'read_private_posts'     => 'manage_options',
		'delete_posts'           => 'manage_options',
		'delete_private_posts'   => 'manage_options',
		'delete_published_posts' => 'manage_options',
		'delete_others_posts'    => 'manage_options',
		'edit_private_posts'     => 'manage_options',
		'edit_published_posts'   => 'manage_options',
		'create_posts'           => 'manage_options',
	);
}






function wpb_register_doc_cpt() {

	register_post_type(
		'doc',
		array(
			'labels'              => array(
				'name'          => __( 'Docs', 'wpb' ),
				'singular_name' => __( 'Doc', 'wpb' ),
				'menu_name'     => __( 'Docs', 'wpb' ),
				'add_new_item'  => __( 'Add New Doc', 'wpb' ),
				'edit_item'     => __( 'Edit Doc', 'wpb' ),
				'all_items'     => __( 'All Docs (edit)', 'wpb' ),
				'search_items'  => __( 'Search Docs', 'wpb' ),
				'not_found'     => __( 'No docs found.', 'wpb' ),
				'parent_item_colon'  => __( 'Parent Doc:', 'wpb' ),
			),

			// Admin only
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_in_rest'        => false, 

			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => false,
			'menu_position'       => 58,
			'menu_icon'           => 'dashicons-media-text',

			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'capabilities'        => wpb_admin_only_capabilities(),

			// Hierarchy: adds the Parent selector (via page-attributes) and nested list screens.
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'revisions', 'page-attributes' ),
		)
	);

	register_taxonomy(
		'doc_category',
		'doc',
		array(
			'labels'            => array(
				'name'          => __( 'Doc Categories', 'wpb' ),
				'singular_name' => __( 'Doc Category', 'wpb' ),
				'menu_name'     => __( 'Categories', 'wpb' ),
			),
			'public'            => false,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'query_var'         => false,
			'rewrite'           => false,
			'capabilities'      => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}




function wpb_get_docs_tree() {

	$pages = get_pages(
		array(
			'post_type'    => 'doc',
			'post_status'  => 'publish',
			'sort_column'  => 'menu_order, post_title',
			'hierarchical' => true,
		)
	);

	$tree = array();
	foreach ( (array) $pages as $page ) {
		$tree[] = array(
			'post'  => $page,
			'depth' => count( get_post_ancestors( $page ) ),
		);
	}

	return $tree;
}




/* -------------------------------------------------------------------------
 * 2. Viewing template
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'wpb_register_docs_view_page' );

function wpb_register_docs_view_page() {
	add_submenu_page(
		'edit.php?post_type=doc',
		__( 'View Docs', 'wpb' ),
		__( 'View Docs', 'wpb' ),
		apply_filters( 'wpb_docs_view_capability', 'edit_posts' ), 
		'wpb-docs-view',
		'wpb_render_docs_view_page'
	);
}

function wpb_render_docs_view_page() {

	if ( ! current_user_can( apply_filters( 'wpb_docs_view_capability', 'edit_posts' ) ) ) {
		wp_die( esc_html__( 'Please see your administrator for permissions to view this page.', 'wpb' ) );
	}

	$tree       = wpb_get_docs_tree();
	$current_id = isset( $_GET['doc'] ) ? absint( $_GET['doc'] ) : 0;

	echo '<div class="wrap"><h1>' . esc_html__( 'Docs', 'wpb' ) . '</h1>';

	if ( empty( $tree ) ) {
		echo '<p>' . esc_html__( 'No documentation yet.', 'wpb' ) . '</p></div>';
		return;
	}

	echo '<div style="display:flex;gap:2rem;margin-top:1rem;">';

	echo '<ul style="min-width:240px;list-style:none;margin:0;padding:0;">';
	foreach ( $tree as $item ) {
		$doc    = $item['post'];
		$url    = esc_url( add_query_arg( array( 'doc' => $doc->ID ) ) );
		$style  = 'margin:0 0 6px ' . ( (int) $item['depth'] * 16 ) . 'px;';
		$style .= ( (int) $doc->ID === $current_id ) ? 'font-weight:600;' : '';
		echo '<li style="' . esc_attr( $style ) . '"><a href="' . $url . '">' . esc_html( $doc->post_title ) . '</a></li>';
	}
	echo '</ul>';

	echo '<div style="flex:1;">';
	$selected = $current_id ? get_post( $current_id ) : $tree[0]['post'];
	if ( $selected && 'doc' === $selected->post_type ) {
		echo '<h2>' . esc_html( $selected->post_title ) . '</h2>';
		echo apply_filters( 'the_content', $selected->post_content ); // phpcs:ignore -- read-only render of trusted admin-authored content
	}
	echo '</div>';

	echo '</div></div>';
}



/* -------------------------------------------------------------------------
 * 3. Export — admin only. Pick which docs to bundle; produces a JSON
 *    download. This is the entire curation step: nothing is exported
 *    unless it's checked here. Parent docs of anything checked are
 *    included automatically so the hierarchy survives.
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', 'wpb_register_docs_export_page' );

function wpb_register_docs_export_page() {
	add_submenu_page(
		'edit.php?post_type=doc',
		__( 'Export Docs', 'wpb' ),
		__( 'Export', 'wpb' ),
		'manage_options',
		'wpb-docs-export',
		'wpb_render_docs_export_page'
	);
}

function wpb_render_docs_export_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Please see your administrator for permissions to view this page.', 'wpb' ) );
	}

	$tree = wpb_get_docs_tree();

	echo '<div class="wrap"><h1>' . esc_html__( 'Export Docs', 'wpb' ) . '</h1>';

	if ( empty( $tree ) ) {
		echo '<p>' . esc_html__( 'No docs to export yet.', 'wpb' ) . '</p></div>';
		return;
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'wpb_docs_export' );
	echo '<input type="hidden" name="action" value="wpb_docs_export" />';

	echo '<p><label><input type="checkbox" id="wpb-select-all" /> ' . esc_html__( 'Select all', 'wpb' ) . '</label></p>';

	echo '<ul style="list-style:none;margin:0;padding:0;">';
	foreach ( $tree as $item ) {
		$doc = $item['post'];
		echo '<li style="margin:0 0 6px ' . ( (int) $item['depth'] * 20 ) . 'px;"><label><input type="checkbox" class="wpb-doc-cb" name="doc_ids[]" value="' . esc_attr( $doc->ID ) . '" /> ' . esc_html( $doc->post_title ) . '</label></li>';
	}
	echo '</ul>';

	echo '<p class="description">' . esc_html__( 'Parent docs of anything you select are included automatically.', 'wpb' ) . '</p>';

	echo '<script>
		document.getElementById("wpb-select-all").addEventListener("change", function () {
			document.querySelectorAll(".wpb-doc-cb").forEach(function (cb) { cb.checked = this.checked; }, this);
		});
	</script>';

	submit_button( __( 'Download Export File', 'wpb' ) );
	echo '</form></div>';
}

add_action( 'admin_post_wpb_docs_export', 'wpb_handle_docs_export' );

function wpb_handle_docs_export() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Please see your administrator for permissions to view this page.', 'wpb' ) );
	}
	check_admin_referer( 'wpb_docs_export' );

	$doc_ids = isset( $_POST['doc_ids'] ) ? array_map( 'absint', (array) $_POST['doc_ids'] ) : array();

	if ( empty( $doc_ids ) ) {
		wp_safe_redirect( add_query_arg( 'wpb_export_error', '1', wp_get_referer() ) );
		exit;
	}

	// Add the ancestors of every selected doc.
	$all_ids = array();
	foreach ( $doc_ids as $id ) {
		$all_ids[] = $id;
		foreach ( get_post_ancestors( $id ) as $ancestor_id ) {
			$all_ids[] = (int) $ancestor_id;
		}
	}
	$all_ids = array_unique( $all_ids );

	$export = array(
		'generated' => gmdate( 'c' ),
		'docs'      => array(),
	);

	foreach ( $all_ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || 'doc' !== $post->post_type ) {
			continue;
		}

		$terms = wp_get_post_terms( $id, 'doc_category', array( 'fields' => 'names' ) );

		$export['docs'][] = array(
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'path'       => get_page_uri( $post ), // e.g. "setup/child-theme"; identifies the doc and its place in the tree
			'content'    => $post->post_content,
			'menu_order' => $post->menu_order,
			'categories' => is_wp_error( $terms ) ? array() : $terms,
		);
	}

	// Parents before children.
	usort(
		$export['docs'],
		function ( $a, $b ) {
			return substr_count( $a['path'], '/' ) <=> substr_count( $b['path'], '/' );
		}
	);

	$filename = 'docs-export-' . gmdate( 'Y-m-d' ) . '.json';

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
}


/* -------------------------------------------------------------------------
 * 4. Import — matches existing docs by path (parent/child slugs), updates
 *    them or creates new ones, and rebuilds the parent/child structure.
 *    Older export files that only have 'slug' still import (as top-level docs).
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'wpb_register_docs_import_page' );

function wpb_register_docs_import_page() {
	add_submenu_page(
		'edit.php?post_type=doc',
		__( 'Import Docs', 'wpb' ),
		__( 'Import', 'wpb' ),
		'manage_options',
		'wpb-docs-import',
		'wpb_render_docs_import_page'
	);
}

function wpb_render_docs_import_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Contact your administrator to use this function.', 'wpb' ) );
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Import Docs', 'wpb' ) . '</h1>';

	if ( isset( $_GET['wpb_imported'] ) ) {
		$count = absint( $_GET['wpb_imported'] );
		echo '<div class="notice notice-success"><p>' .
			esc_html( sprintf( _n( '%d doc imported.', '%d docs imported.', $count, 'wpb' ), $count ) ) .
			'</p></div>';
	}
	if ( isset( $_GET['wpb_import_error'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'That file could not be read as a docs export.', 'wpb' ) . '</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
	wp_nonce_field( 'wpb_docs_import' );
	echo '<input type="hidden" name="action" value="wpb_docs_import" />';
	echo '<input type="file" name="wpb_import_file" accept="application/json" required />';
	submit_button( __( 'Import File', 'wpb' ) );
	echo '</form></div>';
}

add_action( 'admin_post_wpb_docs_import', 'wpb_handle_docs_import' );

function wpb_handle_docs_import() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Contact your administrator to use this function.', 'wpb' ) );
	}
	check_admin_referer( 'wpb_docs_import' );

	if ( empty( $_FILES['wpb_import_file']['tmp_name'] ) ) {
		wp_safe_redirect( add_query_arg( 'wpb_import_error', '1', wp_get_referer() ) );
		exit;
	}

	$raw  = file_get_contents( $_FILES['wpb_import_file']['tmp_name'] ); // phpcs:ignore -- local temp upload, not a remote fetch
	$data = json_decode( $raw, true );

	if ( empty( $data['docs'] ) || ! is_array( $data['docs'] ) ) {
		wp_safe_redirect( add_query_arg( 'wpb_import_error', '1', wp_get_referer() ) );
		exit;
	}

	// Normalise each entry's path and drop unusable entries.
	$docs = array();
	foreach ( $data['docs'] as $doc ) {

		if ( empty( $doc['title'] ) || ! isset( $doc['content'] ) ) {
			continue;
		}

		$raw_path = ! empty( $doc['path'] ) ? $doc['path'] : ( ! empty( $doc['slug'] ) ? $doc['slug'] : $doc['title'] );
		$parts    = array_filter( array_map( 'sanitize_title', explode( '/', $raw_path ) ) );

		if ( empty( $parts ) ) {
			continue;
		}

		$doc['_path'] = implode( '/', $parts );
		$docs[]       = $doc;
	}

	// Parents must exist before their children.
	usort(
		$docs,
		function ( $a, $b ) {
			return substr_count( $a['_path'], '/' ) <=> substr_count( $b['_path'], '/' );
		}
	);

	$imported = 0;

	foreach ( $docs as $doc ) {

		$path      = $doc['_path'];
		$last_slash = strrpos( $path, '/' );
		$slug      = false === $last_slash ? $path : substr( $path, $last_slash + 1 );
		$parent_id = 0;

		if ( false !== $last_slash ) {
			$parent = get_page_by_path( substr( $path, 0, $last_slash ), OBJECT, 'doc' );
			if ( $parent ) {
				$parent_id = $parent->ID;
			}
		}

		$existing = get_page_by_path( $path, OBJECT, 'doc' );

		$post_args = array(
			'post_type'    => 'doc',
			'post_title'   => sanitize_text_field( $doc['title'] ),
			'post_name'    => $slug,
			'post_parent'  => $parent_id,
			'post_content' => wp_kses_post( $doc['content'] ),
			'post_status'  => 'publish',
			'menu_order'   => isset( $doc['menu_order'] ) ? absint( $doc['menu_order'] ) : 0,
		);

		if ( $existing ) {
			$post_args['ID'] = $existing->ID;
			$post_id         = wp_update_post( $post_args, true );
		} else {
			$post_id = wp_insert_post( $post_args, true );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		if ( ! empty( $doc['categories'] ) && is_array( $doc['categories'] ) ) {
			wp_set_object_terms( $post_id, $doc['categories'], 'doc_category', false );
		}

		++$imported;
	}

	wp_safe_redirect(
		add_query_arg(
			'wpb_imported',
			$imported,
			admin_url( 'edit.php?post_type=doc&page=wpb-docs-import' )
		)
	);
	exit;
	}