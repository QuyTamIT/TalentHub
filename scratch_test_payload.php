<?php
$data = ['action' => 'register', 'activityId' => 'a07f36bc-5ba9-4030-82de-c851dff0db47'];
$allowedFields = ['action', 'activityId', 'registrationId', 'reason'];
$input = [];
foreach ($allowedFields as $field) {
    if (array_key_exists($field, $data)) {
        $input[$field] = $data[$field];
    }
}
$action = is_string($input['action'] ?? null) ? trim($input['action']) : '';
unset($input['action']);

$allowed = ['activityId'];
foreach (array_keys($input) as $field) {
    if (!is_string($field) || !in_array($field, $allowed, true)) {
        echo "FAILED: $field not allowed\n";
    }
}
print_r($input);
