<?php
$opm_version      = '1.5';
$opm_release_date = '10/20/2014';
/*
Plugin Name: Order your Posts Manually
Plugin URI: http://cagewebdev.com/order-posts-manually
Description: Order your Posts Manually by Dragging and Dropping them
Version: 1.5
Date: 10/20/2014
Author: Rolf van Gelder
Author URI: http://cagewebdev.com/
License: GPLv2 or later
*/
?>
<?php
/********************************************************************************************

	ADD THE LANGUAGE SUPPORT (LOCALIZATION)

*********************************************************************************************/
function opm_action_init()
{
	// TEXT DOMAIN
	load_plugin_textdomain('order-your-posts-manually', false, dirname(plugin_basename(__FILE__)));
}

// INIT HOOK
add_action('init', 'opm_action_init');


/********************************************************************************************

	ADD THE 'ORDER POSTS MANUALLY' ITEM TO THE ADMIN TOOLS MENU

*********************************************************************************************/
function opm_main()
{	if (function_exists('add_management_page'))
	{	add_management_page(__('Order Your Posts Manually','order-your-posts-manually'), __('Order Your Posts Manually','order-your-posts-manually'), 'administrator','opm-order-posts.php', 'opm_list_posts');
    }
} // opm_main()
add_action('admin_menu', 'opm_main');


/********************************************************************************************

	ADD THE OPTIONS PAGE TO THE SETTINGS MENU

*********************************************************************************************/
function opm_admin_menu()
{	
	if (function_exists('add_options_page'))
	{	add_options_page(__('Order Your Posts Manually Options','order-your-posts-manually'), __('Order Your Posts Manually Options','order-your-posts-manually'), 'manage_options', 'opm_admin', 'opm_options_page');
    }
} // opm_admin_menu()
add_action( 'admin_menu', 'opm_admin_menu' );


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
	$args    = array( 'posts_per_page' => 999999, 'orderby' => $field_name );
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
<script src="//code.jquery.com/jquery-1.10.2.js"></script>
<script src="//code.jquery.com/ui/1.11.0/jquery-ui.js"></script>
<style>
#stickies {
	list-style-type: none;
	margin: 0;
	padding: 0;
}
#stickies li {
	margin: 0 3px 10px 0px;
	padding: 0.3em;
	font-size: 1.1em;
	height: 16px;
}
#sortable {
	list-style-type: none;
	margin: 0;
	padding: 0;
}
#sortable li {
	margin: 0 3px 10px 0px;
	padding: 0.3em;
	font-size: 1.1em;
	height: 16px;
}
#sortable li span {
	position: absolute;
	margin-left: -1.3em;
}
#sortable li:hover {
	background-color: #FF0;
}
.ui-state-default {
	border: solid 1px #663366;
	background-color: #FFF;
}
.placeholder {
	border: dashed 1px #FF0000;
}
small {
	font-size: 0.9em;
}
</style>
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
	
	var data = {
		'action': 'my_action',
		'opm_posts_per_page': <?php echo $opm_posts_per_page;?>,
		'nr_of_stickies': <?php echo $nr_of_stickies;?>,
		'nr_of_posts': <?php echo $nr_of_posts;?>,	// EXCL. STICKIES
		'pagnr': pagnr,
		'field_name': '<?php echo $field_name;?>'
	};

	// <ajaxurl> IS DEFINED SINCE WP v2.8!
	jQuery.post(ajaxurl, data, function(response) {
		$("#sortable").append(response);
		pagnr++;
		busy = false;
		$("#loading").hide();
	});
	
	var end = ((pagnr-1)*<?php echo $opm_posts_per_page;?>)+<?php echo $nr_of_stickies;?>+<?php echo $opm_posts_per_page;?>;
	if(end > <?php echo $nr_of_posts;?> || <?php echo $opm_posts_per_page;?> == 0) done = true;
} // opm_get_posts()


/*************************************************************************************
 *
 *	INITIALIZE JQUERY
 *
 ************************************************************************************/
