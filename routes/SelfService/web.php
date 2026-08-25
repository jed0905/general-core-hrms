<?php

use App\Http\Controllers\Web\SelfService\MyDtrController;
use App\Http\Controllers\Web\SelfService\MyLeaveController;
use App\Http\Controllers\Web\SelfService\SelfServiceDashboard;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\SelfService\MyProfileController;

Route::middleware(['auth', 'web',])->group(function () {

    Route::prefix('self-service')->name('self-service.')->group(function () {

        Route::controller(SelfServiceDashboard::class)->name('dashboard.')->group(function () {
            Route::get('/dashboard', 'index')->name('index');
        });

        Route::get('/phpinfo', function () {
            phpinfo();
        });

        // My Profile Routes
        Route::controller(MyProfileController::class)->name('my-profile.')->group(function () {
            Route::get('/my-profile', 'index')->name('index');

            // Picture Upload
            Route::post('/my-profile/upload-picture/', 'uploadPicture')->name('uploadPicture');

            // Signature Upload
            Route::post('/my-profile/upload-signature/', 'uploadSignature')->name('uploadSignature');

            // Personal Information
            Route::put('/my-profile/personal-information/{id}', 'updatePersonalInformation')->name('updatePersonalInformation');
            Route::put('/my-profile/personal-information/psa/{id}', 'updatePersonalInformationFromPsa')->name('updatePersonalInformationFromPsa');
            // Family Background
            Route::put('/my-profile/family_background/{id}', 'updateFamilyBackground')->name('updateFamilyBackground');
            Route::delete('/my-profile/family_background/deleteChild/{id}', 'deleteEmployeeChild')->name('deleteEmployeeChild');

            // Update Other Information
            Route::put('/my-profile/other-information/special-skills/{id}', 'updateSpecialSkills')->name('updateSpecialSkills');
            Route::delete('/my-profile/other-information/special-skills/{id}', 'deleteSpecialSkills')->name('deleteSpecialSkills');
            Route::put('/my-profile/other-information/non-academic-distinctions/{id}', 'updateNonAcademicDistinctions')->name('updateNonAcademicDistinctions');
            Route::delete('/my-profile/other-information/non-academic-distinctions/{id}', 'deleteNonAcademicDistinctions')->name('deleteNonAcademicDistinctions');
            Route::put('/my-profile/other-information/memberships/{id}', 'updateMemberships')->name('updateMemberships');
            Route::delete('/my-profile/other-information/memberships/{id}', 'deleteMemberships')->name('deleteMemberships');

            Route::put('/my-profile/educational_background/{id}', 'updateEmployeeEducationalBackground')->name('updateEmployeeEducationalBackground');
            Route::delete('/my-profile/educational_background/deleteSchool/{id}/{level}', 'deleteEmployeeEducationalBackground')->name('deleteEmployeeEducationalBackground');

            Route::put('/my-profile/civil_service_eligibility/{id}', 'updateCivilServiceEligibility')->name('updateCivilServiceEligibility');
            Route::delete('/my-profile/civil_service_eligibility/delete/{id}', 'deleteCivilServiceEligibility')->name('deleteCivilServiceEligibility');

            Route::put('/my-profile/work_experience/{id}', 'updateEmployeeWorkExperience')->name('updateEmployeeWorkExperience');
            Route::delete('/my-profile/work_experience/deleteWorkExperience/{id}', 'deleteEmployeeWorkExperience')->name('deleteEmployeeWorkExperience');

            Route::put('/my-profile/voluntary_work/{id}', 'updateEmployeeVoluntaryWork')->name('updateEmployeeVoluntaryWork');
            Route::delete('/my-profile/voluntary_work/deleteVoluntaryWork/{id}', 'deleteEmployeeVoluntaryWork')->name('deleteEmployeeVoluntaryWork');

            Route::put('/my-profile/learning_and_development/{id}', 'updateEmployeeLearningAndDevelopment')->name('updateEmployeeLearningAndDevelopment');
            Route::delete('/my-profile/learning_and_development/deleteEmployeeLearningAndDevelopment/{id}', 'deleteEmployeeLearningAndDevelopment')->name('deleteEmployeeLearningAndDevelopment');

            // Update Additional Information
            Route::put('/my-profile/additional-information/{id}', 'updateAdditionalInformation')->name('updateAdditionalInformation');

            // Delete References
            Route::delete('/my-profile/references/{id}', 'deleteReference')->name('deleteReference');
            Route::delete('/my-profile/references/all/{employee_id}', 'deleteAllReferences')->name('deleteAllReferences');
        });

        // My DTR Routes
        Route::controller(MyDtrController::class)->name('my-dtr.')->group(function () {
            Route::get('/my-dtr', 'index')->name('index');

            Route::get('/my-dtr/poll', 'pullData')->name('pullData');

            // Out-of-office events (official travel/business) with supporting documents
            Route::post('/my-dtr/events', 'storeEvent')->name('events.store');
            Route::delete('/my-dtr/events/{id}', 'destroyEvent')->name('events.destroy');
            Route::delete('/my-dtr/documents/{id}', 'destroyDocument')->name('documents.destroy');
        });

        // My Leaves Routes
        Route::controller(MyLeaveController::class)->name('my-leaves.')->group(function () {
            Route::get('/my-leaves', 'index')->name('index');
            Route::match(['get', 'post'], '/my-leaves/apply', 'apply')->name('apply');
            // Route::post('/my-leaves/check-leave-credits', 'checkLeaveCredits')->name('checkLeaveCredits');
            Route::post('/my-leaves/submitLeave', 'store')->name('store');
            Route::get('/my-leaves/edit/{id}', 'edit')->name('edit');
            Route::put('/my-leaves/edit/{id}', 'update')->name('update');
            Route::post('/my-leaves/cancel', 'cancelMyLeave')->name('cancel');
            Route::get('/my-entitlement', 'entitlements')->name('entitlements');
            Route::get('/my-leaves/view/{id}', 'view')
                ->name('view')
                ->middleware('signed');
            ;

            Route::get('/my-leaves/leave-ledger', 'leaveLedger')
                ->name('leaveLedger');

            Route::get('/my-leaves/leave-ledger/print', 'printLeaveLedger')
                ->name('leaveLedger.print')
                ->middleware('signed');

        });
    });
});
