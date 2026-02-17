<?php
declare(strict_types=1);

namespace Requesterrr\Support;

final class HttpClient
{
    /**
     * @param array{
     *   headers?: array<string,string>,
     *   query?: array<string,mixed>,
     *   json?: array<mixed>|null,
     *   form?: array<string,mixed>|null,
     *   timeout?: int,
     *   verify_ssl?: bool,
     *   cookie_file?: string|null
     * } $options
     * @return array{ok:bool,status:int,body:string,json:mixed,error:string}
     */
    public function request(string $method, string $url, array $options = []): array
    {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'json' => null,
                'error' => 'cURL extension is not available in this PHP runtime.',
            ];
        }

        $query = $options['query'] ?? [];
        if (!empty($query)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'json' => null,
                'error' => 'Failed to initialize cURL.',
            ];
        }

        $headers = [];
        foreach (($options['headers'] ?? []) as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $payload = null;
        if (isset($options['json'])) {
            $payload = json_encode($options['json'], JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } elseif (isset($options['form'])) {
            $payload = http_build_query($options['form']);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, (int) ($options['timeout'] ?? 20));

        $verifySsl = (bool) ($options['verify_ssl'] ?? true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);

        $cookieFile = $options['cookie_file'] ?? null;
        if (is_string($cookieFile) && $cookieFile !== '') {
            $cookieDir = dirname($cookieFile);
            if (!is_dir($cookieDir)) {
                @mkdir($cookieDir, 0777, true);
            }
            curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
        }

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        $responseBody = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false) {
            return [
                'ok' => false,
                'status' => $statusCode,
                'body' => '',
                'json' => null,
                'error' => $curlError !== '' ? $curlError : 'Unknown cURL error',
            ];
        }

        $decoded = json_decode($responseBody, true);
        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status' => $statusCode,
            'body' => $responseBody,
            'json' => $decoded,
            'error' => '',
        ];
    }
}

