<?php
$opm_version      = '1.7.2';
$opm_release_date = '08/06/2015';
/*
Plugin Name: Order your Posts Manually
Plugin URI: http://cagewebdev.com/order-posts-manually
Description: Order your Posts Manually by Dragging and Dropping them
Version: 1.7.2
Date: 08/06/2015
Author: Rolf van Gelder
Author URI: http://cagewebdev.com/
License: GPLv2 or later
*/
?>
<?php
/********************************************************************************************

	REGISTER TEXT DOMAIN FOR LANGUAGE SUPPORT (LOCALIZATION)

*********************************************************************************************/
function opm_action_init()
{
	// TEXT DOMAIN
	load_plugin_textdomain('order-your-posts-manually', false, dirname(plugin_basename(__FILE__)).'/languages/');
} // opm_action_init()

// INIT HOOK
add_action('init', 'opm_action_init');


/********************************************************************************************

	ADD THE 'ORDER POSTS MANUALLY' ITEM TO THE ADMIN TOOLS MENU

*********************************************************************************************/
function opm_main()
{	if (function_exists('add_management_page'))
	{	// v1.7.2 'administrator' changed to 'manage_options'
		add_management_page(__('Order Your Posts Manually','order-your-posts-manually'), __('Order Your Posts Manually','order-your-posts-manually'), 'manage_options','opm-order-posts.php', 'opm_list_posts');
    }
} // opm_main()
add_action('admin_menu', 'opm_main');


/********************************************************************************************

	ADD THE OPTIONS PAGE TO THE SETTINGS MENU

*********************************************************************************************/
function opm_admin_menu()
{	
	if (function_exists('add_options_page'))
	{	add_options_page(__('Order Your Posts Manually','order-your-posts-manually'), __('Order Your Posts Manually','order-your-posts-manually'), 'manage_options', 'opm_admin', 'opm_options_page');
    }
} // opm_admin_menu()
add_action( 'admin_menu', 'opm_admin_menu' );


 /********************************************************************************************

	SHOW A LINK TO THE PLUGIN SETTINGS ON THE MAIN PLUGINS PAGE
	
	Since: v1.2.7

*********************************************************************************************/
function opm_settings_link($links)
{ 
  array_unshift($links, '<a href="options-general.php?page=opm_admin">Settings</a>'); 
  return $links;
} // opm_settings_link()
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'opm_settings_link');


/********************************************************************************************

	LOAD JAVASCRIPT

*********************************************************************************************/
function opm_scripts()
{	wp_register_script( 'opm-jquery-ui', plugins_url('order-your-posts-manually/js/jquery-ui.min.js'), array('jquery'), '1.11.3', true);
	wp_enqueue_script( 'opm-jquery-ui' );	
} // opm_styles()
add_action( 'admin_init', 'opm_scripts' );


/********************************************************************************************

	LOAD STYLE SHEET(S)

*********************************************************************************************/
function opm_styles()
{	wp_enqueue_style ('opm-style', plugin_dir_url(__FILE__) . 'css/style.css',false,'1.0','all');
} // opm_styles()
add_action( 'admin_init', 'opm_styles' );


/********************************************************************************************

	THE MAIN FUNCTION FOR ORDERING THE POSTS

*********************************************************************************************/
function opm_list_posts()
{
	global $wpdb, $opm_version, $opm_release_date;
	
	// GET SORTING ORDER FROM OPTIONS
	$opm_date_field = get_option('opm_date_field');
	$field_name = ($opm_date_field == 0) ? 'post_date' : 'post_modified';

	// GET NUMBER OF POSTS PER PAGE FROM OPTIONS
	$opm_posts_per_page = get_option('opm_posts_per_page');
	
	// DEFAULT: ALL POSTS AT ONCE
	if(!$opm_posts_per_page) $opm_posts_per_page = 0;
	
	// TYPES TO ORDER, DEFAULT = POST
	$opm_post_type = get_option('opm_post_type');
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
		'opm_posts_per_page': <?php echo $opm_posts_per_page;?>,
		'opm_post_type': '<?php echo $opm_post_type;?>',
		'nr_of_stickies': <?php echo $nr_of_stickies;?>,
		'nr_of_posts': <?php echo $nr_of_posts;?>,	// EXCL. STICKIES
		'pagnr': pagnr,
		'field_name': '<?php echo $field_name;?>'
	};

	// <ajaxurl> IS DEFINED SINCE WP v2.8!
	jQuery.post(ajaxurl, data, function(response) {
		jQuery("#sortable").append(response);
		pagnr++;
		busy = false;
		jQuery("#loading").hide();
	});
	
	var end = ((pagnr-1)*<?php echo $opm_posts_per_page;?>)+<?php echo $nr_of_stickies;?>+<?php echo $opm_posts_per_page;?>;
	if(end > <?php echo $nr_of_posts;?> || <?php echo $opm_posts_per_page;?> == 0) done = true;
} // opm_get_posts()


