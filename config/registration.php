<?php

// Transcribed from the supplied 2027 timetable JPEGs and Tuition fees.jpeg.
$meeting = static fn ($day, $time, $start, $note = null) => compact('day', 'time', 'start', 'note');
$course = static fn ($name, ...$meetings) => ['name' => $name, 'options' => [
    ['id' => 'standard', 'label' => null, 'meetings' => $meetings],
]];
$batches = static fn ($name, ...$meetings) => ['name' => $name, 'options' => array_map(
    static fn ($item, $index) => ['id' => 'batch-'.($index + 1), 'label' => 'Batch '.($index + 1), 'meetings' => [$item]],
    $meetings, array_keys($meetings)
)];

return [
    'mail_enabled' => env('REGISTRATION_MAIL_ENABLED', false),
    'mail_timeout' => env('REGISTRATION_MAIL_TIMEOUT', 15),
    'mail_from_address' => env('REGISTRATION_MAIL_FROM_ADDRESS', 'info@adityaclasses.mu'),
    'mail_from_name' => 'Aditya Classes',
    'payment_number' => env('REGISTRATION_PAYMENT_NUMBER', '59473797'),
    'payment_recipient' => env('REGISTRATION_PAYMENT_RECIPIENT', 'Aditya Classes'),
    'catalogue' => [
        'Grade 7' => ['fee' => 400, 'form' => 'Form 1', 'courses' => [
            'english' => $course('English', $meeting('Tuesday', '3:30–4:15 pm', '12 January 2027')),
            'french' => $course('French', $meeting('Tuesday', '4:15–5:00 pm', '12 January 2027')),
            'mathematics' => $course('Mathematics', $meeting('Saturday', '8:00–10:00 am', '9 January 2027')),
        ]],
        'Grade 8' => ['fee' => 500, 'form' => 'Form 2', 'courses' => [
            'english' => $course('English', $meeting('Saturday', '1:15–2:15 pm', '9 January 2027')),
            'french' => $course('French', $meeting('Saturday', '2:15–3:15 pm', '9 January 2027')),
            'mathematics' => $batches('Mathematics', $meeting('Saturday', '3:30–5:00 pm', '9 January 2027'), $meeting('Wednesday', '3:30–5:00 pm', '6 January 2027')),
        ]],
        'Grade 9' => ['fee' => 600, 'form' => 'Form 3', 'courses' => [
            'mathematics' => $batches('Mathematics', $meeting('Friday', '3:30–5:00 pm', '8 January 2027'), $meeting('Saturday', '9:00–10:45 am', '9 January 2027')),
            'english' => $batches('English', $meeting('Monday', '3:30–4:50 pm', '11 January 2027'), $meeting('Saturday', '1:30–2:45 pm', '9 January 2027')),
            'french' => $batches('French', $meeting('Wednesday', '3:30–4:50 pm', '6 January 2027'), $meeting('Saturday', '3:00–4:30 pm', '9 January 2027')),
            'science' => $course('Science', $meeting('Sunday', '8:00–10:00 am', '10 January 2027')),
            'bee' => $course('BEE', $meeting('Saturday', '11:00 am–1:00 pm', '9 January 2027')),
        ]],
        'Grade 10' => ['fee' => 700, 'form' => 'Form 4', 'courses' => [
            'mathematics' => $batches('Mathematics', $meeting('Wednesday', '3:30–5:00 pm', '6 January 2027'), $meeting('Saturday', '11:00 am–12:30 pm', '9 January 2027')),
            'additional-mathematics' => $batches('Additional Mathematics', $meeting('Sunday', '8:00–9:45 am', '10 January 2027'), $meeting('Thursday', '3:30–5:10 pm', '7 January 2027')),
            'accounts' => $batches('Accounts', $meeting('Tuesday', '3:30–5:00 pm', '12 January 2027'), $meeting('Sunday', '10:00–11:45 am', '10 January 2027')),
            'english' => $course('English', $meeting('Saturday', '8:20–9:30 am', '9 January 2027')),
            'french' => $course('French', $meeting('Saturday', '9:40–10:45 am', '9 January 2027')),
            'economics' => $course('Economics', $meeting('Monday', '3:30–5:00 pm', '11 January 2027')),
            'business-studies' => $course('Business Studies', $meeting('Sunday', '12:15–1:30 pm', '10 January 2027')),
            'physics' => $course('Physics', $meeting('Sunday', '10:00 am–12:00 pm', '10 January 2027')),
            'chemistry' => $course('Chemistry', $meeting('Saturday', '11:00 am–12:30 pm', '9 January 2027')),
        ]],
        'Grade 11' => ['fee' => 800, 'form' => 'Form 5', 'courses' => [
            'english' => $course('English', $meeting('Saturday', '11:00 am–12:05 pm', '9 January 2027')),
            'french' => $course('French', $meeting('Saturday', '12:10–1:15 pm', '9 January 2027')),
            'mathematics' => $batches('Mathematics', $meeting('Saturday', '9:00–10:45 am', '9 January 2027'), $meeting('Tuesday', '3:30–5:10 pm', '12 January 2027')),
            'additional-mathematics' => $batches('Additional Mathematics', $meeting('Sunday', '10:00–11:50 am', '10 January 2027'), $meeting('Friday', '3:30–5:30 pm', '8 January 2027')),
            'accounts' => $batches('Accounts', $meeting('Thursday', '3:30–5:00 pm', '7 January 2027'), $meeting('Sunday', '8:00–9:45 am', '10 January 2027')),
            'economics' => $course('Economics', $meeting('Saturday', '7:30–9:00 am', '9 January 2027')),
            'business-studies' => $course('Business Studies', $meeting('Sunday', '1:30–3:00 pm', '10 January 2027')),
            'physics' => $course('Physics', $meeting('Saturday', '1:15–2:45 pm', '9 January 2027')),
            'chemistry' => $course('Chemistry', $meeting('Saturday', '3:00–4:30 pm', '9 January 2027')),
        ]],
        'Grade 12' => ['fee' => 1000, 'form' => 'Lower six', 'courses' => [
            'egp' => $course('English General Paper', $meeting('Friday', '3:30–4:50 pm', '8 January 2027')),
            'mathematics-main' => $course('Mathematics (Main)', $meeting('Saturday', '11:00 am–12:45 pm', '9 January 2027', 'Pure Maths · P1 & P3')),
            'mathematics-sub' => $course('Mathematics (Sub)', $meeting('Saturday', '2:00–3:30 pm', '9 January 2027', 'P1 & P2')),
            'economics' => $course('Economics (Main)', $meeting('Friday', '5:10–6:40 pm', '8 January 2027', 'P1 & P2')),
            'accounts' => $course('Accounts (Main)', $meeting('Saturday', '1:00–2:50 pm', '9 January 2027', 'P1 & P2')),
            'business' => $course('Business (Main)', $meeting('Wednesday', '5:10–6:40 pm', '6 January 2027', 'P1 & P2')),
        ]],
        'Grade 13' => ['fee' => 1100, 'form' => 'Upper six', 'courses' => [
            'egp' => $course('English General Paper', $meeting('Friday', '3:30–4:50 pm', '8 January 2027')),
            'mathematics-main' => $course('Mathematics (Main)',
                $meeting('Saturday', '3:00–4:30 pm', '9 January 2027', 'Statistics · S1 & S2'),
                $meeting('Monday', '5:00–6:30 pm', '12 April 2027', 'Mechanics · M1'),
                $meeting('Monday', '3:30–5:00 pm', '11 January 2027', 'Pure Maths · P1 & P3 revision')),
            'mathematics-sub' => $course('Mathematics (Sub)', $meeting('Saturday', '2:00–3:30 pm', '9 January 2027', 'P1 & P2')),
            'economics' => $course('Economics (Main)', $meeting('Thursday', '5:10–6:40 pm', '7 January 2027', 'P3 & P4')),
            'accounts' => $course('Accounts (Main)', $meeting('Saturday', '4:30–6:00 pm', '9 January 2027', 'P3 & P4')),
            'business' => $course('Business (Main)', $meeting('Tuesday', '5:10–6:40 pm', '12 January 2027', 'P3 & P4')),
        ]],
    ],
];
