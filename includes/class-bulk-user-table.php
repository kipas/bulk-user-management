<?php

if(!class_exists('WP_List_Table')){
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Bulk_User_Table extends WP_List_Table {

  function __construct(){
    global $status, $page;
            
    //Set parent defaults
    parent::__construct( array(
      'singular'  => 'bulk_user',
      'plural'    => 'bulk_users'
    ) );
  }

  function no_items() {
    _e( 'No matching users were found', 'bulk-user-management' );
  }

  function column_cb($item){
    return sprintf(
      '<input type="checkbox" name="%1$s[]" value="%2$s" />',
      /*$1%s*/ $this->_args['singular'],
      /*$2%s*/ $item->ID
    );
  }

  function column_username($item){
    $actions = array();
    if ( get_current_user_id() == $item->ID ) {
      $actions['edit'] = '<a href="' . admin_url('profile.php') . '">Edit</a>';
    } elseif ( current_user_can('edit_users') ) {
      $actions['edit'] = '<a href="' . add_query_arg( 'user_id', $item->ID, admin_url('user-edit.php') ) . '">Edit</a>';
    }

    return sprintf( __('%1$s %2$s %3$s', 'bulk-user-management' ),
      /*$1%s*/ get_avatar($item->ID, 32),
      /*$2%s*/ $item->user_login,
      /*$3%s*/ $this->row_actions($actions)
    );
  }

  function column_name($item){
    return $item->user_firstname . ' ' . $item->user_lastname;
  }

  function column_email($item){
    return sprintf( __('<a href="mailto:%1$s" title="E-mail %1$s">%1$s</a>', 'bulk-user-management' ), $item->user_email );
  }

  function column_sites($item){
    $blogs = get_blogs_of_user($item->ID);
    $crossreference = $this->get_blog_ids();
    $sites = '';
    foreach ( $blogs as $blog )
      if( in_array($blog->userblog_id, $crossreference) ) {
        $sites .= sprintf( '<a href="%s">', esc_attr( $blog->siteurl ) );
        $sites .= $blog->domain;
        if ( '/' != $blog->path )
          $sites .= $blog->path;
        $sites .= "</a><br>";
      } 
    return $sites;
  }

  function get_columns(){
    $columns = array(
      'cb'       => '<input type="checkbox" />',
      'username' => __( 'Username', 'bulk-user-management' ),
      'name'     => __( 'Name', 'bulk-user-management' ),
      'email'    => __( 'E-mail', 'bulk-user-management' ),
      'sites'    => __( 'Sites', 'bulk-user-management' ),
    );
    return $columns;
  }

  function get_sortable_columns() {
    $sortable_columns = array(
      'username' => array('username',false),
      'name'     => array('name',false),
      'email'    => array('email',false)
    );
    return $sortable_columns;
  }

  function get_bulk_actions() {
    $actions = array(
      'modify'    => __( 'Modify', 'bulk-user-management' ),
      'remove'    => __( 'Remove', 'bulk-user-management' )
    );
    return $actions;
  }

  function process_bulk_action() {
    switch( $this->current_action() ) {
      // case 'modify':
      //   wp_die( __("Modify Bulk Action"), 'bulk-user-management' );
      //   break;
    }
  }

  // TODO: replace with blog stickers API
  function get_blog_ids() {
    $user_id = get_current_user_id();
    $blogs = get_blogs_of_user($user_id, false);
    $blog_ids = array();
    $limit = array_map( 'intval', apply_filters('bulk_user_management_limit_blogs', array()) );
    foreach ( $blogs as $blog ) {
      $user = new WP_User($user_id, null, $blog->userblog_id);
      if ( user_can( $user, 'list_users' ) && ( count($limit) == 0 || in_array($blog->userblog_id, $limit) ) )
        $blog_ids[] = $blog->userblog_id;
    }
    return $blog_ids;
  }

  function prepare_items() {
    global $wpdb, $usersearch;
    $screen = get_current_screen();

    $per_page = $screen->get_option('per_page', 'option');
    $per_page = get_user_meta( get_current_user_id(), $per_page, true ); 
    if ( empty ( $per_page) || $per_page < 1 ) {
      $per_page = $screen->get_option( 'per_page', 'default' );
    }

    $usersearch = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

    $paged = $this->get_pagenum();

    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    $this->process_bulk_action();

    $blog_ids = $this->get_blog_ids();

    $meta_query = array();
    $meta_query['relation'] = 'OR';
    foreach ( $blog_ids as $blog_id )
      $meta_query[]['key'] = $wpdb->get_blog_prefix( $blog_id ). 'capabilities';

    $args = array(
      'blog_id'    => null,
      'meta_query' => $meta_query,
      'number'     => $per_page,
      'offset'     => $per_page * ($paged-1),
      'search'     => $usersearch,
      'fields'     => 'all_with_meta'
    );

    if ( '' !== $args['search'] )
      $args['search'] = '*' . $args['search'] . '*';

    if ( isset( $_REQUEST['orderby'] ) )
      $args['orderby'] = $_REQUEST['orderby'];

    if ( isset( $_REQUEST['order'] ) )
      $args['order'] = $_REQUEST['order'];

    $query = new WP_User_Query( $args );

    $this->items = $query->get_results();

    $this->set_pagination_args( array(
      "total_items" => $query->total_users,
      "per_page" => $per_page,
    ) );
  }

  function has_items() {
    return count($this->items) > 0;
  }

/**
   * Outputs the hidden row displayed when inline editing
   */
  function inline_edit() {
    global $mode;
    $screen = get_current_screen();
  ?>

  <table style="display: none"><tbody id="inlineedit">
    <?php wp_nonce_field( 'bulk-user-management-bulk-users', 'bulk-user-management-bulk-users' ) ?>

    <tr id="bulk-edit" class="inline-edit-row <?php echo "bulk-edit-row" ?>" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <h4><?php _e( 'Bulk Edit', 'bulk-user-management' ) ?></h4>

      <div id="bulk-title-div">
        <div id="bulk-titles"></div>
      </div>
    </div></fieldset>

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <span class="title inline-edit-categories-label"><?php _e( 'Sites', 'bulk-user-management' ) ?></span>

      <ul class="cat-checklist category-checklist">
        <?php foreach ( $this->get_blog_ids() as $id ): ?>
          <?php $blog = get_blog_details($id); ?>
          <li><label class="selectit"><input id='blog-<?php echo esc_attr($blog->blog_id); ?>' type=checkbox name=blogs[] value='<?php echo esc_attr($blog->blog_id); ?>'> <?php echo esc_html($blog->blogname); ?></label></li>
        <?php endforeach; ?>
      </ul>
    </div></fieldset>

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <label class="inline-edit-user">
        <span class="title"><?php _e( 'Role', 'bulk-user-management' ); ?></span>
        <select name="new_role" id="new_role-role">
          <?php wp_dropdown_roles( get_option('default_role') ); ?>
        </select>
      </label>
    </div></fieldset>

    <p class="submit inline-edit-save">
      <a accesskey="c" href="#inline-edit" title="<?php esc_attr_e( 'Cancel' ); ?>" class="button-secondary cancel alignleft"><?php _e( 'Cancel', 'bulk-user-management' ); ?></a>
      <?php submit_button( __( 'Update', 'bulk-user-management' ), 'button-primary alignright', 'bulk_edit', false, array( 'accesskey' => 's' ) ); ?>
      <input type="hidden" name="post_view" value="<?php echo esc_attr( $m ); ?>" />
      <input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>" />
      <span class="error" style="display:none"></span>
      <br class="clear" />
    </p>

    </td></tr>

    </tbody></table>
<?php
  }

  /**
   * Outputs hidden row for bulk removing users
   */
  function bulk_remove() {
    global $mode;
    $screen = get_current_screen();
  ?>

  <table style="display: none"><tbody id="inlineedit">
    <?php wp_nonce_field( 'bulk-user-management-bulk-remove-users', 'bulk-user-management-bulk-remove-users' ) ?>

    <tr id="bulk-remove" class="inline-edit-row <?php echo "bulk-edit-row" ?>" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <h4><?php _e( 'Bulk Edit', 'bulk-user-management' ) ?></h4>

      <div id="bulk-title-div">
        <div id="bulk-titles"></div>
      </div>
    </div></fieldset>

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <span class="title inline-edit-categories-label"><?php _e( 'Sites', 'bulk-user-management' ) ?></span>

      <ul class="cat-checklist category-checklist">
        <?php foreach ( $this->get_blog_ids() as $id ): ?>
          <?php $blog = get_blog_details($id); ?>
          <li><label class="selectit"><input id='blog-<?php echo esc_attr($blog->blog_id); ?>' type=checkbox name=blogs[] value='<?php echo esc_attr($blog->blog_id); ?>'> <?php echo esc_html($blog->blogname); ?></label></li>
        <?php endforeach; ?>
      </ul>
    </div></fieldset>

    <p class="submit inline-edit-save">
      <a accesskey="c" href="#inline-edit" title="<?php esc_attr_e( 'Cancel' ); ?>" class="button-secondary cancel alignleft"><?php _e( 'Cancel', 'bulk-user-management' ); ?></a>
      <?php submit_button( __( 'Update', 'bulk-user-management' ), 'button-primary alignright', 'bulk_edit', false, array( 'accesskey' => 's' ) ); ?>
      <input type="hidden" name="post_view" value="<?php echo esc_attr( $m ); ?>" />
      <input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>" />
      <span class="error" style="display:none"></span>
      <br class="clear" />
    </p>

    </td></tr>

    </tbody></table>
<?php
  }
}