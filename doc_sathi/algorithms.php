<?php
function doc_sathi_account_config($role)
{
    $configs = [
        'a' => [
            'table' => 'admin',
            'email_column' => 'aemail',
            'password_column' => 'apassword',
        ],
        'p' => [
            'table' => 'patient',
            'email_column' => 'pemail',
            'password_column' => 'ppassword',
        ],
        'd' => [
            'table' => 'doctor',
            'email_column' => 'docemail',
            'password_column' => 'docpassword',
        ],
    ];

    return $configs[$role] ?? null;
}

function doc_sathi_database_unavailable_message()
{
    return 'Database is currently unavailable. Start MySQL in XAMPP and try again.';
}

function doc_sathi_database_error_details($database = null, $fallbackMessage = '')
{
    $message = trim((string)$fallbackMessage);

    if ($message !== '') {
        return $message;
    }

    if ($database instanceof mysqli) {
        return trim((string)$database->error);
    }

    return '';
}

function doc_sathi_database_is_connection_error($database = null, $fallbackMessage = '')
{
    $message = strtolower(doc_sathi_database_error_details($database, $fallbackMessage));
    $errno = $database instanceof mysqli ? (int)$database->errno : 0;

    if (in_array($errno, [2002, 2003, 2006, 2013, 2055], true)) {
        return true;
    }

    foreach ([
        'server has gone away',
        'lost connection',
        'connection refused',
        'can\'t connect',
        'cannot connect',
        'no connection',
        'is not fully initialized',
        'mysqli object is already closed',
    ] as $needle) {
        if ($message !== '' && strpos($message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function doc_sathi_public_database_error_message($database = null, $fallbackMessage = '')
{
    $message = doc_sathi_database_error_details($database, $fallbackMessage);

    if (doc_sathi_database_is_connection_error($database, $message)) {
        return doc_sathi_database_unavailable_message();
    }

    if ($message !== '') {
        return $message;
    }

    return 'An unexpected database error occurred. Please try again.';
}

function doc_sathi_safe_rollback($database)
{
    if (!($database instanceof mysqli)) {
        return;
    }

    try {
        @$database->rollback();
    } catch (Throwable $exception) {
    }
}

function doc_sathi_prepare($database, $sql)
{
    $stmt = $database->prepare($sql);

    if (!$stmt && function_exists('doc_sathi_runtime_upgrade_schema')) {
        $error = (string)$database->error;
        $missingWorkflowColumns = ['duration_minutes', 'end_time', 'status', 'completed_at', 'completed_by', 'checkup_result', 'gender'];
        $shouldRetry = stripos($error, 'Unknown column') !== false;

        if ($shouldRetry) {
            $shouldRetry = false;

            foreach ($missingWorkflowColumns as $columnName) {
                if (stripos($error, "'" . $columnName . "'") !== false) {
                    $shouldRetry = true;
                    break;
                }
            }
        }

        if ($shouldRetry) {
            doc_sathi_runtime_upgrade_schema($database);
            $stmt = $database->prepare($sql);
        }
    }

    if (!$stmt) {
        throw new RuntimeException(doc_sathi_public_database_error_message($database));
    }

    return $stmt;
}

function doc_sathi_execute($stmt)
{
    if (!$stmt->execute()) {
        throw new RuntimeException(doc_sathi_public_database_error_message(null, $stmt->error));
    }
}

function doc_sathi_bind_dynamic_params($stmt, $types, array $params)
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindArguments = [$types];

    foreach (array_values($params) as $index => $value) {
        $params[$index] = $value;
        $bindArguments[] = &$params[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindArguments);
}

function doc_sathi_search_pattern($keyword)
{
    return '%' . trim((string)$keyword) . '%';
}

function doc_sathi_normalize_search_query($keyword, $maxLength = 120)
{
    $keyword = preg_replace('/\s+/', ' ', trim((string)$keyword));
    $maxLength = max(1, (int)$maxLength);

    if (function_exists('mb_substr')) {
        return mb_substr($keyword, 0, $maxLength, 'UTF-8');
    }

    return substr($keyword, 0, $maxLength);
}

function doc_sathi_like_pattern($keyword, $matchMode = 'contains')
{
    $keyword = doc_sathi_normalize_search_query($keyword);
    $keyword = addcslashes($keyword, "\\%_");

    if ($matchMode === 'prefix') {
        return $keyword . '%';
    }

    return '%' . $keyword . '%';
}

if (!defined('DOC_SATHI_BOOKING_LOCK_TIMEOUT_SECONDS')) {
    define('DOC_SATHI_BOOKING_LOCK_TIMEOUT_SECONDS', 5);
}

if (!defined('DOC_SATHI_DEFAULT_SESSION_DURATION_MINUTES')) {
    define('DOC_SATHI_DEFAULT_SESSION_DURATION_MINUTES', 30);
}

if (!defined('DOC_SATHI_CHECKUP_RESULT_MAX_LENGTH')) {
    define('DOC_SATHI_CHECKUP_RESULT_MAX_LENGTH', 3000);
}

if (!defined('DOC_SATHI_RECOMMENDATION_WEIGHT_SPECIALTY')) {
    define('DOC_SATHI_RECOMMENDATION_WEIGHT_SPECIALTY', 45);
}

if (!defined('DOC_SATHI_RECOMMENDATION_WEIGHT_EARLIEST_SESSION')) {
    define('DOC_SATHI_RECOMMENDATION_WEIGHT_EARLIEST_SESSION', 30);
}

if (!defined('DOC_SATHI_RECOMMENDATION_WEIGHT_AVAILABILITY')) {
    define('DOC_SATHI_RECOMMENDATION_WEIGHT_AVAILABILITY', 15);
}

if (!defined('DOC_SATHI_RECOMMENDATION_WEIGHT_WORKLOAD')) {
    define('DOC_SATHI_RECOMMENDATION_WEIGHT_WORKLOAD', 10);
}

if (!defined('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_DOCTOR_REPEAT')) {
    define('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_DOCTOR_REPEAT', 70);
}

if (!defined('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_SPECIALTY_REPEAT')) {
    define('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_SPECIALTY_REPEAT', 35);
}

if (!defined('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_GLOBAL_POPULARITY')) {
    define('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_GLOBAL_POPULARITY', 8);
}

if (!defined('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_EARLIEST_SESSION')) {
    define('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_EARLIEST_SESSION', 12);
}

if (!defined('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_AVAILABILITY')) {
    define('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_AVAILABILITY', 8);
}

if (!defined('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_WORKLOAD')) {
    define('DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_WORKLOAD', 5);
}

function doc_sathi_password_policy_message()
{
    return 'Password must be at least 8 characters and include at least one letter, one number, and one special character.';
}

function doc_sathi_password_is_strong($password)
{
    return is_string($password)
        && strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function doc_sathi_phone_policy_message()
{
    return 'Mobile number must start with 98 or 97 and contain exactly 10 digits.';
}

function doc_sathi_phone_is_valid($phone)
{
    $phone = trim((string)$phone);

    return preg_match('/^(98|97)[0-9]{8}$/', $phone) === 1;
}

function doc_sathi_valid_genders()
{
    return ['male', 'female', 'other'];
}

function doc_sathi_normalize_gender($gender)
{
    $gender = strtolower(trim((string)$gender));

    $aliases = [
        'm' => 'male',
        'man' => 'male',
        'male' => 'male',
        'f' => 'female',
        'woman' => 'female',
        'female' => 'female',
        'o' => 'other',
        'other' => 'other',
        'non-binary' => 'other',
        'nonbinary' => 'other',
    ];

    return $aliases[$gender] ?? '';
}

function doc_sathi_gender_is_valid($gender)
{
    return in_array(doc_sathi_normalize_gender($gender), doc_sathi_valid_genders(), true);
}

function doc_sathi_gender_required_message()
{
    return 'Please select a gender.';
}

function doc_sathi_gender_label($gender)
{
    $gender = doc_sathi_normalize_gender($gender);

    if ($gender === 'male') {
        return 'Male';
    }

    if ($gender === 'female') {
        return 'Female';
    }

    if ($gender === 'other') {
        return 'Other';
    }

    return '';
}

function doc_sathi_infer_gender_from_name($name)
{
    $name = strtolower(trim((string)$name));
    $name = preg_replace('/\b(dr|mr|mrs|ms|miss|prof)\.?\b/', ' ', $name);
    $name = preg_replace('/[^a-z ]+/', ' ', $name);
    $parts = preg_split('/\s+/', trim($name));
    $firstName = $parts[0] ?? '';

    if ($firstName === '') {
        return 'other';
    }

    $maleNames = [
        'aakash', 'aarav', 'aayush', 'abhas', 'amit', 'anil', 'awas', 'bikash',
        'bikram', 'binod', 'bishal', 'deepak', 'hari', 'kiran', 'krishna', 'manoj',
        'nabin', 'narayan', 'prabin', 'prakash', 'rajan', 'rajesh', 'ram', 'ramesh',
        'roshan', 'sagar', 'santosh', 'shyam', 'sudip', 'sunil', 'sushil',
    ];
    $femaleNames = [
        'anjali', 'anita', 'asmita', 'gita', 'kabita', 'kamala', 'laxmi', 'manisha',
        'menuka', 'nirmala', 'pooja', 'pratiksha', 'puja', 'rekha', 'rita', 'rojina',
        'sabina', 'sabita', 'sangita', 'sarita', 'sarmila', 'sita', 'sunita', 'sushma',
        'susmita',
    ];

    if (in_array($firstName, $maleNames, true)) {
        return 'male';
    }

    if (in_array($firstName, $femaleNames, true)) {
        return 'female';
    }

    return 'other';
}

function doc_sathi_email_policy_message()
{
    return 'Please enter a valid email address.';
}

function doc_sathi_email_is_valid($email)
{
    $email = trim((string)$email);

    return $email !== ''
        && strlen($email) <= 255
        && preg_match('/[\r\n]/', $email) !== 1
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function doc_sathi_parse_natural_number($value, $minimum = 1)
{
    $value = trim((string)$value);

    if ($value === '' || preg_match('/^[0-9]+$/', $value) !== 1) {
        return null;
    }

    $number = (int)$value;

    if ($number < (int)$minimum) {
        return null;
    }

    return $number;
}

function doc_sathi_parse_date($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $timezone = new DateTimeZone('Asia/Kathmandu');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false) {
        return null;
    }

    if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
        return null;
    }

    return $date;
}

function doc_sathi_parse_time($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $timezone = new DateTimeZone('Asia/Kathmandu');

    foreach (['!H:i', '!H:i:s'] as $format) {
        $time = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($time === false) {
            continue;
        }

        if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            continue;
        }

        return $time;
    }

    return null;
}

function doc_sathi_allowed_session_durations()
{
    return [15, 20, 30, 45, 60, 90];
}

function doc_sathi_default_session_duration_minutes()
{
    return DOC_SATHI_DEFAULT_SESSION_DURATION_MINUTES;
}

function doc_sathi_checkup_result_max_length()
{
    return DOC_SATHI_CHECKUP_RESULT_MAX_LENGTH;
}

function doc_sathi_normalize_checkup_result($value)
{
    $value = str_replace(["\r\n", "\r"], "\n", (string)$value);

    return trim($value);
}

function doc_sathi_session_duration_is_valid($durationMinutes)
{
    return in_array((int)$durationMinutes, doc_sathi_allowed_session_durations(), true);
}

function doc_sathi_normalize_session_duration($durationMinutes)
{
    $durationMinutes = (int)$durationMinutes;

    if (doc_sathi_session_duration_is_valid($durationMinutes)) {
        return $durationMinutes;
    }

    return doc_sathi_default_session_duration_minutes();
}

function doc_sathi_session_duration_label($durationMinutes)
{
    $durationMinutes = doc_sathi_normalize_session_duration($durationMinutes);

    return $durationMinutes . ' minutes';
}

function doc_sathi_session_datetime($dateValue, $timeValue)
{
    $date = doc_sathi_parse_date($dateValue);
    $time = doc_sathi_parse_time($timeValue);

    if ($date === null || $time === null) {
        return null;
    }

    $timezone = new DateTimeZone('Asia/Kathmandu');
    $dateTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
        $timezone
    );
    $errors = DateTimeImmutable::getLastErrors();

    if ($dateTime === false) {
        return null;
    }

    if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
        return null;
    }

    return $dateTime;
}

function doc_sathi_reference_datetime($reference = null)
{
    $timezone = new DateTimeZone('Asia/Kathmandu');

    if ($reference instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($reference)->setTimezone($timezone);
    }

    return new DateTimeImmutable('now', $timezone);
}

function doc_sathi_calculate_session_end_datetime($dateValue, $timeValue, $durationMinutes)
{
    if (!doc_sathi_session_duration_is_valid($durationMinutes)) {
        return null;
    }

    $startDateTime = doc_sathi_session_datetime($dateValue, $timeValue);

    if ($startDateTime === null) {
        return null;
    }

    $endDateTime = $startDateTime->modify('+' . (int)$durationMinutes . ' minutes');

    if (!$endDateTime instanceof DateTimeImmutable) {
        return null;
    }

    if ($endDateTime->format('Y-m-d') !== $startDateTime->format('Y-m-d')) {
        return null;
    }

    return $endDateTime;
}

function doc_sathi_session_end_time_from_inputs($dateValue, $timeValue, $durationMinutes)
{
    $endDateTime = doc_sathi_calculate_session_end_datetime($dateValue, $timeValue, $durationMinutes);

    return $endDateTime ? $endDateTime->format('H:i:s') : null;
}

function doc_sathi_schedule_duration_minutes(array $schedule)
{
    return doc_sathi_normalize_session_duration($schedule['duration_minutes'] ?? doc_sathi_default_session_duration_minutes());
}

function doc_sathi_schedule_end_time(array $schedule)
{
    $endTime = trim((string)($schedule['end_time'] ?? ''));

    if ($endTime !== '' && doc_sathi_parse_time($endTime) !== null) {
        return doc_sathi_parse_time($endTime)->format('H:i:s');
    }

    $scheduledate = trim((string)($schedule['scheduledate'] ?? ''));
    $scheduletime = trim((string)($schedule['scheduletime'] ?? ''));

    return doc_sathi_session_end_time_from_inputs(
        $scheduledate,
        $scheduletime,
        doc_sathi_schedule_duration_minutes($schedule)
    ) ?? '';
}

function doc_sathi_session_has_started($dateValue, $timeValue, $reference = null)
{
    $sessionDateTime = doc_sathi_session_datetime($dateValue, $timeValue);

    if ($sessionDateTime === null) {
        return false;
    }

    return $sessionDateTime <= doc_sathi_reference_datetime($reference);
}

function doc_sathi_session_has_ended($dateValue, $endTimeValue, $reference = null)
{
    $sessionEndDateTime = doc_sathi_session_datetime($dateValue, $endTimeValue);

    if ($sessionEndDateTime === null) {
        return false;
    }

    return $sessionEndDateTime <= doc_sathi_reference_datetime($reference);
}

function doc_sathi_session_can_be_managed(array $schedule, $reference = null)
{
    $scheduledate = trim((string)($schedule['scheduledate'] ?? ''));
    $scheduletime = trim((string)($schedule['scheduletime'] ?? ''));

    if (doc_sathi_session_datetime($scheduledate, $scheduletime) === null) {
        return false;
    }

    return !doc_sathi_session_has_started($scheduledate, $scheduletime, $reference);
}

function doc_sathi_session_datetime_is_in_past($dateValue, $timeValue, $reference = null)
{
    $sessionDateTime = doc_sathi_session_datetime($dateValue, $timeValue);

    if ($sessionDateTime === null) {
        return false;
    }

    $timezone = new DateTimeZone('Asia/Kathmandu');

    if ($reference instanceof DateTimeInterface) {
        $referenceDateTime = DateTimeImmutable::createFromInterface($reference)->setTimezone($timezone);
    } else {
        $referenceDateTime = new DateTimeImmutable('now', $timezone);
    }

    $referenceDateTime = $referenceDateTime->setTime(
        (int)$referenceDateTime->format('H'),
        (int)$referenceDateTime->format('i'),
        0
    );

    return $sessionDateTime < $referenceDateTime;
}

function doc_sathi_schedule_overlap_exists($database, $docid, $scheduledate, $scheduletime, $endTime, $excludeScheduleId = 0)
{
    $docid = (int)$docid;
    $excludeScheduleId = (int)$excludeScheduleId;
    $scheduledate = trim((string)$scheduledate);
    $scheduletime = trim((string)$scheduletime);
    $endTime = trim((string)$endTime);

    if (
        $docid <= 0 ||
        doc_sathi_parse_date($scheduledate) === null ||
        doc_sathi_parse_time($scheduletime) === null ||
        doc_sathi_parse_time($endTime) === null
    ) {
        return false;
    }

    $sql = "SELECT scheduleid
            FROM schedule
            WHERE docid = ?
              AND scheduledate = ?
              AND scheduletime < ?
              AND COALESCE(
                    end_time,
                    ADDTIME(
                        scheduletime,
                        SEC_TO_TIME(COALESCE(duration_minutes, " . doc_sathi_default_session_duration_minutes() . ") * 60)
                    )
                  ) > ?";

    if ($excludeScheduleId > 0) {
        $sql .= " AND scheduleid <> ?";
    }

    $sql .= " LIMIT 1";

    $stmt = doc_sathi_prepare($database, $sql);

    if ($excludeScheduleId > 0) {
        $stmt->bind_param("isssi", $docid, $scheduledate, $endTime, $scheduletime, $excludeScheduleId);
    } else {
        $stmt->bind_param("isss", $docid, $scheduledate, $endTime, $scheduletime);
    }

    doc_sathi_execute($stmt);

    return $stmt->get_result()->num_rows > 0;
}

function doc_sathi_normalize_appointment_status($status)
{
    $status = strtolower(trim((string)$status));

    if ($status === 'completed') {
        return 'completed';
    }

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    return 'confirmed';
}

function doc_sathi_appointment_status_details(array $appointment, $reference = null)
{
    $status = doc_sathi_normalize_appointment_status($appointment['status'] ?? '');
    $completedAt = trim((string)($appointment['completed_at'] ?? ''));

    if ($status === 'cancelled') {
        return [
            'code' => 'cancelled',
            'label' => 'Cancelled',
            'tone' => 'danger',
            'can_finish' => false,
            'can_cancel' => false,
        ];
    }

    if ($status === 'completed' || ($completedAt !== '' && $completedAt !== '0000-00-00 00:00:00')) {
        return [
            'code' => 'completed',
            'label' => 'Completed',
            'tone' => 'neutral',
            'can_finish' => false,
            'can_cancel' => false,
        ];
    }

    $scheduledate = trim((string)($appointment['scheduledate'] ?? ''));
    $scheduletime = trim((string)($appointment['scheduletime'] ?? ''));

    if (doc_sathi_session_datetime($scheduledate, $scheduletime) === null) {
        return [
            'code' => 'unscheduled',
            'label' => 'Unscheduled',
            'tone' => 'warning',
            'can_finish' => false,
            'can_cancel' => true,
        ];
    }

    $endTime = doc_sathi_schedule_end_time($appointment);
    $hasStarted = doc_sathi_session_has_started($scheduledate, $scheduletime, $reference);
    $hasEnded = $endTime !== '' && doc_sathi_session_has_ended($scheduledate, $endTime, $reference);
    $canFinish = $hasStarted;
    $canCancel = !$hasEnded;
    $referenceDate = doc_sathi_reference_datetime($reference)->format('Y-m-d');

    if ($hasStarted && !$hasEnded) {
        return [
            'code' => 'in_progress',
            'label' => 'In Progress',
            'tone' => 'info',
            'can_finish' => $canFinish,
            'can_cancel' => $canCancel,
        ];
    }

    if ($hasEnded) {
        return [
            'code' => 'pending_completion',
            'label' => 'Pending Completion',
            'tone' => 'warning',
            'can_finish' => $canFinish,
            'can_cancel' => $canCancel,
        ];
    }

    if ($scheduledate === $referenceDate) {
        return [
            'code' => 'today',
            'label' => 'Today',
            'tone' => 'info',
            'can_finish' => false,
            'can_cancel' => $canCancel,
        ];
    }

    return [
        'code' => 'upcoming',
        'label' => 'Upcoming',
        'tone' => 'success',
        'can_finish' => false,
        'can_cancel' => $canCancel,
    ];
}

function doc_sathi_dob_validation_message()
{
    return 'Please enter a valid date of birth.';
}

function doc_sathi_dob_future_message()
{
    return 'Date of birth cannot be a future date.';
}

function doc_sathi_minimum_age_message($roleLabel, $minimumAge)
{
    return trim((string)$roleLabel) . ' must be at least ' . (int)$minimumAge . ' years old.';
}

function doc_sathi_maximum_age_message($roleLabel, $maximumAge)
{
    return trim((string)$roleLabel) . ' age cannot be more than ' . (int)$maximumAge . ' years.';
}

function doc_sathi_max_dob_for_age($minimumAge)
{
    $minimumAge = max(0, (int)$minimumAge);
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kathmandu'));

    return $today->modify('-' . $minimumAge . ' years')->format('Y-m-d');
}

function doc_sathi_min_dob_for_max_age($maximumAge)
{
    $maximumAge = max(0, (int)$maximumAge);
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kathmandu'));

    return $today->modify('-' . ($maximumAge + 1) . ' years')->modify('+1 day')->format('Y-m-d');
}

function doc_sathi_dob_is_in_future($value)
{
    $date = doc_sathi_parse_date($value);
    if ($date === null) {
        return false;
    }

    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kathmandu'));
    return $date > $today;
}

function doc_sathi_dob_meets_minimum_age($value, $minimumAge)
{
    $date = doc_sathi_parse_date($value);
    if ($date === null) {
        return false;
    }

    $cutoff = new DateTimeImmutable(doc_sathi_max_dob_for_age($minimumAge), new DateTimeZone('Asia/Kathmandu'));
    return $date <= $cutoff;
}

function doc_sathi_age_from_dob($value)
{
    $date = doc_sathi_parse_date($value);
    if ($date === null || doc_sathi_dob_is_in_future($value)) {
        return null;
    }

    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kathmandu'));

    return (int)$date->diff($today)->y;
}

function doc_sathi_age_is_valid($age, $maximumAge)
{
    if (filter_var($age, FILTER_VALIDATE_INT) === false) {
        return false;
    }

    $age = (int)$age;
    $maximumAge = (int)$maximumAge;

    return $age >= 0 && $age <= $maximumAge;
}

function doc_sathi_dob_is_within_maximum_age($value, $maximumAge)
{
    $age = doc_sathi_age_from_dob($value);

    return $age !== null && $age <= (int)$maximumAge;
}

function doc_sathi_doctor_age_is_valid($age)
{
    return doc_sathi_age_is_valid($age, 100);
}

function doc_sathi_patient_age_is_valid($age)
{
    return doc_sathi_age_is_valid($age, 100);
}

function doc_sathi_doctor_dob_is_valid_age($dob)
{
    return doc_sathi_dob_is_within_maximum_age($dob, 100);
}

function doc_sathi_patient_dob_is_valid_age($dob)
{
    return doc_sathi_dob_is_within_maximum_age($dob, 100);
}

function doc_sathi_email_exists($database, $email, $excludeEmail = null)
{
    $email = trim((string)$email);
    $excludeEmail = $excludeEmail === null ? null : trim((string)$excludeEmail);

    if ($excludeEmail === null) {
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM webuser WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
    } else {
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM webuser WHERE email = ? AND email <> ? LIMIT 1");
        $stmt->bind_param("ss", $email, $excludeEmail);
    }

    doc_sathi_execute($stmt);
    return $stmt->get_result()->num_rows > 0;
}

function doc_sathi_patient_phone_exists($database, $phone, $excludePid = null)
{
    $phone = trim((string)$phone);

    if ($excludePid === null) {
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM patient WHERE pnum = ? LIMIT 1");
        $stmt->bind_param("s", $phone);
    } else {
        $excludePid = (int)$excludePid;
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM patient WHERE pnum = ? AND pid <> ? LIMIT 1");
        $stmt->bind_param("si", $phone, $excludePid);
    }

    doc_sathi_execute($stmt);
    return $stmt->get_result()->num_rows > 0;
}

function doc_sathi_doctor_phone_exists($database, $phone, $excludeDocid = null)
{
    $phone = trim((string)$phone);

    if ($excludeDocid === null) {
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM doctor WHERE doctel = ? LIMIT 1");
        $stmt->bind_param("s", $phone);
    } else {
        $excludeDocid = (int)$excludeDocid;
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM doctor WHERE doctel = ? AND docid <> ? LIMIT 1");
        $stmt->bind_param("si", $phone, $excludeDocid);
    }

    doc_sathi_execute($stmt);
    return $stmt->get_result()->num_rows > 0;
}

function doc_sathi_doctor_license_exists($database, $licenseNumber, $excludeDocid = null)
{
    $licenseNumber = trim((string)$licenseNumber);

    if ($licenseNumber === '') {
        return false;
    }

    if ($excludeDocid === null) {
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM doctor WHERE license_number = ? LIMIT 1");
        $stmt->bind_param("s", $licenseNumber);
    } else {
        $excludeDocid = (int)$excludeDocid;
        $stmt = doc_sathi_prepare($database, "SELECT 1 FROM doctor WHERE license_number = ? AND docid <> ? LIMIT 1");
        $stmt->bind_param("si", $licenseNumber, $excludeDocid);
    }

    doc_sathi_execute($stmt);
    return $stmt->get_result()->num_rows > 0;
}

function doc_sathi_get_doctor_by_email($database, $email)
{
    $stmt = doc_sathi_prepare(
        $database,
        "SELECT doctor.*, specialties.sname AS specialty_name
         FROM doctor
         LEFT JOIN specialties ON doctor.specialties = specialties.id
         WHERE doctor.docemail = ?
         LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    doc_sathi_execute($stmt);

    return $stmt->get_result()->fetch_assoc();
}

function doc_sathi_get_doctor_by_id($database, $docid, $approvedOnly = false)
{
    $docid = (int)$docid;
    $sql = "SELECT doctor.*, specialties.sname AS specialty_name
            FROM doctor
            LEFT JOIN specialties ON doctor.specialties = specialties.id
            WHERE doctor.docid = ?";

    if ($approvedOnly) {
        $sql .= " AND doctor.verification_status = 'approved'";
    }

    $sql .= " LIMIT 1";

    $stmt = doc_sathi_prepare($database, $sql);
    $stmt->bind_param("i", $docid);
    doc_sathi_execute($stmt);

    return $stmt->get_result()->fetch_assoc();
}

function doc_sathi_doctor_is_approved($doctor)
{
    return isset($doctor['verification_status']) && $doctor['verification_status'] === 'approved';
}

function doc_sathi_doctor_status_label($status)
{
    $status = $status ?: 'pending';

    switch ($status) {
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Pending verification';
    }
}

function doc_sathi_doctor_status_message($doctor)
{
    $status = $doctor['verification_status'] ?? 'pending';

    if ($status === 'approved') {
        return 'Your account is approved. You can create sessions and receive appointments.';
    }

    if ($status === 'rejected') {
        $reason = trim((string)($doctor['rejection_reason'] ?? ''));
        return 'Your account was rejected.' . ($reason !== '' ? ' Reason: ' . $reason : '');
    }

    return 'Your account is pending admin verification. You can view your dashboard, but you cannot create sessions or receive appointments yet.';
}

function doc_sathi_doctor_verification_document_path($doctor)
{
    $document = trim((string)($doctor['verification_document'] ?? ''));
    if ($document !== '') {
        return $document;
    }

    return trim((string)($doctor['certification_file'] ?? ''));
}

function doc_sathi_doctor_document_categories()
{
    return [
        'cv' => 'CV',
        'education' => 'Education',
        'experience' => 'Experience Letter',
        'license' => 'License',
    ];
}

function doc_sathi_normalize_doctor_document_category($category)
{
    $category = strtolower(trim((string)$category));

    return array_key_exists($category, doc_sathi_doctor_document_categories()) ? $category : '';
}

function doc_sathi_doctor_document_category_label($category)
{
    $category = doc_sathi_normalize_doctor_document_category($category);
    $categories = doc_sathi_doctor_document_categories();

    return $categories[$category] ?? '';
}

function doc_sathi_empty_doctor_document_groups()
{
    $groups = [];

    foreach (doc_sathi_doctor_document_categories() as $category => $label) {
        $groups[$category] = [
            'label' => $label,
            'documents' => [],
        ];
    }

    return $groups;
}

function doc_sathi_doctor_document_max_bytes()
{
    return 8 * 1024 * 1024;
}

function doc_sathi_doctor_document_allowed_extensions()
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
}

function doc_sathi_categorized_uploaded_files($fileBag, $category)
{
    $category = doc_sathi_normalize_doctor_document_category($category);

    if ($category === '' || !is_array($fileBag)) {
        return [];
    }

    $names = $fileBag['name'][$category] ?? [];
    $tmpNames = $fileBag['tmp_name'][$category] ?? [];
    $errors = $fileBag['error'][$category] ?? [];
    $sizes = $fileBag['size'][$category] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $errors = [$errors];
        $sizes = [$sizes];
    }

    $files = [];

    foreach ($names as $index => $name) {
        $files[] = [
            'name' => (string)$name,
            'tmp_name' => (string)($tmpNames[$index] ?? ''),
            'error' => (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($sizes[$index] ?? 0),
        ];
    }

    return $files;
}

function doc_sathi_has_categorized_doctor_document_upload($fileBag)
{
    if (!is_array($fileBag)) {
        return false;
    }

    foreach (array_keys(doc_sathi_doctor_document_categories()) as $category) {
        $errors = $fileBag['error'][$category] ?? [];

        if (!is_array($errors)) {
            $errors = [$errors];
        }

        foreach ($errors as $error) {
            if ((int)$error !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }
    }

    return false;
}

function doc_sathi_clean_original_filename($name)
{
    $name = basename((string)$name);
    $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name);
    $name = trim($name);

    if ($name === '') {
        return 'document';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 255, 'UTF-8');
    }

    return substr($name, 0, 255);
}

