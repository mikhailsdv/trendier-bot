<?php

declare(strict_types=1);

namespace Trendier;

use RuntimeException;

/**
 * Answers right in the webhook response (a Bot API method as JSON), which saves a request to Telegram
 * and needs no bot token.
 */
final class TelegramBot
{
    private const int REQUEST_INTERVAL = 6;
    private const float TRENDS_TIMEOUT = 10;
    private const string HOW_TO_ASK = 'Вот, как нужно спрашивать: «арбуз или дыня» или «Илон Маск или Стив Джобс».';

    public function __construct(
        private readonly Database $db = new Database(),
        private readonly GoogleTrends $trends = new GoogleTrends(),
    ) {
    }

    /** Checks the secret_token passed to setWebhook. */
    public static function isAuthorized(): bool
    {
        $secret = Config::get('TELEGRAM_SECRET');
        return $secret === '' || hash_equals($secret, $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    }

    /**
     * @param array<mixed> $update
     * @return array<string, mixed>|null Bot API method to reply with
     */
    public function handle(array $update): ?array
    {
        $text = trim((string) ($update['message']['text'] ?? ''));
        if ($text === '') {
            return null;
        }
        $chatId = (int) $update['message']['chat']['id'];
        // Commands in groups come as /start@TrendierBot
        $command = strtolower(explode('@', strtok($text, ' '))[0]);

        return match (true) {
            $command === '/start' => $this->send($chatId, Text::dedent('
                👋 Привет. Я помогу узнать, какая из двух вещей более популярна на основе поисковых запросов в Google.

                💬 Спроси меня, например, «арбуз или дыня» или «Илон Маск или Стив Джобс».

                Автор: @mikhailsdv
                Мой канал: @FilteredInternet
            ')),
            $command === '/alice' => $this->aliceLink($chatId),
            (bool) preg_match('/^(.+?)\s+или\s+(.+?)[\s?!.]*$/iu', $text, $words) => $this->compare($chatId, $text, $words[1], $words[2]),
            default => $this->send($chatId, Text::pick([
                'Не совсем понимаю, что вы хотите сравнить. ',
                'Не могу понять, о каких двух вещах идет речь. ',
                'Не могу разобрать команду. ',
            ]) . self::HOW_TO_ASK),
        };
    }

    /** @return array<string, mixed> */
    private function compare(int $chatId, string $text, string $a, string $b): array
    {
        if (mb_strtolower($a) === mb_strtolower($b)) {
            return $this->send($chatId, 'Они одинаково популярны 😑');
        }

        $past = $this->db->secondsSinceLastRequest('chat_id', $chatId);
        if ($past !== null && $past < self::REQUEST_INTERVAL) {
            $wait = self::REQUEST_INTERVAL - $past;
            return $this->send($chatId, "⚠️ Чтобы Google не банил IP нашего сервера, мы ограничиваем частоту запросов от пользователей. Вы можете сделать следующий запрос через $wait " . Text::plural($wait, 'секунду', 'секунды', 'секунд') . '.');
        }

        try {
            $comparison = Comparison::of($a, $b, $this->trends->compare($a, $b, self::TRENDS_TIMEOUT));
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            return $this->send($chatId, '🔥 Поступило слишком много запросов. Скоро все заработает.');
        }

        $answer = match (true) {
            $comparison->isEmpty() => Text::pick([
                'Эти два слова абсолютно непопулярны 🤷‍♂️',
                'Нет никаких данных об этих словах 🤷‍♂️',
                'Они оба непопулярны 🤷‍♂️',
            ]),
            $comparison->isTie() => 'Они одинаково популярны 😑',
            default => $comparison->answer(),
        };
        $this->db->logRequest([
            'word1' => $comparison->winner,
            'word2' => $comparison->loser,
            'command' => $text,
            'answer' => $answer,
            'chat_id' => $chatId,
        ]);
        return $this->send($chatId, $answer);
    }

    /** @return array<string, mixed> */
    private function aliceLink(int $chatId): array
    {
        if ($url = $this->db->aliceLinkOf($chatId)) {
            return $this->send($chatId, '❌ Вы ранее уже получили ссылку на навык. Если вы еще не воспользовались ею, нажмите на кнопку ниже.', $url);
        }
        if ($url = $this->db->giveAliceLink($chatId)) {
            return $this->send($chatId, Text::dedent('
                ✅ Вот ссылка на приватный навык для Алисы. Она деактивируется после того, как вы добавите навык в свой аккаунт.

                ℹ После добавления навыка для его запуска скажите «Алиса, запусти навык Что популярнее».
            '), $url);
        }
        return $this->send($chatId, Text::dedent('
            ❌ К сожалению, ссылок больше нет. Возможно они будут позже.

            Подпишись на мой канал @FilteredInternet, чтобы не пропустить новую партию.
        '));
    }

    /** @return array<string, mixed> */
    private function send(int $chatId, string $text, ?string $skillUrl = null): array
    {
        $message = ['method' => 'sendMessage', 'chat_id' => $chatId, 'text' => $text];
        if ($skillUrl !== null) {
            $message['reply_markup'] = ['inline_keyboard' => [[['text' => 'Установить навык', 'url' => $skillUrl]]]];
        }
        return $message;
    }
}
