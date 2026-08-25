<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\SpecialLeave;
use Illuminate\Support\Facades\URL;

class SpecialLeaveService
{
    public function __construct()
    {
        //
    }

    public function getSpecialLeaves(){
        $specialLeaves = SpecialLeave::with('leaveType')->get()
        ->map(function($specialLeave){
            $specialLeave->edit_link = URL::signedRoute('hrmanagement.leave.specialLeave.edit', ['id' => $specialLeave->id]);
            return $specialLeave;
        });

        return $specialLeaves;
    }

    public function getSpecialLeaveById(int $id){
        $specialLeave = SpecialLeave::findOrFail($id);
        return $specialLeave;
    }

    public function storeSpecialLeave($validated){
        $leaveType = Leave::where('name','Others')->first();
        $data = array_merge($validated, ['leave_type_id' => $leaveType->id]);
        $specialLeave = SpecialLeave::create($data);
        return $specialLeave;
    }

    public function updateSpecialLeave($validated){
        $specialLeave = SpecialLeave::findOrFail($validated['id']);
        $specialLeave->update($validated);
        return $specialLeave;
    }

    public function deleteSpecialLeave(int $id){
        $specialLeave = SpecialLeave::findOrFail($id);
        $specialLeave->delete();
        return $specialLeave;
    }

    public function bulkDestroy($validated){
        $specialLeaves = SpecialLeave::whereIn('id', $validated['ids'])->delete();
        return $specialLeaves;
    }
}