function doc_sathi_doctor_document_mime_is_allowed($tmpName, $extension)
{
    $allowedMimeTypes = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    if (!isset($allowedMimeTypes[$extension]) || !function_exists('finfo_open')) {
        return true;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return true;
    }

    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    return in_array($mimeType, $allowedMimeTypes[$extension], true);
}

function doc_sathi_delete_uploaded_document_paths(array $paths, $baseDir)
{
    $baseDir = rtrim((string)$baseDir, DIRECTORY_SEPARATOR);

    foreach ($paths as $path) {
        $path = trim((string)$path);

        if ($path === '' || strpos($path, 'uploads/verifications/') !== 0) {
            continue;
        }

        $absolutePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

function doc_sathi_upload_categorized_doctor_documents($database, $docid, $fileBag, $baseDir)
{
    $docid = (int)$docid;

    if ($docid <= 0 || !is_array($fileBag)) {
        return ['ok' => true, 'documents' => [], 'uploaded_paths' => []];
    }

    $documents = [];
    $uploadedPaths = [];
    $allowedExtensions = doc_sathi_doctor_document_allowed_extensions();
    $maxBytes = doc_sathi_doctor_document_max_bytes();
    $baseDir = rtrim((string)$baseDir, DIRECTORY_SEPARATOR);

    foreach (doc_sathi_doctor_document_categories() as $category => $label) {
        foreach (doc_sathi_categorized_uploaded_files($fileBag, $category) as $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => $label . ' upload failed. Please choose the file again.', 'uploaded_paths' => $uploadedPaths];
            }

            $size = (int)($file['size'] ?? 0);
            if ($size <= 0 || $size > $maxBytes) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => $label . ' files must be 8 MB or smaller.', 'uploaded_paths' => $uploadedPaths];
            }

            $originalName = doc_sathi_clean_original_filename($file['name'] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => $label . ' files must be PDF, JPG, JPEG, PNG, or WEBP.', 'uploaded_paths' => $uploadedPaths];
            }

            $tmpName = (string)($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => $label . ' upload could not be verified.', 'uploaded_paths' => $uploadedPaths];
            }

            if (!doc_sathi_doctor_document_mime_is_allowed($tmpName, $extension)) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => $label . ' file type does not match its extension.', 'uploaded_paths' => $uploadedPaths];
            }

            $uploadDir = $baseDir
                . DIRECTORY_SEPARATOR . 'uploads'
                . DIRECTORY_SEPARATOR . 'verifications'
                . DIRECTORY_SEPARATOR . 'doctor_' . $docid
                . DIRECTORY_SEPARATOR . $category;

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => 'Could not create the document upload directory.', 'uploaded_paths' => $uploadedPaths];
            }

            $filename = $category . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => 'Could not save the ' . $label . ' file.', 'uploaded_paths' => $uploadedPaths];
            }

            $relativePath = 'uploads/verifications/doctor_' . $docid . '/' . $category . '/' . $filename;
            $uploadedPaths[] = $relativePath;

            try {
                $stmt = doc_sathi_prepare(
                    $database,
                    "INSERT INTO doctor_documents (docid, document_category, original_name, file_path, uploaded_at)
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $stmt->bind_param("isss", $docid, $category, $originalName, $relativePath);
                doc_sathi_execute($stmt);
            } catch (Throwable $exception) {
                doc_sathi_delete_uploaded_document_paths($uploadedPaths, $baseDir);
                return ['ok' => false, 'message' => 'Could not save document details. Please try again.', 'uploaded_paths' => $uploadedPaths];
            }

            $documents[] = [
                'category' => $category,
                'label' => $label,
                'original_name' => $originalName,
                'file_path' => $relativePath,
            ];
        }
    }

    return ['ok' => true, 'documents' => $documents, 'uploaded_paths' => $uploadedPaths];
}

