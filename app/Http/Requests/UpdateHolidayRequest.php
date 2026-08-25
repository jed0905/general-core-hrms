<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $holidayId = $this->route('id'); // Get the holiday ID from route parameter

        return [
            'name' => [
                'required',
                'string',
                'max:255',

                'regex:/^[a-zA-Z0-9\s\-\'.]+$/', // Only allow letters, numbers, spaces, hyphens, apostrophes, and periods
            ],
            'date' => [
                'required',
                'date',
                'date_format:Y-m-d',
            ],
            'recurring' => [
                'required',
                'integer',
                'in:0,1', // 0 for No, 1 for Yes
            ],
            'length' => [
                'required',
                'integer',
                'in:1,2', // 1 for Full Day, 2 for Half Day
            ],
            'operating_unit_ids' => [
                'nullable',
                'array',
            ],
            'operating_unit_ids.*' => [
                'integer',
                'exists:operating_units,id',
            ]
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Holiday name is required.',
            'name.regex' => 'Holiday name contains invalid characters.',
            'date.required' => 'Holiday date is required.',
            'date.date_format' => 'Date must be in YYYY-MM-DD format.',
            'recurring.required' => 'Please specify if the holiday repeats annually.',
            'recurring.in' => 'Invalid recurring option selected.',
            'length.required' => 'Please specify the type of day.',
            'length.in' => 'Invalid day type selected.',
            'operating_unit_ids.exists' => 'Invalid operating unit selected.',
            'operating_unit_ids.*.exists' => 'Invalid operating unit selected.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->cleanHolidayName($this->name),
            'recurring' => $this->convertRecurringValue($this->recurring),
            'operating_unit_ids' => $this->operating_unit_ids,
        ]);
    }

    /**
     * Clean holiday name data.
     */
    private function cleanHolidayName(?string $name): ?string
    {
        if (!$name) {
            return $name;
        }

        // Trim whitespace
        $name = trim($name);

        // Remove extra spaces between words
        $name = preg_replace('/\s+/', ' ', $name);

        // Convert to proper case
        $name = ucwords(strtolower($name));

        return $name;
    }

    /**
     * Convert recurring value to integer.
     */
    private function convertRecurringValue($recurring): int
    {
        if ($recurring === 'Yes' || $recurring === '1' || $recurring === 1 || $recurring === true) {
            return 1;
        }

        return 0;
    }
}
