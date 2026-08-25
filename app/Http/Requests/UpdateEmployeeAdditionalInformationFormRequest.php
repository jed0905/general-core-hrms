<?php

namespace App\Http\Requests;

use App\Traits\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeAdditionalInformationFormRequest extends FormRequest
{
    use SanitizesInput;
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
        
        return [
            // Consanguinity/Affinity questions
            'consanguinityThirdDegree.yes' => 'nullable|boolean',
            'consanguinityThirdDegree.no' => 'nullable|boolean',
            'consanguinityFourthDegree.yes' => 'nullable|boolean',
            'consanguinityFourthDegree.no' => 'nullable|boolean',
            'consanguinityDetails' => 'nullable|string|max:500',

            // Administrative offense
            'administrativeOffense.yes' => 'nullable|boolean',
            'administrativeOffense.no' => 'nullable|boolean',
            'administrativeOffenseDetails' => 'nullable|string|max:500',

            // Criminal charge
            'criminalCharge.yes' => 'nullable|boolean',
            'criminalCharge.no' => 'nullable|boolean',
            'criminalChargeDateFiled' => 'nullable|date|before_or_equal:today',
            'criminalChargeStatus' => 'nullable|string|max:200',

            // Conviction
            'conviction.yes' => 'nullable|boolean',
            'conviction.no' => 'nullable|boolean',
            'convictionDetails' => 'nullable|string|max:500',

            // Separation from service
            'separationFromService.yes' => 'nullable|boolean',
            'separationFromService.no' => 'nullable|boolean',
            'separationFromServiceDetails' => 'nullable|string|max:500',

            // Election candidate
            'electionCandidate.yes' => 'nullable|boolean',
            'electionCandidate.no' => 'nullable|boolean',
            'electionCandidateDetails' => 'nullable|string|max:500',

            // Resigned for election
            'resignedForElection.yes' => 'nullable|boolean',
            'resignedForElection.no' => 'nullable|boolean',
            'resignedForElectionDetails' => 'nullable|string|max:500',

            // Immigrant status
            'immigrantStatus.yes' => 'nullable|boolean',
            'immigrantStatus.no' => 'nullable|boolean',
            'immigrantStatusCountry' => 'nullable|string|max:100|regex:/^[a-zA-Z\s\-\'\.]+$/',

            // Indigenous group
            'indigenousGroup.yes' => 'nullable|boolean',
            'indigenousGroup.no' => 'nullable|boolean',
            'indigenousGroupSpecify' => 'nullable|string|max:200',

            // Person with disability
            'personWithDisability.yes' => 'nullable|boolean',
            'personWithDisability.no' => 'nullable|boolean',
            'personWithDisabilityIdNo' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9\-]+$/',

            // Solo parent
            'soloParent.yes' => 'nullable|boolean',
            'soloParent.no' => 'nullable|boolean',
            'soloParentSpecify' => 'nullable|string|max:200',

            // References
            'references' => 'nullable|array|size:3',
            'references.*.id' => 'nullable|integer',
            'references.*.name' => 'nullable|string|max:100|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'references.*.address' => 'nullable|string|max:255',
            'references.*.telNo' => 'nullable|string|max:20|regex:/^[\+]?[0-9\(\)\-\.\s]+$/',

            // Government Issued ID
            'govIdType' => 'nullable|string|max:100',
            'govIdNumber' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9\-]+$/',
            'govIdDateIssued' => 'nullable|date|before_or_equal:today',
            'govIdPlaceIssued' => 'nullable|string|max:200',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $sanitized = [];

        // Sanitize nested boolean fields and details
        $booleanFields = [
            'consanguinityThirdDegree', 'consanguinityFourthDegree', 'administrativeOffense',
            'criminalCharge', 'conviction', 'separationFromService', 'electionCandidate',
            'resignedForElection', 'immigrantStatus', 'indigenousGroup', 'personWithDisability', 'soloParent'
        ];

        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = [
                    'yes' => filter_var($data[$field]['yes'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'no' => filter_var($data[$field]['no'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        // Sanitize text fields
        $textFields = [
            'consanguinityDetails' => 'text',
            'administrativeOffenseDetails' => 'text',
            'criminalChargeStatus' => 'text',
            'convictionDetails' => 'text',
            'separationFromServiceDetails' => 'text',
            'electionCandidateDetails' => 'text',
            'resignedForElectionDetails' => 'text',
            'immigrantStatusCountry' => 'name',
            'indigenousGroupSpecify' => 'text',
            'personWithDisabilityIdNo' => 'text',
            'soloParentSpecify' => 'text',
        ];

        foreach ($textFields as $field => $type) {
            if (isset($data[$field])) {
                $sanitized[$field] = $this->sanitizeField($data[$field], $type);
            }
        }

        // Sanitize date fields
        if (isset($data['criminalChargeDateFiled'])) {
            $sanitized['criminalChargeDateFiled'] = $this->cleanTextInput($data['criminalChargeDateFiled'], [
                'trim' => true,
                'remove_html' => true,
            ]);
        }

        // Sanitize references array
        if (isset($data['references']) && is_array($data['references'])) {
            $sanitized['references'] = [];
            foreach ($data['references'] as $index => $reference) {
                $sanitized['references'][$index] = [
                    'name' => $this->cleanName($reference['name'] ?? ''),
                    'address' => $this->cleanAddress($reference['address'] ?? ''),
                    'telNo' => $this->cleanPhoneNumber($reference['telNo'] ?? ''),
                ];
            }
        }

        // Sanitize government ID fields
        if (isset($data['govIdType'])) {
            $sanitized['govIdType'] = $this->cleanTextInput($data['govIdType']);
        }
        
        if (isset($data['govIdNumber'])) {
            $sanitized['govIdNumber'] = $this->cleanTextInput($data['govIdNumber'], [
                'trim' => true,
                'remove_extra_spaces' => true,
                'remove_html' => true,
                'convert_case' => 'upper',
            ]);
        }
        
        if (isset($data['govIdDateIssued'])) {
            $sanitized['govIdDateIssued'] = $this->cleanTextInput($data['govIdDateIssued'], [
                'trim' => true,
                'remove_html' => true,
            ]);
        }
        
        if (isset($data['govIdPlaceIssued'])) {
            $sanitized['govIdPlaceIssued'] = $this->cleanTextInput($data['govIdPlaceIssued'], [
                'trim' => true,
                'remove_extra_spaces' => true,
                'remove_html' => true,
                'convert_case' => 'title',
            ]);
        }

        // Merge sanitized data with original data
        $this->replace(array_merge($data, $sanitized));
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            // // Consanguinity messages
            // 'consanguinityThirdDegree.yes.required' => 'Please select Yes or No for third degree consanguinity.',
            // 'consanguinityThirdDegree.no.required' => 'Please select Yes or No for third degree consanguinity.',
            // 'consanguinityFourthDegree.yes.required' => 'Please select Yes or No for fourth degree consanguinity.',
            // 'consanguinityFourthDegree.no.required' => 'Please select Yes or No for fourth degree consanguinity.',
            // 'consanguinityDetails.max' => 'Consanguinity details must not exceed 500 characters.',

            // // Administrative offense messages
            // 'administrativeOffense.yes.required' => 'Please select Yes or No for administrative offense.',
            // 'administrativeOffense.no.required' => 'Please select Yes or No for administrative offense.',
            // 'administrativeOffenseDetails.max' => 'Administrative offense details must not exceed 500 characters.',

            // // Criminal charge messages
            // 'criminalCharge.yes.required' => 'Please select Yes or No for criminal charge.',
            // 'criminalCharge.no.required' => 'Please select Yes or No for criminal charge.',
            // 'criminalChargeDateFiled.date' => 'Please enter a valid date for criminal charge date filed.',
            // 'criminalChargeDateFiled.before_or_equal' => 'Criminal charge date filed cannot be in the future.',
            // 'criminalChargeStatus.max' => 'Criminal charge status must not exceed 200 characters.',

            // // Conviction messages
            // 'conviction.yes.required' => 'Please select Yes or No for conviction.',
            // 'conviction.no.required' => 'Please select Yes or No for conviction.',
            // 'convictionDetails.max' => 'Conviction details must not exceed 500 characters.',

            // // Separation from service messages
            // 'separationFromService.yes.required' => 'Please select Yes or No for separation from service.',
            // 'separationFromService.no.required' => 'Please select Yes or No for separation from service.',
            // 'separationFromServiceDetails.max' => 'Separation details must not exceed 500 characters.',

            // // Election candidate messages
            // 'electionCandidate.yes.required' => 'Please select Yes or No for election candidate.',
            // 'electionCandidate.no.required' => 'Please select Yes or No for election candidate.',
            // 'electionCandidateDetails.max' => 'Election candidate details must not exceed 500 characters.',

            // // Resigned for election messages
            // 'resignedForElection.yes.required' => 'Please select Yes or No for resigned for election.',
            // 'resignedForElection.no.required' => 'Please select Yes or No for resigned for election.',
            // 'resignedForElectionDetails.max' => 'Resignation details must not exceed 500 characters.',

            // // Immigrant status messages
            // 'immigrantStatus.yes.required' => 'Please select Yes or No for immigrant status.',
            // 'immigrantStatus.no.required' => 'Please select Yes or No for immigrant status.',
            // 'immigrantStatusCountry.max' => 'Country name must not exceed 100 characters.',
            // 'immigrantStatusCountry.regex' => 'Country name may only contain letters, spaces, hyphens, apostrophes, and periods.',

            // // Indigenous group messages
            // 'indigenousGroup.yes.required' => 'Please select Yes or No for indigenous group membership.',
            // 'indigenousGroup.no.required' => 'Please select Yes or No for indigenous group membership.',
            // 'indigenousGroupSpecify.max' => 'Indigenous group specification must not exceed 200 characters.',

            // // Person with disability messages
            // 'personWithDisability.yes.required' => 'Please select Yes or No for person with disability.',
            // 'personWithDisability.no.required' => 'Please select Yes or No for person with disability.',
            // 'personWithDisabilityIdNo.max' => 'Disability ID number must not exceed 50 characters.',
            // 'personWithDisabilityIdNo.regex' => 'Disability ID number may only contain letters, numbers, and hyphens.',

            // // Solo parent messages
            // 'soloParent.yes.required' => 'Please select Yes or No for solo parent status.',
            // 'soloParent.no.required' => 'Please select Yes or No for solo parent status.',
            // 'soloParentSpecify.max' => 'Solo parent specification must not exceed 200 characters.',

            // // References messages
            // 'references.required' => 'References are required.',
            // 'references.array' => 'References must be an array.',
            // 'references.size' => 'Exactly 3 references are required.',
            // 'references.*.name.required' => 'Reference name is required.',
            // 'references.*.name.max' => 'Reference name must not exceed 100 characters.',
            // 'references.*.name.regex' => 'Reference name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            // 'references.*.address.required' => 'Reference address is required.',
            // 'references.*.address.max' => 'Reference address must not exceed 255 characters.',
            // 'references.*.telNo.required' => 'Reference telephone number is required.',
            // 'references.*.telNo.max' => 'Reference telephone number must not exceed 20 characters.',
            // 'references.*.telNo.regex' => 'Reference telephone number format is invalid.',

            // // Government ID messages
            // 'govIdType.required' => 'Government ID type is required.',
            // 'govIdType.max' => 'Government ID type must not exceed 100 characters.',
            // 'govIdNumber.required' => 'Government ID number is required.',
            // 'govIdNumber.max' => 'Government ID number must not exceed 50 characters.',
            // 'govIdNumber.regex' => 'Government ID number may only contain letters, numbers, and hyphens.',
            // 'govIdDateIssued.required' => 'Government ID issue date is required.',
            // 'govIdDateIssued.date' => 'Please enter a valid date for government ID issue date.',
            // 'govIdDateIssued.before_or_equal' => 'Government ID issue date cannot be in the future.',
            // 'govIdPlaceIssued.required' => 'Government ID place issued is required.',
            // 'govIdPlaceIssued.max' => 'Government ID place issued must not exceed 200 characters.',
        ];
    }

    /**
     * Sanitize individual field based on type.
     */
    private function sanitizeField(?string $value, string $type): ?string
    {
        if (!$value) {
            return $value;
        }

        switch ($type) {
            case 'name':
                return $this->cleanName($value);
            case 'text':
            default:
                return $this->cleanTextInput($value, [
                    'trim' => true,
                    'remove_extra_spaces' => true,
                    'remove_html' => true,
                    'max_length' => 500,
                ]);
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that exactly one option is selected for each boolean pair
            $booleanFields = [
                'consanguinityThirdDegree' => 'third degree consanguinity',
                'consanguinityFourthDegree' => 'fourth degree consanguinity',
                'administrativeOffense' => 'administrative offense',
                'criminalCharge' => 'criminal charge',
                'conviction' => 'conviction',
                'separationFromService' => 'separation from service',
                'electionCandidate' => 'election candidate',
                'resignedForElection' => 'resigned for election',
                'immigrantStatus' => 'immigrant status',
                'indigenousGroup' => 'indigenous group membership',
                'personWithDisability' => 'person with disability',
                'soloParent' => 'solo parent status',
            ];

            foreach ($booleanFields as $field => $label) {
                $yes = $this->input("{$field}.yes", false);
                $no = $this->input("{$field}.no", false);

                if ($yes === $no) {
                    $validator->errors()->add("{$field}.yes", "Please select exactly one option for {$label}.");
                }
            }

            // Validate conditional required fields
            if ($this->input('consanguinityThirdDegree.yes') || $this->input('consanguinityFourthDegree.yes')) {
                if (empty($this->input('consanguinityDetails'))) {
                    $validator->errors()->add('consanguinityDetails', 'Details are required when answering yes to consanguinity questions.');
                }
            }

            if ($this->input('administrativeOffense.yes')) {
                if (empty($this->input('administrativeOffenseDetails'))) {
                    $validator->errors()->add('administrativeOffenseDetails', 'Details are required when answering yes to administrative offense.');
                }
            }

            if ($this->input('criminalCharge.yes')) {
                if (empty($this->input('criminalChargeDateFiled'))) {
                    $validator->errors()->add('criminalChargeDateFiled', 'Date filed is required when answering yes to criminal charge.');
                }
                if (empty($this->input('criminalChargeStatus'))) {
                    $validator->errors()->add('criminalChargeStatus', 'Status is required when answering yes to criminal charge.');
                }
            }

            if ($this->input('conviction.yes')) {
                if (empty($this->input('convictionDetails'))) {
                    $validator->errors()->add('convictionDetails', 'Details are required when answering yes to conviction.');
                }
            }

            if ($this->input('separationFromService.yes')) {
                if (empty($this->input('separationFromServiceDetails'))) {
                    $validator->errors()->add('separationFromServiceDetails', 'Details are required when answering yes to separation from service.');
                }
            }

            if ($this->input('electionCandidate.yes')) {
                if (empty($this->input('electionCandidateDetails'))) {
                    $validator->errors()->add('electionCandidateDetails', 'Details are required when answering yes to election candidate.');
                }
            }

            if ($this->input('resignedForElection.yes')) {
                if (empty($this->input('resignedForElectionDetails'))) {
                    $validator->errors()->add('resignedForElectionDetails', 'Details are required when answering yes to resigned for election.');
                }
            }

            if ($this->input('immigrantStatus.yes')) {
                if (empty($this->input('immigrantStatusCountry'))) {
                    $validator->errors()->add('immigrantStatusCountry', 'Country is required when answering yes to immigrant status.');
                }
            }

            if ($this->input('indigenousGroup.yes')) {
                if (empty($this->input('indigenousGroupSpecify'))) {
                    $validator->errors()->add('indigenousGroupSpecify', 'Specification is required when answering yes to indigenous group membership.');
                }
            }

            if ($this->input('personWithDisability.yes')) {
                if (empty($this->input('personWithDisabilityIdNo'))) {
                    $validator->errors()->add('personWithDisabilityIdNo', 'ID number is required when answering yes to person with disability.');
                }
            }

            if ($this->input('soloParent.yes')) {
                if (empty($this->input('soloParentSpecify'))) {
                    $validator->errors()->add('soloParentSpecify', 'Specification is required when answering yes to solo parent status.');
                }
            }
        });
    }
}