function doc_sathi_fetch_doctor_documents($database, $docid)
{
    $docid = (int)$docid;
    $groups = doc_sathi_empty_doctor_document_groups();

    if ($docid <= 0) {
        return $groups;
    }

    try {
        $stmt = doc_sathi_prepare(
            $database,
            "SELECT doctor_document_id, docid, document_category, original_name, file_path, uploaded_at
             FROM doctor_documents
             WHERE docid = ?
             ORDER BY FIELD(document_category, 'cv', 'education', 'experience', 'license'), uploaded_at DESC, doctor_document_id DESC"
        );
        $stmt->bind_param("i", $docid);
        doc_sathi_execute($stmt);

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $category = doc_sathi_normalize_doctor_document_category($row['document_category'] ?? '');

            if ($category === '') {
                continue;
            }

            $groups[$category]['documents'][] = $row;
        }
    } catch (Throwable $exception) {
        return $groups;
    }

    return $groups;
}

function doc_sathi_count_grouped_doctor_documents(array $documentGroups)
{
    $count = 0;

    foreach ($documentGroups as $group) {
        $count += count($group['documents'] ?? []);
    }

    return $count;
}

function doc_sathi_grouped_doctor_document_paths(array $documentGroups)
{
    $paths = [];

    foreach ($documentGroups as $group) {
        foreach (($group['documents'] ?? []) as $document) {
            $path = trim((string)($document['file_path'] ?? ''));
            if ($path !== '') {
                $paths[$path] = true;
            }
        }
    }

    return $paths;
}

