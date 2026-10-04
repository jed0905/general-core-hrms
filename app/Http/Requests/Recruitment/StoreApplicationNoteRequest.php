<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addNote', $this->route('application'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
