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

			$replys = $ret["replys"];
			foreach($replys as $reply){
				$status = ["status" => $reply];
				$status["in_reply_to_status_id"] = $mention->id_str;
				$status["status"] = "@{$mention->user->screen_name}\n{$status['status']}";
				var_export($twitter->post("statuses/update", $status));
			}
			$since_id = max($since_id, $mention->id_str);
		}
		setSinceId($mysqli, $since_id);
		sleep(60);
	}

	$mysqli->close();

	function replyMain($mention, $columns){
		$replys = [];

		if (!$columns){	// ゲームスタート
			$status = <<<EOM
ーハイエナ連続殺人事件ー

ヨシ：大変です！「かめのあな」(亀の穴)の社長、「こもろう」(殻二篭郎)が暗殺されました！
ボス：なに？あの謎の組織のトップが？
ヨシ：そうです。首筋を噛みちぎり、亀の穴内部の誰かの犯行でしょう。
……本当に亀の穴内部の仕業なのか？
EOM;
		array_push($replys, $status);
			$status = <<<EOM
ヨシ：ここが事件のあった「かめよしまち」(亀吉町)です。
どこから捜査を始めればよいでしょうか？
ボス、リプライで私に指示をください。

コマンドリスト
・しらべろ　(人名または地名)
・よべ　(人名)
・いけ　(地名)
・たいほしろ
EOM;
			array_push($replys, $status);
		}else{
			$status = <<<EOM
ヨシ：ん？？いまいちおっしゃることが分かりませんが。
もう一度願います、ボス？

コマンドリスト
・しらべろ　(人名または地名)
・よべ　(人名)
・いけ　(地名)
・たいほしろ
EOM;
			array_push($replys, $status);
		}
		return ["replys" => $replys, "columns" => $columns];
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
