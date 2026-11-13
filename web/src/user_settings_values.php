<?php
/**
 * Decode values from the settings form explicitly, without relying on MySQL
 * interpreting numeric strings as positional ENUM indexes.
 *
 * @return array<string,string>|null Null if the submitted settings are invalid.
 */
function normalizeUserSettingsValues(array $input): ?array
{
    $options = [
        'speed' => ['1' => 'No conversion', '2' => 'km to miles', '3' => 'miles to km'],
        'temp' => ['1' => 'No conversion', '2' => 'Celsius to Fahrenheit', '3' => 'Fahrenheit to Celsius'],
        'pressure' => ['1' => 'No conversion', '2' => 'Psi to Bar', '3' => 'Bar to Psi'],
        'boost' => ['1' => 'No conversion', '2' => 'Psi to Bar', '3' => 'Bar to Psi'],
        'time' => ['1' => '24', '2' => '12'],
        'sessions_filter' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'],
        'api_gps' => ['0' => '0', '1' => '1'],
    ];

    $result = [];
    foreach ($options as $field => $values) {
        if (!isset($input[$field]) || !is_string($input[$field])
            || !array_key_exists($input[$field], $values)) {
            return null;
        }
        $result[$field] = $values[$input[$field]];
    }
    return $result;
}
