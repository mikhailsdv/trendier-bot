<?php
	require_once("vendor/autoload.php");
	require_once("config.php");
	require_once("functions.php");

	function tgapi($method, $parameters = []) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/" . $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
		$result = json_decode(curl_exec($ch), true);
		curl_close($ch);
		return $result;
	}

	function alice_response($response) {
		$body = [
			"response" => [
				"text" => $response["text"],
				//"tts" =>  "Здравствуйте! Это мы, хоров+одо в+еды.",
				/*"buttons" =>  [
					[
						"title" =>  "Надпись на кнопке",
						"payload" =>  [],
						"url" =>  "https => //example.com/",
						"hide" =>  true
					]
				],*/
				"end_session" => $response["end_session"]
			],
			"version" => "1.0"
		];
		if ($response["tts"] && is_string($response["tts"]) && !empty($response["tts"])) {
			$body["response"]["tts"] = $response["tts"];
		}
		echo json_encode($body);
		exit;
	}
	
	function pluralize($n, $word1, $word2, $word3) {
		$n10 = $n % 10;
		$n100 = $n % 100;
		if ($n10 == 1 && $n100 != 11) {
			return $word1;
		}
		if (
			(2 <= $n10 && $n10 <= 4) &&
			!(12 <= $n100 && $n100 <= 14)
		) {
			return $word2;
		}
		return $word3;
	}

	function get_random_answer($word1, $word2, $times) {
		$answer = arr_rand([
			"%word1 популярнее",
			"%word1 однозначно популярнее",
			"Я проверила: %word1 %times популярнее, чем %word2",
			"По моим источникам %word1 %times популярнее, чем %word2",
			"Смело заявляю, что %word1 %times популярнее, чем %word2",
			"За последний год %word1 популярнее, чем %word2",
			"Насколько я знаю, %word1 %times популярнее, чем %word2",
			"Мне хорошо известно, что %word1 %times популярнее, чем %word2",
			"Я знаю наверняка, что %word1 %times популярнее, чем %word2",
			"Как оказалось, %word1 %times популярнее, чем %word2",
		]);
		$answer = preg_replace("/%word1/", $word1, $answer);
		$answer = preg_replace("/%word2/", $word2, $answer);
		if (is_infinite($times)) {
			$answer = preg_replace("/%times/", "в бесконечное количесво раз", $answer);
		}
		else if ($times > 1) {
			$answer = preg_replace("/%times/", "в $times " . pluralize($times, "раз", "раза", "раз"), $answer);
		}
		else {
			$answer = preg_replace("/%times/", arr_rand(["чуточку", "немного"]), $answer);
		}
		return $answer;
	}

	function get_tg_random_answer($word1, $word2, $times) {
		$answer = arr_rand([
			"%word1 популярнее",
			"%word1 однозначно популярнее",
			"Я проверил: %word1 %times популярнее, чем %word2",
			"По моим источникам %word1 %times популярнее, чем %word2",
			"Смело заявляю, что %word1 %times популярнее, чем %word2",
			"За последний год %word1 популярнее, чем %word2",
			"Насколько я знаю, %word1 %times популярнее, чем %word2",
			"Мне хорошо известно, что %word1 %times популярнее, чем %word2",
			"Я знаю наверняка, что %word1 %times популярнее, чем %word2",
			"Как оказалось, %word1 %times популярнее, чем %word2",
		]);
		$answer = preg_replace("/%word1/", $word1, $answer);
		$answer = preg_replace("/%word2/", $word2, $answer);
		if (is_infinite($times)) {
			$answer = preg_replace("/%times/", "в бесконечное количесво раз", $answer);
		}
		else if ($times > 1) {
			$answer = preg_replace("/%times/", "в $times " . pluralize($times, "раз", "раза", "раз"), $answer);
		}
		else {
			$answer = preg_replace("/%times/", arr_rand(["чуточку", "немного"]), $answer);
		}
		return $answer;
	}

	function arr_rand($arr) {
		return $arr[array_rand($arr)];
	}

	function close_connection() {
		ignore_user_abort(true);
		set_time_limit(0);
		ob_start();
		header("HTTP/1.1 200 OK");
		header("Content-Type: text/html; charset=utf-8");
		echo "ok";
		header("Connection: close");
		header("Content-Length: " . ob_get_length());
		ob_end_flush();
		ob_flush();
		flush();
	}

	function trim_message($str) {
		return preg_replace("/[\t]/", "", trim($str));
	}