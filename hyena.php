<?php
12
	require "twitteroauth/autoload.php";
	use Abraham\TwitterOAuth\TwitterOAuth;
	require "/var/hyenaconf.php";

	$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_SECRET);
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	while(1){
		$since_id = getSinceId($mysqli);
		$mentions = getMentions($twitter, $since_id);
		foreach($mentions as $mention){
			$columns = null;
			if ($state = $mysqli->prepare("select * from user where id=?")){
				$state->bind_param("i", $mention->user->id_str);
				$state->execute();
				$res = $state->get_result();
				$columns = $res->fetch_assoc();
				$state->close();
			}

			$ret = replyMain($mention, $columns);

			if ($columns){
			}else{
				if ($state = $mysqli->prepare("insert into user (id) values (?)")){
					$state->bind_param("i", $mention->user->id_str);
					$state->execute();
					$state->close();
				}
			}

			$reply = $ret["reply"];
			$reply["in_reply_to_status_id"] = $mention->id_str;
			$reply["status"] = "@{$mention->user->screen_name}\n{$reply['status']}";
			print_r($reply);
			print_r($columns);
			var_export($twitter->post("statuses/update", $reply));
			$since_id = max($since_id, $mention->id_str);
		}
		setSinceId($mysqli, $since_id);
		sleep(60);
	}

	$mysqli->close();

	function replyMain($mention, $columns){
		$reply = [];

		if (!$columns){	// ゲームスタート
			$reply["status"] = <<<EOM
ーハイエナ連続殺人事件ー

ヨシ：大変です！亀の穴の社長、トローパ・ザ・グレートが暗殺されました！
ボス：なに？あの謎の組織のトップが？
ヨシ：そうです。首筋を噛みちぎり、亀の穴内部の誰かの犯行でしょう。
ボスは考えた、本当に亀の穴内部の仕業なのか？と……
EOM;
		}else{
			$reply["status"] = "Command List (not implemented yet! )\n1. たいほしろ\n2. ばしょいどう\n";
		}
		return ["reply" => $reply, "columns" => $columns];
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
