<?php
/*
Plugin Name: No Duplicate Content in Comments
Plugin URI: http://bitsignals.com/2009/03/31/no-duplicate-content-in-comments
Description: Check comments for duplicate content using Google AJAX Search API as they are published. Emails you whenever it finds a match so you can check it and decide if you will delete it.
Version: 1.0
Author: Julian Yanover
Author URI: http://bitsignals.com/
*/


/*  Copyright 2009  Julian Yanover  (email : julianyanover@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Call the language function and load the textdomain
function ndcc_textdomain() {
	load_plugin_textdomain('wp-no-duplicate-content-in-comments', 'wp-content/plugins/no-duplicate-content-in-comments');
}
add_action('init', 'ndcc_textdomain');

// Send the email alerting of the copy
function alert_mail_ndcc ($comment_ndcc, $searchfound)  {
    
    $blog_title = get_bloginfo('name');
    $blog_url = get_bloginfo('url');
    $blog_email = get_bloginfo('admin_email');

	$subject = __('NDCC Plugin found duplicate content in a comment', 'wp-no-duplicate-content-in-comments');
	
	$comment_id = $comment_ndcc['comment_ID'];
	$comment_post_id = $comment_ndcc['comment_post_ID'];
	$comment_author = $comment_ndcc['comment_author'];
	$searchfound = urldecode($searchfound);
	$searchfound_readable = str_replace ("+", " ", $searchfound);
	$searchfound = str_replace ("\"", "%22", $searchfound);

	$message = __('NDCC Plugin found duplicate content in a comment. The phrase that triggered this alert is:', 'wp-no-duplicate-content-in-comments');
	$message .= '<br /><br />';
	$message .= '<em>'.$searchfound_readable.'</em><br /><br />';
	$message .= __('This is comment number ', 'wp-no-duplicate-content-in-comments');
	$message .= $comment_id;
	$message .= __(', and it was left by ', 'wp-no-duplicate-content-in-comments');
	$message .= "<strong>".$comment_author.'</strong> <a href="'.get_permalink($comment_post_id).'">';
	$message .= __('on this post', 'wp-no-duplicate-content-in-comments');
	$message .= '</a>.<br /><br />';
    $message .= __('Look at the results provided by Google', 'wp-no-duplicate-content-in-comments');
    $message .= __(' <a href="http://www.google.com/search?q=', 'wp-no-duplicate-content-in-comments');
	$message .= $searchfound.'">';
	$message .= __('here', 'wp-no-duplicate-content-in-comments');
	$message .= '</a>.';
	
	$headers = "From: ".$blog_title." <".$blog_email."> \r\n".
			"Reply-To: ".$blog_email." \r\n".
    		"Content-type: text/html; charset=utf-8\r\n";
			"X-Mailer: PHP";

	// Send the email
	wp_mail($blog_email, $subject, $message, $headers);
}

// Check the strings with Google
function check_google_api_ndcc ($search,$ndcc_api) {

	$blog_url = get_bloginfo('url');

	// Create the api url search, with your API and the search string
	$url = "http://ajax.googleapis.com/ajax/services/search/web?v=1.0&key=".$ndcc_api."&q=".$search;

		// sendRequest
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_REFERER, $blog_url);
		$body = curl_exec($ch);
		curl_close($ch);

		// now, process the JSON string
		$json = json_decode($body);

		// If there are results, then there is at least one match
		if (!empty($json->responseData->results))
			$found = true;
		else
		    $found = false;

	return $found;
	
}

// Main function
function check_duplicate_ndcc ($comment_id, $approval) {
	global $wpdb;
	
	// Get the comment
	$table = $wpdb->prefix . 'comments';
	$comment_ndcc = $wpdb->get_row("SELECT * FROM $table WHERE comment_ID = $comment_id",ARRAY_A);

	// Get the API key entered at the admin panel
    $ndcc_api = get_option('google_ndcc_api_key');

	// If the API has been entered and the comment is not spam proceed
	if ($ndcc_api!="" and $approval==1) {

	    // Clean the comment
		$content = strip_tags($comment_ndcc['comment_content']);
		$content = trim($content);
		// Quotations would bring trouble when doing an exact match search
		$content = str_replace("\"","",$content);
		$content = str_replace("&ldquo;","",$content);
		$content = str_replace("&rdquo;","",$content);
		$content = str_replace("&hellip;","",$content);

    	// Check for duplicate if the comment has over 50 words
	    $quantwordscom = count(explode(" ", $content));
    	if ($quantwordscom>50) {

			// Explode the comment by blank spaces
			$contentexploded = explode(" ",$content);
		
			// Initialize the search string
			$searchstring = "";
		
			// Divide by 8 the array count to know how many start points can there be
			$maxdivisions = number_format($quantwordscom / 8,0);
			$maxdivisions--;
		
			// Get the starting points to check
			// First one
			$checkpos_ndcc[0] = rand(0,$maxdivisions);
			// Second one needs to be different
			$checkpos_ndcc[1] = $checkpos_ndcc[0];
			while ($checkpos_ndcc[1] == $checkpos_ndcc[0])
				$checkpos_ndcc[1] = rand(0,$maxdivisions);
			
			// Generate both search strings
			for ($i = 0; $i <= 1; $i++) {

				// Concatenate to form each search string with 8 elements (words)
				for ($j = $checkpos_ndcc[$i]*8; $j <= (($checkpos_ndcc[$i]*8)+7); $j++) {
					$searchstring_ndcc[$i] .= $contentexploded[$j]." ";
				}
			
	            $searchstring_ndcc[$i] = trim(str_replace("\r","",$searchstring_ndcc[$i]));

                $searchstring_ndcc[$i] = urlencode($searchstring_ndcc[$i]);
				$searchstring_ndcc[$i] = trim($searchstring_ndcc[$i]);
				$searchstring_ndcc[$i] = "%22".$searchstring_ndcc[$i]."%22";

				// The Google API can't have spaces, replace them with +
				$searchstring_ndcc[$i] = str_replace(" ","+",$searchstring_ndcc[$i]);
			}
		
			// Initialize found
			$found = false;
			$k=0;
			// If it is found on the first search, don't do the second one
			while ($found==false and $k<=1) {
				$found = check_google_api_ndcc($searchstring_ndcc[$k],$ndcc_api);
				$searchfound = $searchstring_ndcc[$k];

				$k++;
    	    }

		    if ($found==true)
		    	alert_mail_ndcc ($comment_ndcc, $searchfound);
		
		}

	}

}

// Plugin configuration page
function no_duplicate_comment_conf() {

	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?'));

		check_admin_referer();
		$key = strip_tags($_POST['key']);

		if ( empty($key) ) {
			$key_status = 'empty';
			delete_option('google_ndcc_api_key');
		} else {
			update_option('google_ndcc_api_key', $key);
		}

	}

?>
<?php if ( !empty($_POST ) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php endif; ?>
<div class="wrap">
<h2><?php _e('No Duplicate Content in Comments Configuration', 'wp-no-duplicate-content-in-comments'); ?></h2>
<div class="narrow">
<form action="" method="post" id="ndcc-conf" style="margin: auto; width: 400px; ">

	<p><?php _e('You need to enter your Google AJAX Search API Key below. You can get one from Google <a href="http://code.google.com/apis/ajaxsearch/signup.html">right here</a>.', 'wp-no-duplicate-content-in-comments'); ?></p>

<?php wp_nonce_field(); ?>
<h3><label for="key">Google AJAX Search API</label></h3>
<p><input id="key" name="key" type="text" size="25" value="<?php echo get_option('google_ndcc_api_key'); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" /></p>

	<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
</form>
</div>
</div>

<?php
}

function ndcc_config_page() {
	if ( function_exists('add_submenu_page') )
		add_submenu_page('plugins.php', 'No Duplicate Content in Comments', 'No Duplicate Content in Comments', 'manage_options', 'ndcc-key-config', 'no_duplicate_comment_conf');
}

// Add the menu link
add_action('admin_menu', 'ndcc_config_page');

// Trigger the checking when posting a comment
add_action('comment_post', 'check_duplicate_ndcc', 10, 2);

?>