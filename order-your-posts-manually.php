<?php
$opm_version      = '1.3';
$opm_release_date = '08/21/2014';
/*
Plugin Name: Order your Posts Manually
Plugin URI: http://cagewebdev.com/order-posts-manually
Description: Order your Posts Manually by Dragging and Dropping them
Version: 1.3
Date: 08/21/2014
Author: Rolf van Gelder
Author URI: http://cagewebdev.com/
License: GPL2
*/
?>
<?php
/********************************************************************************************

	ADD THE 'ORDER POSTS MANUALLY' ITEM TO THE ADMIN TOOLS MENU

*********************************************************************************************/
function opm_main()
{	if (function_exists('add_management_page'))
	{	add_management_page(__('Order Posts Manually'), __('Order Posts Manually'), 'administrator','opm-order-posts.php', 'opm_list_posts');
    }
} // opm_main()
add_action('admin_menu', 'opm_main');


/********************************************************************************************

	ADD THE OPTIONS PAGE TO THE SETTINGS MENU

*********************************************************************************************/
function opm_admin_menu()
{	
	if (function_exists('add_options_page'))
	{	add_options_page(__('Order Posts Options'), __('Order Posts Options'), 'manage_options', 'opm_admin', 'opm_options_page');
    }
} // opm_admin_menu()
add_action( 'admin_menu', 'opm_admin_menu' );


