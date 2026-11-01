<?php

declare(strict_types=1);

namespace Trendier;

final readonly class Comparison
{
    private function __construct(
        public string $winner,
        public string $loser,
        private int $winnerScore,
        private int $loserScore,
    ) {
    }

    /** @param array{int, int} $scores Google Trends interest of $a and $b */
    public static function of(string $a, string $b, array $scores): self
    {
        return $scores[0] >= $scores[1]
            ? new self($a, $b, $scores[0], $scores[1])
            : new self($b, $a, $scores[1], $scores[0]);
    }

    /** Neither word has any search interest. */
    public function isEmpty(): bool
    {
        return $this->winnerScore === 0;
    }

    public function isTie(): bool
    {
        return $this->winnerScore === $this->loserScore;
    }

    /** @param bool $female Alice speaks of herself in the feminine */
    public function answer(bool $female = false): string
    {
        $a = $female ? 'а' : '';
        $answer = Text::pick([
            '%winner однозначно популярнее',
            "Я проверил$a: %winner %times популярнее, чем %loser",
            'По моим источникам %winner %times популярнее, чем %loser',
            'Смело заявляю, что %winner %times популярнее, чем %loser',
            'За последний год %winner %times популярнее, чем %loser',
            'Насколько я знаю, %winner %times популярнее, чем %loser',
            'Мне хорошо известно, что %winner %times популярнее, чем %loser',
            'Я знаю наверняка, что %winner %times популярнее, чем %loser',
            'Как оказалось, %winner %times популярнее, чем %loser',
            'Могу с уверенностью заявить: %winner %times популярнее, чем %loser',
        ]);
        return mb_ucfirst(strtr($answer, [
            '%winner' => $this->winner,
            '%loser' => $this->loser,
            '%times' => $this->times(),
        ]));
    }

    private function times(): string
    {
        if ($this->loserScore === 0) {
            return 'в бесконечное количество раз';
        }
        $times = (int) round($this->winnerScore / $this->loserScore);
        return $times > 1
            ? "в $times " . Text::plural($times, 'раз', 'раза', 'раз')
            : Text::pick(['чуточку', 'немного']);
    }
}
