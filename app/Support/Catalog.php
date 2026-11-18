<?php

namespace App\Support;

/**
 * Every Cashfree Secure ID product VerifyXpeed sells.
 *
 * flow:    sync   = one call, result comes back immediately
 *          link   = creates a link the end-user opens (DigiLocker, e-sign...), result arrives later
 *          otp    = two steps: send OTP, then verify OTP (Mobile 360)
 * body:    json | multipart
 * vid:     whether Cashfree expects our verification_id in the body
 * outcome: how to read valid/invalid from the response
 * price / cost: default rupees before GST (admin can change both in Pricing)
 */
class Catalog
{
    public const CATEGORIES = [
        'identity' => ['label' => 'Identity', 'emoji' => '🪪'],
        'bank'     => ['label' => 'Bank & UPI', 'emoji' => '🏦'],
        'business' => ['label' => 'Business', 'emoji' => '🏢'],
        'ai'       => ['label' => 'Face & AI', 'emoji' => '🤳'],
        'risk'     => ['label' => 'Risk & data', 'emoji' => '🛰️'],
        'flows'    => ['label' => 'Onboarding links', 'emoji' => '🔗'],
    ];

    public static function all(): array
    {
        static $all = null;
        if ($all !== null) return $all;

        $pan = ['name' => 'pan', 'label' => 'PAN number', 'type' => 'text', 'rules' => ['required', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'], 'placeholder' => 'ABCPV1234D', 'upper' => true, 'mask' => true];
        $optName = ['name' => 'name', 'label' => 'Name to match (optional)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9 .\-\/&]*$/'], 'placeholder' => 'John Doe'];
        $dob = ['name' => 'dob', 'label' => 'Date of birth', 'type' => 'date', 'rules' => ['required', 'date_format:Y-m-d']];
        $img = fn ($n, $l) => ['name' => $n, 'label' => $l, 'type' => 'file', 'rules' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'], 'accept' => 'image/jpeg,image/png'];

        $all = [
            /* ---------------- Identity ---------------- */
            'pan' => [
                'name' => 'PAN Verify', 'emoji' => '🪪', 'cat' => 'identity', 'color' => 'lime',
                'tagline' => 'Is this PAN real? Get the registered name, status & Aadhaar link.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/pan', 'body' => 'json', 'vid' => false, 'api_version' => '2022-10-26',
                'outcome' => 'valid_bool', 'price' => 3, 'cost' => 1.5,
                'fields' => [$pan, $optName],
                'test' => ['pan' => 'ABCPV1234D', 'name' => 'John Doe'],
                'highlights' => ['registered_name', 'pan_status_desc', 'type', 'name_match_result', 'aadhaar_seeding_status_desc'],
            ],
            'pan_lite' => [
                'name' => 'PAN Lite', 'emoji' => '⚡', 'cat' => 'identity', 'color' => 'lime',
                'tagline' => 'PAN + name + date of birth match in one quick yes/no.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/pan-lite', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 4, 'cost' => 2,
                'fields' => [$pan, ['name' => 'name', 'label' => 'Name as per PAN', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'placeholder' => 'John Doe'], $dob],
                'test' => ['pan' => 'ABCPV1234D', 'name' => 'John Doe', 'dob' => '1993-06-30'],
                'highlights' => ['name', 'name_match', 'dob_match', 'pan_status', 'aadhaar_seeding_status_desc'],
            ],
            'pan_360' => [
                'name' => 'PAN 360', 'emoji' => '🔭', 'cat' => 'identity', 'color' => 'lime',
                'tagline' => 'Full PAN profile: DOB, gender, masked Aadhaar, address & more.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/pan/advance', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 8, 'cost' => 4,
                'fields' => [$pan, $optName],
                'test' => ['pan' => 'AZJPG7110R'],
                'highlights' => ['registered_name', 'date_of_birth', 'gender', 'masked_aadhaar_number', 'aadhaar_linked', 'type'],
            ],
            'digilocker' => [
                'name' => 'Aadhaar via DigiLocker', 'emoji' => '🔐', 'cat' => 'identity', 'color' => 'pink',
                'tagline' => 'Send a DigiLocker link. The person consents, you get Aadhaar / PAN / DL documents.',
                'flow' => 'link', 'method' => 'POST', 'path' => '/digilocker', 'body' => 'json', 'vid' => true,
                'outcome' => 'link', 'price' => 12, 'cost' => 6,
                'fields' => [
                    ['name' => 'document_requested', 'label' => 'Documents to fetch', 'type' => 'multiselect', 'options' => ['AADHAAR' => 'Aadhaar', 'PAN' => 'PAN', 'DRIVING_LICENSE' => 'Driving licence'], 'rules' => ['required', 'array', 'min:1'], 'default' => ['AADHAAR']],
                    ['name' => 'user_flow', 'label' => 'Person has a DigiLocker account?', 'type' => 'select', 'options' => ['signup' => 'Not sure / new user', 'signin' => 'Yes, existing account'], 'rules' => ['nullable', 'in:signup,signin']],
                ],
                'extra' => ['purpose' => 'KYC Verification'],
                'link_key' => 'url',
                'status' => ['path' => '/digilocker', 'query' => 'verification_id', 'key' => 'status',
                    'valid' => ['AUTHENTICATED'], 'invalid' => ['EXPIRED', 'CONSENT_DENIED']],
                'documents' => ['AADHAAR', 'PAN', 'DRIVING_LICENSE'],
                'highlights' => ['status', 'user_details.name', 'user_details.dob', 'user_details.gender', 'document_consent'],
            ],
            'digilocker_account' => [
                'name' => 'DigiLocker Account Check', 'emoji' => '📱', 'cat' => 'identity', 'color' => 'pink',
                'tagline' => 'Does this mobile number have a DigiLocker account? Decide signin vs signup.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/digilocker/verify-account', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 2, 'cost' => 1,
                'fields' => [['name' => 'mobile_number', 'label' => 'Mobile number', 'type' => 'text', 'rules' => ['required', 'regex:/^(\+?91)?[6-9][0-9]{9}$/'], 'placeholder' => '9988xxxxxx', 'mask' => true]],
                'test' => ['mobile_number' => '9988776655'],
                'highlights' => ['status', 'digilocker_id'],
            ],
            'aadhaar_masking' => [
                'name' => 'Aadhaar Masking', 'emoji' => '🙈', 'cat' => 'identity', 'color' => 'pink',
                'tagline' => 'Upload an Aadhaar card image, get back a copy with the first 8 digits hidden.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/aadhaar-masking', 'body' => 'multipart', 'vid' => true,
                'outcome' => 'status', 'price' => 3, 'cost' => 1.5,
                'fields' => [['name' => 'image', 'label' => 'Aadhaar card (JPG, PNG or PDF)', 'type' => 'file', 'rules' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'], 'accept' => 'image/jpeg,image/png,application/pdf']],
                'highlights' => ['status', 'image_link'],
            ],
            'driving_licence' => [
                'name' => 'Driving Licence', 'emoji' => '🚗', 'cat' => 'identity', 'color' => 'cyan',
                'tagline' => 'Licence holder name, validity, vehicle classes & photo.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/driving-license', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 6, 'cost' => 3,
                'fields' => [['name' => 'dl_number', 'label' => 'DL number', 'type' => 'text', 'rules' => ['required', 'string', 'max:30'], 'placeholder' => 'KA0120198900984', 'upper' => true, 'mask' => true], $dob],
                'test' => ['dl_number' => 'KA0120198900984', 'dob' => '1994-08-05'],
                'highlights' => ['details_of_driving_licence.name', 'details_of_driving_licence.date_of_issue', 'dl_validity.non_transport.to', 'status'],
            ],
            'voter_id' => [
                'name' => 'Voter ID', 'emoji' => '🗳️', 'cat' => 'identity', 'color' => 'cyan',
                'tagline' => 'EPIC number check: name, age, address & constituency.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/voter-id', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 6, 'cost' => 3,
                'fields' => [['name' => 'epic_number', 'label' => 'EPIC (voter ID) number', 'type' => 'text', 'rules' => ['required', 'string', 'max:20'], 'placeholder' => 'UAI4574761', 'upper' => true, 'mask' => true], array_merge($optName, ['rules' => ['nullable', 'string', 'max:50']])],
                'test' => ['epic_number' => 'UAI4574761'],
                'highlights' => ['name', 'age', 'gender', 'state', 'assembly_constituency'],
            ],
            'passport' => [
                'name' => 'Passport', 'emoji' => '🛂', 'cat' => 'identity', 'color' => 'cyan',
                'tagline' => 'Indian passport check by file number + date of birth.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/passport', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 6, 'cost' => 3,
                'fields' => [['name' => 'file_number', 'label' => 'Passport file number', 'type' => 'text', 'rules' => ['required', 'string', 'max:20'], 'placeholder' => 'PA1234567890123', 'upper' => true, 'mask' => true], $dob, $optName],
                'test' => ['file_number' => 'PA1065913390615', 'dob' => '1990-01-01'],
                'highlights' => ['name', 'dob', 'application_type', 'application_received_date'],
            ],
            'vehicle_rc' => [
                'name' => 'Vehicle RC', 'emoji' => '🏍️', 'cat' => 'identity', 'color' => 'cyan',
                'tagline' => 'Owner, model, insurance, PUC, blacklist & challans for any vehicle number.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/vehicle-rc', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 6, 'cost' => 3,
                'fields' => [['name' => 'vehicle_number', 'label' => 'Vehicle number', 'type' => 'text', 'rules' => ['required', 'string', 'max:15'], 'placeholder' => 'TS09AB1234', 'upper' => true]],
                'test' => ['vehicle_number' => 'HJ01ME5678'],
                'highlights' => ['owner', 'model', 'rc_status', 'vehicle_insurance_upto', 'blacklist_status', 'is_commercial'],
            ],

            /* ---------------- Bank & UPI ---------------- */
            'bank_account' => [
                'name' => 'Bank Account (Penny Drop)', 'emoji' => '🏦', 'cat' => 'bank', 'color' => 'violet',
                'tagline' => '₹1 test credit: confirms the account is live and returns the holder name.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/bank-account/sync', 'body' => 'json', 'vid' => false,
                'outcome' => 'account_status', 'price' => 5, 'cost' => 2.5,
                'fields' => [
                    ['name' => 'bank_account', 'label' => 'Account number', 'type' => 'text', 'rules' => ['required', 'alpha_num', 'between:6,40'], 'placeholder' => '026291800001191', 'mask' => true],
                    ['name' => 'ifsc', 'label' => 'IFSC', 'type' => 'text', 'rules' => ['required', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'], 'placeholder' => 'YESB0000262', 'upper' => true],
                    $optName,
                ],
                'test' => ['bank_account' => '026291800001191', 'ifsc' => 'YESB0000262', 'name' => 'John Doe'],
                'highlights' => ['name_at_bank', 'account_status_code', 'bank_name', 'branch', 'name_match_result'],
            ],
            'upi_penny_drop' => [
                'name' => 'UPI ID Verify', 'emoji' => '💸', 'cat' => 'bank', 'color' => 'violet',
                'tagline' => '₹1 to a UPI ID: get the real name, bank account & IFSC behind it.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/upi/penny-drop', 'body' => 'json', 'vid' => true, 'api_version' => '2024-12-01',
                'outcome' => 'status', 'consent' => true, 'price' => 4, 'cost' => 2,
                'fields' => [['name' => 'vpa', 'label' => 'UPI ID', 'type' => 'text', 'rules' => ['required', 'regex:/^[A-Za-z0-9.\-_]{2,256}@[A-Za-z]{2,64}$/'], 'placeholder' => 'name@okhdfc', 'mask' => true], $optName],
                'test' => ['vpa' => 'success@upi'],
                'highlights' => ['name_at_bank', 'ifsc', 'name_match_result', 'status'],
            ],
            'ifsc' => [
                'name' => 'IFSC Lookup', 'emoji' => '🧭', 'cat' => 'bank', 'color' => 'violet',
                'tagline' => 'Bank, branch, address and which rails (NEFT/IMPS/UPI) are live.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/ifsc', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 1, 'cost' => 0.5,
                'fields' => [['name' => 'ifsc', 'label' => 'IFSC', 'type' => 'text', 'rules' => ['required', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'], 'placeholder' => 'HDFC0000001', 'upper' => true]],
                'test' => ['ifsc' => 'HDFC0000001'],
                'highlights' => ['bank', 'branch', 'city', 'imps', 'neft', 'upi'],
            ],
            'reverse_penny_drop' => [
                'name' => 'Reverse Penny Drop', 'emoji' => '🔁', 'cat' => 'bank', 'color' => 'violet',
                'tagline' => 'The person pays ₹1 by UPI. You get their verified bank account & name. ₹1 goes back.',
                'flow' => 'link', 'method' => 'POST', 'path' => '/reverse-penny-drop', 'body' => 'json', 'vid' => true,
                'outcome' => 'link', 'price' => 6, 'cost' => 3,
                'fields' => [$optName],
                'link_key' => 'upi_link',
                'status' => ['path' => '/remitter/status', 'query' => 'verification_id', 'key' => 'status',
                    'valid' => ['SUCCESS'], 'invalid' => ['FAILURE', 'EXPIRED']],
                'highlights' => ['status', 'name_at_bank', 'bank_account', 'ifsc', 'upi', 'name_match_result'],
            ],

            /* ---------------- Business ---------------- */
            'gstin' => [
                'name' => 'GSTIN Verify', 'emoji' => '🧾', 'cat' => 'business', 'color' => 'orange',
                'tagline' => 'Legal & trade name, status, registration date and address.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/gstin', 'body' => 'json', 'vid' => false,
                'outcome' => 'valid_bool', 'price' => 3, 'cost' => 1.5,
                'fields' => [
                    ['name' => 'GSTIN', 'label' => 'GSTIN', 'type' => 'text', 'rules' => ['required', 'alpha_num', 'size:15'], 'placeholder' => '29AAICP2912R1ZR', 'upper' => true],
                    ['name' => 'business_name', 'label' => 'Business name (optional)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:200']],
                ],
                'test' => ['GSTIN' => '29AAICP2912R1ZR'],
                'highlights' => ['legal_name_of_business', 'trade_name_of_business', 'gst_in_status', 'taxpayer_type', 'date_of_registration'],
            ],
            'pan_gstin' => [
                'name' => 'PAN → GSTINs', 'emoji' => '🧩', 'cat' => 'business', 'color' => 'orange',
                'tagline' => 'Every GST registration linked to a PAN, across all states.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/pan-gstin', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 4, 'cost' => 2,
                'fields' => [$pan], 'test' => ['pan' => 'DUMPY1001A'],
                'highlights' => ['status', 'gstin_list'],
            ],
            'cin' => [
                'name' => 'Company (CIN)', 'emoji' => '🏛️', 'cat' => 'business', 'color' => 'orange',
                'tagline' => 'MCA company details, status and directors list.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/cin', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 5, 'cost' => 2.5,
                'fields' => [['name' => 'cin', 'label' => 'CIN', 'type' => 'text', 'rules' => ['required', 'alpha_num', 'size:21'], 'placeholder' => 'U72900KA2015PTC082989', 'upper' => true]],
                'test' => ['cin' => 'U72900KA2015PTC082989'],
                'highlights' => ['company_name', 'cin_status', 'incorporation_date', 'email'],
            ],
            'udyam' => [
                'name' => 'Udyam (MSME)', 'emoji' => '🏭', 'cat' => 'business', 'color' => 'orange',
                'tagline' => 'MSME registration: enterprise name, type, activity & certificate.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/udyam', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 4, 'cost' => 2,
                'fields' => [['name' => 'udyam', 'label' => 'Udyam number', 'type' => 'text', 'rules' => ['required', 'string', 'max:25'], 'placeholder' => 'UDYAM-UP-43-1234567', 'upper' => true]],
                'test' => ['udyam' => 'UDYAM-UP-43-1234567'],
                'highlights' => ['enterprise_name', 'enterprise_type', 'organization_type', 'major_activity', 'date_of_udyam_registration'],
            ],
            'pan_udyam' => [
                'name' => 'PAN → Udyam', 'emoji' => '🔎', 'cat' => 'business', 'color' => 'orange',
                'tagline' => 'Find the MSME (Udyam) registration linked to a PAN.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/pan-udyam', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 4, 'cost' => 2,
                'fields' => [$pan], 'test' => ['pan' => 'AMJCL2021N'],
                'highlights' => ['status', 'udyam'],
            ],
            'fssai' => [
                'name' => 'FSSAI Licence', 'emoji' => '🍱', 'cat' => 'business', 'color' => 'orange',
                'tagline' => 'Food business licence: name, type, active or not, products.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/fssai', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 4, 'cost' => 2,
                'fields' => [['name' => 'fssai_number', 'label' => 'FSSAI number (14 digits)', 'type' => 'text', 'rules' => ['required', 'digits:14'], 'placeholder' => '10121002000677']],
                'test' => ['fssai_number' => '10121002000677'],
                'highlights' => ['business_name', 'license_type', 'license_active_flag', 'district', 'state'],
            ],

            /* ---------------- Face & AI ---------------- */
            'face_match' => [
                'name' => 'Face Match', 'emoji' => '🤳', 'cat' => 'ai', 'color' => 'pink',
                'tagline' => 'Selfie vs ID photo: same person or not, with a score.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/face-match', 'body' => 'multipart', 'vid' => true,
                'outcome' => 'face_match', 'price' => 5, 'cost' => 2.5,
                'fields' => [$img('first_image', 'Selfie'), $img('second_image', 'Photo from ID'),
                    ['name' => 'threshold', 'label' => 'Match threshold (optional, 0.1–0.99)', 'type' => 'text', 'rules' => ['nullable', 'numeric', 'gt:0', 'lt:1'], 'placeholder' => '0.75']],
                'highlights' => ['face_match_result', 'face_match_score', 'status'],
            ],
            'face_liveness' => [
                'name' => 'Liveness Check', 'emoji' => '👀', 'cat' => 'ai', 'color' => 'pink',
                'tagline' => 'Real person or a photo of a photo? Plus age range, glasses, blur…',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/face-liveness', 'body' => 'multipart', 'vid' => true, 'api_version' => '2024-12-01',
                'outcome' => 'liveness', 'price' => 5, 'cost' => 2.5,
                'fields' => [['name' => 'image', 'label' => 'Selfie', 'type' => 'file', 'rules' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'], 'accept' => 'image/jpeg,image/png']],
                'highlights' => ['liveness', 'liveness_score', 'status', 'age_range', 'gender.value'],
            ],
            'name_match' => [
                'name' => 'Name Match', 'emoji' => '🔤', 'cat' => 'ai', 'color' => 'pink',
                'tagline' => '"Rahul K" vs "Rahul Kumar"? Smart fuzzy match with a score.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/name-match', 'body' => 'json', 'vid' => true,
                'outcome' => 'name_match', 'price' => 1, 'cost' => 0.5,
                'fields' => [
                    ['name' => 'name_1', 'label' => 'Name 1', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'placeholder' => 'JOHN DOE'],
                    ['name' => 'name_2', 'label' => 'Name 2', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'placeholder' => 'JOHN DE'],
                ],
                'test' => ['name_1' => 'JOHN DOE', 'name_2' => 'JOHN DE'],
                'highlights' => ['reason', 'score'],
            ],
            'smart_ocr' => [
                'name' => 'Smart OCR', 'emoji' => '📸', 'cat' => 'ai', 'color' => 'pink',
                'tagline' => 'Photo of any ID → clean data. Fraud & quality checks included.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/bharat-ocr', 'body' => 'multipart', 'vid' => true, 'api_version' => '2024-12-01',
                'outcome' => 'status', 'price' => 6, 'cost' => 3,
                'fields' => [
                    ['name' => 'document_type', 'label' => 'Document type', 'type' => 'select', 'rules' => ['required', 'in:PAN,AADHAAR,DRIVING_LICENCE,VOTER_ID,PASSPORT,VEHICLE_RC,CANCELLED_CHEQUE,INVOICE'],
                        'options' => ['PAN' => 'PAN card', 'AADHAAR' => 'Aadhaar', 'DRIVING_LICENCE' => 'Driving licence', 'VOTER_ID' => 'Voter ID', 'PASSPORT' => 'Passport', 'VEHICLE_RC' => 'Vehicle RC', 'CANCELLED_CHEQUE' => 'Cancelled cheque', 'INVOICE' => 'Invoice']],
                    ['name' => 'file', 'label' => 'Document image or PDF', 'type' => 'file', 'rules' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'accept' => 'image/jpeg,image/png,application/pdf'],
                    ['name' => 'do_verification', 'label' => 'Also verify with government records (PAN & DL)', 'type' => 'checkbox', 'rules' => ['nullable', 'boolean']],
                ],
                'highlights' => ['document_type', 'status', 'document_fields', 'fraud_checks', 'quality_checks'],
            ],

            /* ---------------- Risk & data ---------------- */
            'mobile_360' => [
                'name' => 'Mobile 360', 'emoji' => '📡', 'cat' => 'risk', 'color' => 'cyan',
                'tagline' => 'OTP to the person\'s phone → linked PAN, bank, employment, risk score & more.',
                'flow' => 'otp', 'method' => 'POST', 'path' => '/mobile360/otp/send', 'body' => 'json', 'vid' => true, 'api_version' => '2024-12-01',
                'outcome' => 'link', 'consent' => true, 'price' => 15, 'cost' => 8,
                'fields' => [
                    ['name' => 'mobile_number', 'label' => 'Mobile number (10 digits)', 'type' => 'text', 'rules' => ['required', 'regex:/^[6-9][0-9]{9}$/'], 'placeholder' => '9999999999', 'mask' => true],
                    $optName,
                    ['name' => 'notification_modes', 'label' => 'Send OTP by', 'type' => 'multiselect', 'options' => ['SMS' => 'SMS', 'WHATSAPP' => 'WhatsApp'], 'rules' => ['required', 'array', 'min:1'], 'default' => ['SMS']],
                ],
                'test' => ['mobile_number' => '9999999999'],
                'otp' => ['path' => '/mobile360/otp/verify', 'valid' => ['SUCCESS'], 'invalid' => ['DETAILS_NOT_FOUND']],
                'highlights' => ['status', 'personal_details.full_name', 'personal_details.age', 'credit_score', 'risk_intelligence.risk_level', 'mobile_number_intelligence.current_service_provider'],
            ],
            'employment' => [
                'name' => 'Employment Check', 'emoji' => '💼', 'cat' => 'risk', 'color' => 'cyan',
                'tagline' => 'EPFO / UAN history: current employer, joining & exit dates.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/advance-employment', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 12, 'cost' => 6,
                'fields' => [
                    ['name' => 'phone', 'label' => 'Mobile number', 'type' => 'text', 'rules' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'], 'placeholder' => '9988775566', 'mask' => true],
                    ['name' => 'pan', 'label' => 'PAN (optional)', 'type' => 'text', 'rules' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'], 'upper' => true, 'mask' => true],
                    ['name' => 'uan', 'label' => 'UAN (optional)', 'type' => 'text', 'rules' => ['nullable', 'digits:12'], 'mask' => true],
                    ['name' => 'employee_name', 'label' => 'Employee name (optional)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100']],
                    ['name' => 'employer_name', 'label' => 'Employer name (optional)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150']],
                ],
                'hint' => 'Give at least a mobile number, PAN or UAN.',
                'require_one' => ['phone', 'pan', 'uan'],
                'test' => ['phone' => '9988775566'],
                'highlights' => ['status', 'recent_employment_details.employer_details.establishment_name', 'recent_employment_details.employee_details.joining_date', 'recent_employment_details.employee_details.employed'],
            ],
            'ip' => [
                'name' => 'IP Intelligence', 'emoji' => '🌐', 'cat' => 'risk', 'color' => 'cyan',
                'tagline' => 'Where is this IP really from? Proxy / VPN detection with risk scores.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/ip', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 1, 'cost' => 0.5,
                'fields' => [['name' => 'ip_address', 'label' => 'IP address', 'type' => 'text', 'rules' => ['required', 'ip'], 'placeholder' => '1.0.171.255']],
                'test' => ['ip_address' => '1.0.171.255'],
                'highlights' => ['city_name', 'region_name', 'country_name', 'proxy_type', 'proxy_type_risk_score'],
            ],
            'geocoding' => [
                'name' => 'Address → Location', 'emoji' => '📍', 'cat' => 'risk', 'color' => 'cyan',
                'tagline' => 'Turn an address into coordinates, city & pincode. Measure distance too.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/geocoding', 'body' => 'json', 'vid' => true, 'api_version' => '2024-12-01',
                'outcome' => 'status', 'price' => 1, 'cost' => 0.5,
                'fields' => [['name' => 'address', 'label' => 'Full address', 'type' => 'textarea', 'rules' => ['required', 'string', 'max:500'], 'placeholder' => 'Bangalore Karnataka']],
                'extra' => ['country_code' => 'IN'],
                'test' => ['address' => 'Bangalore Karnataka'],
                'highlights' => ['geo_latitude', 'geo_longitude', 'city', 'state', 'pincode'],
            ],
            'reverse_geocoding' => [
                'name' => 'Location → Address', 'emoji' => '🗺️', 'cat' => 'risk', 'color' => 'cyan',
                'tagline' => 'Coordinates in, full address out. Great for field-visit checks.',
                'flow' => 'sync', 'method' => 'POST', 'path' => '/reverse-geocoding', 'body' => 'json', 'vid' => true,
                'outcome' => 'status', 'price' => 1, 'cost' => 0.5,
                'fields' => [
                    ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'text', 'rules' => ['required', 'numeric', 'between:-90,90'], 'placeholder' => '12.9274915'],
                    ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'text', 'rules' => ['required', 'numeric', 'between:-180,180'], 'placeholder' => '77.6326806'],
                ],
                'test' => ['latitude' => '12.9274915', 'longitude' => '77.6326806'],
                'highlights' => ['address', 'city', 'state', 'pincode'],
            ],

            /* ---------------- Onboarding links ---------------- */
            'esign' => [
                'name' => 'Aadhaar e-Sign', 'emoji' => '✍️', 'cat' => 'flows', 'color' => 'lime',
                'tagline' => 'Upload a PDF, the signer gets an email and signs with Aadhaar OTP.',
                'flow' => 'link', 'method' => 'POST', 'path' => '/esignature', 'body' => 'json', 'vid' => true,
                'outcome' => 'link', 'price' => 30, 'cost' => 18,
                'fields' => [
                    ['name' => 'document', 'label' => 'Document (PDF, max 10 MB)', 'type' => 'file', 'rules' => ['required', 'file', 'mimes:pdf', 'max:10240'], 'accept' => 'application/pdf'],
                    ['name' => 'signer_name', 'label' => 'Signer name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    ['name' => 'signer_email', 'label' => 'Signer email', 'type' => 'text', 'rules' => ['required', 'email'], 'mask' => true],
                    ['name' => 'signer_phone', 'label' => 'Signer phone (optional)', 'type' => 'text', 'rules' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'], 'mask' => true],
                    ['name' => 'page', 'label' => 'Sign on page', 'type' => 'text', 'rules' => ['required', 'integer', 'min:1'], 'default' => '1'],
                    ['name' => 'expiry_in_days', 'label' => 'Link valid for (days, max 15)', 'type' => 'text', 'rules' => ['required', 'integer', 'between:1,15'], 'default' => '7'],
                ],
                'link_key' => 'signing_link',
                'status' => ['path' => '/esignature', 'query' => 'verification_id', 'key' => 'status',
                    'valid' => ['SUCCESS'], 'invalid' => ['EXPIRED', 'FAILURE']],
                'highlights' => ['status', 'signed_doc_url', 'signers'],
            ],
            'kyc_link' => [
                'name' => 'KYC Form Link', 'emoji' => '📝', 'cat' => 'flows', 'color' => 'lime',
                'tagline' => 'One link, full KYC: the person fills a Cashfree-hosted form (template from your dashboard).',
                'flow' => 'link', 'method' => 'POST', 'path' => '/form', 'body' => 'json', 'vid' => true,
                'outcome' => 'link', 'price' => 20, 'cost' => 10,
                'fields' => [
                    ['name' => 'phone', 'label' => 'Person\'s mobile', 'type' => 'text', 'rules' => ['required', 'regex:/^[6-9][0-9]{9}$/'], 'mask' => true],
                    $optName,
                    ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'text', 'rules' => ['nullable', 'email'], 'mask' => true],
                    ['name' => 'notification_types', 'label' => 'Send link by', 'type' => 'multiselect', 'options' => ['sms' => 'SMS', 'email' => 'Email', 'whatsapp' => 'WhatsApp'], 'rules' => ['nullable', 'array'], 'default' => ['sms']],
                ],
                'link_key' => 'form_link',
                'status' => ['path' => '/form', 'query' => 'verificationID', 'key' => 'form_status',
                    'valid' => ['SUCCESS', 'VERIFIED', 'COMPLETED', 'APPROVED'], 'invalid' => ['FAILED', 'EXPIRED', 'REJECTED']],
                'highlights' => ['form_status', 'verification_details'],
            ],
            'vkyc' => [
                'name' => 'Video KYC', 'emoji' => '🎥', 'cat' => 'flows', 'color' => 'lime',
                'tagline' => 'RBI-style video KYC link with agent call. Needs templates set up in Cashfree.',
                'flow' => 'link', 'method' => 'POST', 'path' => '/vkyc', 'body' => 'json', 'vid' => true, 'api_version' => '2024-12-01',
                'outcome' => 'link', 'price' => 60, 'cost' => 40,
                'fields' => [
                    ['name' => 'phone', 'label' => 'Person\'s mobile', 'type' => 'text', 'rules' => ['required', 'regex:/^[6-9][0-9]{9}$/'], 'mask' => true],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9 .\-]*$/']],
                    ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'text', 'rules' => ['nullable', 'email'], 'mask' => true],
                ],
                'link_key' => 'vkyc_link',
                'status' => ['path' => '/vkyc', 'query' => 'verification_id', 'key' => 'status',
                    'valid' => ['APPROVED', 'SUCCESS', 'COMPLETED'], 'invalid' => ['REJECTED', 'EXPIRED', 'FAILED', 'CANCELLED']],
                'highlights' => ['status', 'sub_status', 'link_expiry', 'recording_link'],
            ],
        ];

        foreach ($all as $k => &$p) {
            $p['key'] = $k;
            $p['api_version'] ??= null;
            $p['consent'] ??= false;
            $p['extra'] ??= [];
            $p['test'] ??= [];
        }
        return $all;
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** Products that can run from a CSV (one call, JSON body, no files). */
    public static function bulkable(): array
    {
        return array_filter(self::all(), fn ($p) => $p['flow'] === 'sync' && $p['body'] === 'json');
    }
}