/********************************************************************************************

	THE MAIN FUNCTION FOR ORDERING THE POSTS

*********************************************************************************************/
function opm_list_posts()
{
	global $wpdb, $opm_version;
	
	// GET SORTING ORDER FROM OPTIONS
	$opm_date_field = get_option('opm_date_field');
	$field_name = ($opm_date_field == 0) ? 'post_date' : 'post_modified';

	// GET NUMBER OF POSTS PER PAGE FROM OPTIONS
	$opm_posts_per_page = get_option('opm_posts_per_page');
	
	// DEFAULT: ALL POSTS AT ONCE
	if(!$opm_posts_per_page) $opm_posts_per_page = 0;

	/*************************************************************************
	*
	*	SAVE SETTINGS
	*
	*************************************************************************/
	if(count($_POST)>0 && $_POST['action'] == 'update_dates')
	{
		$dates = explode('#', $_POST['dates']);
		$postids = explode('&', $_POST['sortdata']);
		for($p=0; $p<count($postids); $p++)
		{	$q = explode('=', $postids[$p]);
			$post_id = $q[1];
			$sql = "
			UPDATE $wpdb->posts
			SET `".$field_name."` = '$dates[$p]'
			WHERE `ID` = $post_id
			";
			$wpdb -> get_results($sql);
		}
		echo "<div class='updated'><p><strong>SORT ORDER SAVED!</strong>";
	}

	$sql = "
	SELECT `ID`, `".$field_name."`, `post_title`
	FROM   $wpdb->posts
	WHERE `post_type`   = 'post'
	AND   `post_status` = 'publish'
	ORDER BY `".$field_name."` DESC
	";
	$results = $wpdb -> get_results($sql);

	$dates                = '';
	$nr_of_stickies       = 0;
	$nr_of_posts          = 0;
	
	/*************************************************************************************
	 *
	 *	COUNT THE NUMBER OF STICKIES AND SAVE THE ORIGINAL DATES TO A STRING
	 *
	 ************************************************************************************/
	for($i=0; $i<count($results); $i++)
	{
		if(is_sticky($results[$i]->ID))
		{
			$nr_of_stickies++;
		}
		else
		{	$nr_of_posts++;
			if($dates) $dates .= "#";
			if($field_name == 'post_date')
			{
				$dates .= $results[$i]->post_date;
				$mode = 'creation date';
			}
			else
			{
				$dates .= $results[$i]->post_modified;
				$mode = 'modification date';
			}
		}
	} // for($i=0; $i<count($results); $i++)
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
function get_posts()
{
	if(done) return;
	
	var data = {
		'action': 'my_action',
		'opm_posts_per_page': <?php echo $opm_posts_per_page;?>,
		'nr_of_stickies': <?php echo $nr_of_stickies;?>,
		'nr_of_posts': <?php echo $nr_of_posts;?>,
		'pagnr': pagnr,
		'field_name': '<?php echo $field_name;?>'
	};

/*		$start = ($pagnr-1)*$opm_posts_per_page + $nr_of_stickies;
		$end   = $start + $opm_posts_per_page;
		$end   = min($end, $nr_of_posts);*/

	// <ajaxurl> IS DEFINED SINCE WP v2.8
	$.post(ajaxurl, data, function(response) {
		// alert('Got this from the server: ' + response);
		$("#sortable").append(response);
		pagnr++;
		busy = false;
		$("#loading").hide();
	});
	
	var end = (pagnr-1)*<?php echo $opm_posts_per_page;?>+<?php echo $nr_of_stickies;?>+<?php echo $opm_posts_per_page;?>;
	if(end > <?php echo $nr_of_posts;?>) done = true;
} // get_posts()


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
	if(!done) get_posts();
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
		// GET NEXT OF POSTS
		get_posts();
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
    <h1>Order your Posts Manually - v<?php echo $opm_version;?> (sort type: <?php echo $mode; ?>)</h1>
    <p><strong><br />
      WARNING:<br />
      Running this plugin will actually change the CREATION- or MODIFICATION dates of your posts in the database, to change the display order.<br />
      It will swap some of the dates. <br />
      So, if you think the EXACT DATES of when a post was created and / or modified are more important than the order of the posts: DON'T USE THIS PLUGIN!</strong></p>
    <br />
    <strong style="color:#00F;">STICKY POSTS (<?php echo $nr_of_stickies;?>):</strong><br />
    <br />
    <ul id="stickies">
      <?php
	/*************************************************************************
	*
	*	DISPLAY STICKIES
	*
	*************************************************************************/	  
	for($i=0; $i<count($results); $i++)
	{	if(is_sticky($results[$i]->ID))
		{
			if($field_name == 'post_date')
				$this_date = $results[$i]->post_date;
			else
				$this_date = $results[$i]->post_modified;			
?>
      <li class="ui-state-default" title="Post ID: <?php echo $results[$i]->ID?>"><small><?php echo $this_date?></small> * <strong><?php echo $results[$i]->post_title?></strong></li>
      <?php
		}
	} // for($i=0; $i<count($results); $i++)
?>
    </ul>
    <br />
    <strong style="color:#00F;">REGULAR POSTS (<?php echo $nr_of_posts;?>):</strong><br />
    <br />
    <strong>Drag and drop the posts to change the display order!</strong><br />
    (After changing the order, don't forget to click the <strong>SAVE CHANGES</strong> button to actually update the posts)<br />
    <br />
    <input name="submit" type="submit" value="SAVE CHANGES" class="button-primary button-large" />
    &nbsp;&nbsp;&nbsp;
    <input name="cancel" value="RELOAD POSTS" type="button" onclick="self.location='';" class="button" />
    <br />
    <br />
<?php    
	/*************************************************************************
	*
	*	PLACEHOLDER FOR THE ACTUAL POSTS
	*
	*************************************************************************/
?>	
    <ul id="sortable">
    </ul><br />
    <div id="loading" style="display:none;">Loading new posts...<br /><br /><br /></div>
<?php    
	/*************************************************************************
	*
	*	BOTTOM BUTTONS
	*
	*************************************************************************/
?>	    
    <input name="submit" type="submit" value="SAVE CHANGES" class="button-primary button-large" />
    &nbsp;&nbsp;&nbsp;
    <input name="cancel" value="RELOAD POSTS" type="button" onclick="self.location='';" class="button" />
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
		$start = ($pagnr-1)*$opm_posts_per_page + $nr_of_stickies;
		$end   = $start + $opm_posts_per_page;
		$end   = min($end, $nr_of_posts);
	}
	else
	{	// ALL POSTS
		$start = 1;
		$end   = $nr_of_posts;
	}
	
	$sql = "
	SELECT `ID`, `".$field_name."`, `post_title`
	FROM   $wpdb->posts
	WHERE `post_type`   = 'post'
	AND   `post_status` = 'publish'
	ORDER BY `".$field_name."` DESC
	";
	$results = $wpdb -> get_results($sql);	

	// COLLECT THE POSTS
	$posts = '';
	for($i=$start; $i<$end; $i++)
	{	if($field_name == 'post_date')
			$this_date = $results[$i]->post_date;
		else
			$this_date = $results[$i]->post_modified;
		$posts .= '<li id="post-id-'.$results[$i]->ID.'" class="ui-state-default" title="Post ID: '.$results[$i]->ID.'"><small>'.$this_date.'</small> * <strong>'.$results[$i]->post_title.'</strong></li>';
	} // for($i=$start; $i<$end; $i++)

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
		echo "<div class='updated'><p><strong>Order Posts Manually OPTIONS UPDATED!</strong>";
	}

	$opm_date_field = get_option('opm_date_field');
	if(!$opm_date_field) $opm_date_field = '0';	// sort order: creation date (default)
	
	$opm_posts_per_page = get_option('opm_posts_per_page');
	if(!$opm_posts_per_page) $opm_posts_per_page = '0';	// ALL posts (default)
