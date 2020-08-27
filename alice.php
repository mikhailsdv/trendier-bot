<?php
	require_once("vendor/autoload.php");
	require_once("config.php");
	require_once("functions.php");

	$input = json_decode(file_get_contents("php://input"), true);
	$command = $input["request"]["command"];

	if (preg_match("/^.*(что|кто) популярне(е|й)\s?:?-? (.+) или (.+)\??$/i", $command, $matches)) {
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

		if (!$result) {
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

		foreach ($result as $key => $value) {
			foreach ($value["value"] as $wordKey => $wordPopularity) {
				$words[$wordKey]["popularity"] += $wordPopularity;
			}
		}
		usort($words, function($a, $b) {
			return $b["popularity"] - $a["popularity"];
		});
		$times = round($words[0]["popularity"] / $words[1]["popularity"]);
		$answer = get_random_answer($words[0]["word"], $words[1]["word"], $times);
		
		/*$database = new Medoo\Medoo([
			"database_type" => "mysql",
			"database_name" => MYSQLI_DB,
			"server" => MYSQLI_HOST,
			"username" => MYSQLI_USERNAME,
			"password" => MYSQLI_PASSWORD,
			"port" => MYSQLI_PORT,
			"collation" => "utf8mb4_general_ci",
			"charset" => "utf8mb4",
		]);
		$database->insert(MYSQLI_TABLE, [
			"word1" => $words[0]["word"],
			"word2" => $words[1]["word"],
			"command" => $command,
			"answer" => $answer
		]);*/

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