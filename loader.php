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


add_action('buddyforms_front_js_css_enqueue', 'buddyforms_afe_front_js_css_enqueue');

function buddyforms_afe_front_js_css_enqueue(){
  wp_enqueue_script( 'bf-afe-js', plugins_url('assets/js/ajax.js', __FILE__) , array( 'jquery' ) );
}

function buddyforms_afe_admin_settings_sidebar_metabox(){
     add_meta_box('buddyforms_afe', __("Advanced Form Elements",'buddyforms'), 'buddyforms_afe_admin_settings_sidebar_metabox_html', 'buddyforms', 'side', 'low');
}
add_filter('add_meta_boxes','buddyforms_afe_admin_settings_sidebar_metabox');

function buddyforms_afe_admin_settings_sidebar_metabox_html(){
    global $post, $buddyforms;

    if($post->post_type != 'buddyforms')
         return;

    echo '<b>Taxonomy Hierarchically</b><p><a href="#" data-fieldtype="tax-afe" class="bf_add_element_action">Taxonomy</a></p>';
}


 /*
  * Create the new Form Builder Form Element for teh ACF Field Groups
  *
  */
 function buddyforms_afe_create_new_form_builder_form_element($form_fields, $form_slug, $field_type, $field_id){
     global $post;

     $buddyform = get_post_meta($post->ID, '_buddyforms_options', true);

     switch ($field_type) {

         case 'tax-afe':
         $taxonomies = buddyforms_taxonomies($buddyform);
         $taxonomy = isset($customfield['taxonomy']) ? $customfield['taxonomy'] : false;
         $form_fields['general']['taxonomy']        = new Element_Select('<b>' . __('Taxonomy', 'buddyforms') . '</b>', "buddyforms_options[form_fields][" . $field_id . "][taxonomy]", $taxonomies, array('value' => $taxonomy, 'class' => 'bf_tax_select', 'id' => $field_id));
         break;

     }

     return $form_fields;
 }
 add_filter('buddyforms_form_element_add_field','buddyforms_afe_create_new_form_builder_form_element',1,5);

 /*
  * Display the new ACF Field Groups Form Element in the Frontend Form
  *
  */
 function buddyforms_afe_create_frontend_element($form, $form_args){
     global $buddyforms, $nonce;

     extract($form_args);

     $post_type = $buddyforms[$form_slug]['post_type'];

     if(!$post_type)
         return $form;

     if(!isset($customfield['type']))
         return $form;

echo $customfield['taxonomy'];

     switch ($customfield['type']) {
        case 'tax-afe':
        $categories =  get_categories(array(
          'parent' => 0,
          'hide_empty' => 0,
          'taxonomy' => $customfield['taxonomy'],
          'hierarchical' => false,
        ));
        $main_cats = false;
        foreach($categories as $key => $category){
          $main_cats[$category->term_taxonomy_id] = $category->name;
        }

        $form->addElement( new Element_Select('Test ', 'tax_tax_tax[parent]', $main_cats, array( 'class' => 'tax_tax_tax' )));
        $form->addElement( new Element_HTML('<div id="taxtax_container"></div>'));


         break;
       }
       return $form;
 }
 add_filter('buddyforms_create_edit_form_display_element','buddyforms_afe_create_frontend_element',1,2);

function bf_afe_fields_group_create_frontend_form_element_ajax(){

  if(!isset($_POST['data']))
    return;

  $parent_id = $_POST['data'];

  $term = get_term_by( 'id', $parent_id, 'product_cat'  ); // get current term

  $parents = get_term($term->parent, 'product_cat' ); // get parent term

  $children =  get_categories(array(
     'child_of' => $parent_id,
     'hide_empty' => 0,
     'taxonomy' => 'product_cat',
  ));

  if(($parents->term_id!="" && sizeof($children)>0)) {

    $children_parent =  get_categories(array(
       'child_of' => $term->parent,
       'hide_empty' => 0,
       'taxonomy' => 'product_cat',
    ));

    $children_parents = false;
    foreach($children_parent as $key => $child){

        if($child->parent == $term->parent)
            $children_parents[$child->term_taxonomy_id] = $child->name;
    }

    if(is_array($children_parents))
      $select = new Element_Select('Test ', 'tax_tax_tax[sub][]', $children_parents, array('class' => 'tax_tax_tax'));

    if(is_object($select)) {
      $select->render();
    }

    $childs = false;
    foreach($children as $key => $child){
        if($child->parent == $parent_id)
            $childs[$child->term_taxonomy_id] = $child->name;
    }

    if(is_array($childs))
      $select = new Element_Select('Test ', 'tax_tax_tax[sub][]', $childs, array('class' => 'tax_tax_tax'));

    if(is_object($select)) {
      $select->render();
    }

  }elseif(($parents->term_id!="") && (sizeof($children)==0)) {

    $children_parent =  get_categories(array(
       'child_of' => $term->parent,
       'hide_empty' => 0,
       'taxonomy' => 'product_cat',
    ));

    $children_parents = false;
    foreach($children_parent as $key => $child){

        if($child->parent == $term->parent)
            $children_parents[$child->term_taxonomy_id] = $child->name;
    }

    if(is_array($children_parents))
      $select = new Element_Select('Test ', 'tax_tax_tax[sub][]', $children_parents, array('class' => 'tax_tax_tax', 'value' => $parent_id));

    if(is_object($select)) {
      $select->render();
    }

    die();
    return;

  }elseif(($parents->term_id=="") && (sizeof($children)>0)) {

      $childs = false;
      foreach($children as $key => $child){
          if($child->parent == $parent_id)
              $childs[$child->term_taxonomy_id] = $child->name;
      }

      if(is_array($childs))
        $select = new Element_Select('Test ', 'tax_tax_tax[sub][]', $childs, array('class' => 'tax_tax_tax'));

      if(is_object($select)) {
        $select->render();
      } else {
        echo false;
      }

  }

  die();

}
add_action('wp_ajax_bf_afe_fields_group_create_frontend_form_element_ajax', 'bf_afe_fields_group_create_frontend_form_element_ajax');
