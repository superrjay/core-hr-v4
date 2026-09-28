<?php
declare(strict_types=1);

final class GeminiService
{
    private string $apiKey;
    private string $model;
    private int $timeoutSeconds;

    public function __construct(?string $apiKey = null, ?string $model = null, int $timeoutSeconds = 30)
    {
        $config = require __DIR__ . '/../config/ai.php';
        $this->apiKey = $apiKey ?? (string) ($config['api_key'] ?? getenv('GEMINI_API_KEY') ?: '');
        $this->model = $model ?? (string) ($config['model'] ?? getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function generate(string $prompt, array $schema = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 'MISSING_API_KEY',
                'message' => 'Gemini API key is not configured.',
            ];
        }

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        if ($schema !== []) {
            $payload['generationConfig']['responseSchema'] = $schema;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($this->apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false || $curlError !== '') {
            return [
                'ok' => false,
                'status' => 'TIMEOUT',
                'message' => 'Gemini API request timed out or failed to connect.',
            ];
        }

        $decoded = json_decode((string) $responseBody, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'status' => 'INVALID_RESPONSE',
                'message' => 'Gemini returned an invalid response format.',
            ];
        }

        if (isset($decoded['error'])) {
            $errorCode = $decoded['error']['status'] ?? 'API_ERROR';
            $message = $decoded['error']['message'] ?? 'Gemini request failed.';

            if (str_contains(strtolower((string) $message), 'rate limit') || $errorCode === 'RESOURCE_EXHAUSTED') {
                return ['ok' => false, 'status' => 'RATE_LIMIT', 'message' => 'Gemini request rate limit reached. Please try again later.'];
            }

            if (str_contains(strtolower((string) $message), 'api key') || $errorCode === 'INVALID_ARGUMENT') {
                return ['ok' => false, 'status' => 'INVALID_API_KEY', 'message' => 'Gemini API credentials are invalid.'];
            }

            return ['ok' => false, 'status' => strtoupper((string) $errorCode), 'message' => 'Gemini service is currently unavailable.'];
        }

        if ($httpCode >= 400) {
            return [
                'ok' => false,
                'status' => 'HTTP_ERROR',
                'message' => 'Gemini service responded with an HTTP error.',
            ];
        }

        $candidate = $decoded['candidates'][0] ?? null;
        if (!is_array($candidate)) {
            return [
                'ok' => false,
                'status' => 'MALFORMED_RESPONSE',
                'message' => 'Gemini response did not contain a valid candidate.',
            ];
        }

        $parts = $candidate['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        if ($text === '') {
            return [
                'ok' => false,
                'status' => 'EMPTY_RESPONSE',
                'message' => 'Gemini returned an empty reply.',
            ];
        }

        try {
            $parsed = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'ok' => false,
                'status' => 'INVALID_JSON',
                'message' => 'Gemini returned invalid JSON for processing.',
            ];
        }

        return [
            'ok' => true,
            'data' => $parsed,
            'text' => $text,
            'model' => $this->model,
        ];
    }
}
