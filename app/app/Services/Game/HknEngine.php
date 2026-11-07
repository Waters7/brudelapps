<?php

namespace App\Services\Game;

/** Fixed integer simulation: browser scores are never trusted. Keep engine.js in sync. */
class HknEngine
{
    public const FINISH = 62000;

    public const MAX_TICKS = 18000;

    public function course(int $seed): array
    {
        $random = $seed;
        $next = function () use (&$random): int {
            return $random = ($random * 48271) % 2147483647;
        };
        $items = [];
        for ($at = 1300; $at < self::FINISH - 1200; $at += 650 + $next() % 420) {
            $items[] = ['at' => $at, 'width' => 28 + $next() % 18, 'height' => 32 + $next() % 25, 'hit' => false];
        }

        return $items;
    }

    public function replay(int $seed, int $ticks, array $jumps, array $answers = []): array
    {
        $questions = json_decode(file_get_contents(dirname(__DIR__, 3).'/public/games/hkn62/trivia.json'), true, flags: JSON_THROW_ON_ERROR);
        $quizIndex = $quizTicks = $feedbackTicks = 0;
        $quiz = false;
        $answerInputs = array_column($answers, 'choice', 'tick');
        $obstacles = $this->course($seed);
        $distance = $y = $velocity = $freeze = $invincible = $next = 0;
        $lives = 3;
        $inputs = array_fill_keys($jumps, true);
        for ($tick = 1; $tick <= min($ticks, self::MAX_TICKS); $tick++) {
            if ($tick >= self::MAX_TICKS) {
                break;
            }
            if (array_key_exists($tick, $answerInputs) && (! $quiz || $feedbackTicks > 0)) {
                return ['won' => false, 'ticks' => $tick];
            }
            if (($quiz || $feedbackTicks > 0) && isset($inputs[$tick])) {
                return ['won' => false, 'ticks' => $tick];
            }
            if ($feedbackTicks > 0) {
                $feedbackTicks--;

                continue;
            }
            if ($quiz) {
                $quizTicks++;
                $choice = $answerInputs[$tick] ?? null;
                if ($choice !== null || $quizTicks >= 720) {
                    $correct = $choice === $questions[$quizIndex]['correct'];
                    $feedbackTicks = 180;
                    $quiz = false;
                    $quizIndex++;
                    if (! $correct) {
                        $lives--;
                        $freeze = 90;
                        if ($lives <= 0) {
                            return ['won' => false, 'ticks' => $tick];
                        }
                    }
                }

                continue;
            }
            if (isset($inputs[$tick]) && $y === 0) {
                $velocity = 19;
            }
            if ($velocity !== 0 || $y > 0) {
                $y = max(0, $y + $velocity);
                $velocity--;
                if ($y === 0) {
                    $velocity = 0;
                }
            }
            if ($invincible > 0) {
                $invincible--;
            }
            if ($freeze > 0) {
                $freeze--;
            } else {
                $distance = min(self::FINISH, $distance + 9 + 2 * intdiv($distance, 21000));
            }
            while ($next < count($obstacles) && $obstacles[$next]['at'] + $obstacles[$next]['width'] < $distance - 14) {
                $next++;
            }
            if (isset($obstacles[$next])) {
                $o = &$obstacles[$next];
                if (! $o['hit'] && $invincible === 0 && $o['at'] < $distance + 14 && $distance - 14 < $o['at'] + $o['width'] && $y < $o['height'] - 4) {
                    $o['hit'] = true;
                    $lives--;
                    $freeze = 90;
                    $invincible = 150;
                    if ($lives <= 0) {
                        return ['won' => false, 'ticks' => $tick];
                    }
                }
                unset($o);
            }
            if ($distance >= self::FINISH) {
                return ['won' => $tick === $ticks, 'ticks' => $tick];
            }
            if ($quizIndex < count($questions) && $distance >= $questions[$quizIndex]['km'] * 1000) {
                $quiz = true;
                $quizTicks = 0;
            }
        }

        return ['won' => false, 'ticks' => $ticks];
    }
}
