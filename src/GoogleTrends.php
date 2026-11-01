<?php

declare(strict_types=1);

namespace Trendier;

use RuntimeException;

/** Minimal Google Trends client: average interest of two keywords over the last 12 months, worldwide. */
final class GoogleTrends
{
    private const string EXPLORE_URL = 'https://trends.google.com/trends/api/explore';
    private const string MULTILINE_URL = 'https://trends.google.com/trends/api/widgetdata/multiline';
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    /**
     * @param float $timeout Seconds for the whole comparison
     * @return array{int, int} Average interest of $a and $b
     * @throws RuntimeException When Google fails or the time is out
     */
    public function compare(string $a, string $b, float $timeout): array
    {
        $deadline = microtime(true) + $timeout;
        // Google answers 429 to the first cookie-less request and sets the NID cookie;
        // keeping the cookie between requests saves that round trip.
        $cookies = sys_get_temp_dir() . '/trendier-bot-google-cookies.txt';
        // One handle for both requests reuses the TLS connection.
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_COOKIEFILE => $cookies,
            CURLOPT_COOKIEJAR => $cookies,
            CURLOPT_CONNECTTIMEOUT_MS => 1000,
        ]);

        $explore = $this->get($curl, self::EXPLORE_URL, [
            'req' => json_encode([
                'comparisonItem' => [
                    ['keyword' => $a, 'geo' => '', 'time' => 'today 12-m'],
                    ['keyword' => $b, 'geo' => '', 'time' => 'today 12-m'],
                ],
                'category' => 0,
                'property' => '',
            ], JSON_UNESCAPED_UNICODE),
        ], $deadline);
        $widget = array_find($explore['widgets'] ?? [], fn (array $widget) => ($widget['id'] ?? '') === 'TIMESERIES')
            ?? throw new RuntimeException('Google Trends: no TIMESERIES widget');

        $data = $this->get($curl, self::MULTILINE_URL, [
            // Google rejects an empty geo encoded as [], it must be {}
            'req' => str_replace('"geo":[]', '"geo":{}', json_encode($widget['request'], JSON_UNESCAPED_UNICODE)),
            'token' => $widget['token'],
        ], $deadline)['default'] ?? [];

        if (isset($data['averages'][1])) {
            return [(int) $data['averages'][0], (int) $data['averages'][1]];
        }
        $sums = [0, 0];
        foreach ($data['timelineData'] ?? [] as $point) {
            $sums[0] += $point['value'][0] ?? 0;
            $sums[1] += $point['value'][1] ?? 0;
        }
        return $sums;
    }

    /**
     * @param array<string, string> $query
     * @return array<mixed>
     */
    private function get(\CurlHandle $curl, string $url, array $query, float $deadline, bool $retry = true): array
    {
        $left = $deadline - microtime(true);
        if ($left < 0.1) {
            throw new RuntimeException('Google Trends: timeout');
        }
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '?' . http_build_query(['hl' => 'en-US', 'tz' => 0] + $query),
            CURLOPT_TIMEOUT_MS => (int) ($left * 1000),
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($status === 429 && $retry) {
            return $this->get($curl, $url, $query, $deadline, false);
        }
        if ($status !== 200 || !is_string($body)) {
            throw new RuntimeException("Google Trends: HTTP $status " . curl_error($curl));
        }
        // The JSON is prefixed with the ")]}'" anti-hijacking guard.
        $json = json_decode(substr($body, (int) strpos($body, '{')), true);
        return is_array($json) ? $json : throw new RuntimeException('Google Trends: invalid JSON');
    }
}
