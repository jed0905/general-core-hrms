<?php

namespace App\Traits;

trait SanitizesInput
{
    /**
     * Clean and sanitize general text input.
     *
     * @param string|null $input
     * @param array $options
     * @return string|null
     */
    protected function cleanTextInput(?string $input, array $options = []): ?string
    {
        if (!$input) {
            return $input;
        }

        $defaults = [
            'trim' => true,
            'remove_extra_spaces' => true,
            'remove_special_chars' => false,
            'allowed_special_chars' => '',
            'max_length' => null,
            'convert_case' => null, // 'upper', 'lower', 'title', 'sentence'
            'remove_html' => true,
            'remove_line_breaks' => false,
        ];

        $options = array_merge($defaults, $options);
        $cleaned = $input;

        // Remove HTML tags and entities
        if ($options['remove_html']) {
            $cleaned = strip_tags($cleaned);
            $cleaned = html_entity_decode($cleaned, ENT_QUOTES, 'UTF-8');
        }

        // Remove line breaks if specified
        if ($options['remove_line_breaks']) {
            $cleaned = str_replace(["\r", "\n", "\t"], ' ', $cleaned);
        }

        // Trim whitespace
        if ($options['trim']) {
            $cleaned = trim($cleaned);
        }

        // Remove extra spaces
        if ($options['remove_extra_spaces']) {
            $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        }

        // Remove special characters (keep only alphanumeric, spaces, and allowed special chars)
        if ($options['remove_special_chars']) {
            $allowedPattern = $options['allowed_special_chars'] 
                ? '\\s' . preg_quote($options['allowed_special_chars'], '/') 
                : '\\s';
            $cleaned = preg_replace('/[^a-zA-Z0-9' . $allowedPattern . ']/u', '', $cleaned);
        }

        // Apply case conversion
        if ($options['convert_case']) {
            switch ($options['convert_case']) {
                case 'upper':
                    $cleaned = mb_strtoupper($cleaned, 'UTF-8');
                    break;
                case 'lower':
                    $cleaned = mb_strtolower($cleaned, 'UTF-8');
                    break;
                case 'title':
                    $cleaned = mb_convert_case($cleaned, MB_CASE_TITLE, 'UTF-8');
                    break;
                case 'sentence':
                    $cleaned = mb_convert_case($cleaned, MB_CASE_TITLE_SIMPLE, 'UTF-8');
                    break;
            }
        }

        // Limit length
        if ($options['max_length'] && mb_strlen($cleaned, 'UTF-8') > $options['max_length']) {
            $cleaned = mb_substr($cleaned, 0, $options['max_length'], 'UTF-8');
        }

        return $cleaned;
    }

    /**
     * Clean username input.
     *
     * @param string|null $username
     * @return string|null
     */
    protected function cleanUsername(?string $username): ?string
    {
        if (!$username) {
            return $username;
        }

        return $this->cleanTextInput($username, [
            'trim' => true,
            'remove_extra_spaces' => true,

            'max_length' => 50,
            'convert_case' => 'lower',
            'remove_html' => true,
            'remove_line_breaks' => true,
        ]);
    }

    /**
     * Clean password input (minimal cleaning to preserve security).
     *
     * @param string|null $password
     * @return string|null
     */
    protected function cleanPassword(?string $password): ?string
    {
        if (!$password) {
            return $password;
        }

        // Only trim leading/trailing whitespace to preserve password integrity
        return trim($password);
    }

    /**
     * Clean email input.
     *
     * @param string|null $email
     * @return string|null
     */
    protected function cleanEmail(?string $email): ?string
    {
        if (!$email) {
            return $email;
        }

        return $this->cleanTextInput($email, [
            'trim' => true,
            'remove_extra_spaces' => true,
            'convert_case' => 'lower',
            'remove_html' => true,
            'remove_line_breaks' => true,
        ]);
    }

    /**
     * Clean name input (first name, last name, etc.).
     *
     * @param string|null $name
     * @return string|null
     */
    protected function cleanName(?string $name): ?string
    {
        if (!$name) {
            return $name;
        }

        return $this->cleanTextInput($name, [
            'trim' => true,
            'remove_extra_spaces' => true,
            'remove_special_chars' => true,
            'allowed_special_chars' => "'-.",
            'max_length' => 50,
            'convert_case' => 'title',
            'remove_html' => true,
            'remove_line_breaks' => true,
        ]);
    }

    /**
     * Clean phone number input.
     *
     * @param string|null $phone
     * @return string|null
     */
    protected function cleanPhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return $phone;
        }

        return $this->cleanTextInput($phone, [
            'trim' => true,
            'remove_extra_spaces' => true,
            'remove_special_chars' => true,
            'allowed_special_chars' => '+()-.',
            'max_length' => 20,
            'remove_html' => true,
            'remove_line_breaks' => true,
        ]);
    }

    /**
     * Clean address input.
     *
     * @param string|null $address
     * @return string|null
     */
    protected function cleanAddress(?string $address): ?string
    {
        if (!$address) {
            return $address;
        }

        return $this->cleanTextInput($address, [
            'trim' => true,
            'remove_extra_spaces' => true,
            'remove_special_chars' => true,
            'allowed_special_chars' => "'-.,#",
            'max_length' => 255,
            'convert_case' => 'title',
            'remove_html' => true,
        ]);
    }

    /**
     * Sanitize array of data based on field types.
     *
     * @param array $data
     * @param array $fieldTypes
     * @return array
     */
    /**
     * Clean numeric input.
     *
     * @param string|null $input
     * @return string|null
     */
    protected function cleanNumeric(?string $input): ?string
    {
        if (!$input) {
            return $input;
        }

        // Remove any non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $input);
        
        // Trim leading zeros
        $cleaned = ltrim($cleaned, '0');
        
        // If the string is empty after cleaning (was all zeros), return '0'
        return $cleaned === '' ? '0' : $cleaned;
    }

    protected function sanitizeData(array $data, array $fieldTypes = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            $fieldType = $fieldTypes[$key] ?? 'text';

            switch ($fieldType) {
                case 'username':
                    $sanitized[$key] = $this->cleanUsername($value);
                    break;
                case 'password':
                    $sanitized[$key] = $this->cleanPassword($value);
                    break;
                case 'email':
                    $sanitized[$key] = $this->cleanEmail($value);
                    break;
                case 'name':
                    $sanitized[$key] = $this->cleanName($value);
                    break;
                case 'phone':
                    $sanitized[$key] = $this->cleanPhoneNumber($value);
                    break;
                case 'address':
                    $sanitized[$key] = $this->cleanAddress($value);
                    break;
                case 'numeric':
                    $sanitized[$key] = $this->cleanNumeric($value);
                    break;
                default:
                    $sanitized[$key] = $this->cleanTextInput($value);
                    break;
            }
        }

        return $sanitized;
    }
}
