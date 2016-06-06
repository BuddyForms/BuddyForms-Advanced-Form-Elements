<?php
/*
 Plugin Name: BuddyForms Advanced Form Elements
 Plugin URI: http://buddyforms.com/downloads/buddyforms-advanced-form-elements/
 Description: Add some advanced form ellements to BuddyForms
 Version: 0.1
 Author: Sven Lehnert
 Author URI: https://profiles.wordpress.org/svenl77
 License: GPLv2 or later
 Network: false

 *****************************************************************************
 *
 * This script is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 ****************************************************************************
 */


add_action( 'buddyforms_front_js_css_enqueue', 'buddyforms_afe_front_js_css_enqueue' );

function buddyforms_afe_front_js_css_enqueue() {
	wp_enqueue_script( 'bf-afe-js', plugins_url( 'assets/js/ajax.js', __FILE__ ), array( 'jquery' ) );
}

function buddyforms_afe_admin_settings_sidebar_metabox() {
	add_meta_box( 'buddyforms_afe', __( "Advanced Form Elements", 'buddyforms' ), 'buddyforms_afe_admin_settings_sidebar_metabox_html', 'buddyforms', 'side', 'low' );
}

add_filter( 'add_meta_boxes', 'buddyforms_afe_admin_settings_sidebar_metabox' );

function buddyforms_afe_admin_settings_sidebar_metabox_html() {
	global $post, $buddyforms;

	if ( $post->post_type != 'buddyforms' ) {
		return;
	}

	echo '<b>Taxonomy Hierarchically</b><p><a href="#" data-fieldtype="tax-afe" class="bf_add_element_action">Taxonomy</a></p>';
}

/*
 * Create the new Form Builder Form Element for teh AFE Field Groups
 *
 */
function buddyforms_afe_create_new_form_builder_form_element( $form_fields, $form_slug, $field_type, $field_id ) {
	global $post;

	$buddyform = get_post_meta( $post->ID, '_buddyforms_options', true );

	switch ( $field_type ) {

		case 'tax-afe':
			$taxonomies = buddyforms_taxonomies( $buddyform );

			$taxonomy = false;
			if ( isset( $buddyform['form_fields'][ $field_id ]['taxonomy'] ) ) {
				$taxonomy = $buddyform['form_fields'][ $field_id ]['taxonomy'];
			}
			$form_fields['general']['taxonomy'] = new Element_Select( '<b>' . __( 'Taxonomy', 'buddyforms' ) . '</b>', "buddyforms_options[form_fields][" . $field_id . "][taxonomy]", $taxonomies, array(
				'value' => $taxonomy,
				'class' => 'bf_tax_select',
				'id'    => $field_id
			) );
			break;

	}

	return $form_fields;
}

add_filter( 'buddyforms_form_element_add_field', 'buddyforms_afe_create_new_form_builder_form_element', 1, 5 );

/*
 * Display the new AFE Field Groups Form Element in the Frontend Form
 *
 */