function doc_sathi_document_path_is_grouped(array $documentGroups, $documentPath)
{
    $documentPath = trim((string)$documentPath);

    if ($documentPath === '') {
        return false;
    }

    $paths = doc_sathi_grouped_doctor_document_paths($documentGroups);

    return isset($paths[$documentPath]);
}

function doc_sathi_doctor_has_verification_documents($database, array $doctor)
{
    if (doc_sathi_doctor_verification_document_path($doctor) !== '') {
        return true;
    }

    return doc_sathi_count_grouped_doctor_documents(
        doc_sathi_fetch_doctor_documents($database, (int)($doctor['docid'] ?? 0))
    ) > 0;
}

function doc_sathi_upload_verification_document($file, $baseDir)
{
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Please upload a supporting document.'];
    }

    $maxBytes = 8 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Supporting document must be 8 MB or smaller.'];
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        return ['ok' => false, 'message' => 'Document must be a PDF, PNG, JPG, JPEG, or WEBP file.'];
    }

    $allowedMimeTypes = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
        return ['ok' => false, 'message' => 'Document file type does not match its extension.'];
    }

    $uploadDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'verifications';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Could not create verification upload directory.'];
    }

    $filename = 'verification_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'message' => 'Could not save the supporting document.'];
    }

    return [
        'ok' => true,
        'path' => 'uploads/verifications/' . $filename,
    ];
}

function doc_sathi_upload_certification($file, $baseDir)
{
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Please upload a certification file.'];
    }

    $maxBytes = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Certification file must be 5 MB or smaller.'];
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

    if (!in_array($extension, $allowedExtensions, true)) {
        return ['ok' => false, 'message' => 'Certification must be a PDF, JPG, JPEG, or PNG file.'];
    }

    $allowedMimeTypes = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
    ];

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
        return ['ok' => false, 'message' => 'Certification file type does not match its extension.'];
    }

    $uploadDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'certifications';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Could not create certification upload directory.'];
    }

    $filename = 'cert_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'message' => 'Could not save the certification file.'];
    }

    return [
        'ok' => true,
        'path' => 'uploads/certifications/' . $filename,
    ];
}

function doc_sathi_authenticate_user($database, $email, $password)
{
    $email = trim((string)$email);

    $stmt = doc_sathi_prepare($database, "SELECT usertype FROM webuser WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    doc_sathi_execute($stmt);
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        return ['ok' => false, 'message' => 'No account found for this email.'];
    }

    $role = $user['usertype'];
    $config = doc_sathi_account_config($role);

    if (!$config) {
        return ['ok' => false, 'message' => 'Invalid account type. Contact the administrator.'];
    }

    $table = $config['table'];
    $emailColumn = $config['email_column'];
    $passwordColumn = $config['password_column'];

    $accountStmt = doc_sathi_prepare(
        $database,
        "SELECT `$passwordColumn` AS password_hash FROM `$table` WHERE `$emailColumn` = ? LIMIT 1"
    );
    $accountStmt->bind_param("s", $email);
    doc_sathi_execute($accountStmt);
    $account = $accountStmt->get_result()->fetch_assoc();

    if (!$account) {
        return ['ok' => false, 'message' => 'No account found for this email.'];
    }

    $storedPassword = (string)$account['password_hash'];
    $isLegacyPlainText = password_get_info($storedPassword)['algo'] === 0;
    $passwordMatches = password_verify($password, $storedPassword);

    if (!$passwordMatches && $isLegacyPlainText) {
        $passwordMatches = hash_equals($storedPassword, $password);
    }

    if (!$passwordMatches) {
        return ['ok' => false, 'message' => 'Wrong credentials: Invalid email or password'];
    }

    if ($isLegacyPlainText || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = doc_sathi_prepare(
            $database,
            "UPDATE `$table` SET `$passwordColumn` = ? WHERE `$emailColumn` = ?"
        );
        $updateStmt->bind_param("ss", $newHash, $email);
        doc_sathi_execute($updateStmt);
    }

    return ['ok' => true, 'usertype' => $role];
}

function doc_sathi_patient_has_booking($database, $pid, $scheduleid)
{
    $pid = (int)$pid;
    $scheduleid = (int)$scheduleid;

    if ($pid <= 0 || $scheduleid <= 0) {
        return false;
    }

    $stmt = doc_sathi_prepare(
        $database,
        "SELECT appoid FROM appointment WHERE pid = ? AND scheduleid = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $pid, $scheduleid);
    doc_sathi_execute($stmt);

    return $stmt->get_result()->num_rows > 0;
}

function doc_sathi_patient_booking_map($database, $pid, array $scheduleIds = [])
{
    $pid = (int)$pid;

    if ($pid <= 0) {
        return [];
    }

    $filteredScheduleIds = [];
    foreach ($scheduleIds as $scheduleId) {
        $scheduleId = (int)$scheduleId;
        if ($scheduleId > 0) {
            $filteredScheduleIds[] = $scheduleId;
        }
    }

    $sql = "SELECT scheduleid FROM appointment WHERE pid = ?";
    $types = "i";
    $params = [$pid];

    if (!empty($filteredScheduleIds)) {
        $placeholders = implode(',', array_fill(0, count($filteredScheduleIds), '?'));
        $sql .= " AND scheduleid IN (" . $placeholders . ")";
        $types .= str_repeat('i', count($filteredScheduleIds));
        foreach ($filteredScheduleIds as $scheduleId) {
            $params[] = $scheduleId;
        }
    }

    $stmt = doc_sathi_prepare($database, $sql);
    doc_sathi_bind_dynamic_params($stmt, $types, $params);
    doc_sathi_execute($stmt);

    $map = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $map[(int)$row['scheduleid']] = true;
    }

    return $map;
}

