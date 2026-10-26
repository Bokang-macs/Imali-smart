<?php
// Helper for calling outside websites safely, with a time limit and a cache.
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Returns the file path used to cache one outside request.
function api_cache_path(string $key): string
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($directory)) @mkdir($directory, 0750, true);
    return $directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
}

// Gets JSON from an outside website with a time limit and cache.
function external_json_get(string $url, int $ttl = 900, string $cacheKey = ''): array
{
    $cacheFile = api_cache_path($cacheKey !== '' ? $cacheKey : $url);
    if (is_readable($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached)) return ['ok' => true, 'data' => $cached, 'cached' => true];
    }
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'HTTP client unavailable.'];
    $handle = curl_init($url);
    if (function_exists('imali_ca_bundle') && ($ca = imali_ca_bundle())) curl_setopt($handle, CURLOPT_CAINFO, $ca);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $raw = curl_exec($handle); $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE); $curlError=curl_error($handle); curl_close($handle);
    if ($raw===false || $curlError!=='' || $status<200 || $status>=300) { error_log('[Imali Smart] External GET failed '.$status.' '.$url.($curlError!==''?' curl: '.$curlError:'')); return ['ok'=>false,'error'=>'The external service is temporarily unavailable.']; }
    try {$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR); if(!is_array($data))throw new RuntimeException('Unexpected response shape'); @file_put_contents($cacheFile,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); return ['ok'=>true,'data'=>$data,'cached'=>false];} catch(Throwable $e){error_log('[Imali Smart] External JSON invalid: '.$e->getMessage());return ['ok'=>false,'error'=>'The external service returned an unreadable response.'];}
}

// Sends JSON to an outside website with a time limit.
function external_json_post(string $url, array $payload, array $headers = []): array
{
    if (!function_exists('curl_init')) return ['ok'=>false,'error'=>'HTTP client unavailable.'];
    $handle=curl_init($url); if (function_exists('imali_ca_bundle') && ($ca = imali_ca_bundle())) curl_setopt($handle, CURLOPT_CAINFO, $ca); $headers=array_merge(['Content-Type: application/json','Accept: application/json'],$headers);
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $raw=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);$curlError=curl_error($handle);curl_close($handle);
    if($raw===false||$curlError!==''||$status<200||$status>=300){error_log('[Imali Smart] External POST failed '.$status.' '.$url.($curlError!==''?' curl: '.$curlError:'').($status>=400?' body: '.substr((string)$raw,0,300):''));return ['ok'=>false,'error'=>'The external service did not accept the request.','status'=>$status];}
    $data=json_decode($raw,true);return ['ok'=>true,'data'=>is_array($data)?$data:[],'status'=>$status];
}
