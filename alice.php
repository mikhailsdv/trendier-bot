<?php
	require_once("vendor/autoload.php");
	require_once("config.php");
	require_once("functions.php");

	$input = json_decode(file_get_contents("php://input"), true);
	$command = $input["request"]["command"];

	$numberToWords = new NumberToWords\NumberToWords();
	$numberTransformer = $numberToWords->getNumberTransformer("ru");

	if (preg_match("/^.*(что|кто) популярне(е|й)\s?:?-? (.+) или (.+)\??$/i", $command, $matches)) {
		if (mb_strtolower($matches[1]) === mb_strtolower($matches[2])) {
			alice_response([
				"text" => "Они одинаково популярны",
				"end_session" => true,
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
			"session_id" => $input["session"]["session_id"],
			"ORDER" => [
				"id" => "DESC",
			],
			"LIMIT" => 1
		]);

		if (count($user_prev_messages) === 1) {
			$once_per = 8;
			$past = time() - strtotime($user_prev_messages[0]["date"]);
			if ($past < $once_per) {
				alice_response([
					"text" => "Чтобы Google не банил IP нашего сервера, мы ограничиваем частоту запросов от пользователей. Вы можете сделать следующий запрос через " . $numberTransformer->toWords($once_per - $past) . " " . pluralize(($once_per - $past), "секунду", "секунды", "секунд") . ".",
					"end_session" => false,
				]);
				exit;
			}
		}

		$words_pure = [$matches[3], $matches[4]];
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
			alice_response([
				"text" => arr_rand([
					"К сожалению, сейчас я не могу дать ответ на ваш вопрос. Мои серверы перегружены. Попробуйте завтра.",
					"Не могу могу обработать запрос. Кажется, у нас какие-то неполадки. Попроуйте позже.",
					"Извините, но из-за технических неполадок я временно не могу дать ответ на ваш вопрос. Попробуйте завтра.",
				]),
				"end_session" => true,
			]);
			exit;
		}
		else if (is_array($result) && count($result) === 0) {
			alice_response([
				"text" => arr_rand([
					"Эти два слова абсолютно непопулярны",
					"Нет никаких данных об этих словах",
					"Они оба непопулярны",
					"Я не нашла никаких данных по этим словам",
				]),
				"end_session" => true,
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
			"Я проверила: %word1 %times популярнее, чем %word2",
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
			$tts = $answer = preg_replace("/%times/", "в бесконечное количество раз", $answer);
		}
		else if ($times > 1) {
			$num_to_words = $numberTransformer->toWords($times);
			$answer = preg_replace("/%times/", "в $num_to_words " . pluralize($times, "раз", "раза", "раз"), $answer);
		}
		else {
			$tts = $answer = preg_replace("/%times/", arr_rand(["чуточку", "немного"]), $answer);
		}

		$database->insert(MYSQLI_TABLE, [
			"word1" => $words[0]["word"],
			"word2" => $words[1]["word"],
			"command" => $command,
			"answer" => $answer,
			"session_id" => $input["session"]["session_id"],
		]);

		alice_response([
			"text" => $answer,
			"end_session" => false,
		]);
	}
	else if ($command === "" || preg_match("/^(привет|помощь)$/i", $command) || preg_match("/(что|как) ты (умеешь|можешь|делаешь|работаешь)/i", $command)) {
		alice_response([
			"text" => "Привет. Я помогу узнать, какая из двух вещей более популярна. Спроси меня, например, что популярнее: арбуз или дыня? Или кто популярнее: Илон Маск или Стив Джобс?",
			"end_session" => false,
		]);
	}
	else {
		alice_response([
			"text" => arr_rand([
				"Не совсем поняла, что вы хотите сравнить. Давайте еще разок.",
				"Не могу понять, о каких двух вещах идет речь. Давайте еще разок.",
				"Не могу разобрать команду. Попроуйте еще раз.",
			]),
			"end_session" => false,
		]);
	}