function doc_sathi_schedule_time_window(array $schedule)
{
    $scheduledate = trim((string)($schedule['scheduledate'] ?? ''));
    $scheduletime = trim((string)($schedule['scheduletime'] ?? ''));
    $durationMinutes = doc_sathi_schedule_duration_minutes($schedule);
    $endTime = doc_sathi_schedule_end_time($schedule);
    $startDateTime = doc_sathi_session_datetime($scheduledate, $scheduletime);

    if ($startDateTime === null) {
        return null;
    }

    if ($endTime === '') {
        $endDateTime = doc_sathi_calculate_session_end_datetime($scheduledate, $scheduletime, $durationMinutes);
    } else {
        $endDateTime = doc_sathi_session_datetime($scheduledate, $endTime);
    }

    if (!$endDateTime instanceof DateTimeImmutable || $endDateTime <= $startDateTime) {
        return null;
    }

    return [
        'scheduledate' => $scheduledate,
        'scheduletime' => $startDateTime->format('H:i:s'),
        'end_time' => $endDateTime->format('H:i:s'),
        'duration_minutes' => $durationMinutes,
        'start' => $startDateTime,
        'end' => $endDateTime,
    ];
}

function doc_sathi_schedule_windows_overlap(array $leftSchedule, array $rightSchedule)
{
    $leftWindow = doc_sathi_schedule_time_window($leftSchedule);
    $rightWindow = doc_sathi_schedule_time_window($rightSchedule);

    if ($leftWindow === null || $rightWindow === null) {
        return false;
    }

    return $leftWindow['start'] < $rightWindow['end']
        && $leftWindow['end'] > $rightWindow['start'];
}

function doc_sathi_patient_active_appointments($database, $pid, $excludeScheduleId = 0)
{
    $pid = (int)$pid;
    $excludeScheduleId = (int)$excludeScheduleId;

    if ($pid <= 0) {
        return [];
    }

    $sql = "SELECT a.appoid,
                   a.scheduleid,
                   a.apponum,
                   a.status,
                   a.completed_at,
                   s.title,
                   s.scheduledate,
                   s.scheduletime,
                   s.duration_minutes,
                   s.end_time,
                   d.docname
            FROM appointment a
            INNER JOIN schedule s ON s.scheduleid = a.scheduleid
            INNER JOIN doctor d ON d.docid = s.docid
            WHERE a.pid = ?
              AND COALESCE(a.status, 'confirmed') <> 'cancelled'";

    if ($excludeScheduleId > 0) {
        $sql .= " AND a.scheduleid <> ?";
    }

    $sql .= " ORDER BY s.scheduledate ASC, s.scheduletime ASC, a.appoid ASC";

    $stmt = doc_sathi_prepare($database, $sql);

    if ($excludeScheduleId > 0) {
        $stmt->bind_param("ii", $pid, $excludeScheduleId);
    } else {
        $stmt->bind_param("i", $pid);
    }

    doc_sathi_execute($stmt);

    $appointments = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    return $appointments;
}

function doc_sathi_patient_overlapping_appointment($database, $pid, array $targetSchedule, $excludeScheduleId = 0)
{
    $appointments = doc_sathi_patient_active_appointments($database, $pid, $excludeScheduleId);

    foreach ($appointments as $appointment) {
        if (doc_sathi_schedule_windows_overlap($appointment, $targetSchedule)) {
            return $appointment;
        }
    }

    return null;
}

function doc_sathi_patient_booking_conflict_map($database, $pid, array $candidateSchedules)
{
    $pid = (int)$pid;

    if ($pid <= 0 || empty($candidateSchedules)) {
        return [];
    }

    $appointments = doc_sathi_patient_active_appointments($database, $pid);
    if (empty($appointments)) {
        return [];
    }

    $conflictMap = [];

    foreach ($candidateSchedules as $schedule) {
        $scheduleId = (int)($schedule['scheduleid'] ?? 0);

        if ($scheduleId <= 0 || isset($conflictMap[$scheduleId])) {
            continue;
        }

        foreach ($appointments as $appointment) {
            if ((int)($appointment['scheduleid'] ?? 0) === $scheduleId) {
                continue;
            }

            if (doc_sathi_schedule_windows_overlap($appointment, $schedule)) {
                $conflictMap[$scheduleId] = $appointment;
                break;
            }
        }
    }

    return $conflictMap;
}

function doc_sathi_fetch_schedule_for_booking($database, $scheduleid)
{
    $scheduleid = (int)$scheduleid;

    if ($scheduleid <= 0) {
        return null;
    }

    $stmt = doc_sathi_prepare(
        $database,
        "SELECT s.scheduleid,
                s.docid,
                s.title,
                s.scheduledate,
                s.scheduletime,
                s.duration_minutes,
                s.end_time,
                s.nop,
                d.docname,
                d.docemail,
                d.specialties
         FROM schedule s
         INNER JOIN doctor d ON d.docid = s.docid
         WHERE s.scheduleid = ?
           AND d.verification_status = 'approved'
         LIMIT 1"
    );
    $stmt->bind_param("i", $scheduleid);
    doc_sathi_execute($stmt);

    return $stmt->get_result()->fetch_assoc() ?: null;
}

function doc_sathi_validate_schedule_for_booking(array $schedule, $reference = null)
{
    $scheduledate = trim((string)($schedule['scheduledate'] ?? ''));
    $scheduletime = trim((string)($schedule['scheduletime'] ?? ''));
    $endTime = doc_sathi_schedule_end_time($schedule);

    if (doc_sathi_session_datetime($scheduledate, $scheduletime) === null) {
        return 'invalid-session';
    }

    if ($endTime === '' || doc_sathi_session_datetime($scheduledate, $endTime) === null) {
        return 'invalid-session';
    }

    if (doc_sathi_session_has_ended($scheduledate, $endTime, $reference)) {
        return 'session-expired';
    }

    return null;
}

function doc_sathi_schedule_lock_name($scheduleid)
{
    return 'doc_sathi_schedule_' . (int)$scheduleid;
}

function doc_sathi_patient_lock_name($pid)
{
    return 'doc_sathi_patient_' . (int)$pid;
}

function doc_sathi_acquire_schedule_lock($database, $scheduleid, $timeoutSeconds = DOC_SATHI_BOOKING_LOCK_TIMEOUT_SECONDS)
{
    $scheduleid = (int)$scheduleid;
    $timeoutSeconds = max(1, (int)$timeoutSeconds);

    if ($scheduleid <= 0) {
        return false;
    }

    $lockName = doc_sathi_schedule_lock_name($scheduleid);
    // Serialize booking attempts per schedule so two patients cannot read the
    // same free slot and insert the same appointment number concurrently.
    $stmt = doc_sathi_prepare($database, "SELECT GET_LOCK(?, ?) AS lock_status");
    $stmt->bind_param("si", $lockName, $timeoutSeconds);
    doc_sathi_execute($stmt);
    $row = $stmt->get_result()->fetch_assoc();

    return isset($row['lock_status']) && (int)$row['lock_status'] === 1 ? $lockName : false;
}

function doc_sathi_acquire_patient_lock($database, $pid, $timeoutSeconds = DOC_SATHI_BOOKING_LOCK_TIMEOUT_SECONDS)
{
    $pid = (int)$pid;
    $timeoutSeconds = max(1, (int)$timeoutSeconds);

    if ($pid <= 0) {
        return false;
    }

    $lockName = doc_sathi_patient_lock_name($pid);
    $stmt = doc_sathi_prepare($database, "SELECT GET_LOCK(?, ?) AS lock_status");
    $stmt->bind_param("si", $lockName, $timeoutSeconds);
    doc_sathi_execute($stmt);
    $row = $stmt->get_result()->fetch_assoc();

    return isset($row['lock_status']) && (int)$row['lock_status'] === 1 ? $lockName : false;
}

function doc_sathi_release_schedule_lock($database, $lockName)
{
    $lockName = trim((string)$lockName);

    if ($lockName === '') {
        return false;
    }

    $stmt = doc_sathi_prepare($database, "SELECT RELEASE_LOCK(?) AS lock_status");
    $stmt->bind_param("s", $lockName);
    doc_sathi_execute($stmt);
    $row = $stmt->get_result()->fetch_assoc();

    return isset($row['lock_status']) && (int)$row['lock_status'] === 1;
}

function doc_sathi_smallest_available_appointment_number($capacity, array $appointmentNumbers)
{
    $capacity = max(0, (int)$capacity);

    if ($capacity <= 0) {
        return null;
    }

    $usedNumbers = [];
    foreach ($appointmentNumbers as $appointmentNumber) {
        $appointmentNumber = (int)$appointmentNumber;
        if ($appointmentNumber >= 1 && $appointmentNumber <= $capacity) {
            $usedNumbers[$appointmentNumber] = true;
        }
    }

    for ($appointmentNumber = 1; $appointmentNumber <= $capacity; $appointmentNumber++) {
        if (!isset($usedNumbers[$appointmentNumber])) {
            return $appointmentNumber;
        }
    }

    return null;
}

function doc_sathi_schedule_booking_snapshot($database, $scheduleid)
{
    $schedule = doc_sathi_fetch_schedule_for_booking($database, $scheduleid);

    if (!$schedule) {
        return null;
    }

    $scheduleid = (int)$schedule['scheduleid'];
    $capacity = max(0, (int)($schedule['nop'] ?? 0));

    $appointmentStmt = doc_sathi_prepare(
        $database,
        "SELECT appoid, pid, apponum
         FROM appointment
         WHERE scheduleid = ?
         ORDER BY apponum ASC, appoid ASC"
    );
    $appointmentStmt->bind_param("i", $scheduleid);
    doc_sathi_execute($appointmentStmt);

    $appointments = [];
    $appointmentNumbers = [];
    $booked = 0;

    $result = $appointmentStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $booked++;
        $row['appoid'] = (int)$row['appoid'];
        $row['pid'] = (int)$row['pid'];
        $row['apponum'] = (int)$row['apponum'];
        $appointments[] = $row;
        $appointmentNumbers[] = $row['apponum'];
    }

    $nextAppointmentNumber = null;
    if ($capacity > 0 && $booked < $capacity) {
        $nextAppointmentNumber = doc_sathi_smallest_available_appointment_number($capacity, $appointmentNumbers);
    }

    return [
        'schedule' => $schedule,
        'appointments' => $appointments,
        'capacity' => $capacity,
        'booked' => $booked,
        'available' => max(0, $capacity - $booked),
        'next_apponum' => $nextAppointmentNumber,
        'is_full' => $capacity <= 0 || $booked >= $capacity || $nextAppointmentNumber === null,
    ];
}

function doc_sathi_schedule_booking_status($database, $scheduleid)
{
    $snapshot = doc_sathi_schedule_booking_snapshot($database, $scheduleid);

    if (!$snapshot) {
        return null;
    }

    return [
        'capacity' => (int)$snapshot['capacity'],
        'booked' => (int)$snapshot['booked'],
        'available' => (int)$snapshot['available'],
        'is_full' => (bool)$snapshot['is_full'],
        'next_apponum' => $snapshot['next_apponum'],
    ];
}

