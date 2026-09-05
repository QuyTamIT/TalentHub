<?php
declare(strict_types=1);
namespace TalentHub\Support;

class GeminiService
{
    private string $apiKey;
    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '';
    }

    public function generateInternshipSuggestions(string $skillsJson): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY is not set in environment.');
        }

        $promptText = "Given the following student skills in JSON format: " . $skillsJson . ". Please suggest suitable internship positions. Return ONLY a valid JSON array of objects.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($this->endpoint . '?key=' . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        try {
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode >= 400) {
                throw new \Exception('API returned HTTP ' . $httpCode . ': ' . (string) $response);
            }
            
            $decoded = json_decode((string) $response, true);
            $responseText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            
            $suggestions = json_decode($responseText, true);
            if (!is_array($suggestions)) {
                throw new \Exception('Gemini output is not a valid JSON array.');
            }
            return $suggestions;
        } catch (\Throwable $e) {
            throw new \Exception('Gemini API call failed: ' . $e->getMessage());
        } finally {
            curl_close($ch);
        }
    }
}
