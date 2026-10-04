<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;

/**
 * Employee documents are HR records: access comes from the employee.documents.*
 * permissions only (no self-service yet). Routes are scoped so a document is
 * always checked against the employee it belongs to. Role names are never checked.
 */
class EmployeeDocumentPolicy
{
    public function viewAny(User $user, Employee $employee): bool
    {
        return $user->can('employee.documents.view');
    }

    public function view(User $user, EmployeeDocument $document): bool
    {
        return $user->can('employee.documents.view');
    }

    public function create(User $user, Employee $employee): bool
    {
        return $user->can('employee.documents.create');
    }

    public function update(User $user, EmployeeDocument $document): bool
    {
        return $user->can('employee.documents.update');
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        return $user->can('employee.documents.delete');
    }
}
