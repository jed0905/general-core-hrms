<?php

namespace App\Http\Resources;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // dd($this->request);
        return [
            'id' => $this->id,
            'role' => $this->roles->pluck('id')->first(),
            'roles' => $this->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => Str::title(str_replace('_', ' ', $role->name)),
                ];
            }),
            'username' => $this->username,
            'status' => $this->status,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'edit_link' => $this->edit_link,
            'can_delete' => $this->can_delete,
            // Add any other fields you want to expose
        ];
    }
}
