<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\PsaAccessToken;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class PsaVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, EmployeeService $employeeService)
    {
        // dd($request['id_number']);
        // dd('PSA Verification API Endpoint Reached');
        // Test DATA
        // $request = [
        //     'firstName' => 'JUAN',
        //     'middleName' => 'SANTOS',
        //     'lastName' => 'DELA CRUZ',
        //     'birthdate' => '1989-09-12',
        //     'suffix' => 'JR',
        //     'face_liveness_session_id' => '1234567890',
        //     'is_update' => true,
        //     'employee_id' => 5,
        // ];
        // $request = [
        //     'idNumber' => 'AAA000',
        //     'verificationType' => 'pcn',
        //     'face_liveness_session_id' => '1234567890',
        // ];

        // dd($request->all());

        $response = null;
        $authenticationResponse = null;
        $verificationType = strtoupper($request['verificationType']);
        $face_liveness_session_id = $request['face_liveness_session_id'] ?? null;
        // dd($verificationType);

        if (config('app.env') === 'uat') {
            $baseUrl = config('app.psa.uat.base_url');
            $clientId = config('app.psa.uat.client_id');
            $clientSecret = config('app.psa.uat.client_secret');
            $face_liveness_session_id = '1234567890';
        } else {
            $baseUrl = config('app.psa.production.base_url');
            $clientId = config('app.psa.production.client_id');
            $clientSecret = config('app.psa.production.client_secret');
        }

        // Get latest access token
        $latestAccessToken = PsaAccessToken::latest()->first();

        $access_token = null;

        // dd($verificationType);

        // Check if we need a new one
        if (
            !$latestAccessToken || // none found
            !$latestAccessToken->expires_at || // expires_at missing
            $latestAccessToken->expires_at < time() // expired
        ) {
            try {
                // Request new access token from PSA API and retry up to 3 times on server errors or rate limiting
                $authenticationResponse = retry(3, function () use ($baseUrl, $clientId, $clientSecret) {
                    $response = Http::asForm()
                        ->timeout(10)
                        ->post($baseUrl . '/auth', [
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret,
                        ]);

                    if ($response->serverError() || $response->status() === 429) {
                        throw new \Exception('Retryable server error');
                    }

                    return $response;
                }, 200);

                if ($authenticationResponse->failed()) {
                    Log::error('PSA API Authentication Failed', [
                        'response' => $authenticationResponse->body(),
                    ]);
                    throw new \Exception('Failed to fetch access token from PSA API');
                }
            } catch (\Exception $e) {
                Log::error('PSA API Authentication Exception', [
                    'error' => $e->getMessage(),
                ]);
                return back()->withErrors([
                    'psa' => 'Unable to authenticate with PSA server. Please try again later.'
                ]);
            }

            $data = $authenticationResponse->json('data');

            // Save the new token
            $latestAccessToken = PsaAccessToken::create([
                'access_token' => Crypt::encryptString($data['access_token']),
                'token_type' => $data['token_type'],
                'expires_at' => $data['expires_at'], // still Unix timestamp
            ]);
        }

        // Use the latest (new or existing) token
        $access_token = Crypt::decryptString($latestAccessToken->access_token);

        // If there is a valid access token
        // Start PSA Verification Process by calling the appropriate endpoint
        if ($access_token) {
            if ($verificationType == 'PSA') // Verify via Personal Info
            {

                try {
                    $response = Http::retry(3, 300)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->withToken($access_token)
                        ->post($baseUrl . '/query', [
                            'first_name' => strtoupper($request['firstName']),
                            'middle_name' => isset($request['middleName']) ? strtoupper($request['middleName']) : '',
                            'last_name' => strtoupper($request['lastName']),
                            'birth_date' => $request['birthdate'],
                            'suffix' => isset($request['suffix']) ? strtoupper($request['suffix']) : '',
                            'face_liveness_session_id' => $face_liveness_session_id ?? '',
                        ]);

                    // dd($response->json());

                    // If PSA returned an HTTP error (400, 401, 500, etc.)
                    if ($response->failed()) {
                        Log::error('PSA API responded with an error', [
                            'endpoint' => $baseUrl . '/query',
                            'error' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return back()->withErrors([
                            'psa' => 'PSA server error. Please try again later.'
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log network/connection errors
                    Log::error('PSA API connection failed', [
                        'endpoint' => $baseUrl . '/query',
                        'error' => $e->getMessage(),
                    ]);

                    return back()->withErrors([
                        'psa' => 'Unable to reach PSA server. Please try again later.'
                    ]);
                }
            } else {  // Verify via ID Number
                try {
                    $response = Http::retry(3, 300)
                        ->withHeaders([
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                            ])
                        ->withToken($access_token)
                        ->post($baseUrl . '/query/qr', [
                                'value' => $request['id_number'],
                                'face_liveness_session_id' => $face_liveness_session_id ?? '',
                            ]);

                    // If PSA returned an HTTP error (400, 401, 500, etc.)
                    if ($response->failed()) {
                        Log::error('PSA API responded with an error', [
                            'endpoint' => $baseUrl . '/query/qr',
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return back()->withErrors([
                            'psa' => 'PSA server error. Please try again later.'
                        ]);
                    }

                    // Handle successful response
                    $data = $response->json();
                } catch (\Exception $e) {
                    // Log network/connection errors
                    Log::error('PSA API connection failed', [
                        'endpoint' => $baseUrl . '/query/qr',
                        'error' => $e->getMessage(),
                    ]);

                    return back()->withErrors([
                        'psa' => 'Unable to reach PSA server. Please try again later.'
                    ]);
                }
            }
        }

        // Check the response if the person is verified or not
        if (isset($response->json()['data']['verified']) && $response->json()['data']['verified'] == false) {
            // if the request['is_update'] is set to true the user should be redirected back to the personal information page
            if ($request['is_update']) {

                return redirect()->back()->withErrors(['error' => 'PSA Verification Failed. Make sure you are already registered in the National ID System.']);
            }
            // if the is_update is not set, redirect to employee creation page
            else {
                Log::error('PSA Verification Failed', [
                    'request' => $request->all(),
                    'response' => $response->json(),
                ]);
                return redirect()->route('hrmanagement.employee.create')->withErrors(['error' => 'PSA Verification Failed. Please check all details if correct then try again.']);
            }
        }
        // If verified, process the data
        else {
            // Sanitize and normalize data
            $sanitize = function ($value) {
                if (!$value)
                    return null;

                $value = strtolower(trim($value));
                $words = explode(' ', $value);
                $lowercaseWords = ['of', 'and', 'the', 'in', 'on', 'at', 'to', 'for', 'by', 'from'];

                foreach ($words as $index => &$word) {
                    // Always capitalize the first word or if it's not in lowercaseWords
                    if ($index === 0 || !in_array($word, $lowercaseWords)) {
                        $word = ucfirst($word);
                    }
                }

                return implode(' ', $words);
            };
            // dd($request);
            // Prepare personal info array
            $personalInfo = [
                'date_hired' => $request['date_hired'] ?? now()->toDateString(),
                'first_name' => $sanitize($response->json('data.first_name')),
                'middle_name' => $sanitize($response->json('data.middle_name')),
                'last_name' => $sanitize($response->json('data.last_name')),
                'suffix' => $sanitize($response->json('data.suffix')),
                'birth_date' => $response->json('data.birth_date'),
                'place_of_birth' => $sanitize($response->json('data.place_of_birth')),
                'blood_type' => $this->normalizeBloodType($response->json('data.blood_type')),
                'sex' => trim($response->json('data.gender') ?? $response->json('data.sex')),
                'marital_status' => $sanitize($response->json('data.marital_status')),
                'mobile_number' => trim($response->json('data.mobile_number')), // don't format numbers
                'email' => strtolower(trim($response->json('data.email'))),
                'barangay' => $sanitize($response->json('data.barangay')),
                'municipality' => $sanitize($response->json('data.municipality')),
                'province' => $sanitize($response->json('data.province')),
                'postal_code' => trim($response->json('data.postal_code')),
            ];

            if (isset($request['is_update'])) {
                // $employeeService->updatePsaVerificationResponse($personalInfo, $request['employee_id']);

                // return redirect()->back()->with('success', 'Personal information updated via PSA verification successfully.');
                return back()->with('psaLoadedData', $personalInfo);
            } else {
                // Save employee data by calling EmployeeService
                // $employee = $employeeService->storePsaVerificationResponse($personalInfo, 2);
                return back()->with('psaLoadedData', $personalInfo);
            }

            // Generate a signed edit URL
            // $signedEditLink = URL::signedRoute('hrmanagement.employee.edit', [
            //     'id' => $employee->id
            // ]);

            // return redirect($signedEditLink);
        }
    }

    private function normalizeBloodType($value)
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        // handle "UNKNOWN", "unknown", etc.
        if (strtolower($value) === 'unknown') {
            return null;
        }

        return strtoupper($value); // Normalized: A+, O-, AB, etc.
    }
}
