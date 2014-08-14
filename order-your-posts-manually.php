<?php
$opm_version      = '1.1';
$opm_release_date = '08/14/2014';
/*
Plugin Name: Order your Posts Manually
Plugin URI: http://cagewebdev.com/order-posts-manually
Description: Order your Posts Manually by Dragging and Dropping them
Version: 1.1
Date: 07/29/2014
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
	
	$opm_date_field = get_option('opm_date_field');
	
	$field_name = ($opm_date_field == 0) ? 'post_date' : 'post_modified';

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
$(document).ready(function () {
    $('#sortable').sortable({
        // axis: 'y',
		placeholder: 'placeholder',
        stop: function (event, ui) {
	        var oData = $(this).sortable('serialize');
            $('#sortdata').val(oData);
		}
    });
});
</script>
<?php
$dates = '';
$nr_of_stickies = 0;
$nr_of_posts    = 0;
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
    <strong>Drag and drop the posts to change the display order!</strong><br />
    (After changing the order, don't forget to click the <strong>SAVE CHANGES</strong> button to actually update the posts!)<br />
    <br />
    <strong style="color:#00F;">STICKY POSTS (<?php echo $nr_of_stickies;?>):</strong><br />
    <br />
    <ul id="stickies">
      <?php
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
	}
?>
    </ul>  
    <br />
    <strong style="color:#00F;">REGULAR POSTS (<?php echo $nr_of_posts;?>):</strong><br />
    <br />
  <input name="submit" type="submit" value="SAVE CHANGES" class="button-primary button-large" />
  &nbsp;&nbsp;&nbsp;
  <input name="cancel" value="RELOAD POSTS" type="button" onclick="self.location='';" class="button" /> <br /><br />
    <ul id="sortable">
      <?php
	for($i=0; $i<count($results); $i++)
	{	if(!is_sticky($results[$i]->ID))
		{
			if($field_name == 'post_date')
				$this_date = $results[$i]->post_date;
			else
				$this_date = $results[$i]->post_modified;		
?>
      <li id="post-id-<?php echo $results[$i]->ID?>" class="ui-state-default" title="Post ID: <?php echo $results[$i]->ID?>"><small><?php echo $this_date?></small> * <strong><?php echo $results[$i]->post_title?></strong></li>
      <?php
		} // if(!is_sticky($results[$i]->ID))
	} // for($i=0; $i<count($results); $i++)
?>
    </ul>
    <br />
    <input name="submit" type="submit" value="SAVE CHANGES" class="button-primary button-large" />
    &nbsp;&nbsp;&nbsp;
    <input name="cancel" value="RELOAD POSTS" type="button" onclick="self.location='';" class="button" />
  </div>
</form>
<?php
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
		echo "<div class='updated'><p><strong>Order Posts Manually OPTIONS UPDATED!</strong>";
	}

	$opm_date_field = get_option('opm_date_field');
	if(!$opm_date_field) $opm_date_field = '0';	// sort order: creation date (default)
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
    Download page: <a href="http://wordpress.org/plugins/rvg-order-posts-manually/" target="_blank">http://wordpress.org/plugins/rvg-order-posts-manually/</a> </p>
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
    <input name="submit" type="submit" value="SAVE OPTIONS" class="button-primary button-large" />
  </form>
</div>
<?php
}
?>
