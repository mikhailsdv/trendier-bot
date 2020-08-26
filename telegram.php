<?php
	require_once("vendor/autoload.php");
	require_once("config.php");
	require_once("functions.php");

	$input = json_decode(file_get_contents("php://input"), true);
	close_connection();

	if (
		isset($input["message"]["text"]) &&
		mb_strlen($input["message"]["text"]) > 0
	) {
		tgapi("sendChatAction", [
			"action" => "typing",
			"chat_id" => $input["message"]["chat"]["id"]
		]);
		//if (preg_match("/^.*(что|кто) популярне(е|й):? (.+) или (.+)\??$/i", $input["message"]["text"], $matches)) {
		if (preg_match("/^(.+) или (.+)$/", $input["message"]["text"], $matches)) {
			if (mb_strtolower($matches[1]) === mb_strtolower($matches[2])) {
				tgapi("sendMessage", [
					"text" => "Они одинаково популярны 😑",
					"chat_id" => $input["message"]["chat"]["id"]
				]);
				exit;
			}

			$database = new Medoo\Medoo([
				"database_type" => "mysql",
				"database_name" => MYSQLI_DB,
				"server" => MYSQLI_HOST,
				"username" => MYSQLI_USERNAME,
				"password" => MYSQLI_PASSWORD,
				"port" => MYSQLI_PORT,
				"collation" => "utf8mb4_general_ci",
				"charset" => "utf8mb4",
			]);
			$user_prev_messages = $database->select(MYSQLI_TABLE, [
				"id",
				"date",
			], [
				"chat_id" => $input["message"]["chat"]["id"],
				"ORDER" => [
					"id" => "DESC",
				],
				"LIMIT" => 1
			]);

			if (count($user_prev_messages) === 1) {
				$once_per = 15;
				$past = time() - strtotime($user_prev_messages[0]["date"]);
				if ($past < $once_per) {
					tgapi("sendMessage", [
						"text" => "ℹ Чтобы Google не банил IP нашего сервера, мы ограничиваем частоту запросов от пользователей. Вы можете сделать следующий запрос через " . ($once_per - $past) . " секунд.",
						"chat_id" => $input["message"]["chat"]["id"]
					]);
					exit;
				}
			}

			$words_pure = [$matches[1], $matches[2]];
			$words = array_map(function($item) {
				return [
					"word" => $item,
					"popularity" => 0,
				];
			}, $words_pure);
			
			$options = [
				"hl"  => "en-US",//"ru-RU",
				"tz"  => 0,
				"geo" => "",
			];
			$gt = new Google\GTrends($options);
			$result = $gt->interestOverTime($words_pure);

			if (!$result) {
				tgapi("sendMessage", [
					"text" => "🔥 Поступило слишком много запросов. Скоро все заработает.",
					"chat_id" => $input["message"]["chat"]["id"]
				]);
				exit;
			}

			foreach ($result as $key => $value) {
				foreach ($value["value"] as $wordKey => $wordPopularity) {
					$words[$wordKey]["popularity"] += $wordPopularity;
				}
			}
			usort($words, function($a, $b) {
				return $b["popularity"] - $a["popularity"];
			});
			$times = round($words[0]["popularity"] / $words[1]["popularity"]);
			$answer = get_tg_random_answer($words[0]["word"], $words[1]["word"], $times);

			tgapi("sendMessage", [
				"text" => $answer,
				"chat_id" => $input["message"]["chat"]["id"]
			]);

			$database->insert(MYSQLI_TABLE, [
				"word1" => $words[0]["word"],
				"word2" => $words[1]["word"],
				"command" => $input["message"]["text"],
				"answer" => $answer,
				"chat_id" => $input["message"]["chat"]["id"]
			]);
		}
		else if ($input["message"]["text"] === "/start") {
			tgapi("sendMessage", [
				"text" => trim_message("
					👋 Привет. Я помогу узнать, какая из двух вещей более популярна на основе поисковых запросов в Google.

					💬 Спроси меня, например, «арбуз или дыня» или «Илон Маск или Стив Джобс».

					Автор: @mikhailsdv
					Мой канал: @FilteredInternet
				"),
				"chat_id" => $input["message"]["chat"]["id"]
			]);
		}
		else {
			tgapi("sendMessage", [
				"text" => arr_rand([
					"Не совсем понимаю, что вы хотите сравнить. Вот, как нужно спрашивать «арбуз или дыня» или «Илон Маск или Стив Джобс».",
					"Не могу понять, о каких двух вещах идет речь. Вот, как нужно спрашивать «арбуз или дыня» или «Илон Маск или Стив Джобс».",
					"Не могу разобрать команду. Вот, как нужно спрашивать «арбуз или дыня» или «Илон Маск или Стив Джобс».",
				]),
				"chat_id" => $input["message"]["chat"]["id"]
			]);
		}
	}