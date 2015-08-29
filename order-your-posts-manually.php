<?php
/**
 * @package Order your Posts Manually
 * @version 1.8
 */
/*
Plugin Name: Order your Posts Manually
Plugin URI: http://cagewebdev.com/order-posts-manually
Description: Order your Posts Manually by Dragging and Dropping them
Version: 1.8
Date: 08/29/2015
Author: Rolf van Gelder
Author URI: http://cagewebdev.com/
License: GPLv2 or later
*/
?>
<?php
/***********************************************************************************
 *
 * 	ORDER YOUR POSTS MANUALLY - MAIN CLASS
 *
 ***********************************************************************************/
 
// CREATE INSTANCE
global $opm_class;
$opm_class = new OrderYourPostsManually; 
 
class OrderYourPostsManually
{
	var $opm_version      = '1.8';
	var $opm_release_date = '08/29/2015';
	
	/*******************************************************************************
	 * 	CONSTRUCTOR
	 *******************************************************************************/
	function __construct()
	{
		// INITIALIZE PLUGIN
		add_action('init', array(&$this, 'opm_init'));

		// USE THE NON-MINIFIED VERSION OF JS AND CSS WHILE DEBUGGING
		$this->script_minified = (defined('WP_DEBUG') && WP_DEBUG) ? '' : '.min';
		$this->script_minified = '';
		
		// GET OPTIONS FROM DB (JSON FORMAT)
		$this->opm_options = get_option('opm_options');

		// FIRST RUN: SET DEFAULT SETTINGS
		$this->opm_init_settings();
	
		// BASE NAME OF THE PLUGIN
		$this->plugin_basename = plugin_basename(__FILE__);
		$this->plugin_basename = substr($this->plugin_basename, 0, strpos( $this->plugin_basename, '/'));
		
		// LOCALIZATION
		add_action('init', array(&$this, 'opm_i18n'));						
	} // __construct()
	
	
	/*******************************************************************************
	 * 	INITIALIZE PLUGIN
	 *******************************************************************************/	
	function opm_init()
	{
		if (!$this->opm_is_frontend_page() && is_user_logged_in())
		{	// BACKEND PAGE
			// ADD BACKEND STYLE SHEET
			add_action('admin_init', array(&$this, 'opm_be_scripts'));
			add_action('admin_init', array(&$this, 'opm_be_styles'));		
			add_action('admin_menu', array(&$this, 'opm_admin_menu'));
			add_action('admin_menu', array(&$this, 'opm_admin_tools'));
			add_filter('plugin_action_links_'.plugin_basename(__FILE__), array(&$this, 'opm_settings_link'));
		}
	} // opm_init()
	
	
	/*******************************************************************************
	 * 	INITIALIZE SETTINGS (FIRST TIME)
	 *******************************************************************************/
	function opm_init_settings()
	{
		// print_r($this->opm_options);			
		if ($this->opm_options === false)
		{	// NO SETTINGS YET: SET DEFAULTS
			$this->opm_options['opm_date_field']      = '0'; // CREATION DATE
			$this->opm_options['opm_posts_per_page']  = '0'; // ALL POSTS
			$this->opm_options['opm_post_type']       = 'post';
			$this->opm_options['opm_show_thumbnails'] = 'N';
			$this->opm_options['opm_thumbnail_size']  = '100';
				
			// SAVE OPTIONS ARRAY
			update_option('opm_options', $this->opm_options);
		} // if ( false === $this->opm_options )
	} // opm_init_settings()


	/*******************************************************************************
	 * 	LOAD SETTINGS PAGE
	 *******************************************************************************/
	function opm_settings()
	{	// LOAD THE SETTINGS PAGE
		include_once(trailingslashit(dirname( __FILE__ )).'/admin/settings.php');
	} // opm_settings()	
	
	
	/*******************************************************************************
	 * 	DEFINE TEXT DOMAIN (FOR LOCALIZATION)
	 *******************************************************************************/	
	function opm_i18n()
	{
		load_plugin_textdomain('order-your-posts-manually', false, dirname(plugin_basename( __FILE__ )).'/languages/');
	} // opm_i18n()	
	
	
	/*******************************************************************************
	 * 	IS THIS A FRONTEND PAGE?
	 *******************************************************************************/
	function opm_is_frontend_page()
	{	
		if (isset($GLOBALS['pagenow']))
			return !is_admin() && !in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
		else
			return !is_admin();
	} // opm_is_frontend_page()


