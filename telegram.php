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

		if (preg_match("/^(.+?) или (.+?)\??$/", $input["message"]["text"], $matches)) {
			if (mb_strtolower($matches[1]) === mb_strtolower($matches[2])) {
				tgapi("sendMessage", [
					"text" => "Они одинаково популярны 😑",
					"chat_id" => $input["message"]["chat"]["id"]
				]);
				exit;
			}
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
				$once_per = 8;
				$past = time() - strtotime($user_prev_messages[0]["date"]);
				if ($past < $once_per) {
					tgapi("sendMessage", [
						"text" => "ℹ Чтобы Google не банил IP нашего сервера, мы ограничиваем частоту запросов от пользователей. Вы можете сделать следующий запрос через " . ($once_per - $past) . " " . pluralize(($once_per - $past), "секунду", "секунды", "секунд") . ".",
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

			if ($result === false) {
				tgapi("sendMessage", [
					"text" => "🔥 Поступило слишком много запросов. Скоро все заработает.",
					"chat_id" => $input["message"]["chat"]["id"]
				]);
				exit;
			}
			else if (is_array($result) && count($result) === 0) {
				tgapi("sendMessage", [
					"text" => arr_rand([
						"Эти два слова абсолютно непопулярны 🤷‍♂️ ",
						"Нет никаких данных об этих словах 🤷‍♂️",
						"Они оба непопулярны 🤷‍♂️",
					]),
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
			$answer = arr_rand([
				"%word1 однозначно популярнее",
				"Я проверил: %word1 %times популярнее, чем %word2",
				"По моим источникам %word1 %times популярнее, чем %word2",
				"Смело заявляю, что %word1 %times популярнее, чем %word2",
				"За последний год %word1 популярнее, чем %word2",
				"Насколько я знаю, %word1 %times популярнее, чем %word2",
				"Мне хорошо известно, что %word1 %times популярнее, чем %word2",
				"Я знаю наверняка, что %word1 %times популярнее, чем %word2",
				"Как оказалось, %word1 %times популярнее, чем %word2",
				"Могу с уверенностью заявить: %word1 %times популярнее, чем %word2",
			]);
			$answer = preg_replace("/%word1/", $words[0]["word"], $answer);
			$answer = preg_replace("/%word2/", $words[1]["word"], $answer);
			if (is_infinite($times)) {
				$answer = preg_replace("/%times/", "в бесконечное количество раз", $answer);
			}
			else if ($times > 1) {
				$answer = preg_replace("/%times/", "в $times " . pluralize($times, "раз", "раза", "раз"), $answer);
			}
			else {
				$answer = preg_replace("/%times/", arr_rand(["чуточку", "немного"]), $answer);
			}

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
		else if ($input["message"]["text"] === "/alice") {
			$is_already_given = $database->select("trendier_bot_alice", [
				"url",
				"id",
			], [
				"is_given" => 1,
				"user_id" => $input["message"]["chat"]["id"],
				"LIMIT" => 1
			]);
			if (count($is_already_given) === 1) {
				tgapi("sendMessage", [
					"text" => "❌ Вы ранее уже получили ссылку на навык. Если вы еще не воспользовались ею, нажмите на кнопку ниже.",
					"chat_id" => $input["message"]["chat"]["id"],
					"reply_markup" => json_encode([
						"inline_keyboard" => [
							[[
								"text"	=> "Установить навык",
								"url"	=> $is_already_given[0]["url"]
							]]
						]
					])
				]);
			}
			else {
				$skill_link = $database->select("trendier_bot_alice", [
					"url",
					"id",
				], [
					"is_given" => 0,
					"LIMIT" => 1
				]);
				if (count($skill_link) === 1) {
					tgapi("sendMessage", [
						"text" => trim_message("
							✅ Вот ссылка на привытный навык для Алисы. Она деактивируется после того, как вы добавите навык в свой аккаунт.

							ℹ Полсе добавления навыка для его запуска скажите «Алиса, запусти навык Что популярнее».
						"),
						"chat_id" => $input["message"]["chat"]["id"],
						"reply_markup" => json_encode([
							"inline_keyboard" => [
								[[
									"text"	=> "Установить навык",
									"url"	=> $skill_link[0]["url"]
								]]
							]
						])
					]);
					$database->update("trendier_bot_alice", [
						"is_given" => 1,
						"user_id" => $input["message"]["chat"]["id"],
					], [
						"id" => $skill_link[0]["id"],
					]);
				}
				else {
					tgapi("sendMessage", [
						"text" => trim_message("
							❌ К сожалению, ссылок больше нет. Возможно они будут позже.

							Подпишись на мой канал @FilteredInternet, чтобы не пропустить новую партию.
						"),
						"chat_id" => $input["message"]["chat"]["id"],
					]);
				}
			}
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