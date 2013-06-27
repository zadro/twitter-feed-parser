<?php

/**
 * Twitter Feed Parser
 * 
 * @version  1.0
 * @author	Dario Zadro
 * @link	http://zadroweb.com/your-twitter-feed-is-broken/
 * 
 * Notes:
 * Caching is used because Twitter only allow 180 queries for every 15 minutes
 * See: https://dev.twitter.com/docs/rate-limiting/1.1
 * Super simple debug mode will output returned API variable
 * --
 * Twitter time can be displayed (ex. "about 1 hour ago") by setting the 
 * $twitter_time param to true.
 * 
 * Credits:
 * Twitter API: https://github.com/J7mbo/twitter-api-php
 * Hashtag/Username Parsing: http://snipplr.com/view/16221/get-twitter-tweets/
 * Time Ago (modified) Function: http://css-tricks.com/snippets/php/time-ago-function/
 */

require_once('TwitterAPIExchange.php');
 
// Your Twitter App Settings
// https://dev.twitter.com/apps
$access_token			= 'Add_Your_Access_Token';
$access_token_secret	= 'Add_Your_Access_Token_Secret';
$consumer_key			= 'Add_Your_Consumer_Key';
$consumer_secret		= 'Add_Your_Consumer_Secret';

// Some variables
$twitter_username 		= 'zadroweb';
$number_tweets			= 5; // How many tweets to display? max 20
$ignore_replies 		= true; // Should we ignore replies?
$twitter_caching		= true; // You can change to false for some reason
$twitter_cache_time 	= 60*60; // 1 Hour
$twitter_cache_file 	= 'tweets.txt'; // Check your permissions
$twitter_debug			= false; // Set to "true" to see all returned values

// Settings for TwitterAPIExchange.php
$url					= 'https://api.twitter.com/1.1/statuses/user_timeline.json';
$getfield 				= '?screen_name='.$twitter_username;
$requestMethod 			= 'GET';

// Simple function to get Twitter style "time ago"
function ago($tweet_time,$twitter_id){
	
	global $twitter_username;
	
    $m = time()-strtotime($tweet_time); $o='just now';
    $t = array('year'=>31556926,'month'=>2629744,'week'=>604800,'day'=>86400,'hour'=>3600,'minute'=>60,'second'=>1);
    foreach($t as $u=>$s){
        if($s<=$m){$v=floor($m/$s); $o='about '.$v.' '.$u.($v==1?'':'s').' ago'; break;}
    }
    return '<a href="http://twitter.com/'.$twitter_username.'/statuses/'.$twitter_id.'">'.$o.'</a>';
	
}

// Let's run the API then JSON decode and store in variable
$settings = array(
    'oauth_access_token' 		=> $access_token,
    'oauth_access_token_secret' => $access_token_secret,
    'consumer_key' 				=> $consumer_key,
    'consumer_secret'			=> $consumer_secret
);
$twitter = new TwitterAPIExchange($settings);
$twitter_stream = json_decode($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());

// Flag for twitter error
$tweet_flag = 0;

if(!$twitter_debug) {
	
	// Time that the cache was last filled.
	$cache_created_on = ((@file_exists($twitter_cache_file))) ? @filemtime($twitter_cache_file) : 0;
	
	// Output the cache file is valid time
	if ( (time() - $twitter_cache_time < $cache_created_on) && $twitter_caching) {
		
		// Tweets present, all good
		$tweet_flag = 1;
		
		// Get tweets from cache
		@readfile($twitter_cache_file);	
	
	} else {
		
		// Check if at least 1 tweet returned from API
		if(isset($twitter_stream[0]->text)) {
	
			ob_start(); // Start buffer
			
			$tweets = '<ul class="twitter_stream">'; // Start display element
			$tweet_count = 0; // Initialize tweet start count
				
			foreach($twitter_stream as $tweet){
				
				$tweet_text = htmlspecialchars($tweet->text);
				$tweet_start_char = substr($tweet_text, 0, 1);
				
				if ($tweet_start_char != '@' || $ignore_replies == false) {
				
					// Let's create links from hashtags, mentions, other links
					$tweet_text = preg_replace('/(https?:\/\/[^\s"<>]+)/','<a href="$1">$1</a>', $tweet_text);
					$tweet_text = preg_replace('/(^|[\n\s])@([^\s"\t\n\r<:]*)/is', '$1<a href="http://twitter.com/$2">@$2</a>', $tweet_text);
					$tweet_text = preg_replace('/(^|[\n\s])#([^\s"\t\n\r<:]*)/is', '$1<a href="http://twitter.com/search?q=%23$2">#$2</a>', $tweet_text);
					
					// Building tweets display element
					$tweets .= '<li>'.$tweet_text.' <span class="twitter_date">'.ago($tweet->created_at,$tweet->id).'</span></li>'."\n";
					
					// Count them tweets and quit if necessary
					$tweet_count++; if ($tweet_count >= $number_tweets) break;
					
				}
				
			}
			
			echo $tweets.'</ul>'; // End display element
			
			// Write new cache file
			$file = @fopen($twitter_cache_file, 'w');
		
			// Save contents and flush buffer
			@fwrite($file, ob_get_contents()); 
			@fclose($file); 
			ob_end_flush();
			
			// Tweets present, all good
			$tweet_flag = 1;
		
		}
	
	}

} else {

	// Debug mode, just output twitter stream variable
	echo '<pre>';
	print_r($twitter_stream);
	echo '</pre>';
	
}

// If API didn't work for some reason, output some text
if (!$tweet_flag){
	echo $tweets = '<ul class="twitter_stream twitter_error"><li>Oops, something went wrong with our twitter feed - <a href="http://twitter.com/$twitter_username/">Follow us on Twitter!</a></li></ul>';
}
			 		 