	/*******************************************************************************
	 * 	LOAD BACKEND JAVASCRIPT
	 *******************************************************************************/
	function opm_be_scripts()
	{	// true: in footer
		wp_register_script('opm-backend', plugins_url('js/order_your_posts_manually'.$this->script_minified.'.js', __FILE__), array('jquery', 'jquery-ui-core', 'jquery-ui-draggable', 'jquery-ui-sortable', 'jquery-ui-position'), '1.0', true);
		wp_enqueue_script('opm-backend');
	} // opm_be_scripts()
	

	/*******************************************************************************
	 * 	LOAD BACKEND STYLESHEET
	 *******************************************************************************/
	function opm_be_styles()
	{	
		wp_register_style('opm-be-style', plugins_url('css/order_your_posts_manually'.$this->script_minified.'.css', __FILE__));
		wp_enqueue_style('opm-be-style');
	} // opm_be_styles()


	/*******************************************************************************
	 * 	ADD PAGE TO THE SETTINGS MENU
	 *******************************************************************************/
	function opm_admin_menu()
	{	
		if (function_exists('add_options_page'))
			add_options_page(__('Order Your Posts Manually', 'order-your-posts-manually'), __( 'Order Your Posts Manually', 'order-your-posts-manually' ), 'manage_options', 'opm_settings', array( &$this, 'opm_settings'));		

	} // opm_admin_menu()


	/*******************************************************************************
	 * 	ADD THE 'ORDER POSTS MANUALLY' ITEM TO THE ADMIN TOOLS MENU
	 *******************************************************************************/	
	function opm_admin_tools()
	{	if (function_exists('add_management_page'))
		{	// v1.7.2 'administrator' changed to 'manage_options'
			add_management_page(__('Order Your Posts Manually','order-your-posts-manually'), __('Order Your Posts Manually','order-your-posts-manually'), 'manage_options','opm-order-posts.php', array( &$this, 'opm_list_posts'));
		}
	} // opm_admin_tools()
	

	/*******************************************************************************
	 * 	SHOW A LINK TO THE PLUGIN SETTINGS ON THE MAIN PLUGINS PAGE
	 *******************************************************************************/		
	function opm_settings_link($links)
	{ 
	  array_unshift($links, '<a href="options-general.php?page=opm_settings">Settings</a>'); 
	  return $links;
	} // opm_settings_link()
	

