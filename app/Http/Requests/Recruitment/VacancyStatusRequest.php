<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Vacancy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Status actions: open / hold / reopen need "publish"; close / fill / cancel need "close".
 */
class VacancyStatusRequest extends FormRequest
{
    public const ACTIONS = [
        'open' => ['to' => Vacancy::STATUS_OPEN, 'ability' => 'publish'],
        'hold' => ['to' => Vacancy::STATUS_ON_HOLD, 'ability' => 'publish'],
        'reopen' => ['to' => Vacancy::STATUS_OPEN, 'ability' => 'publish'],
        'close' => ['to' => Vacancy::STATUS_CLOSED, 'ability' => 'close'],
        'fill' => ['to' => Vacancy::STATUS_FILLED, 'ability' => 'close'],
        'cancel' => ['to' => Vacancy::STATUS_CANCELLED, 'ability' => 'close'],
    ];

    public function action(): string
    {
        return $this->route('action');
    }

    public function targetStatus(): string
    {
        return self::ACTIONS[$this->action()]['to'];
    }

    public function authorize(): bool
    {
        return isset(self::ACTIONS[$this->action()])
            && $this->user()->can(self::ACTIONS[$this->action()]['ability'], $this->route('vacancy'));
    }

    public function rules(): array
    {
        return [
            'reason' => [in_array($this->action(), ['hold', 'cancel'], true) ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