function doc_sathi_next_appointment_number($database, $scheduleid)
{
    $snapshot = doc_sathi_schedule_booking_snapshot($database, $scheduleid);

    if (!$snapshot) {
        return null;
    }

    return $snapshot['next_apponum'];
}

function doc_sathi_book_appointment($database, $pid, $scheduleid, $bookingDate)
{
    $pid = (int)$pid;
    $scheduleid = (int)$scheduleid;
    $bookingDate = trim((string)$bookingDate);

    if ($pid <= 0 || $scheduleid <= 0 || $bookingDate === '') {
        return ['ok' => false, 'reason' => 'invalid-request'];
    }

    $patientLockName = doc_sathi_acquire_patient_lock($database, $pid);

    if ($patientLockName === false) {
        return ['ok' => false, 'reason' => 'lock-timeout'];
    }

    $lockName = doc_sathi_acquire_schedule_lock($database, $scheduleid);

    if ($lockName === false) {
        try {
            doc_sathi_release_schedule_lock($database, $patientLockName);
        } catch (Throwable $releaseException) {
            // Ignore release failures if the request is already failing.
        }

        return ['ok' => false, 'reason' => 'lock-timeout'];
    }

    try {
        $snapshot = doc_sathi_schedule_booking_snapshot($database, $scheduleid);

        if (!$snapshot) {
            return ['ok' => false, 'reason' => 'session-not-found'];
        }

        $validationReason = doc_sathi_validate_schedule_for_booking($snapshot['schedule']);
        if ($validationReason !== null) {
            return ['ok' => false, 'reason' => $validationReason];
        }

        $duplicate = null;
        foreach ($snapshot['appointments'] as $appointment) {
            if ((int)$appointment['pid'] === $pid) {
                $duplicate = $appointment;
                break;
            }
        }

        if ($duplicate) {
            return [
                'ok' => false,
                'reason' => 'already-booked',
                'apponum' => (int)$duplicate['apponum'],
            ];
        }

        $conflictingAppointment = doc_sathi_patient_overlapping_appointment($database, $pid, $snapshot['schedule'], $scheduleid);
        if ($conflictingAppointment) {
            return [
                'ok' => false,
                'reason' => 'time-conflict',
                'conflict_scheduleid' => (int)($conflictingAppointment['scheduleid'] ?? 0),
                'conflict_title' => trim((string)($conflictingAppointment['title'] ?? '')),
                'conflict_apponum' => (int)($conflictingAppointment['apponum'] ?? 0),
                'conflict_docname' => trim((string)($conflictingAppointment['docname'] ?? '')),
                'conflict_date' => trim((string)($conflictingAppointment['scheduledate'] ?? '')),
                'conflict_time' => trim((string)($conflictingAppointment['scheduletime'] ?? '')),
                'conflict_end_time' => trim((string)(doc_sathi_schedule_end_time($conflictingAppointment) ?? '')),
            ];
        }

        if ($snapshot['is_full'] || $snapshot['next_apponum'] === null) {
            return ['ok' => false, 'reason' => 'session-full'];
        }

        // Greedy allocation: reuse the smallest free number in 1..capacity so
        // cancelled gaps are filled before any higher appointment number is used.
        $apponum = (int)$snapshot['next_apponum'];
        $insertStmt = doc_sathi_prepare(
            $database,
            "INSERT INTO appointment (pid, apponum, scheduleid, appodate) VALUES (?, ?, ?, ?)"
        );
        $insertStmt->bind_param("iiis", $pid, $apponum, $scheduleid, $bookingDate);
        doc_sathi_execute($insertStmt);

        return ['ok' => true, 'apponum' => $apponum];
    } catch (Throwable $exception) {
        return ['ok' => false, 'reason' => 'database-error'];
    } finally {
        try {
            doc_sathi_release_schedule_lock($database, $lockName);
        } catch (Throwable $releaseException) {
            // Ignore release failures after the booking path has already resolved.
        }

        try {
            doc_sathi_release_schedule_lock($database, $patientLockName);
        } catch (Throwable $releaseException) {
            // Ignore release failures after the booking path has already resolved.
        }
    }
}

function doc_sathi_normalize_text($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/', ' ', $value);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function doc_sathi_search_doctors($database, $query = '', $limit = 50)
{
    $query = doc_sathi_normalize_search_query($query);
    $limit = max(1, min(200, (int)$limit));

    if ($query === '') {
        $stmt = doc_sathi_prepare(
            $database,
            "SELECT doctor.*, specialties.sname AS specialty_name, 0 AS live_search_rank
             FROM doctor
             LEFT JOIN specialties ON doctor.specialties = specialties.id
             WHERE doctor.verification_status = 'approved'
             ORDER BY doctor.docname ASC
             LIMIT " . $limit
        );
        doc_sathi_execute($stmt);
    } else {
        $prefixPattern = doc_sathi_like_pattern($query, 'prefix');
        $partialPattern = doc_sathi_like_pattern($query, 'contains');

        $stmt = doc_sathi_prepare(
            $database,
            "SELECT DISTINCT doctor.*,
                    specialties.sname AS specialty_name,
                    CASE
                        WHEN LOWER(doctor.docname) LIKE LOWER(?) THEN 100
                        WHEN LOWER(COALESCE(specialties.sname, '')) LIKE LOWER(?) THEN 90
                        WHEN LOWER(COALESCE(doctor.clinic_name, '')) LIKE LOWER(?) THEN 80
                        WHEN LOWER(doctor.docname) LIKE LOWER(?) THEN 60
                        WHEN LOWER(COALESCE(specialties.sname, '')) LIKE LOWER(?) THEN 50
                        WHEN LOWER(COALESCE(doctor.clinic_name, '')) LIKE LOWER(?) THEN 40
                        WHEN LOWER(doctor.docemail) LIKE LOWER(?) THEN 20
                        ELSE 0
                    END AS live_search_rank
             FROM doctor
             LEFT JOIN specialties ON doctor.specialties = specialties.id
             WHERE doctor.verification_status = 'approved'
               AND (
                    LOWER(doctor.docname) LIKE LOWER(?)
                    OR LOWER(COALESCE(specialties.sname, '')) LIKE LOWER(?)
                    OR LOWER(COALESCE(doctor.clinic_name, '')) LIKE LOWER(?)
                    OR LOWER(doctor.docemail) LIKE LOWER(?)
               )
             ORDER BY live_search_rank DESC, doctor.docname ASC
             LIMIT " . $limit
        );
        $stmt->bind_param(
            "sssssssssss",
            $prefixPattern,
            $prefixPattern,
            $prefixPattern,
            $partialPattern,
            $partialPattern,
            $partialPattern,
            $partialPattern,
            $partialPattern,
            $partialPattern,
            $partialPattern,
            $partialPattern
        );
        doc_sathi_execute($stmt);
    }

    $doctors = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }

    return $doctors;
}

function doc_sathi_get_patient_doctor_preference_scores($database, $patientId)
{
    $patientId = (int)$patientId;

    if ($patientId <= 0) {
        return [
            'doctor_counts' => [],
            'specialty_counts' => [],
            'total_history' => 0,
        ];
    }

    $stmt = doc_sathi_prepare(
        $database,
        "SELECT doctor.docid,
                doctor.specialties AS specialty_id,
                COUNT(appointment.appoid) AS total_bookings
         FROM appointment
         INNER JOIN schedule ON schedule.scheduleid = appointment.scheduleid
         INNER JOIN doctor ON doctor.docid = schedule.docid
         WHERE appointment.pid = ?
           AND COALESCE(appointment.status, 'confirmed') <> 'cancelled'
         GROUP BY doctor.docid, doctor.specialties"
    );
    $stmt->bind_param("i", $patientId);
    doc_sathi_execute($stmt);

    $doctorCounts = [];
    $specialtyCounts = [];
    $totalHistory = 0;
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $doctorId = (int)($row['docid'] ?? 0);
        $specialtyId = (int)($row['specialty_id'] ?? 0);
        $bookings = max(0, (int)($row['total_bookings'] ?? 0));

        if ($doctorId > 0 && $bookings > 0) {
            $doctorCounts[$doctorId] = ($doctorCounts[$doctorId] ?? 0) + $bookings;
            $totalHistory += $bookings;
        }

        if ($specialtyId > 0 && $bookings > 0) {
            $specialtyCounts[$specialtyId] = ($specialtyCounts[$specialtyId] ?? 0) + $bookings;
        }
    }

    arsort($doctorCounts);
    arsort($specialtyCounts);

    return [
        'doctor_counts' => $doctorCounts,
        'specialty_counts' => $specialtyCounts,
        'total_history' => $totalHistory,
    ];
}

function doc_sathi_get_global_doctor_popularity_scores($database)
{
    $stmt = doc_sathi_prepare(
        $database,
        "SELECT schedule.docid,
                COUNT(appointment.appoid) AS total_bookings
         FROM appointment
         INNER JOIN schedule ON schedule.scheduleid = appointment.scheduleid
         WHERE COALESCE(appointment.status, 'confirmed') <> 'cancelled'
         GROUP BY schedule.docid"
    );
    doc_sathi_execute($stmt);

    $doctorCounts = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $doctorId = (int)($row['docid'] ?? 0);
        $bookings = max(0, (int)($row['total_bookings'] ?? 0));

        if ($doctorId > 0 && $bookings > 0) {
            $doctorCounts[$doctorId] = $bookings;
        }
    }

    arsort($doctorCounts);

    return $doctorCounts;
}

function doc_sathi_recommendation_search_priority($searchTerm, array $values)
{
    $searchTerm = doc_sathi_normalize_text($searchTerm);

    if ($searchTerm === '') {
        return 0;
    }

    $partialMatch = false;

    foreach ($values as $value) {
        $candidate = doc_sathi_normalize_text($value);

        if ($candidate === '') {
            continue;
        }

        if ($candidate === $searchTerm) {
            return 2;
        }

        if (strpos($candidate, $searchTerm) !== false) {
            $partialMatch = true;
        }
    }

    return $partialMatch ? 1 : 0;
}

function doc_sathi_specialty_relevance_score($searchTerm, $specialtyName)
{
    $searchTerm = doc_sathi_normalize_text($searchTerm);
    $specialtyName = doc_sathi_normalize_text($specialtyName);

    if ($searchTerm === '' || $specialtyName === '') {
        return 0.0;
    }

    if ($specialtyName === $searchTerm) {
        return 1.0;
    }

    if (strpos($specialtyName, $searchTerm) !== false || strpos($searchTerm, $specialtyName) !== false) {
        return 0.8;
    }

    return 0.0;
}

