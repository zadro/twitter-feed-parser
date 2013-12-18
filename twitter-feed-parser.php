<?php

/**
 * Twitter Feed Parser
 * 
 * @version  	1.1.2
 * @author	Dario Zadro
 * @link	http://zadroweb.com/your-twitter-feed-is-broken/
 * 
 * Notes:
 * Caching is used - Twitter only allows 180 queries for every 15 minutes
 * See: https://dev.twitter.com/docs/rate-limiting/1.1
 * Super simple debug mode will output returned API variable
 * --
 * Twitter time is displayed (ex. "about 1 hour ago")
 * 
 * Credits:
 * Twitter API: https://github.com/J7mbo/twitter-api-php
 * Hashtag/Username Parsing: http://snipplr.com/view/16221/get-twitter-tweets/
 * Time Ago (modified) Function: http://css-tricks.com/snippets/php/time-ago-function/
 */
 
// Your Twitter App Settings
// https://dev.twitter.com/apps
$access_token			= 'Add_Your_Access_Token';
$access_token_secret		= 'Add_Your_Access_Token_Secret';
$consumer_key			= 'Add_Your_Consumer_Key';
$consumer_secret		= 'Add_Your_Consumer_Secret';

// Some variables
$twitter_username 		= 'zadroweb';
$number_tweets			= 5; // How many tweets to display? max 20
$ignore_replies 		= true; // Should we ignore replies?
$twitter_caching		= true; // You can change to false for some reason
$twitter_cache_time 		= 60*60; // 1 Hour
$twitter_cache_file 		= 'tweets.txt'; // Check your permissions
$twitter_debug			= false; // Set to "true" to see all returned values

require_once('TwitterAPIExchange.php');
		
// Settings for TwitterAPIExchange.php
$url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
$getfield = '?screen_name='.$twitter_username;
$requestMethod = 'GET';
$settings = array(
	'oauth_access_token' => $access_token,
	'oauth_access_token_secret' => $access_token_secret,
	'consumer_key' => $consumer_key,
	'consumer_secret' => $consumer_secret
);

// Flag for twitter error
$tweet_flag = 0;

if (!$twitter_debug) {
	
	// Time the cache was last created
	$cache_created_on = ((@file_exists($twitter_cache_file))) ? @filemtime($twitter_cache_file) : 0;
	
	// Output the cache file if valid time
	if ( (time() - $twitter_cache_time < $cache_created_on) && $twitter_caching) {
		
		// Tweets should be in cache file, all good
		$tweet_flag = 1;
		
		// Get tweets from cache
		@readfile($twitter_cache_file);	
	
	} else {
		
		// Let's run the API then JSON decode and store in variable
		$twitter = new TwitterAPIExchange($settings);
		$twitter_stream = json_decode($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());
		
		// Check if at least 1 tweet returned from API
		if (is_array($twitter_stream) && isset($twitter_stream[0]->text)) {
	
			ob_start(); // Start buffer
			
			$tweets = '<ul class="twitter_stream">'; // Start display element
			$tweet_count = 0; // Initialize tweet start count
				
			foreach ($twitter_stream as $tweet){
				
				$tweet_text = htmlspecialchars($tweet->text);
				$tweet_start_char = substr($tweet_text, 0, 1);
				
				if ($tweet_start_char != '@' || $ignore_replies == false) {
				
					// Let's create links from hashtags, mentions, urls
					$tweet_text = preg_replace('/(https?:\/\/[^\s"<>]+)/','<a href="$1">$1</a>', $tweet_text);
					$tweet_text = preg_replace('/(^|[\n\s])@([^\s"\t\n\r<:]*)/is', '$1<a href="http://twitter.com/$2">@$2</a>', $tweet_text);
					$tweet_text = preg_replace('/(^|[\n\s])#([^\s"\t\n\r<:]*)/is', '$1<a href="http://twitter.com/search?q=%23$2">#$2</a>', $tweet_text);
					
					// Building tweets display element
					$tweets .= '<li>'.$tweet_text.' <span class="twitter_date">'.ago($tweet->created_at,$tweet->id,$tweet->user->screen_name).'</span></li>'."\n";
					
					// Count tweets and quit if necessary
					$tweet_count++; if ($tweet_count >= $number_tweets) break;
					
				}
				
			}
			
			echo $tweets.'</ul>'; // End display element
			
			// Write new cache file in the same directory
			$file = @fopen(dirname(__FILE__).'/'.$twitter_cache_file, 'w');
		
			// Save contents and flush buffer
			@fwrite($file, ob_get_contents()); 
			@fclose($file); 
			ob_end_flush();
			
			// Tweets present, all good
			$tweet_flag = 1;
		
		}
	
	}

} else {
	
	// Let's run the API then JSON decode and store in variable
	$twitter = new TwitterAPIExchange($settings);
	$twitter_stream = json_decode($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());

	// Debug mode, just output twitter stream variable
	echo '<pre>';
	print_r($twitter_stream);
	echo '</pre>';
	
}

// If API didn't work for some reason, output some text
if (!$tweet_flag) {
	echo $tweets = '<ul class="twitter_stream twitter_error"><li>Oops, something went wrong with our twitter feed - <a href="http://twitter.com/'.$twitter_username.'/">Follow us on Twitter!</a></li></ul>';
}

// Simple function to get Twitter style "time ago"
function ago($tweet_time,$tweet_id,$tweet_name) {
		
    	$m = time()-strtotime($tweet_time); $o='just now';
    	$t = array('year'=>31556926,'month'=>2629744,'week'=>604800,'day'=>86400,'hour'=>3600,'minute'=>60,'second'=>1);
    	foreach($t as $u=>$s){
        	if($s<=$m){$v=floor($m/$s); $o='about '.$v.' '.$u.($v==1?'':'s').' ago'; break;}
    	}
	return '<a href="http://twitter.com/'.$tweet_name.'/statuses/'.$tweet_id.'">('.$o.')</a>';
	
}
