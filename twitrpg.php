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
			$replys = $ret["replys"];
			$new_columns = $ret["columns"];

			if ($columns){
				if ($new_columns){
					if ($state = $mysqli->prepare("update user set id=?, location=?, isHaveKey=? where id=?")){
						$state->bind_param("isii", $new_columns["id"], $new_columns["location"], $new_columns["isHaveKey"], $mention->user->id_str);
						$state->execute();
						$state->close();
					}
				}else{
					if ($state = $mysqli->prepare("delete from user where id=?")){
						$state->bind_param("i", $mention->user->id_str);
						$state->execute();
						$state->close();
					}
				}
			}else{
				if ($state = $mysqli->prepare("insert into user (id) values (?)")){
					$state->bind_param("i", $mention->user->id_str);
					$state->execute();
					$state->close();
				}
			}

			$in_reply_to_status_id = $mention->id_str;
			foreach($replys as $reply){
				$status = ["status" => $reply];
				$status["in_reply_to_status_id"] = $in_reply_to_status_id;
				$status["status"] = "@{$mention->user->screen_name}\n{$status['status']}";
				var_export($status = $twitter->post("statuses/update", $status));
				$in_reply_to_status_id = $status->id_str;
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
ー雪山脱出ゲームー

ある日、私は車で会社に向かっていた。毎日同じことの繰り返し、そうぼやきながら、ボーッと車を運転していた。しかし、気がつくと、目の前に純白の景色が広がっていた。どうやら私は、雪山で遭難していた！
EOM;
		array_push($replys, $status);
			$status = <<<EOM
ここは「ゆきやま」。目の前には「どあ」がある。さらに、遠くのほうには「こや」が見える。どうする？

コマンドリスト
・いく　(場所)
・しらべる　(場所または物)
・とる　(物)
・つかう　(物)
EOM;
			array_push($replys, $status);
		}else{
			$status = <<<EOM
？私はどうすればよいのだろう？

コマンドリスト
・いく　(場所)
・しらべる　(場所または物)
・とる　(物)
・つかう　(物)
EOM;
			array_push($replys, $status);

			$cmds = mb_split("[ \t　,]+", $mention->text);
			var_export($cmds);
			if (count($cmds) == 3){
				if ($cmds[1] == "いく"){
					$replys =["私は「${cmds[2]}」を知らない。"];

					if ($cmds[2] == "ゆきやま"){
						$replys =["ここは「${cmds[2]}」だ。"];
						$columns["location"] = "ゆきやま";
					}else if ($cmds[2] == "こや"){
						$replys =["ここは「${cmds[2]}」だ。"];
						$columns["location"] = "こや";
					}
				}else if ($cmds[1] == "しらべる"){
					$replys =["ここには「${cmds[2]}」はない。"];

					if ($cmds[2] == "ゆきやま"){
						if ($columns["location"] == "ゆきやま"){
							$replys =["目の前には「どあ」がある。さらに、遠くのほうには「こや」が見えるような……？"];
						}
					}else if ($cmds[2] == "こや"){
						if ($columns["location"] == "ゆきやま"){
							$replys =["なんでこんなことろに小屋が……？人は住んでいるのだろうか？"];
						}else if ($columns["location"] == "こや"){
							$replys =["なんと！玄関に「かぎ」が落ちている。"];
						}
					}else if ($cmds[2] == "どあ"){
						if ($columns["location"] == "ゆきやま"){
							$replys =["とても頑丈で、こじ開けることはできないなあ。"];
						}
					}else if ($cmds[2] == "かぎ"){
						if ($columns["location"]=="こや" || $columns["isHaveKey"]==TRUE){
							$replys =["おそらく「こや」の鍵だろう。小屋の住人は不用心のようだ。"];
						}
					}
				}else if ($cmds[1] == "とる"){
					$replys =["ここには「${cmds[2]}」はない。"];

					if ($cmds[2] == "どあ"){
						if ($columns["location"]=="ゆきやま"){
							$replys =["こんな頑丈なドアは、どうやっても取り外せない。たとえ雪男の怪力でも……"];
						}
					}else if ($cmds[2] == "かぎ"){
						if ($columns["location"]=="こや" || $columns["isHaveKey"]==FALSE){
							$replys =["「${cmds[2]}」を入手した。"];
							$columns["isHaveKey"] = TRUE;
						}
					}
				}else if ($cmds[1] == "つかう"){
					$replys =["私は「${cmds[2]}」を持っていない。"];

					if ($cmds[2] == "かぎ"){
						if ($columns["isHaveKey"]==TRUE){
							$replys =["ここでは「${cmds[2]}」の使いようがない。"];

							if ($columns["location"] == "ゆきやま"){
								$replys =["ドアが開き、元の世界につながっている……。私は喜んでドアをくぐった。しかし、そこで見たのは……私の車がボロ小屋に突っ込んでいる風景だった。どうやら私は、事故を起こして気絶していたようだ。私は急いで警察に連絡した……　おしまい"];
								$columns = null;
							}
						}
					}
				}
			}
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