$(document).ready(function ()
{	// TAKE CARE OF THE DRAGGING AND DROPPING
	$('#sortable').sortable({
			placeholder: 'placeholder',
			stop: function (event, ui) {
				var oData = $(this).sortable('serialize');
				$('#sortdata').val(oData);
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
$(window).scroll(function()
{	if(!busy && !done && ($(window).scrollTop() + $(window).height() == $(document).height()))
	{	busy = true;
		$("#loading").show();
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
<form action="" method="post">
  <input type="hidden" id="action" name="action" value="update_dates" />
  <input type="hidden" id="sortdata" name="sortdata" value="" />
  <input type="hidden" id="dates" name="dates" value="<?php echo $dates;?>" />
  <br />
  <div id="post_table" style="margin:20px;">
    <h1>Order your Posts Manually - v<?php echo $opm_version;?> (<?php echo __('sort type', 'order-your-posts-manually'); ?>: <?php echo $mode; ?>)</h1>
    <p>
    <?php echo __('Version', 'order-your-posts-manually'); ?>: <strong>v<?php echo $opm_version; ?></strong> - <strong><?php echo $opm_release_date; ?></strong><br />
    <?php echo __('Author', 'order-your-posts-manually'); ?>: <strong>Rolf van Gelder - <a href="http://cagewebdev.com" target="_blank">CAGE Web Design</a>, Eindhoven, <?php echo __('The Netherlands', 'order-your-posts-manually'); ?></strong><br>
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
	$loader_image = plugins_url().'/order-your-posts-manually/loader.gif';
?>
    <ul id="sortable">
    </ul>
    <br />
    <div id="loading" style="display:none;" align="center"><img src="<?php echo $loader_image;?>" /><br />
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

*********************************************************************************************/
add_action( 'wp_ajax_my_action', 'my_action_callback' );

function my_action_callback()
{
	global $wpdb;

	// GET THE PARAMETERS
	$pagnr                = intval($_POST['pagnr']);
	$opm_posts_per_page   = intval($_POST['opm_posts_per_page']);
	$nr_of_stickies       = intval($_POST['nr_of_stickies']);
	$nr_of_posts          = intval($_POST['nr_of_posts']);
	$field_name           = $_POST['field_name'];

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

	$args    = array( 'posts_per_page' => 999999, 'orderby' => $field_name );
    $myposts = get_posts( array( 'post__not_in' => get_option( 'sticky_posts' ), 'posts_per_page' => 999999, 'orderby' => $field_name ) );
	
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

	// NEEDED FOR AN AJAX SERVER
	die();
}


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
		echo "<div class='updated'><p><strong>".__('Order Your Posts Manually OPTIONS UPDATED!','order-your-posts-manually')."</strong></p></div>";
	}

	$opm_date_field = get_option('opm_date_field');
	if(!$opm_date_field) $opm_date_field = '0';	// sort order: creation date (default)
	
	$opm_posts_per_page = get_option('opm_posts_per_page');
	if(!$opm_posts_per_page) $opm_posts_per_page = '0';	// ALL posts (default)
?>
<script src="//code.jquery.com/jquery-1.10.2.js"></script>
<style type="text/css">
#opm_options_form {
	margin: 40px;
}
</style>
<div id="opm_options_form">
  <p>
  <h1><?php echo __('Order Your Posts Manually', 'order-your-posts-manually'); ?></h1>
  <em><strong><?php echo __('With this plugin your visually can change the order of the posts for when they will be displayed', 'order-your-posts-manually'); ?></strong></em>
  </p>
  <p><?php echo __('Version', 'order-your-posts-manually'); ?>: <strong>v<?php echo $opm_version; ?></strong> - <strong><?php echo $opm_release_date; ?></strong><br />
    <?php echo __('Author', 'order-your-posts-manually'); ?>: <strong>Rolf van Gelder - <a href="http://cagewebdev.com" target="_blank">CAGE Web Design</a>, Eindhoven, <?php echo __('The Netherlands', 'order-your-posts-manually'); ?></strong><br>
    <?php echo __('Website', 'order-your-posts-manually'); ?>: <a href="http://cagewebdev.com" target="_blank">http://cagewebdev.com</a><br />
    <?php echo __('Plugin page', 'order-your-posts-manually'); ?>: <a href="http://cagewebdev.com/order-posts-manually/" target="_blank">http://cagewebdev.com/order-posts-manually/</a><br />
    <?php echo __('Download page', 'order-your-posts-manually'); ?>: <a href="http://wordpress.org/plugins/order-your-posts-manually/" target="_blank">http://wordpress.org/plugins/order-your-posts-manually/</a></p>
  <br />
  <hr />
  <br />
  <h2><?php echo __('Order Your Posts Manually - Options', 'order-your-posts-manually'); ?></h2>
  <p><strong><?php echo __('WARNING','order-your-posts-manually');?>:<br />
    <?php echo __('Running this plugin will actually change the CREATION- or MODIFICATION dates of your posts in the database, to change the display order.', 'order-your-posts-manually'); ?><br />
    <?php echo __('It will swap some of the dates.', 'order-your-posts-manually'); ?><br />
    <?php echo __('So, if you think the EXACT DATES of when a post was created and / or modified are more important than the order of the posts: DON\'T USE THIS PLUGIN!', 'order-your-posts-manually'); ?></strong></p></strong></p>
  <p><?php echo __('Per default WordPress orders the posts using the <strong>CREATION date</strong> (also known as \'<strong>post_date</strong>\'). Last created posts first.','order-your-posts-manually');?></p>
  <p><?php echo __('Some site designers (including myself) prefer the ordering of the posts using the <strong>MODIFICATION date</strong> (go to <a href="http://web20bp.com/kb/wordpress-sort-posts-on-modified-date/" target="_blank">this page</a> to see how to do that).<br />So, last modified posts will show up first.', 'order-your-posts-manually'); ?></p>
  <p><?php echo __('If you belong to the second category (<strong>MODIFICATION date</strong>) select the second option in the drop down box below!', 'order-your-posts-manually'); ?></p>
  <form action="" method="post" name="options_form">
    <input name="action" type="hidden" value="save_options" />
    <select name="opm_date_field" id="opm_date_field">
      <option value="0"><?php echo __('use CREATION DATES of the posts', 'order-your-posts-manually'); ?></option>
      <option value="1"><?php echo __('use MODIFICATION DATES of the posts', 'order-your-posts-manually'); ?></option>
    </select>
    <script type="text/javascript">
	$("#opm_date_field").val(<?php echo $opm_date_field; ?>);
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
	$("#opm_posts_per_page").val('<?php echo $opm_posts_per_page; ?>');
	</script><br />
    <br />
    <br />
    <input name="submit" type="submit" value="<?php echo __('SAVE OPTIONS', 'order-your-posts-manually'); ?>" class="button-primary button-large" />
  </form>
</div>
<?php
}
?>
