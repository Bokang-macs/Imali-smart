<?php
// External reference rates with short-lived session caching and safe fallbacks.
declare(strict_types=1);

function external_json(string $url): ?array
{
    $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: ImaliSmart/1.0\r\n"]]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function reference_rates(): array
{
    $cacheKey = 'imali_reference_rates';
    if (isset($_SESSION[$cacheKey], $_SESSION[$cacheKey]['expires']) && $_SESSION[$cacheKey]['expires'] > time()) return $_SESSION[$cacheKey]['data'];
    $frankfurter = external_json('https://api.frankfurter.dev/v2/rate/zar/usd');
    $sarb = external_json('https://custom.resbank.co.za/SarbWebApi/WebIndicators/HomePageRates');
    $indicators = [];
    foreach (is_array($sarb) ? $sarb : [] as $item) {
        if (!is_array($item) || empty($item['Name'])) continue;
        $indicators[(string)$item['Name']] = ['value' => (float)($item['Value'] ?? 0), 'date' => (string)($item['Date'] ?? ''), 'direction' => (int)($item['UpDown'] ?? 0)];
    }
    $data = ['currency' => ['rate' => isset($frankfurter['rate']) ? (float)$frankfurter['rate'] : null, 'date' => (string)($frankfurter['date'] ?? ''), 'available' => is_array($frankfurter)], 'indicators' => $indicators, 'fetched_at' => date(DATE_ATOM)];
    $_SESSION[$cacheKey] = ['expires' => time() + 900, 'data' => $data];
    return $data;
}

function convert_zar_to_usd(float $amount, ?array $rates = null): ?float
{
    $rate = ($rates ?? reference_rates())['currency']['rate'] ?? null;
    return is_numeric($rate) && (float)$rate > 0 ? $amount * (float)$rate : null;
}