function buddyforms_afe_create_frontend_element( $form, $form_args ) {
	global $buddyforms, $nonce;

	extract( $form_args );

	$customfield_val = explode( ',', $customfield_val );
	$post_type       = $buddyforms[ $form_slug ]['post_type'];

	if ( ! $post_type ) {
		return $form;
	}

	if ( ! isset( $customfield['type'] ) ) {
		return $form;
	}

	switch ( $customfield['type'] ) {
		case 'tax-afe':
			$categories = get_categories( array(
				'parent'       => 0,
				'hide_empty'   => 0,
				'taxonomy'     => $customfield['taxonomy'],
				'hierarchical' => false,
			) );
			$main_cats  = false;
			if ( is_array( $categories ) ) :
				$main_cats['none'] = 'Select One';
				foreach ( $categories as $key => $category ) {
					$main_cats[ $category->term_taxonomy_id ] = $category->name;
				}
			endif;

			$form->addElement( new Element_Select( $customfield['name'], $customfield['slug'] . '-select', $main_cats, array(
				'value'         => $customfield_val,
				'class'         => 'tax_tax_tax',
				'data-id'       => $customfield['slug'],
				'data-taxonomy' => $customfield['taxonomy']
			) ) );
			$form->addElement( new Element_HTML( '<div id="taxtax_container">' ) );

			$terms = wp_get_post_terms( $post_id, $customfield['taxonomy'] );

			foreach ( $terms as $key => $term ) {

				$term_children = get_categories( array(
					'parent'       => $term->term_id,
					'hide_empty'   => 0,
					'taxonomy'     => $customfield['taxonomy'],
					'hierarchical' => false,
				) );

				$term_children_array = false;
				if ( is_array( $term_children ) ) :
					$term_children_array['none'] = 'Select One';
					foreach ( $term_children as $key => $term_child ) {
						$term_children_array[ $term_child->term_taxonomy_id ] = $term_child->name;
					}
				endif;

				if ( is_array( $term_children_array ) && count( $term_children_array ) > 1 ) {
					$select = new Element_Select( '', 'tax_tax_tax[sub][]', $term_children_array, array(
						'class'         => 'tax_tax_tax',
						'value'         => $customfield_val,
						'data-id'       => $customfield['slug'],
						'data-taxonomy' => $customfield['taxonomy']
					) );
					ob_start();
					$select->render();
					$select = ob_get_clean();
					$form->addElement( new Element_HTML( $select ) );
				}

			}

			$form->addElement( new Element_HTML( '</div>' ) );

			$form->addElement( new Element_Hidden( $customfield['slug'], $form_args['customfield_val'] ) );

			break;
	}

	return $form;
}

add_filter( 'buddyforms_create_edit_form_display_element', 'buddyforms_afe_create_frontend_element', 1, 2 );

// Ajax load new child select
function bf_afe_fields_group_create_frontend_form_element_ajax() {
	if ( ! isset( $_POST['data'] ) ) {
		echo 'false';
		die();
	}

	$cats     = $_POST['data'];
	$id       = $_POST['id'];
	$taxonomy = $_POST['taxonomy'];

	if ( is_array( $cats ) ) {
		foreach ( $cats as $cat_key => $cat ) {

			$childs_of_cat = get_categories( array(
				'child_of'   => $cat,
				'hide_empty' => 0,
				'taxonomy'   => $taxonomy,
			) );

			if ( ! $childs_of_cat ) {
				die();
			}

			$childs_of_cat_array = false;
			if ( is_array( $childs_of_cat ) ) :
				$childs_of_cat_array['none'] = 'Select One';
				foreach ( $childs_of_cat as $key => $child_of_cat ) {
					if ( $child_of_cat->parent == $cat ) {
						$childs_of_cat_array[ $child_of_cat->term_taxonomy_id ] = $child_of_cat->name;
					}
				}
			endif;

			$select_value = false;
			if ( isset( $cats[ $cat_key + 1 ] ) ) {
				$select_value = $cats[ $cat_key + 1 ];
			}

			if ( is_array( $childs_of_cat_array ) ) {
				$select = new Element_Select( 'Test ', 'tax_tax_tax', $childs_of_cat_array, array(
					'class'         => 'tax_tax_tax',
					'value'         => $select_value,
					'data-id'       => $id,
					'data-taxonomy' => $taxonomy
				) );
			}

			if ( is_object( $select ) ) {
				$select->render();
			}
		}
	}
	die();
}

add_action( 'wp_ajax_bf_afe_fields_group_create_frontend_form_element_ajax', 'bf_afe_fields_group_create_frontend_form_element_ajax' );

