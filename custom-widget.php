<?php
/*
Plugin Name: Branch Widget
Version: 0.1
Author: Pragyan


  Shows how to create a widget with a dropdown list of posts for a given post type 
  and then retrieve HTML specific to the selected post via AJAX to insert into the         
  page.

  Yes, the name of the class is insanely long in hopes that you'll be forced to 
  think about what would be a better name.

  This can be used in your theme's functions.php file or as a standalone file in a
  plugin or theme you are developing.

*/

class My_Widget extends WP_Widget {
  /*
   * My_Widget() used by the Widget API 
   * to initialize the Widget class
   */
  function __construct() {
    $widget_ops = array(
      'classname' => 'branch-widget',
      'description' => 'Custom Branch Detail Widget',
  );

  parent::__construct( 'branch_widget', 'Branch Widget', $widget_ops );
  }
  /*
   * widget() used by the Widget API to display a form in the widget area of the admin console
   *
   */
  function form( $instance ) {
    global $wp_post_types;
    $instance = self::defaults($instance);  // Get default values

    // Build the options list for our select
    $options = array();
    foreach($wp_post_types as $post_type) {
      if ($post_type->publicly_queryable) {
        $selected_html = '';
        if ($post_type->name==$instance['post_type']) {
          $selected_html = ' selected="true"';
          $post_type_object = $post_type;
        }
        $options[] = "<option value=\"{$post_type->name}\"{$selected_html}>{$post_type->label}</option>";
      }
    }
    $options = implode("\n",$options);

    // Get form attributes from Widget API convenience functions
    $post_type_field_id = $this->get_field_id( 'post_type' );
    $post_type_field_name = $this->get_field_name( 'post_type' );

    // Get HTML for the form
    $html = array();
    $html = <<<HTML
<p>
  <label for="{$post_type_field_id}">Post Type:</label>
  <select id="{$post_type_field_id}" name="{$post_type_field_name}">
    {$options}
  </select>
</p>

HTML;
    echo $html;
  }
  /*
   * widget() used by the Widget API to display the widget on the external site
   *
   */
  function widget( $args, $instance ) {
    extract( $args );
    $post_type = $instance['post_type'];
    $dropdown_name = $this->get_field_id( $post_type );
    // jQuery code to response to change in drop down
    $ajax_url = admin_url('admin-ajax.php');
	$script = 
<<<SCRIPT
			<script type="text/javascript">
			jQuery( function($) {
			var ajaxurl = "{$ajax_url}";
			$("select#{$dropdown_name}").change( function() {
				var data = {
				action: 'get_post_data_via_AJAX',
				post_id: $(this).val()
				};
				$.post(ajaxurl, data, function(response) {
				if (typeof(response)=="string") {
					response = eval('(' + response + ')');
				}
				if (response.result=='success') {
					if (response.html.length==0) {
					response.html = 'Nothing Found';
					}
					$("#{$dropdown_name}-target").html(response.html);
				}
				});
				return false;
			});
			});
			</script>
SCRIPT;
    echo $script;
    echo "<div>";
    // if ( $instance['title'] )
    //    echo "{$before_title}{$instance['title']}{$after_title}";

    global $wp_post_types;
    // Dirty ugly hack because get_pages() called by wp_dropdown_pages() ignores non-hierarchical post types
    $hierarchical = $wp_post_types[$post_type]->hierarchical;
    $wp_post_types[$post_type]->hierarchical = true;

    // Show a drop down of post types
    wp_dropdown_pages(array(
      'post_type'   => $post_type,
      'name'        => $dropdown_name,
      'id'          => $dropdown_name,
      'post_status' => ($post_type=='attachment' ? 'inherit' : 'publish'),
    ));

    $wp_post_types[$post_type]->hierarchical = $hierarchical;

    echo "</div>";

    // Create our post html target for jQuery to fill in
    echo "<div id=\"{$dropdown_name}-target\"></div>";

  }
  /*
   * update() used by the Widget API to capture the values for a widget upon save.
   *
   */
  function update( $new_instance, $old_instance ) {
    return $this->defaults($new_instance);
  }
  /*
   * defaults() conveninence function to set defaults, to be called from 2 places
   *
   */
  static function defaults( $instance ) {
    // Give post_type a default value
    
	if (!get_post_type_object($instance['post_type']))
		
      $instance['post_type'] = 'post';

    return $instance;
  }
  /*
   * self::action_init() ensures we have jQuery when we need it, called by the 'init' hook
   *
   */
  static function action_init() {
    wp_enqueue_script('jquery');
  }
  /*
   * self::action_widgets_init() registers our widget when called by the 'widgets_init' hook
   *
   */
  static function action_widgets_init() {
    register_widget( 'My_Widget' );
  }
  /*
   * self::get_post_data_via_AJAX() is the function that will be called by AJAX
   *
   */
  static function get_post_data_via_AJAX() {
    $post_id = intval(isset($_POST['post_id']) ? $_POST['post_id'] : 0);
    $html = self::get_post_data_html($post_id);
    $json = json_encode(array(
      'result'  => 'success',
      'html'    => $html,
    ));
    header('Content-Type:application/json',true,200);
    echo $json;
    die();
  }
  /*
   * self::on_load() initializes our hooks
   *
   */
  static function on_load() {
    add_action('init',array(__CLASS__,'action_init'));
    add_action('widgets_init',array(__CLASS__,'action_widgets_init'));

    require_once(ABSPATH."/wp-includes/pluggable.php");
    $user = wp_get_current_user();
    $priv_no_priv = ($user->ID==0 ? '_nopriv' : '');
    add_action("wp_ajax{$priv_no_priv}_get_post_data_via_AJAX",array(__CLASS__,'get_post_data_via_AJAX'));
    add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_template_assets'));
  }
  /*
   *  get_post_data_html($post_id)
   *
   *    This is the function that generates the HTML to send back to the client
   *    Below is a generic want to list post meta but you'll probably want to
   *    write custom code and use the outrageously long named hook called:
   *
   *      'html-for-list-custom-post-type-posts-with-ajax'
   *
   */
  static function get_post_data_html($post_id) {
    $html = array();
    $html[] .= '<ul>';
    $html[] .= '<li><label class = "detail-label">Address</label>:' .get_post_custom($post_id)["address"][0].'</li>';
    $html[] .= '<li><label class = "detail-label">Office Hours</label>:' .get_post_custom($post_id)["office_hours"][0].'</li>';
    $html[] .= '<li><label class = "detail-label">Phone</label>:' .get_post_custom($post_id)["phone"][0].'</li>';
    $html[] .= '<li><label class = "detail-label">Email</label>:' .get_post_custom($post_id)["email"][0].'</li>';
    $html[] .= '<li><label class = "detail-label">Fax</label>:' .get_post_custom($post_id)["fax"][0].'</li>';

    $html[] .= '</ul>';

    return apply_filters('html-for-list-custom-post-type-posts-with-ajax',implode("\n",$html));
  }

  static public function enqueue_template_assets(){
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'list-style',  $plugin_url . "/css/widget-style.css");
}
}
// This sets the necessary hooks
My_Widget::on_load();