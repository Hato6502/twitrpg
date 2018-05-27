<?php
	require "twitteroauth/autoload.php";
	use Abraham\TwitterOAuth\TwitterOAuth;
	require "/var/hyenaconf.php";

	$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_SECRET);
	$mentions = $twitter->get("statuses/mentions_timeline", ["count" => "200", ]);

	while(1){
		foreach($mentions as $mention){
			$reply = replyMain($mention);
			$reply["in_reply_to_status_id"] = $mention->id_str;
			$reply["status"] = "@{$mention->user->screen_name}\n{$reply['status']}";
			print_r($reply);
			$twitter->post("statuses/update", $reply);
		}

		sleep(60);
	}

	function replyMain($mention){
		$reply = [];
		$reply["status"] = "Command List (not implemented yet! )\n1. たいほしろ\n2. ばしょいどう\n";
		return $reply;
	}
?>