// Save the taxonomies
function buddyforms_afe_update_post_meta( $customfield, $post_id ) {

	if ( $customfield['type'] != 'tax-afe' ) {
		return;
	}

	if ( ! isset( $_POST[ $customfield['slug'] ] ) ) {
		return;
	}

	if ( $_POST[ $customfield['slug'] ] == 'none' ) {
		return;
	}

	if ( ! isset( $customfield['taxonomy'] ) ) {
		return;
	}

	wp_delete_object_term_relationships( $post_id, $customfield['taxonomy'] );

	$taxonomy = get_taxonomy( $customfield['taxonomy'] );

	if ( isset( $customfield['multiple'] ) ) {

		if ( isset( $taxonomy->hierarchical ) && $taxonomy->hierarchical == true ) {

			if ( isset( $_POST[ $customfield['slug'] ] ) ) {
				$tax_item = $_POST[ $customfield['slug'] ];
			}

			if ( $tax_item[0] == - 1 && ! empty( $customfield['taxonomy_default'] ) ) {
				//$taxonomy_default = explode(',', $customfield['taxonomy_default'][0]);
				foreach ( $customfield['taxonomy_default'] as $key => $tax ) {
					$tax_item[ $key ] = $tax;
				}
			}

			wp_set_post_terms( $post_id, $tax_item, $customfield['taxonomy'], false );
		} else {

			$slug = Array();

			if ( isset( $_POST[ $customfield['slug'] ] ) ) {
				$postCategories = $_POST[ $customfield['slug'] ];

				foreach ( $postCategories as $postCategory ) {
					$term   = get_term_by( 'id', $postCategory, $customfield['taxonomy'] );
					$slug[] = $term->slug;
				}
			}

			wp_set_post_terms( $post_id, $slug, $customfield['taxonomy'], false );
		}
		if ( isset( $_POST[ $customfield['slug'] . '_creat_new_tax' ] ) && ! empty( $_POST[ $customfield['slug'] . '_creat_new_tax' ] ) ) {
			$creat_new_tax = explode( ',', $_POST[ $customfield['slug'] . '_creat_new_tax' ] );
			if ( is_array( $creat_new_tax ) ) {
				foreach ( $creat_new_tax as $key => $new_tax ) {
					$wp_insert_term = wp_insert_term( $new_tax, $customfield['taxonomy'] );
					wp_set_post_terms( $post_id, $wp_insert_term, $customfield['taxonomy'], true );
				}
			}
		}
	} else {
		wp_delete_object_term_relationships( $post_id, $customfield['taxonomy'] );
		if ( isset( $_POST[ $customfield['slug'] . '_creat_new_tax' ] ) && ! empty( $_POST[ $customfield['slug'] . '_creat_new_tax' ] ) ) {
			$creat_new_tax = explode( ',', $_POST[ $customfield['slug'] . '_creat_new_tax' ] );
			if ( is_array( $creat_new_tax ) ) {
				foreach ( $creat_new_tax as $key => $new_tax ) {
					$wp_insert_term = wp_insert_term( $new_tax, $customfield['taxonomy'] );
					wp_set_post_terms( $post_id, $wp_insert_term, $customfield['taxonomy'], true );
				}
			}
		} else {
			if ( isset( $taxonomy->hierarchical ) && $taxonomy->hierarchical == true ) {

				if ( isset( $_POST[ $customfield['slug'] ] ) ) {
					$tax_item = $_POST[ $customfield['slug'] ];
				}

				if ( $tax_item[0] == - 1 && ! empty( $customfield['taxonomy_default'] ) ) {
					//$taxonomy_default = explode(',', $customfield['taxonomy_default'][0]);
					foreach ( $customfield['taxonomy_default'] as $key => $tax ) {
						$tax_item[ $key ] = $tax;
					}
				}

				wp_set_post_terms( $post_id, $tax_item, $customfield['taxonomy'], false );
			} else {

				$slug = Array();
				if ( isset( $_POST[ $customfield['slug'] ] ) ) {
					$postCategories = $_POST[ $customfield['slug'] ];

					foreach ( $postCategories as $postCategory ) {
						$term   = get_term_by( 'id', $postCategory, $customfield['taxonomy'] );
						$slug[] = $term->slug;
					}
				}

				wp_set_post_terms( $post_id, $slug, $customfield['taxonomy'], false );
			}
		}
	}

}

add_action( 'buddyforms_update_post_meta', 'buddyforms_afe_update_post_meta', 10, 2 );
