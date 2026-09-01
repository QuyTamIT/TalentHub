<?php
header('Content-Type: application/json; charset=utf-8');

$env = parse_ini_file(__DIR__ . '/.env');

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbUser = $env['DB_USERNAME'] ?? 'root';
$dbPass = $env['DB_PASSWORD'] ?? '';
$dbName = $env['DB_DATABASE'] ?? 'talenthub';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$apiKey = $env['GEMINI_API_KEY'] ?? '';

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$skills = $input['skills'] ?? '';

if (!$userId || !$skills) {
    echo json_encode(['error' => 'Missing user_id or skills']);
    exit;
}

$prompt = "Đóng vai chuyên gia hướng nghiệp, phân tích kỹ năng: {$skills}. Trả về MỘT object JSON duy nhất, định dạng:
{
  \"summary\": {\"strengths\": \"...\", \"weaknesses\": \"...\", \"potential\": \"...\"},
  \"careers\": [{\"role\": \"...\", \"match_percent\": 90, \"reason\": \"...\"}],
  \"skill_gaps\": [{\"group\": \"...\", \"priority\": \"Cao\", \"has\": [\"...\"], \"needs\": [\"...\"]}],
  \"roadmap\": [{\"phase\": \"0-30\", \"goal\": \"...\", \"tasks\": [\"...\"]}, {\"phase\": \"31-60\", \"goal\": \"...\", \"tasks\": [\"...\"]}, {\"phase\": \"61-90\", \"goal\": \"...\", \"tasks\": [\"...\"]}],
  \"badges\": [{\"name\": \"...\", \"match_percent\": 80, \"reason\": \"...\"}]
}";
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent?key=' . $apiKey;

$payload = [
    'contents' => [
        ['parts' => [['text' => $prompt]]]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json'
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

try {
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    
    $decoded = json_decode($response, true);
    $resultText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO ai_suggestions (user_id, prompt, result) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $prompt, $resultText);
    $stmt->execute();
    $stmt->close();
    
    echo $response;
} catch (Exception $e) {
    echo json_encode(['error' => 'API request failed: ' . $e->getMessage()]);
} finally {
    curl_close($ch);
    $conn->close();
}