?>
<style type="text/css">
#opm_options_form {
	margin: 40px;
}
</style>
<div id="opm_options_form">
  <p>
  <h1>Order your Posts Manually</h1>
  <em><strong>With this plugin your visually can change the order of the posts for when they will be displayed</strong></em>
  </p>
  <p> Version: <strong>v<?php echo $opm_version; ?></strong> - <strong><?php echo $opm_release_date; ?></strong><br />
    Author: <strong>Rolf van Gelder - CAGE Web Design, Eindhoven, The Netherlands</strong><br>
    Website: <a href="http://cagewebdev.com" target="_blank">http://cagewebdev.com</a><br />
    Plugin page: <a href="http://cagewebdev.com/order-posts-manually/" target="_blank">http://cagewebdev.com/order-posts-manually/</a><br />
    Download page: <a href="http://wordpress.org/plugins/order-your-posts-manually/" target="_blank">http://wordpress.org/plugins/order-your-posts-manually/</a></p>
  <br />
  <hr />
  <br />
  <h2>Order your Posts Manually - Options</h2>
  <p><strong>WARNING:<br />
    Running this plugin will actually change the CREATION- or MODIFICATION dates of your posts in the database, to change the display order.<br />
    It will swap some of the dates. </strong><strong><br />
    So if you think the EXACT DATES of when a post was created and / or modified are extremely important: DON'T USE THIS PLUGIN!<br />
    If you think the DISPLAY ORDER of your posts 
    is more important than the exact dates, then DO USE this plugin!</strong></p>
  <p>Per default WordPress orders the posts using the <strong>CREATION date</strong> (also known as '<strong>post_date</strong>'). Last created posts first.</p>
  <p>Some site designers (including myself) prefer the ordering of the posts using the <strong>MODIFICATION date</strong> (go to <a href="http://web20bp.com/kb/wordpress-sort-posts-on-modified-date/" target="_blank">this page</a> to see how to do that).<br />
    So, last modified posts will show up first.</p>
  <p>If you belong to the second category (<strong>MODIFICATION date</strong>) select the second option in the drop down box below!</p>
  <form action="" method="post" name="options_form">
    <input name="action" type="hidden" value="save_options" />
    <select name="opm_date_field" id="opm_date_field">
      <option value="0">use CREATION DATES of the posts</option>
      <option value="1">use MODIFICATION DATES of the posts</option>
    </select>
    <script type="text/javascript">
	jQuery("#opm_date_field").val(<?php echo $opm_date_field; ?>);
	</script><br />
    <br />
    <p><strong>Number of posts to show per page:</strong></p>
    <select name="opm_posts_per_page" id="opm_posts_per_page">
      <option value="0">show ALL posts at once</option>
      <option value="10">10</option>
      <option value="25">25</option>
      <option value="50">50</option>
      <option value="100">100</option>
      <option value="250">250</option>
      <option value="500">500</option>
    </select>
    <script type="text/javascript">
	jQuery("#opm_posts_per_page").val(<?php echo $opm_posts_per_page; ?>);
	</script><br />
    <br />
    <br />
    <input name="submit" type="submit" value="SAVE OPTIONS" class="button-primary button-large" />
  </form>
</div>
<?php
}
?>