	/*******************************************************************************
	 * 	MAIN FUNCTION: LIST THE POSTS
	 *******************************************************************************/
	function opm_list_posts()
	{
		global $wpdb, $opm_version, $opm_release_date;
		
		// GET SORTING ORDER FROM OPTIONS
		$opm_date_field = $this->opm_options['opm_date_field'];
		$field_name = ($opm_date_field == 0) ? 'post_date' : 'post_modified';
	
		// GET NUMBER OF POSTS PER PAGE FROM OPTIONS
		$opm_posts_per_page = $this->opm_options['opm_posts_per_page'];
		
		// DEFAULT: ALL POSTS AT ONCE
		if(!$opm_posts_per_page) $opm_posts_per_page = 0;
		
		// TYPES TO ORDER, DEFAULT = POST
		$opm_post_type = $this->opm_options['opm_post_type'];
		if(!$opm_post_type) $opm_post_type = 'post';
	
		/*************************************************************************
		*
		*	UPDATE POST DATES
		*
		*************************************************************************/
		if(count($_POST)>0 && $_POST['action'] == 'update_dates')
		{
			$dates   = explode('#', $_POST['dates']);
			$postids = explode('&', $_POST['sortdata']);
			
			for($p=0; $p<count($postids); $p++)
			{	$q = explode('=', $postids[$p]);
				$post_id = $q[1];
				$sql = "
				UPDATE $wpdb->posts SET `".$field_name."` = '$dates[$p]' WHERE `ID` = $post_id";
				$wpdb -> get_results($sql);
			}
			echo "<div class='updated'><p><strong>".__('SORT ORDER SAVED!', 'order-your-posts-manually')."</strong></p></div>";
		} // if(count($_POST)>0 && $_POST['action'] == 'update_dates')
		
	
		/*************************************************************************
		*
		*	GET THE POSTS
		*
		*************************************************************************/
		$cat_id = '0';
		if(isset($_REQUEST['cat_id']) && $_REQUEST['cat_id'] > 0)
		{	$cat_id = $_REQUEST['cat_id'];
			$args = array(
				'posts_per_page' => 999999,
				'category' => $cat_id,
				'orderby' => $field_name
			);
		}
		else
		{	$args = array(
				'posts_per_page' => 999999,
				'orderby' => $field_name
			);
		}
		$myposts = get_posts($args);
	
		$dates                = '';
		$nr_of_stickies       = 0;
		$nr_of_posts          = 0;
		
		/*************************************************************************************
		 *
		 *	COUNT THE NUMBER OF STICKIES AND SAVE THE ORIGINAL DATES TO A STRING
		 *
		 ************************************************************************************/
		foreach($myposts as $post)
		{
			if(is_sticky($post->ID))
			{
				$nr_of_stickies++;
			}
			else
			{
				$nr_of_posts++;
				if($dates) $dates .= "#";
				if($field_name == 'post_date')
				{
					$dates .= $post->post_date;
					$mode = __('creation date', 'order-your-posts-manually');
				}
				else
				{
					$dates .= $post->post_modified;
					$mode = __('modification date', 'order-your-posts-manually');
				}			
			} // if(is_sticky($post->ID))
		} // foreach($myposts as $post)
	?>
<script type="text/javascript">
	var pagnr = 1;
	var busy  = false;
	var done  = false;
	
	/*************************************************************************************
	 *
	 *	GET SETS OF POSTS (PER PAGE)
	 *
	 ************************************************************************************/
	function opm_get_posts()
	{
		if(done) return;
		
		// PARAMETERS FOR THE AJAX CALL
		var data = {
			'action': 'opm_action',	// v1.7.1
			'cat_id': <?php echo $cat_id;?>,
			'opm_posts_per_page': <?php echo $this->opm_options['opm_posts_per_page'];?>,
			'opm_post_type': '<?php echo $this->opm_options['opm_post_type'];?>',
			'opm_show_thumbnails': '<?php echo $this->opm_options['opm_show_thumbnails']?>',
			'opm_thumbnail_size': '<?php echo $this->opm_options['opm_thumbnail_size']?>',
			'nr_of_stickies': <?php echo $nr_of_stickies;?>,
			'nr_of_posts': <?php echo $nr_of_posts;?>,	// EXCL. STICKIES
			'pagnr': pagnr,
			'field_name': '<?php echo $field_name;?>'
		};
	
		// <ajaxurl> IS DEFINED SINCE WP v2.8!
		jQuery.post(ajaxurl, data, function(response) {
			jQuery("#opm-sortable").append(response);
			pagnr++;
			busy = false;
			jQuery("#opm-loading").hide();
		});
		
		var end = ((pagnr-1)*<?php echo $opm_posts_per_page;?>)+<?php echo $nr_of_stickies;?>+<?php echo $opm_posts_per_page;?>;
		if((end > <?php echo $nr_of_posts;?>) || (<?php echo $opm_posts_per_page;?> == 0)) done = true;
	} // opm_get_posts()
	
	
	/*************************************************************************************
	 *
	 *	INITIALIZE JQUERY
	 *
	 ************************************************************************************/
	jQuery(document).ready(function ()
	{	// TAKE CARE OF THE DRAGGING AND DROPPING
		jQuery('#opm-sortable').sortable({
				placeholder: 'opm-placeholder',
				stop: function (event, ui) {
					var oData = jQuery(this).sortable('serialize');
					jQuery('#sortdata').val(oData);
				}
		});
		// GET NEXT SET OF POSTS
		if(!done) opm_get_posts();
	});
	
	
	/*************************************************************************************
	 *
	 *	CHECK IF WE ARE AT THE END OF THE PAGE
	 *
	 ************************************************************************************/
	jQuery(window).scroll(function()
	{	// alert(busy+" "+done);
		if(!busy && !done && (jQuery(window).scrollTop() + jQuery(window).height() == jQuery(document).height()))
		{	busy = true;
			jQuery("#opm-loading").show();
			// GET NEXT SET OF POSTS
			opm_get_posts();
		}
	});
	</script>
<?php
	/*************************************************************************************
	 *
	 *	DISPLAY THE PAGE
	 *
	 ************************************************************************************/
	?>
<script type="text/javascript">
function opm_cat_id_onchange()
{
	var cat_id = jQuery("#opm_cat_id").val();
	self.location = '<?php echo site_url().'/wp-admin/tools.php?page=opm-order-posts.php&cat_id='?>'+cat_id;
}
</script>

<form action="" method="post">
  <input type="hidden" id="action" name="action" value="update_dates" />
  <input type="hidden" id="sortdata" name="sortdata" value="" />
  <input type="hidden" id="dates" name="dates" value="<?php echo $dates;?>" />
  <div id="opm-post-table">
    <div class="opm-title-bar">
      <h2>
        <?php
			$sorttype = __('sort type', 'order-your-posts-manually');
		?>
        <?php _e( 'Order Your Posts Manually ('.$sorttype.': '.$mode.')', 'order-your-posts-manually' ); ?>
      </h2>
    </div>  
    <div class="opm-intro">
      <?php _e( 'Plugin version', 'order-your-posts-manually' ); ?>: v<?php echo $this->opm_version?> [<?php echo $this->opm_release_date?>] - <a href="http://cagewebdev.com/order-posts-manually/" target="_blank">
      <?php _e( 'Plugin page', 'order-your-posts-manually' ); ?></a> - <a href="http://wordpress.org/plugins/order-your-posts-manually/" target="_blank">
      <?php _e( 'Download page', 'order-your-posts-manually' ); ?></a> - <a href="http://rvg.cage.nl/" target="_blank">
      <?php _e( 'Author', 'order-your-posts-manually' ); ?></a> - <a href="http://cagewebdev.com/" target="_blank">
      <?php _e( 'Company', 'order-your-posts-manually' ); ?></a> - <a href="http://cagewebdev.com/index.php/donations-opm/" target="_blank">
      <?php _e( 'Donation page', 'order-your-posts-manually' ); ?></a></strong>
      <p> <?php echo __('WARNING','order-your-posts-manually');?>:<br />
        <?php echo __('Running this plugin will actually change the CREATION- or MODIFICATION dates of your posts in the database, to change the display order.', 'order-your-posts-manually'); ?><br />
        <?php echo __('It will swap some of the dates.', 'order-your-posts-manually'); ?><br />
        <?php echo __('So, if you think the EXACT DATES of when a post was created and / or modified are more important than the order of the posts: DON\'T USE THIS PLUGIN!', 'order-your-posts-manually'); ?></p>
    </div>
    <strong style="color:#00F;"><?php echo __('STICKY POSTS', 'order-your-posts-manually'); ?> (<?php echo $nr_of_stickies;?>):</strong><br />
    <br />
    <ul id="opm-stickies">
      <?php
		/*************************************************************************
		*
		*	DISPLAY STICKIES
		*
		*************************************************************************/
		foreach($myposts as $post)
		{	if(is_sticky($post->ID))
			{
				if($field_name == 'post_date')
					$this_date = $post->post_date;
				else
					$this_date = $post->post_modified;			
	?>
      <li class="ui-state-default" title="Post ID: <?php echo $post->ID?>"><small><?php echo $this_date?></small> * <strong><?php echo $post->post_title?></strong></li>
      <?php
			}
		} // foreach($myposts as $post)
	?>
    </ul>
    <br />
    <strong style="color:#00F;"><?php echo __('REGULAR POSTS', 'order-your-posts-manually'); ?> (<?php echo $nr_of_posts;?>):</strong><br />
    <br />
    <strong><?php echo __('Drag and drop the posts to change the display order!', 'order-your-posts-manually'); ?></strong><br />
    (<?php echo __('After changing the order, don\'t forget to click the <strong>SAVE CHANGES</strong> button to actually update the posts', 'order-your-posts-manually'); ?>)<br />
    <br />
    <?php _e('Category', 'order-your-posts-manually')?>
    :
    <select name="opm_cat_id" id="opm_cat_id" onchange="opm_cat_id_onchange();">
      <option value="0">
      <?php _e('* ALL *', 'order-your-posts-manually')?>
      </option>
      <?php
		$args = array(
		  'hide_empty' => 1,
		  'orderby' => $field_name,
		  'order' => 'ASC'
		);
		$cat_id = 0;
		if(isset($_REQUEST['cat_id']) && $_REQUEST['cat_id'] > 0) $cat_id = $_REQUEST['cat_id'];
		$categories = get_categories($args);
		foreach ( $categories as $category )
		{	$selected = '';
			if($category->cat_ID == $cat_id) $selected = 'selected="selected"';
			echo '<option value="'.$category->cat_ID.'" '.$selected.'>'.__($category->name, 'order-your-posts-manually').'</option>';
		}
	?>
    </select>
    <br />
    <br />
    <input name="submit" type="submit" value="<?php echo __('SAVE CHANGES', 'order-your-posts-manually'); ?>" class="button-primary button-large" />
    &nbsp;&nbsp;&nbsp;
    <input name="cancel" value="<?php echo __('RELOAD POSTS', 'order-your-posts-manually'); ?>" type="button" onclick="self.location='';" class="button" />
    <br />
    <br />
    <?php    
		/*************************************************************************
		*
		*	PLACEHOLDER FOR THE ACTUAL POSTS
		*
		*************************************************************************/
		$loader_image = plugins_url().'/order-your-posts-manually/images/loader.gif';
	?>
    <ul id="opm-sortable">
    </ul>
    <br />
    <?php
		/*************************************************************************
		*
		*	LOADING ANIMATION
		*
		*************************************************************************/	
		?>
    <div id="opm-loading" align="center"><img src="<?php echo $loader_image;?>" /><br />
      <br />
      <br />
    </div>
    <?php    
		/*************************************************************************
		*
		*	BOTTOM BUTTONS
		*
		*************************************************************************/
	?>
    <input name="submit" type="submit" value="<?php echo __('SAVE CHANGES', 'order-your-posts-manually'); ?>" class="button-primary button-large" />
    &nbsp;&nbsp;&nbsp;
    <input name="cancel" value="<?php echo __('RELOAD POSTS', 'order-your-posts-manually'); ?>" type="button" onclick="self.location='';" class="button" />
  </div>
</form>
<?php
	} // function opm_list_posts()
	

} // OrderYourPostsManually
?>
<?php
/********************************************************************************************

	AJAX SERVER FOR RETRIEVING SETS OF POSTS
	
	v1.7.1	bugs fixed

*********************************************************************************************/
function opm_action_callback()
{
	global $wpdb;

	// GET THE PARAMETERS
	if(!isset($_POST['pagnr'])) wp_die();
	
	$pagnr               = intval($_POST['pagnr']);
	$cat_id              = $_POST['cat_id'];
	$opm_posts_per_page  = intval($_POST['opm_posts_per_page']);
	$opm_post_type       = $_POST['opm_post_type'];
	$opm_show_thumbnails = $_POST['opm_show_thumbnails'];
	$opm_thumbnail_size  = $_POST['opm_thumbnail_size'];
	$nr_of_stickies      = intval($_POST['nr_of_stickies']);
	$nr_of_posts         = intval($_POST['nr_of_posts']);
	$field_name          = $_POST['field_name'];

	if($opm_posts_per_page > 0)
	{	// LIMITED NUMBER OF POSTS PER PAGE
		$start = ($pagnr-1)*$opm_posts_per_page;
		$end   = $start + $opm_posts_per_page;
		$end   = min($end, $nr_of_posts);
	}
	else
	{	// ALL POSTS
		$start = 0;
		$end   = $nr_of_posts;
	}

	if(isset($cat_id) && $cat_id > 0)
	{
		$myposts = get_posts( array(
			'category' => $cat_id,
			'post_type' => $opm_post_type,
			'post__not_in' => get_option( 'sticky_posts' ),
			'posts_per_page' => 999999,
			'orderby' => $field_name
		) );
	}
	else
	{
		$myposts = get_posts( array(
			'post_type' => $opm_post_type,
			'post__not_in' => get_option( 'sticky_posts' ),
			'posts_per_page' => 999999,
			'orderby' => $field_name
		) );	
	}
	
	if (count($myposts) < 1)
	{
		_e('No '.$post_type.'s found', 'order-your-posts-manually');
	}
	else
	{
		// COLLECT THE POSTS
		$posts = '';
		for($i=$start; $i<$end; $i++)
		{
			$thumb = wp_get_attachment_image_src( get_post_thumbnail_id($myposts[$i]->ID), 'thumbnail' );
			$url = $thumb['0'];			
			if($field_name == 'post_date')
				$this_date = $myposts[$i]->post_date;
			else
				$this_date = $myposts[$i]->post_modified;
			if($url && $opm_show_thumbnails == "Y")
			{	$posts .= '<li id="post-id-'.$myposts[$i]->ID.'" class="ui-state-default" style="height:'.$opm_thumbnail_size.'px;" title="Post ID: '.$myposts[$i]->ID.'"><div class="opm-post-text" style="float:left"><small>'.$this_date.'</small> * <strong>'.$myposts[$i]->post_title.'</strong></div><div class="opm-post-thumb" style="float:right"><img src="'.$url.'" width="'.$opm_thumbnail_size.'" height="'.$opm_thumbnail_size.'"></div></li>';
			}
			else
			{	$posts .= '<li id="post-id-'.$myposts[$i]->ID.'" class="ui-state-default" title="Post ID: '.$myposts[$i]->ID.'"><small>'.$this_date.'</small> * <strong>'.$myposts[$i]->post_title.'</strong></li>';
			}
		}

		// RETURN THE SET OF POSTS TO THE CALLER
		echo $posts;
	}

	// NEEDED FOR AN AJAX SERVER
	wp_die();
} // opm_action_callback()
add_action( 'wp_ajax_opm_action', 'opm_action_callback' );
?>