function doc_sathi_recommendation_reference_timestamp($reference = null)
{
    if (is_numeric($reference)) {
        return (int)$reference;
    }

    if ($reference instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($reference)
            ->setTimezone(new DateTimeZone('Asia/Kathmandu'))
            ->getTimestamp();
    }

    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kathmandu')))->getTimestamp();
}

function doc_sathi_session_timestamp($scheduledate, $scheduletime)
{
    $sessionDateTime = doc_sathi_session_datetime($scheduledate, $scheduletime);

    return $sessionDateTime ? $sessionDateTime->getTimestamp() : null;
}

function doc_sathi_recommendation_time_score($sessionTimestamp, $reference = null)
{
    $sessionTimestamp = $sessionTimestamp === null ? null : (int)$sessionTimestamp;

    if ($sessionTimestamp === null) {
        return 0.0;
    }

    $referenceTimestamp = doc_sathi_recommendation_reference_timestamp($reference);

    if ($sessionTimestamp < $referenceTimestamp) {
        return 0.0;
    }

    $hoursUntilSession = ($sessionTimestamp - $referenceTimestamp) / 3600;

    return 1 / (1 + ($hoursUntilSession / 72));
}

function doc_sathi_recommendation_relative_availability($remaining, $maxRemaining)
{
    $remaining = max(0, (int)$remaining);
    $maxRemaining = max(0, (int)$maxRemaining);

    if ($maxRemaining <= 0) {
        return 0.0;
    }

    return min(1.0, $remaining / $maxRemaining);
}

function doc_sathi_recommendation_workload_score($booked, $capacity)
{
    $booked = max(0, (int)$booked);
    $capacity = max(0, (int)$capacity);

    if ($capacity <= 0) {
        return 0.0;
    }

    $loadRatio = min(1.0, $booked / $capacity);

    return max(0.0, 1.0 - $loadRatio);
}

function doc_sathi_recommendation_score($specialtyScore, $earliestSessionScore, $availabilityScore, $workloadScore)
{
    // Specialty relevance carries the highest weight. Time to the next bookable
    // session is next, followed by open capacity and inverse workload.
    return
        ($specialtyScore * DOC_SATHI_RECOMMENDATION_WEIGHT_SPECIALTY) +
        ($earliestSessionScore * DOC_SATHI_RECOMMENDATION_WEIGHT_EARLIEST_SESSION) +
        ($availabilityScore * DOC_SATHI_RECOMMENDATION_WEIGHT_AVAILABILITY) +
        ($workloadScore * DOC_SATHI_RECOMMENDATION_WEIGHT_WORKLOAD);
}

function doc_sathi_fetch_doctor_schedule_metrics($database, array $doctorIds)
{
    $filteredDoctorIds = [];
    foreach ($doctorIds as $doctorId) {
        $doctorId = (int)$doctorId;
        if ($doctorId > 0) {
            $filteredDoctorIds[] = $doctorId;
        }
    }

    $filteredDoctorIds = array_values(array_unique($filteredDoctorIds));
    if (empty($filteredDoctorIds)) {
        return [];
    }

    $metrics = [];
    foreach ($filteredDoctorIds as $doctorId) {
        $metrics[$doctorId] = [
            'total_capacity' => 0,
            'total_booked' => 0,
            'total_remaining' => 0,
            'earliest_available_session_ts' => null,
        ];
    }

    $currentMoment = new DateTimeImmutable('now', new DateTimeZone('Asia/Kathmandu'));
    $today = $currentMoment->format('Y-m-d');
    $currentTime = $currentMoment->format('H:i:s');

    $placeholders = implode(',', array_fill(0, count($filteredDoctorIds), '?'));
    $sql = "SELECT s.scheduleid,
                   s.docid,
                   s.scheduledate,
                   s.scheduletime,
                   COALESCE(s.nop, 0) AS nop,
                   COUNT(a.appoid) AS booked_count
            FROM schedule s
            LEFT JOIN appointment a
              ON a.scheduleid = s.scheduleid
             AND COALESCE(a.status, 'confirmed') <> 'cancelled'
            WHERE s.docid IN (" . $placeholders . ")
              AND (
                  s.scheduledate > ?
                  OR (s.scheduledate = ? AND s.scheduletime >= ?)
              )
            GROUP BY s.scheduleid, s.docid, s.scheduledate, s.scheduletime, s.nop";

    $stmt = doc_sathi_prepare($database, $sql);
    $params = $filteredDoctorIds;
    $params[] = $today;
    $params[] = $today;
    $params[] = $currentTime;
    doc_sathi_bind_dynamic_params($stmt, str_repeat('i', count($filteredDoctorIds)) . 'sss', $params);
    doc_sathi_execute($stmt);

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sessionTimestamp = doc_sathi_session_timestamp($row['scheduledate'], $row['scheduletime']);
        if ($sessionTimestamp === null) {
            continue;
        }

        $doctorId = (int)$row['docid'];
        if (!isset($metrics[$doctorId])) {
            continue;
        }

        $capacity = max(0, (int)$row['nop']);
        $booked = max(0, (int)$row['booked_count']);
        $remaining = max(0, $capacity - $booked);

        $metrics[$doctorId]['total_capacity'] += $capacity;
        $metrics[$doctorId]['total_booked'] += $booked;
        $metrics[$doctorId]['total_remaining'] += $remaining;

        if (
            $remaining > 0 &&
            $sessionTimestamp !== null &&
            (
                $metrics[$doctorId]['earliest_available_session_ts'] === null ||
                $sessionTimestamp < $metrics[$doctorId]['earliest_available_session_ts']
            )
        ) {
            $metrics[$doctorId]['earliest_available_session_ts'] = $sessionTimestamp;
        }
    }

    return $metrics;
}

function doc_sathi_compare_doctors_for_recommendation(array $left, array $right)
{
    $leftPriority = (int)($left['recommendation_search_priority'] ?? 0);
    $rightPriority = (int)($right['recommendation_search_priority'] ?? 0);
    if ($leftPriority !== $rightPriority) {
        return $rightPriority <=> $leftPriority;
    }

    $leftScore = (float)($left['recommendation_score'] ?? 0);
    $rightScore = (float)($right['recommendation_score'] ?? 0);
    if ($leftScore !== $rightScore) {
        return $rightScore <=> $leftScore;
    }

    $leftTs = $left['recommendation_metrics']['earliest_available_session_ts'] ?? null;
    $rightTs = $right['recommendation_metrics']['earliest_available_session_ts'] ?? null;

    if ($leftTs === null && $rightTs !== null) {
        return 1;
    }

    if ($leftTs !== null && $rightTs === null) {
        return -1;
    }

    if ($leftTs !== null && $rightTs !== null && (int)$leftTs !== (int)$rightTs) {
        return (int)$leftTs <=> (int)$rightTs;
    }

    $leftRemaining = (int)($left['recommendation_metrics']['total_remaining'] ?? 0);
    $rightRemaining = (int)($right['recommendation_metrics']['total_remaining'] ?? 0);
    if ($leftRemaining !== $rightRemaining) {
        return $rightRemaining <=> $leftRemaining;
    }

    $nameCompare = strcasecmp((string)($left['docname'] ?? ''), (string)($right['docname'] ?? ''));
    if ($nameCompare !== 0) {
        return $nameCompare;
    }

    return (int)($left['docid'] ?? 0) <=> (int)($right['docid'] ?? 0);
}

function doc_sathi_rank_doctors($database, array $doctors, $searchTerm = '')
{
    if (empty($doctors)) {
        return [];
    }

    $doctorIds = [];
    foreach ($doctors as $doctor) {
        $doctorIds[] = (int)($doctor['docid'] ?? 0);
    }

    $metricsByDoctor = doc_sathi_fetch_doctor_schedule_metrics($database, $doctorIds);
    $maxRemaining = 0;
    foreach ($metricsByDoctor as $metrics) {
        if ((int)$metrics['total_remaining'] > $maxRemaining) {
            $maxRemaining = (int)$metrics['total_remaining'];
        }
    }

    $referenceTimestamp = doc_sathi_recommendation_reference_timestamp();

    foreach ($doctors as $index => $doctor) {
        $doctorId = (int)($doctor['docid'] ?? 0);
        $metrics = $metricsByDoctor[$doctorId] ?? [
            'total_capacity' => 0,
            'total_booked' => 0,
            'total_remaining' => 0,
            'earliest_available_session_ts' => null,
        ];

        $specialtyName = trim((string)($doctor['specialty_name'] ?? ''));
        $specialtyScore = doc_sathi_specialty_relevance_score($searchTerm, $specialtyName);
        $timeScore = doc_sathi_recommendation_time_score($metrics['earliest_available_session_ts'], $referenceTimestamp);
        $availabilityScore = doc_sathi_recommendation_relative_availability($metrics['total_remaining'], $maxRemaining);
        $workloadScore = doc_sathi_recommendation_workload_score($metrics['total_booked'], $metrics['total_capacity']);

        $doctors[$index]['recommendation_search_priority'] = doc_sathi_recommendation_search_priority(
            $searchTerm,
            [$doctor['docname'] ?? '', $doctor['docemail'] ?? '', $specialtyName]
        );
        $doctors[$index]['recommendation_score'] = round(
            doc_sathi_recommendation_score($specialtyScore, $timeScore, $availabilityScore, $workloadScore),
            4
        );
        $doctors[$index]['recommendation_metrics'] = [
            'specialty_score' => $specialtyScore,
            'time_score' => $timeScore,
            'availability_score' => $availabilityScore,
            'workload_score' => $workloadScore,
            'total_capacity' => (int)$metrics['total_capacity'],
            'total_booked' => (int)$metrics['total_booked'],
            'total_remaining' => (int)$metrics['total_remaining'],
            'earliest_available_session_ts' => $metrics['earliest_available_session_ts'],
        ];
    }

    usort($doctors, 'doc_sathi_compare_doctors_for_recommendation');

    return $doctors;
}

