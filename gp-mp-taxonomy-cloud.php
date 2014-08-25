<?php
/*
Plugin Name: GP MP Taxonomy Cloud
Plugin URI: http://genderplayful.com
Description: Copy of Marketpress global products tag cloud for additional taxonomy types
Author: Angela Fox
Version: 1.0.1
Author URI: http://genderplayful.com/members/fleetfootedfox/
*/

/**
 * Display Global Products tag cloud.
 *
 * @param bool $echo Optional. Whether or not to echo.
 * @param int $limit Optional. How many tags to display.
 * @param string $seperator Optional. String to seperate tags by.
 * @param string $include Optional. What to show, 'tags', 'categories', or 'both'.
 */
function gp_mp_global_tag_cloud( $echo = true, $limit = 45, $seperator = ' ', $include = 'both' ) {
  global $wpdb;
  $settings = get_site_option( 'mp_network_settings' );

  //include categories as well
  if ($include == 'tags')
    $where = " WHERE t.type = 'product_tag'";
  else if ($include == 'categories')
    $where = " WHERE t.type = 'product_category'";
  else if ($include == 'clothing_types')
    $where = " WHERE t.type = 'clothing_types'";
  else if ($include == 'styles')
    $where = " WHERE t.type = 'styles'";
  else if ($include == 'colors')
    $where = " WHERE t.type = 'colors'";
  
  $tags = $wpdb->get_results( "SELECT name, slug, type, count(post_id) as count FROM {$wpdb->base_prefix}mp_terms t LEFT JOIN {$wpdb->base_prefix}mp_term_relationships r ON t.term_id = r.term_id$where GROUP BY t.term_id ORDER BY count DESC LIMIT $limit", ARRAY_A );

	if ( !$tags )
		return;

  //sort by name
  foreach ($tags as $tag) {
    //skip empty tags
    if ( $tag['count'] == 0 )
      continue;
      
    if ($tag['type'] == 'product_category')
      $tag['link'] = get_home_url( mp_main_site_id(), $settings['slugs']['marketplace'] . '/' . $settings['slugs']['categories'] . '/' . $tag['slug'] . '/' );
    else if ($tag['type'] == 'product_tag')
      $tag['link'] = get_home_url( mp_main_site_id(), $settings['slugs']['marketplace'] . '/' . $settings['slugs']['tags'] . '/' . $tag['slug'] . '/' );
      
    $sorted_tags[$tag['name']] = $tag;
  }
  
  ksort( $sorted_tags );

  //remove keys
  $tags = array();
  foreach( $sorted_tags as $tag )
    $tags[] = $tag;

  $counts = array();
	$real_counts = array(); // For the alt tag
	foreach ( (array) $tags as $key => $tag ) {
		$real_counts[ $key ] = $tag['count'];
		$counts[ $key ] = $tag['count'];
	}

	$min_count = min( $counts );
	$spread = max( $counts ) - $min_count;
	if ( $spread <= 0 )
		$spread = 1;
	$font_spread = 22 - 8;
	if ( $font_spread < 0 )
		$font_spread = 1;
	$font_step = $font_spread / $spread;

	$a = array();

	foreach ( $tags as $key => $tag ) {
		$count = $counts[ $key ];
		$real_count = $real_counts[ $key ];
		$tag_link = '#' != $tag['link'] ? esc_url( $tag['link'] ) : '#';
		$tag_id = isset($tags[ $key ]['id']) ? $tags[ $key ]['id'] : $key;
		$tag_name = $tags[ $key ]['name'];
		$a[] = "<a href='$tag_link' class='tag-link-$tag_id' title='" . esc_attr( $real_count ) . ' ' . __( 'Products', 'mp' ) . "' style='font-size: " .
			( 8 + ( ( $count - $min_count ) * $font_step ) )
			. "pt;'>$tag_name</a>";
	}

	$return = join( $seperator, $a );

	if ( $echo )
		echo '<div id="mp_tag_cloud">' . $return . '</div>';

	return '<div id="mp_tag_cloud">' . $return . '</div>';
}

  function gp_index_taxonomies($post_id) {
    global $wpdb, $current_site, $mp;

  	$blog_public = get_blog_status( $wpdb->blogid, 'public');
  	$blog_archived = get_blog_status( $wpdb->blogid, 'archived');
  	$blog_mature = get_blog_status( $wpdb->blogid, 'mature');
  	$blog_spam = get_blog_status( $wpdb->blogid, 'spam');
  	$blog_deleted = get_blog_status( $wpdb->blogid, 'deleted');

  	$post = get_post($post_id);

    //skip all cases where we shouldn't index
  	if ( $post->post_type != 'product' )
      return;

    //remove old post if necessary
    if ( $post->post_status != 'publish' || !empty($post->post_password) || empty($post->post_title) || empty($post->post_content) || $blog_archived || $blog_mature || $blog_spam || $blog_deleted ) {
      $this->delete_product($post_id);
      return;
    }

	//get product terms
		$taxonomies = array( 'clothing_types', 'styles', 'colors' );
		$new_terms = wp_get_object_terms( array( $post_id ), $taxonomies );
		if ( count($new_terms) ) {
			
      //get existing terms
      foreach ($new_terms as $term)
        $new_slugs[] = $term->slug;
  		$slug_list = implode( "','", $new_slugs );
      $existing_terms = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mp_terms WHERE slug IN ('$slug_list')" );
      $existing_slugs = array();
      if ( is_array($existing_terms) && count($existing_terms) ) {
        foreach ($existing_terms as $term) {
          $existing_slugs[$term->term_id] = $term->slug;
        }
      }
      
      //if updating
      if ($existed) {
      
        //get existing terms
        $old_terms = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mp_term_relationships r INNER JOIN {$wpdb->base_prefix}mp_terms t ON r.term_id = t.term_id WHERE r.post_id = $global_id" );
        $old_slugs = array();
        foreach ($old_terms as $term) {
          $old_slugs[$term->term_id] = $term->slug;
        }
        
        //process
        foreach ($new_terms as $term) {
        
          //is it a new term?
          if ( !in_array($term->slug, $old_slugs) ) {
          
            //check if in terms, but not attached
            if ( in_array($term->slug, $existing_slugs) ) {
            
              //add relationship
              $id = array_search($term->slug, $existing_slugs);
              $wpdb->insert( $wpdb->base_prefix . 'mp_term_relationships', array( 'term_id' => $id, 'post_id' => $global_id ) );
              $id_list[] = $id;
            } else { //brand new term
            
              //insert term
              $wpdb->insert( $wpdb->base_prefix . 'mp_terms', array( 'name' => $term->name, 'slug' => $term->slug, 'type' => $term->taxonomy ) );
              $id = $wpdb->insert_id;
              
              //add relationship
              $wpdb->insert( $wpdb->base_prefix . 'mp_term_relationships', array( 'term_id' => $id, 'post_id' => $global_id ) );
              $id_list[] = $id;
            }
            
          } else {
            $id_list[] = array_search($term->slug, $old_slugs);
          }
        }
        
        //remove extra relationships
        $id_whitelist = implode( "','", $id_list );
        $wpdb->query( "DELETE FROM {$wpdb->base_prefix}mp_term_relationships WHERE post_id = $global_id AND term_id NOT IN ('$id_whitelist')" );

      } else { //new post

        //process
        foreach ($new_terms as $term) {

          //check if in terms, but not attached
          if ( in_array($term->slug, $existing_slugs) ) {

            //add relationship
            $id = array_search($term->slug, $existing_slugs);
            $wpdb->insert( $wpdb->base_prefix . 'mp_term_relationships', array( 'term_id' => $id, 'post_id' => $global_id ) );

          } else { //brand new term

            //insert term
            $wpdb->insert( $wpdb->base_prefix . 'mp_terms', array( 'name' => $term->name, 'slug' => $term->slug, 'type' => $term->taxonomy ) );
            $id = $wpdb->insert_id;

            //add relationship
            $wpdb->insert( $wpdb->base_prefix . 'mp_term_relationships', array( 'term_id' => $id, 'post_id' => $global_id ) );

          }

        }

      }
        
    } else { //no terms, so adjust counts of existing

      //delete term relationships
      $wpdb->query( "DELETE FROM {$wpdb->base_prefix}mp_term_relationships WHERE post_id = $global_id" );
    }

  }
    add_action( 'save_post', array(&$this, 'gp_index_taxonomies') );
    add_action( 'delete_post', array(&$this, 'delete_product') );
?>
