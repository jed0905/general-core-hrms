<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdditionalInformation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'employee_additional_information';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',

        // Consanguinity/Affinity questions
        'consanguinity_third_degree_yes',
        'consanguinity_third_degree_no',
        'consanguinity_fourth_degree_yes',
        'consanguinity_fourth_degree_no',
        'consanguinity_details',

        // Administrative offense
        'administrative_offense_yes',
        'administrative_offense_no',
        'administrative_offense_details',

        // Criminal charge
        'criminal_charge_yes',
        'criminal_charge_no',
        'criminal_charge_date_filed',
        'criminal_charge_status',

        // Conviction
        'conviction_yes',
        'conviction_no',
        'conviction_details',

        // Separation from service
        'separation_from_service_yes',
        'separation_from_service_no',
        'separation_from_service_details',

        // Election candidate
        'election_candidate_yes',
        'election_candidate_no',
        'election_candidate_details',

        // Resigned for election
        'resigned_for_election_yes',
        'resigned_for_election_no',
        'resigned_for_election_details',

        // Immigrant status
        'immigrant_status_yes',
        'immigrant_status_no',
        'immigrant_status_country',

        // Indigenous group
        'indigenous_group_yes',
        'indigenous_group_no',
        'indigenous_group_specify',

        // Person with disability
        'person_with_disability_yes',
        'person_with_disability_no',
        'person_with_disability_id_no',

        // Solo parent
        'solo_parent_yes',
        'solo_parent_no',
        'solo_parent_specify',

        // Government Issued ID
        'gov_id_type',
        'gov_id_number',
        'gov_id_date_issued',
        'gov_id_place_issued',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'consanguinity_third_degree_yes' => 'boolean',
        'consanguinity_third_degree_no' => 'boolean',
        'consanguinity_fourth_degree_yes' => 'boolean',
        'consanguinity_fourth_degree_no' => 'boolean',
        'administrative_offense_yes' => 'boolean',
        'administrative_offense_no' => 'boolean',
        'criminal_charge_yes' => 'boolean',
        'criminal_charge_no' => 'boolean',
        'criminal_charge_date_filed' => 'date',
        'conviction_yes' => 'boolean',
        'conviction_no' => 'boolean',
        'separation_from_service_yes' => 'boolean',
        'separation_from_service_no' => 'boolean',
        'election_candidate_yes' => 'boolean',
        'election_candidate_no' => 'boolean',
        'resigned_for_election_yes' => 'boolean',
        'resigned_for_election_no' => 'boolean',
        'immigrant_status_yes' => 'boolean',
        'immigrant_status_no' => 'boolean',
        'indigenous_group_yes' => 'boolean',
        'indigenous_group_no' => 'boolean',
        'person_with_disability_yes' => 'boolean',
        'person_with_disability_no' => 'boolean',
        'solo_parent_yes' => 'boolean',
        'solo_parent_no' => 'boolean',
        'gov_id_date_issued' => 'date',
    ];

    /**
     * Get the employee that owns the additional information.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
