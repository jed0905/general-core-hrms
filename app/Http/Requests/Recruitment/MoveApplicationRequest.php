<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Advance or shortlist. expected_stage_id is the stage the user saw; the
 * pipeline service refuses the move if the application has moved since.
 */
class MoveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route()->getName() === 'recruitment.applications.shortlist' ? 'shortlist' : 'advance';

        return $this->user()->can($ability, $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'expected_stage_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