/*************************************************************************************
 *
 *	INITIALIZE JQUERY
 *
 ************************************************************************************/
jQuery(document).ready(function ()
{	// TAKE CARE OF THE DRAGGING AND DROPPING
	jQuery('#sortable').sortable({
			placeholder: 'placeholder',
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
{	if(!busy && !done && (jQuery(window).scrollTop() + jQuery(window).height() == jQuery(document).height()))
	{	busy = true;
		jQuery("#loading").show();
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
  <br />
  <div id="post-table">
    <h1>Order your Posts Manually (<?php echo __('sort type', 'order-your-posts-manually'); ?>: <?php echo $mode; ?>)</h1>
    <p> <?php echo __('Version', 'order-your-posts-manually'); ?>: <strong>v<?php echo $opm_version; ?></strong> - <strong><?php echo $opm_release_date; ?></strong><br />
      <?php echo __('Author', 'order-your-posts-manually'); ?>: <a href="http://rvg.cage.nl" target="_blank">Rolf van Gelder</a> - <a href="http://cagewebdev.com" target="_blank">CAGE Web Design</a>, Eindhoven, <?php echo __('The Netherlands', 'order-your-posts-manually'); ?></strong><br>
    </p>
    <p><strong><br />
      <?php echo __('WARNING', 'order-your-posts-manually'); ?>:<br />
      <?php echo __('Running this plugin will actually change the CREATION- or MODIFICATION dates of your posts in the database, to change the display order.', 'order-your-posts-manually'); ?><br />
      <?php echo __('It will swap some of the dates.', 'order-your-posts-manually'); ?><br />
      <?php echo __('So, if you think the EXACT DATES of when a post was created and / or modified are more important than the order of the posts: DON\'T USE THIS PLUGIN!', 'order-your-posts-manually'); ?></strong></p>
    <br />
    <strong style="color:#00F;"><?php echo __('STICKY POSTS', 'order-your-posts-manually'); ?> (<?php echo $nr_of_stickies;?>):</strong><br />
    <br />
    <ul id="stickies">
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
    <ul id="sortable">
    </ul>
    <br />
    <?php
	/*************************************************************************
	*
	*	LOADING ANIMATION
	*
	*************************************************************************/	
	?>
    <div id="loading" align="center"><img src="<?php echo $loader_image;?>" /><br />
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


/********************************************************************************************

	AJAX SERVER FOR RETRIEVING SETS OF POSTS
	
	v1.7.1	bugs fixed

*********************************************************************************************/
function opm_action_callback()
{
	global $wpdb;

	// GET THE PARAMETERS
	if(!isset($_POST['pagnr'])) wp_die();
	
	$pagnr              = intval($_POST['pagnr']);
	$cat_id             = $_POST['cat_id'];
	$opm_posts_per_page = intval($_POST['opm_posts_per_page']);
	$opm_post_type      = $_POST['opm_post_type'];	
	$nr_of_stickies     = intval($_POST['nr_of_stickies']);
	$nr_of_posts        = intval($_POST['nr_of_posts']);
	$field_name         = $_POST['field_name'];

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
			if($field_name == 'post_date')
				$this_date = $myposts[$i]->post_date;
			else
				$this_date = $myposts[$i]->post_modified;
			$posts .= '<li id="post-id-'.$myposts[$i]->ID.'" class="ui-state-default" title="Post ID: '.$myposts[$i]->ID.'"><small>'.$this_date.'</small> * <strong>'.$myposts[$i]->post_title.'</strong></li>';
		}

		// RETURN THE SET OF POSTS TO THE CALLER
		echo $posts;
	}

	// NEEDED FOR AN AJAX SERVER
	wp_die();
} // opm_action_callback()
add_action( 'wp_ajax_opm_action', 'opm_action_callback' );


/********************************************************************************************

	CREATE THE OPTIONS PAGE

*********************************************************************************************/
function opm_options_page()
{
	global $opm_version, $opm_release_date, $wpdb;
	
	if (isset($_POST['action']) && $_POST['action']=='save_options')
	{
		update_option('opm_date_field', $_REQUEST['opm_date_field']);
		update_option('opm_posts_per_page', $_REQUEST['opm_posts_per_page']);
		update_option('opm_post_type', $_REQUEST['opm_post_type']);
		echo "<div class='updated'><p><strong>".__('Order Your Posts Manually SETTINGS UPDATED!','order-your-posts-manually')."</strong></p></div>";
	}

	$opm_date_field = get_option('opm_date_field');
	if(!$opm_date_field) $opm_date_field = '0';	// sort order: creation date (default)
	
	$opm_posts_per_page = get_option('opm_posts_per_page');
	if(!$opm_posts_per_page) $opm_posts_per_page = '0';	// ALL posts (default)
	
	$opm_post_type = get_option('opm_post_type');
	if(!$opm_post_type) $opm_post_type = 'post';
?>
<!--<script src="//code.jquery.com/jquery-1.10.2.js"></script>-->
<div class="opm-title-bar">
  <h2>
    <?php _e( 'Order Your Posts Manually - change the display order of your posts by dragging and dropping', 'order-your-posts-manually' ); ?>
  </h2>
</div>
<div class="opm-intro">
  <?php _e( 'Plugin version', 'order-your-posts-manually' ); ?>: v<?php echo $opm_version?> [<?php echo $opm_release_date?>] - <a href="http://cagewebdev.com/order-posts-manually/" target="_blank"><?php _e( 'Plugin page', 'order-your-posts-manually' ); ?></a> - <a href="http://wordpress.org/plugins/order-your-posts-manually/" target="_blank"><?php _e( 'Download page', 'order-your-posts-manually' ); ?></a> - <a href="http://cagewebdev.com/index.php/donations-opm/" target="_blank"><?php _e( 'Donation page', 'order-your-posts-manually' ); ?></a>
  <p> <?php echo __('WARNING','order-your-posts-manually');?>:<br />
    <?php echo __('Running this plugin will actually change the CREATION- or MODIFICATION dates of your posts in the database, to change the display order.', 'order-your-posts-manually'); ?><br />
    <?php echo __('It will swap some of the dates.', 'order-your-posts-manually'); ?><br />
    <?php echo __('So, if you think the EXACT DATES of when a post was created and / or modified are more important than the order of the posts: DON\'T USE THIS PLUGIN!', 'order-your-posts-manually'); ?></p>
</div>
<div id="opm-options-form">
  <h2><?php echo __('Settings', 'order-your-posts-manually'); ?></h2>
  <p><?php echo __('Per default WordPress orders the posts using the <strong>CREATION date</strong> (also known as \'<strong>post_date</strong>\'). Last created posts first.','order-your-posts-manually');?></p>
  <p><?php echo __('Some site designers (including myself) prefer the ordering of the posts using the <strong>MODIFICATION date</strong> (go to <a href="http://web20bp.com/kb/wordpress-sort-posts-on-modified-date/" target="_blank">this page</a> to see how to do that).<br />So, last modified posts will show up first.', 'order-your-posts-manually'); ?></p>
  <p><?php echo __('If you belong to the second category (<strong>MODIFICATION date</strong>) select the second option in the drop down box below!', 'order-your-posts-manually'); ?></p>
  <form action="" method="post" name="opm_settings" id="opm_settings">
    <input name="action" type="hidden" value="save_options" />
    <select name="opm_date_field" id="opm_date_field">
      <option value="0"><?php echo __('use CREATION DATES of the posts', 'order-your-posts-manually'); ?></option>
      <option value="1"><?php echo __('use MODIFICATION DATES of the posts', 'order-your-posts-manually'); ?></option>
    </select>
    <script type="text/javascript">
	jQuery("#opm_date_field").val(<?php echo $opm_date_field; ?>);
	</script><br />
    <br />
    <p><strong><?php echo __('Number of posts to show per page', 'order-your-posts-manually'); ?>:</strong></p>
    <select name="opm_posts_per_page" id="opm_posts_per_page">
      <option value="0"><?php echo __('show ALL posts at once', 'order-your-posts-manually'); ?></option>
      <option value="10">10</option>
      <option value="25">25</option>
      <option value="50">50</option>
      <option value="100">100</option>
      <option value="250">250</option>
      <option value="500">500</option>
    </select>
    <script type="text/javascript">
	jQuery("#opm_posts_per_page").val('<?php echo $opm_posts_per_page; ?>');
	</script><br />
    <br />
    <p><strong><?php echo __('Post type to show (default: \'post\')', 'order-your-posts-manually'); ?>:</strong></p>
    <input name="opm_post_type" id="opm_post_type" type="text" value="<?php echo $opm_post_type?>" size="20" />
    <br />
    <br />
    <br />
    <input name="submit" type="submit" value="<?php echo __('SAVE SETTINGS', 'order-your-posts-manually'); ?>" class="button-primary button-large" />
  </form>
</div>
<?php
}
?>
