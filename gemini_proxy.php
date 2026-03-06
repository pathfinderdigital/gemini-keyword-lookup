<?php
// gemini_proxy.php
header('Content-Type: application/json');

// Check for the 'payload' parameter instead of 'url'
if (!isset($_GET['payload']) || empty(trim($_GET['payload']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid payload.']);
    exit;
}

// Decode the Base64 string back into the actual URL
$websiteUrl = base64_decode(trim($_GET['payload']));

// (Optional but good practice) Check if it decoded into a valid URL format
if (filter_var($websiteUrl, FILTER_VALIDATE_URL) === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Decoded payload is not a valid URL.']);
    exit;
}

$apiKey = 'REPLACE-WITH-GEMINI-KEY'; // Replace with your actual Gemini API key

// ... [Keep the rest of your PHP script exactly the same from here down] ...
$prompt = " " . $websiteUrl . ". Provide exactly 10 highly relevant keywords for this website. They must be in a JSON array.";

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ]
];

// 4. Execute the cURL request to Gemini
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. Handle the response
if ($response === false) {
    // If your server's cURL completely fails to connect to the outside world
    http_response_code(500);
    echo json_encode(['error' => 'Server cURL error: ' . $curlError]);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    $responseData = json_decode($response, true);
    
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Find the position of the first '[' and the last ']'
        $start = strpos($responseText, '[');
        $end = strrpos($responseText, ']');

       if ($start !== false && $end !== false) {
            // Extract ONLY the text between (and including) those brackets
            $cleanText = substr($responseText, $start, $end - $start + 1);
            
            // Output the perfectly clean JSON array directly to the frontend
            echo $cleanText; 
        } else {
            // If Gemini completely failed to include an array, trigger an error and SHOW the raw text
            http_response_code(500);
            echo json_encode(['error' => "Gemini didn't return an array. Raw output: " . $responseText]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Unexpected response structure from Gemini.']);
    }
} else {
    // Force a 400 error here so the browser NEVER thinks the file is missing again
    http_response_code(400); 
    
    // Decode Google's error response so we can read it
    $geminiData = json_decode($response, true);
    $errorMsg = isset($geminiData['error']['message']) ? $geminiData['error']['message'] : 'No message provided by Google.';
    
    // Send Google's exact complaint to the frontend
    echo json_encode(['error' => "Google API Error (HTTP $httpCode): $errorMsg"]);
}
?>