function doc_sathi_patient_recommendation_reason(array $doctor, $doctorRepeat, $specialtyRepeat, $globalPopularity, $hasPatientHistory)
{
    $doctorRepeat = (int)$doctorRepeat;
    $specialtyRepeat = (int)$specialtyRepeat;
    $globalPopularity = (int)$globalPopularity;
    $specialtyName = trim((string)($doctor['specialty_name'] ?? ''));
    $specialtyLabel = $specialtyName !== '' ? $specialtyName : 'this specialty';

    if ($doctorRepeat >= 2) {
        return 'You booked this doctor ' . $doctorRepeat . ' times';
    }

    if ($doctorRepeat === 1) {
        return 'You booked this doctor before';
    }

    if ($specialtyRepeat >= 2) {
        return 'You often book ' . $specialtyLabel;
    }

    if ($specialtyRepeat === 1) {
        return 'Matches your past ' . $specialtyLabel . ' booking';
    }

    if (!$hasPatientHistory && $globalPopularity > 0) {
        return 'Popular with patients';
    }

    return 'Available approved doctor';
}

function doc_sathi_compare_patient_recommended_doctors(array $left, array $right)
{
    $leftScore = (float)($left['recommendation_score'] ?? 0);
    $rightScore = (float)($right['recommendation_score'] ?? 0);
    if ($leftScore !== $rightScore) {
        return $rightScore <=> $leftScore;
    }

    $leftDoctorRepeat = (int)($left['recommendation_metrics']['doctor_repeat_score'] ?? 0);
    $rightDoctorRepeat = (int)($right['recommendation_metrics']['doctor_repeat_score'] ?? 0);
    if ($leftDoctorRepeat !== $rightDoctorRepeat) {
        return $rightDoctorRepeat <=> $leftDoctorRepeat;
    }

    $leftSpecialtyRepeat = (int)($left['recommendation_metrics']['specialty_repeat_score'] ?? 0);
    $rightSpecialtyRepeat = (int)($right['recommendation_metrics']['specialty_repeat_score'] ?? 0);
    if ($leftSpecialtyRepeat !== $rightSpecialtyRepeat) {
        return $rightSpecialtyRepeat <=> $leftSpecialtyRepeat;
    }

    $leftTs = $left['recommendation_metrics']['earliest_available_session_ts'] ?? null;
    $rightTs = $right['recommendation_metrics']['earliest_available_session_ts'] ?? null;

    if ($leftTs === null && $rightTs !== null) {
        return 1;
    }

    if ($leftTs !== null && $rightTs === null) {
        return -1;
    }

    if ($leftTs !== null && $rightTs !== null && (int)$leftTs !== (int)$rightTs) {
        return (int)$leftTs <=> (int)$rightTs;
    }

    return strcasecmp((string)($left['docname'] ?? ''), (string)($right['docname'] ?? ''));
}

function doc_sathi_get_recommended_doctors_for_patient($database, $patientId, $limit = 5)
{
    $patientId = (int)$patientId;
    $limit = max(1, min(12, (int)$limit));

    $doctors = doc_sathi_search_doctors($database, '', 200);
    if (empty($doctors)) {
        return [];
    }

    $doctorIds = [];
    foreach ($doctors as $doctor) {
        $doctorIds[] = (int)($doctor['docid'] ?? 0);
    }

    $patientPreferences = doc_sathi_get_patient_doctor_preference_scores($database, $patientId);
    $hasPatientHistory = (int)($patientPreferences['total_history'] ?? 0) > 0;
    $globalDoctorCounts = $hasPatientHistory ? [] : doc_sathi_get_global_doctor_popularity_scores($database);
    $metricsByDoctor = doc_sathi_fetch_doctor_schedule_metrics($database, $doctorIds);
    $maxRemaining = 0;

    foreach ($metricsByDoctor as $metrics) {
        $maxRemaining = max($maxRemaining, (int)($metrics['total_remaining'] ?? 0));
    }

    $referenceTimestamp = doc_sathi_recommendation_reference_timestamp();
    $doctorCounts = $patientPreferences['doctor_counts'] ?? [];
    $specialtyCounts = $patientPreferences['specialty_counts'] ?? [];

    foreach ($doctors as $index => $doctor) {
        $doctorId = (int)($doctor['docid'] ?? 0);
        $specialtyId = (int)($doctor['specialties'] ?? 0);
        $doctorRepeatScore = (int)($doctorCounts[$doctorId] ?? 0);
        $specialtyRepeatScore = $specialtyId > 0 ? (int)($specialtyCounts[$specialtyId] ?? 0) : 0;
        $globalPopularityScore = (int)($globalDoctorCounts[$doctorId] ?? 0);
        $metrics = $metricsByDoctor[$doctorId] ?? [
            'total_capacity' => 0,
            'total_booked' => 0,
            'total_remaining' => 0,
            'earliest_available_session_ts' => null,
        ];

        $timeScore = doc_sathi_recommendation_time_score($metrics['earliest_available_session_ts'], $referenceTimestamp);
        $availabilityScore = doc_sathi_recommendation_relative_availability($metrics['total_remaining'], $maxRemaining);
        $workloadScore = doc_sathi_recommendation_workload_score($metrics['total_booked'], $metrics['total_capacity']);
        $score =
            ($doctorRepeatScore * DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_DOCTOR_REPEAT) +
            ($specialtyRepeatScore * DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_SPECIALTY_REPEAT) +
            ($globalPopularityScore * DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_GLOBAL_POPULARITY) +
            ($timeScore * DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_EARLIEST_SESSION) +
            ($availabilityScore * DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_AVAILABILITY) +
            ($workloadScore * DOC_SATHI_PATIENT_RECOMMENDATION_WEIGHT_WORKLOAD);

        $doctors[$index]['recommendation_score'] = round($score, 4);
        $doctors[$index]['recommendation_reason'] = doc_sathi_patient_recommendation_reason(
            $doctor,
            $doctorRepeatScore,
            $specialtyRepeatScore,
            $globalPopularityScore,
            $hasPatientHistory
        );
        $doctors[$index]['recommendation_metrics'] = [
            'doctor_repeat_score' => $doctorRepeatScore,
            'specialty_repeat_score' => $specialtyRepeatScore,
            'global_popularity_score' => $globalPopularityScore,
            'time_score' => $timeScore,
            'availability_score' => $availabilityScore,
            'workload_score' => $workloadScore,
            'total_capacity' => (int)$metrics['total_capacity'],
            'total_booked' => (int)$metrics['total_booked'],
            'total_remaining' => (int)$metrics['total_remaining'],
            'earliest_available_session_ts' => $metrics['earliest_available_session_ts'],
            'has_patient_history' => $hasPatientHistory,
        ];
    }

    usort($doctors, 'doc_sathi_compare_patient_recommended_doctors');

    return array_slice($doctors, 0, $limit);
}

function doc_sathi_compare_sessions_for_recommendation(array $left, array $right)
{
    $leftPriority = (int)($left['recommendation_search_priority'] ?? 0);
    $rightPriority = (int)($right['recommendation_search_priority'] ?? 0);
    if ($leftPriority !== $rightPriority) {
        return $rightPriority <=> $leftPriority;
    }

    $leftScore = (float)($left['recommendation_score'] ?? 0);
    $rightScore = (float)($right['recommendation_score'] ?? 0);
    if ($leftScore !== $rightScore) {
        return $rightScore <=> $leftScore;
    }

    $leftTs = $left['recommendation_metrics']['session_timestamp'] ?? null;
    $rightTs = $right['recommendation_metrics']['session_timestamp'] ?? null;

    if ($leftTs === null && $rightTs !== null) {
        return 1;
    }

    if ($leftTs !== null && $rightTs === null) {
        return -1;
    }

    if ($leftTs !== null && $rightTs !== null && (int)$leftTs !== (int)$rightTs) {
        return (int)$leftTs <=> (int)$rightTs;
    }

    $leftRemaining = (int)($left['remaining_slots'] ?? 0);
    $rightRemaining = (int)($right['remaining_slots'] ?? 0);
    if ($leftRemaining !== $rightRemaining) {
        return $rightRemaining <=> $leftRemaining;
    }

    $titleCompare = strcasecmp((string)($left['title'] ?? ''), (string)($right['title'] ?? ''));
    if ($titleCompare !== 0) {
        return $titleCompare;
    }

    return (int)($left['scheduleid'] ?? 0) <=> (int)($right['scheduleid'] ?? 0);
}

function doc_sathi_rank_sessions(array $sessions, $searchTerm = '')
{
    if (empty($sessions)) {
        return [];
    }

    $maxRemaining = 0;
    foreach ($sessions as $session) {
        $capacity = max(0, (int)($session['nop'] ?? 0));
        $booked = max(0, (int)($session['booked_count'] ?? 0));
        $remaining = max(0, $capacity - $booked);

        if ($remaining > $maxRemaining) {
            $maxRemaining = $remaining;
        }
    }

    $referenceTimestamp = doc_sathi_recommendation_reference_timestamp();

    foreach ($sessions as $index => $session) {
        $capacity = max(0, (int)($session['nop'] ?? 0));
        $booked = max(0, (int)($session['booked_count'] ?? 0));
        $remaining = max(0, $capacity - $booked);
        $sessionTimestamp = doc_sathi_session_timestamp($session['scheduledate'] ?? '', $session['scheduletime'] ?? '');
        $specialtyName = trim((string)($session['specialty_name'] ?? ''));
        $timeScore = $remaining > 0 ? doc_sathi_recommendation_time_score($sessionTimestamp, $referenceTimestamp) : 0.0;
        $specialtyScore = doc_sathi_specialty_relevance_score($searchTerm, $specialtyName);
        $availabilityScore = doc_sathi_recommendation_relative_availability($remaining, $maxRemaining);
        $workloadScore = doc_sathi_recommendation_workload_score($booked, $capacity);

        $sessions[$index]['booked_count'] = $booked;
        $sessions[$index]['remaining_slots'] = $remaining;
        $sessions[$index]['recommendation_search_priority'] = doc_sathi_recommendation_search_priority(
            $searchTerm,
            [
                $session['docname'] ?? '',
                $session['docemail'] ?? '',
                $session['title'] ?? '',
                $session['scheduledate'] ?? '',
                $specialtyName,
            ]
        );
        $sessions[$index]['recommendation_score'] = round(
            doc_sathi_recommendation_score($specialtyScore, $timeScore, $availabilityScore, $workloadScore),
            4
        );
        $sessions[$index]['recommendation_metrics'] = [
            'specialty_score' => $specialtyScore,
            'time_score' => $timeScore,
            'availability_score' => $availabilityScore,
            'workload_score' => $workloadScore,
            'session_timestamp' => $sessionTimestamp,
        ];
    }

    usort($sessions, 'doc_sathi_compare_sessions_for_recommendation');

    return $sessions;
}
?>
