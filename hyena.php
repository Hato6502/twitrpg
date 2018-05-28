<?php
	require "twitteroauth/autoload.php";
	use Abraham\TwitterOAuth\TwitterOAuth;
	require "/var/hyenaconf.php";

	$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_SECRET);
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	while(1){
		$since_id = getSinceId($mysqli);
		$mentions = getMentions($twitter, $since_id);
		foreach($mentions as $mention){
			$reply = replyMain($mention);
			$reply["in_reply_to_status_id"] = $mention->id_str;
			$reply["status"] = "@{$mention->user->screen_name}\n{$reply['status']}";
			print_r($reply);
			$twitter->post("statuses/update", $reply);
			$since_id = max($since_id, $mention->id_str);
		}
		setSinceId($mysqli, $since_id);
		sleep(60);
	}

	$mysqli->close();

	function replyMain($mention){
		$reply = [];
		$reply["status"] = "Command List (not implemented yet! )\n1. たいほしろ\n2. ばしょいどう\n";
		return $reply;
	}

	function getMentions($twitter, $since_id){
		$params = ["count" => "200"];
		if ($since_id) $params["since_id"] = $since_id;
		return $twitter->get("statuses/mentions_timeline", $params);
	}

	function getSinceId($mysqli){
		$since_id = null;
		if ($res = $mysqli->query("select since_id from system")){
			$since_id = $res->fetch_assoc()["since_id"];
			$res->close();
		}
		return $since_id;
	}

	function setSinceId($mysqli, $since_id){
		if ($state = $mysqli->prepare("update system set since_id=?")){
			$state->bind_param("i", $since_id);
			$state->execute();
			$state->close();
		}
	}
?>
