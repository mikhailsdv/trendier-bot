<?php

declare(strict_types=1);

namespace Trendier;

use RuntimeException;

/** Alice waits 3 seconds for an answer, so Google Trends gets what is left after the network and the database. */
final class AliceSkill
{
    private const int REQUEST_INTERVAL = 8;
    private const float TRENDS_TIMEOUT = 2.2;
    private const string GREETING = 'Привет. Я помогу узнать, какая из двух вещей более популярна. Спроси меня, например, что популярнее: арбуз или дыня? Или кто популярнее: Илон Маск или Стив Джобс?';

    public function __construct(
        private readonly Database $db = new Database(),
        private readonly GoogleTrends $trends = new GoogleTrends(),
    ) {
    }

    /**
     * @param array<mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        // Yandex sends the command lowercased and without punctuation
        $command = trim((string) ($request['request']['command'] ?? ''));

        return match (true) {
            $command === ''
                || (bool) preg_match('/^(привет|помощь|помоги)$|(что|как) ты (умеешь|можешь|делаешь|работаешь)/u', $command)
                => self::response(self::GREETING),
            (bool) preg_match('/^(хватит|стоп|выход|выйти|закончить)$/u', $command) => self::response('До встречи!', true),
            (bool) preg_match('/^(?:.*?(?:что|кто) популярне[ей]\s*)?(.+?)\s+или\s+(.+)$/u', $command, $words)
                => $this->compare($command, (string) ($request['session']['session_id'] ?? ''), $words[1], $words[2]),
            default => self::response(Text::pick([
                'Не совсем поняла, что вы хотите сравнить. Давайте еще разок.',
                'Не могу понять, о каких двух вещах идет речь. Давайте еще разок.',
                'Не могу разобрать команду. Попробуйте еще раз.',
            ])),
        };
    }

    /** @return array<string, mixed> */
    public static function response(string $text, bool $endSession = false): array
    {
        return ['response' => ['text' => $text, 'end_session' => $endSession], 'version' => '1.0'];
    }

    /** @return array<string, mixed> */
    private function compare(string $command, string $sessionId, string $a, string $b): array
    {
        if ($a === $b) {
            return self::response('Они одинаково популярны');
        }

        $past = $this->db->secondsSinceLastRequest('session_id', $sessionId);
        if ($past !== null && $past < self::REQUEST_INTERVAL) {
            $wait = self::REQUEST_INTERVAL - $past;
            return self::response("Чтобы Google не банил IP нашего сервера, мы ограничиваем частоту запросов от пользователей. Вы можете сделать следующий запрос через $wait " . Text::plural($wait, 'секунду', 'секунды', 'секунд') . '.');
        }

        try {
            $comparison = Comparison::of($a, $b, $this->trends->compare($a, $b, self::TRENDS_TIMEOUT));
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            return self::response(Text::pick([
                'К сожалению, сейчас я не могу дать ответ на ваш вопрос. Мои серверы перегружены. Попробуйте позже.',
                'Не могу обработать запрос. Кажется, у нас какие-то неполадки. Попробуйте позже.',
                'Извините, но из-за технических неполадок я временно не могу дать ответ на ваш вопрос. Попробуйте позже.',
            ]));
        }

        $answer = match (true) {
            $comparison->isEmpty() => Text::pick([
                'Эти два слова абсолютно непопулярны',
                'Нет никаких данных об этих словах',
                'Они оба непопулярны',
                'Я не нашла никаких данных по этим словам',
            ]),
            $comparison->isTie() => 'Они одинаково популярны',
            default => $comparison->answer(female: true),
        };
        $this->db->logRequest([
            'word1' => $comparison->winner,
            'word2' => $comparison->loser,
            'command' => $command,
            'answer' => $answer,
            'session_id' => $sessionId,
        ]);
        return self::response($answer);
    }
}
