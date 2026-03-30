<?php

declare(strict_types=1);

// Start user session
session_name('user_session');
session_start();

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/../config/env.php";

// Load .env file
load_env(__DIR__ . "/../.env");

// Set local time and JSON response
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

// Check if logged in as user
$isUser  = isset($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'user');
$user_id = $isUser ? (int) $_SESSION['user_id'] : 0;

// Read JSON request body
$raw  = file_get_contents("php://input");
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

// Get message and extra data
$userMsg = trim((string)($body['message'] ?? ''));
$meta    = $body['meta'] ?? [];
if (!is_array($meta)) {
    $meta = [];
}

$metaSource = trim((string)($meta['source'] ?? 'typed'));
if ($metaSource === '') {
    $metaSource = 'typed';
}

// Message for out-of-scope questions
const OUT_OF_SCOPE_REPLY =
"Paumanhin, ang inyong katanungan ay hindi sakop ng kasalukuyang FAQ database ng system. " .
    "Maaaring makipag-ugnayan sa pinakamalapit na PhilHealth Office o bisitahin ang opisyal na " .
    "website ng PhilHealth para sa mas detalyadong impormasyon.";

// Signal from AI for out-of-scope
const OUT_OF_SCOPE_SIGNAL = "OUT_OF_SCOPE";

// If message is empty
if ($userMsg === '') {
    http_response_code(400);
    echo json_encode([
        "response"      => "Hi! Maaari kayong mag-type ng tanong o pumili mula sa quick options sa ibaba.",
        "node_id"       => "root",
        "quick_replies" => quickReplies("root", $isUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
   Check if message is related to PhilHealth or the system
   ========================================================= */
function isPhilhealthRelated(string $text): bool
{
    $t = mb_strtolower($text);

    // Simple navigation words
    $nav = [
        'menu',
        'main menu',
        'back',
        'login',
        'register',
        'sign in',
        'book appointment',
        'service requirements',
        'queue status',
        'talk to agent',
        'magpatuloy',
        'tapusin'
    ];
    if (in_array(trim($t), $nav, true)) {
        return true;
    }

    // Related to PhilHealth and the appointment system
    $keywords = [
        'philhealth',
        'phic',
        'yakap',
        'konsulta',
        'benefit',
        'benefits',
        'benepisyo',
        'benepisyong pangkalusugan',
        'health benefit',
        'membership',
        'miyembro',
        'premium',
        'contribution',
        'hulog',
        'appointment',
        'book',
        'booking',
        'schedule',
        'petsa',
        'oras',
        'queue',
        'kiosk',
        'check in',
        'check-in',
        'counter',
        'serving',
        'done',
        'requirement',
        'requirements',
        'document',
        'documents',
        'id',
        'pin',
        'mpr',
        'dependent',
        'principal',
        'employed',
        'voluntary',
        'indigent',
        'senior',
        'pwd',
        'claims',
        'z benefits',
        'z benefit',
        'gamot',
        'oecb',
        'lhio',
        'nhip',
        'uhc',
        'ra 11223',
        'user login',
        'register',
        'account',
        'username',
        'password',
        'portal',
        'system',
        'platform',
        'website',
        'online',
        'walk-in',
        'pcu',
        'upsc',
        'pcc',
        'mdr',
        'pmrf',
        'pan',
        'acr',
        'hci',
        'fpe',
        'e.e.c.l',
        'eekl',
        'gamot availment slip',
        'gas',
        'outpatient emergency care benefit',
        'emergency care',
        'inpatient',
        'outpatient',
        'registration',
        'informal economy',
        'self-employed',
        'self employed',
        'step-by-step',
        'procedure',
        'proseso',
        'pagkuha ng appointment'
    ];

    foreach ($keywords as $kw) {
        if ($kw !== '' && str_contains($t, $kw)) {
            return true;
        }
    }

    return false;
}

/* =========================================================
   FAQ loading and matching
   ========================================================= */
function normalizeText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', trim($text));
    return $text ?? '';
}

function loadFAQ(): array
{
    $faqFile = __DIR__ . '/../FAQ/augmented_faq.jsonl';

    if (!file_exists($faqFile)) {
        error_log("FAQ file not found: $faqFile");
        return [];
    }

    $faq = [];
    $handle = fopen($faqFile, 'r');

    if (!$handle) {
        error_log("Unable to open FAQ file: $faqFile");
        return [];
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $data = json_decode($line, true);
        if (!is_array($data) || !isset($data['messages']) || !is_array($data['messages'])) {
            continue;
        }

        $question = '';
        $answer   = '';

        foreach ($data['messages'] as $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $role    = (string)($msg['role'] ?? '');
            $content = trim((string)($msg['content'] ?? ''));

            if ($role === 'user' && $question === '') {
                $question = $content;
            }

            if ($role === 'assistant' && $answer === '') {
                $answer = $content;
            }
        }

        if ($question !== '' && $answer !== '') {
            $faq[] = [
                'question' => normalizeText($question),
                'answer'   => $answer,
            ];
        }
    }

    fclose($handle);
    return $faq;
}

function findBestFAQ(string $userMsg, array $faq, float $threshold = 0.25): ?array
{
    if (empty($faq)) {
        return null;
    }

    $userMsg = normalizeText($userMsg);
    $userWords = array_values(array_filter(explode(' ', $userMsg)));

    if (empty($userWords)) {
        return null;
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($faq as $item) {
        $question = normalizeText((string)$item['question']);
        $qWords = array_values(array_filter(explode(' ', $question)));

        if (empty($qWords)) {
            continue;
        }

        $common = count(array_intersect($userWords, $qWords));
        $score  = $common / max(1, count($qWords));

        if (str_contains($question, $userMsg)) {
            $score += 0.20;
        }

        if (str_contains($userMsg, $question)) {
            $score += 0.15;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = (string)$item['answer'];
        }
    }

    if ($best !== null && $bestScore >= $threshold) {
        return [
            'answer' => $best,
            'confidence' => $bestScore
        ];
    }

    return null;
}

/* =========================================================
   Requirements loading and matching
   ========================================================= */
function loadRequirements(): array
{
    $reqFile = __DIR__ . '/../config/appointment_requirements.php';

    if (file_exists($reqFile)) {
        $data = require $reqFile;
        return is_array($data) ? $data : [];
    }

    error_log("Appointment requirements file not found: $reqFile");
    return [];
}

function findRequirements(string $userMsg, array $requirements): ?array
{
    $userMsgLower = mb_strtolower($userMsg);
    $bestMatch = null;
    $bestScore = 0;

    foreach ($requirements as $key => $item) {
        if (!is_array($item)) {
            continue;
        }

        if (isset($item['keywords']) && is_array($item['keywords'])) {
            foreach ($item['keywords'] as $kw) {
                $kw = (string)$kw;

                if ($kw !== '' && str_contains($userMsgLower, mb_strtolower($kw))) {
                    $score = strlen($kw);

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = ['type' => $key, 'item' => $item];
                    }
                }
            }
        }

        if (isset($item['categories']) && is_array($item['categories'])) {
            foreach ($item['categories'] as $catKey => $category) {
                if (!is_array($category)) {
                    continue;
                }

                foreach (($category['keywords'] ?? []) as $kw) {
                    $kw = (string)$kw;

                    if ($kw !== '' && str_contains($userMsgLower, mb_strtolower($kw))) {
                        $score = strlen($kw) + 5;

                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestMatch = [
                                'type'          => $key,
                                'category'      => $catKey,
                                'item'          => $item,
                                'category_data' => $category
                            ];
                        }
                    }
                }

                if (isset($category['specific']) && is_array($category['specific'])) {
                    foreach ($category['specific'] as $subKey => $sub) {
                        if (!is_array($sub)) {
                            continue;
                        }

                        foreach (($sub['keywords'] ?? []) as $kw) {
                            $kw = (string)$kw;

                            if ($kw !== '' && str_contains($userMsgLower, mb_strtolower($kw))) {
                                $score = strlen($kw) + 10;

                                if ($score > $bestScore) {
                                    $bestScore = $score;
                                    $bestMatch = [
                                        'type'          => $key,
                                        'category'      => $catKey,
                                        'subcategory'   => $subKey,
                                        'item'          => $item,
                                        'category_data' => $category,
                                        'sub_data'      => $sub
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return ($bestMatch && $bestScore > 5) ? $bestMatch : null;
}

function formatRequirements(array $match, array $reqArray): string
{
    $type = (string)$match['type'];
    $item = $reqArray[$type] ?? null;

    if (!is_array($item)) {
        return OUT_OF_SCOPE_REPLY;
    }

    $lines = [];
    $lines[] = '"' . (string)($item['label'] ?? $type) . '"';

    if (isset($match['sub_data']) && is_array($match['sub_data'])) {
        $sub = $match['sub_data'];
        $lines[] = '"' . (string)($sub['label'] ?? '') . '"';

        if (isset($match['category']) && isset($item['categories'][$match['category']]['general_requirements'])) {
            $gr = $item['categories'][$match['category']]['general_requirements'];
            if (is_array($gr)) {
                $lines = array_merge($lines, $gr);
            }
        }

        if (isset($sub['requirements']) && is_array($sub['requirements'])) {
            $lines = array_merge($lines, $sub['requirements']);
        }
    } elseif (isset($match['category_data']) && is_array($match['category_data'])) {
        $cat = $match['category_data'];
        $lines[] = '"' . (string)($cat['label'] ?? '') . '"';

        if (isset($cat['requirements']) && is_array($cat['requirements'])) {
            $lines = array_merge($lines, $cat['requirements']);
        }

        if (isset($match['category']) && isset($item['categories'][$match['category']]['general_requirements'])) {
            $gr = $item['categories'][$match['category']]['general_requirements'];
            if (is_array($gr)) {
                $lines = array_merge($lines, $gr);
            }
        }
    } elseif (isset($item['requirements']) && is_array($item['requirements'])) {
        $lines = array_merge($lines, $item['requirements']);
    } else {
        $lines[] = "Please specify your category (e.g., principal member, dependent, employed private, etc.).";

        if (isset($item['categories']) && is_array($item['categories'])) {
            $lines[] = "Available categories:";
            foreach ($item['categories'] as $cat) {
                if (is_array($cat)) {
                    $lines[] = "• " . (string)($cat['label'] ?? '');
                }
            }
        }
    }

    return implode("\n", array_filter($lines, fn($x) => (string)$x !== ''));
}

/* =========================================================
   Quick replies and node checking
   ========================================================= */
function quickReplies(string $nodeId, bool $isUser): array
{
    $tree = [
        "root" => [
            ["label" => "Benepisyong Pangkalusugan", "value" => "Benepisyong Pangkalusugan", "node_id" => "benefits"],
            ["label" => "Impormasyon sa Membership", "value" => "Impormasyon sa Membership", "node_id" => "membership"],
            ["label" => "Proseso ng Pagkuha ng Appointment", "value" => "Proseso ng Pagkuha ng Appointment", "node_id" => "appointment_process"],
            ["label" => "Step-by-step procedure", "value" => "Step-by-step procedure", "node_id" => "stepbystep"],
            ["label" => "Service Requirements", "value" => "Service requirements", "node_id" => "req"],
            ["label" => "My Queue Status", "value" => "Queue status", "node_id" => "queue"],
            ["label" => "Talk to Agent", "value" => "Talk to agent", "node_id" => "agent"]
        ],

        "benefits" => [
            ["label" => "Inpatient / Outpatient", "value" => "Inpatient / Outpatient", "node_id" => "benefits_inpatient"],
            ["label" => "Z Benefits", "value" => "Z Benefits", "node_id" => "benefits_z"],
            ["label" => "Konsulta", "value" => "Konsulta", "node_id" => "benefits_konsulta"],
            ["label" => "Emergency Care", "value" => "Emergency Care", "node_id" => "benefits_emergency"],
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "benefits_inpatient" => [
            ["label" => "Z Benefits", "value" => "Z Benefits", "node_id" => "benefits_z"],
            ["label" => "Konsulta", "value" => "Konsulta", "node_id" => "benefits_konsulta"],
            ["label" => "Emergency Care", "value" => "Emergency Care", "node_id" => "benefits_emergency"],
            ["label" => "Back to Benefits", "value" => "Benepisyong Pangkalusugan", "node_id" => "benefits"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "benefits_z" => [
            ["label" => "Inpatient / Outpatient", "value" => "Inpatient / Outpatient", "node_id" => "benefits_inpatient"],
            ["label" => "Konsulta", "value" => "Konsulta", "node_id" => "benefits_konsulta"],
            ["label" => "Emergency Care", "value" => "Emergency Care", "node_id" => "benefits_emergency"],
            ["label" => "Back to Benefits", "value" => "Benepisyong Pangkalusugan", "node_id" => "benefits"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "benefits_konsulta" => [
            ["label" => "Inpatient / Outpatient", "value" => "Inpatient / Outpatient", "node_id" => "benefits_inpatient"],
            ["label" => "Z Benefits", "value" => "Z Benefits", "node_id" => "benefits_z"],
            ["label" => "Emergency Care", "value" => "Emergency Care", "node_id" => "benefits_emergency"],
            ["label" => "Back to Benefits", "value" => "Benepisyong Pangkalusugan", "node_id" => "benefits"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "benefits_emergency" => [
            ["label" => "Inpatient / Outpatient", "value" => "Inpatient / Outpatient", "node_id" => "benefits_inpatient"],
            ["label" => "Z Benefits", "value" => "Z Benefits", "node_id" => "benefits_z"],
            ["label" => "Konsulta", "value" => "Konsulta", "node_id" => "benefits_konsulta"],
            ["label" => "Back to Benefits", "value" => "Benepisyong Pangkalusugan", "node_id" => "benefits"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "membership" => [
            ["label" => "Registration", "value" => "Registration", "node_id" => "membership_registration"],
            ["label" => "Membership Categories", "value" => "Membership categories", "node_id" => "membership_categories"],
            ["label" => "Updating Records", "value" => "Updating records", "node_id" => "membership_update"],
            ["label" => "Informal Economy / Voluntary", "value" => "Informal Economy / Voluntary membership", "node_id" => "membership_informal"],
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "membership_registration" => [
            ["label" => "Membership Categories", "value" => "Membership categories", "node_id" => "membership_categories"],
            ["label" => "Updating Records", "value" => "Updating records", "node_id" => "membership_update"],
            ["label" => "Back to Membership", "value" => "Impormasyon sa Membership", "node_id" => "membership"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "membership_categories" => [
            ["label" => "Registration", "value" => "Registration", "node_id" => "membership_registration"],
            ["label" => "Updating Records", "value" => "Updating records", "node_id" => "membership_update"],
            ["label" => "Informal Economy / Voluntary", "value" => "Informal Economy / Voluntary membership", "node_id" => "membership_informal"],
            ["label" => "Back to Membership", "value" => "Impormasyon sa Membership", "node_id" => "membership"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "membership_update" => [
            ["label" => "Registration", "value" => "Registration", "node_id" => "membership_registration"],
            ["label" => "Membership Categories", "value" => "Membership categories", "node_id" => "membership_categories"],
            ["label" => "Back to Membership", "value" => "Impormasyon sa Membership", "node_id" => "membership"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "membership_informal" => [
            ["label" => "Registration", "value" => "Registration", "node_id" => "membership_registration"],
            ["label" => "Membership Categories", "value" => "Membership categories", "node_id" => "membership_categories"],
            ["label" => "Back to Membership", "value" => "Impormasyon sa Membership", "node_id" => "membership"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "appointment_process" => [
            ["label" => "How to Book Online?", "value" => "How to book online appointment?", "node_id" => "book_how"],
            ["label" => "What Services Can I Book?", "value" => "What services can I book?", "node_id" => "book_services"],
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "stepbystep" => [
            ["label" => "New User Registration", "value" => "New user registration", "node_id" => "step_new_user"],
            ["label" => "Booking Procedure", "value" => "Booking procedure", "node_id" => "step_booking"],
            ["label" => "Appointment Day", "value" => "Appointment day", "node_id" => "step_day"],
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "step_new_user" => [
            ["label" => "Booking Procedure", "value" => "Booking procedure", "node_id" => "step_booking"],
            ["label" => "Appointment Day", "value" => "Appointment day", "node_id" => "step_day"],
            ["label" => "Back to Step-by-step", "value" => "Step-by-step procedure", "node_id" => "stepbystep"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "step_booking" => [
            ["label" => "New User Registration", "value" => "New user registration", "node_id" => "step_new_user"],
            ["label" => "Appointment Day", "value" => "Appointment day", "node_id" => "step_day"],
            ["label" => "Back to Step-by-step", "value" => "Step-by-step procedure", "node_id" => "stepbystep"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "step_day" => [
            ["label" => "New User Registration", "value" => "New user registration", "node_id" => "step_new_user"],
            ["label" => "Booking Procedure", "value" => "Booking procedure", "node_id" => "step_booking"],
            ["label" => "Back to Step-by-step", "value" => "Step-by-step procedure", "node_id" => "stepbystep"],
            ["label" => "Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "req" => [
            ["label" => "What documents do I need?", "value" => "What documents are required?", "node_id" => "req_docs"],
            ["label" => "Requirements per service", "value" => "Show requirements per service", "node_id" => "req_per_service"],
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "queue" => [
            ["label" => "My queue number today", "value" => "What is my queue number today?", "node_id" => "queue_num"],
            ["label" => "Meaning of Serving/Done", "value" => "What does Serving mean?", "node_id" => "queue_meaning"],
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "agent" => [
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],

        "login" => [
            ["label" => "Back to Main Menu", "value" => "Menu", "node_id" => "root"]
        ],
    ];

    // Change root buttons if not logged in
    if (!$isUser) {
        if (isset($tree['root'])) {
            $tree['root'] = array_values(array_filter($tree['root'], function ($item) {
                return !in_array($item['node_id'], ['queue', 'agent'], true);
            }));

            $tree['root'][] = [
                "label"   => "Mag-login / Mag-register",
                "value"   => "Login",
                "node_id" => "login"
            ];
        }
    }

    return $tree[$nodeId] ?? $tree["root"];
}

function detectNode(string $msg, bool $isUser): string
{
    $m = normalizeText($msg);

    if ($m === 'menu' || $m === 'main menu' || $m === 'back') {
        return "root";
    }

    // Specific benefits first
    if (
        str_contains($m, 'z benefits') ||
        str_contains($m, 'z benefit') ||
        str_contains($m, 'ano ang z benefits')
    ) {
        return "benefits_z";
    }

    if (
        str_contains($m, 'emergency care') ||
        str_contains($m, 'ano ang emergency care') ||
        str_contains($m, 'outpatient emergency care benefit') ||
        str_contains($m, 'oecb')
    ) {
        return "benefits_emergency";
    }

    if (
        str_contains($m, 'konsulta') ||
        str_contains($m, 'primary care')
    ) {
        return "benefits_konsulta";
    }

    if (
        str_contains($m, 'inpatient') ||
        str_contains($m, 'outpatient')
    ) {
        return "benefits_inpatient";
    }

    // Membership specific
    if (
        str_contains($m, 'registration') ||
        str_contains($m, 'magparehistro') ||
        str_contains($m, 'pagpaparehistro')
    ) {
        return "membership_registration";
    }

    if (
        str_contains($m, 'membership categories') ||
        str_contains($m, 'category ng membership') ||
        str_contains($m, 'categories')
    ) {
        return "membership_categories";
    }

    if (
        str_contains($m, 'updating records') ||
        str_contains($m, 'update records') ||
        str_contains($m, 'amendment') ||
        str_contains($m, 'mdr')
    ) {
        return "membership_update";
    }

    if (
        str_contains($m, 'informal economy') ||
        str_contains($m, 'voluntary') ||
        str_contains($m, 'self employed') ||
        str_contains($m, 'self-employed')
    ) {
        return "membership_informal";
    }

    // Step-by-step specific
    if (
        str_contains($m, 'new user registration')
    ) {
        return "step_new_user";
    }

    if (
        str_contains($m, 'booking procedure')
    ) {
        return "step_booking";
    }

    if (
        str_contains($m, 'appointment day')
    ) {
        return "step_day";
    }

    // General sections
    if ($isUser) {
        if (str_contains($m, 'book') || str_contains($m, 'appointment') || str_contains($m, 'online')) {
            return "appointment_process";
        }
        if (str_contains($m, 'require') || str_contains($m, 'document') || str_contains($m, 'requirements')) {
            return "req";
        }
        if (str_contains($m, 'queue') || str_contains($m, 'serving') || str_contains($m, 'done') || str_contains($m, 'number')) {
            return "queue";
        }
        if (str_contains($m, 'agent') || str_contains($m, 'human') || str_contains($m, 'staff')) {
            return "agent";
        }
    } else {
        if (str_contains($m, 'login') || str_contains($m, 'log in') || str_contains($m, 'register') || str_contains($m, 'sign in')) {
            return "login";
        }
    }

    if (
        str_contains($m, 'benepisyo') ||
        str_contains($m, 'kalusugan') ||
        str_contains($m, 'health benefit')
    ) {
        return "benefits";
    }

    if (
        str_contains($m, 'membership') ||
        str_contains($m, 'miyembro') ||
        str_contains($m, 'pagiging miyembro') ||
        str_contains($m, 'informal economy') ||
        str_contains($m, 'informal') ||
        str_contains($m, 'self-employed') ||
        str_contains($m, 'self employed') ||
        str_contains($m, 'voluntary') ||
        str_contains($m, 'voluntary member') ||
        str_contains($m, 'pagpaparehistro') ||
        str_contains($m, 'registration') ||
        str_contains($m, 'magparehistro')
    ) {
        return "membership";
    }

    if (
        str_contains($m, 'appointment') ||
        str_contains($m, 'process') ||
        str_contains($m, 'proseso') ||
        str_contains($m, 'pagkuha')
    ) {
        return "appointment_process";
    }

    if (
        str_contains($m, 'step') ||
        str_contains($m, 'procedure') ||
        str_contains($m, 'hakbang')
    ) {
        return "stepbystep";
    }

    return "root";
}

/* =========================================================
   Queue status checking
   ========================================================= */
function is_queue_status_question(string $m): bool
{
    $m = mb_strtolower(trim($m));

    $keywords = [
        "queue number",
        "queue no",
        "my queue",
        "ano queue",
        "ano ang queue",
        "queue ko",
        "number ko",
        "pila ko",
        "queue number ko",
        "queue today",
        "ngayon",
        "queue status",
        "status ng queue",
        "my queue status"
    ];

    foreach ($keywords as $k) {
        if ($k !== '' && str_contains($m, $k)) {
            return true;
        }
    }

    return false;
}

function getLatestQueueToday(mysqli $conn, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT
            q.queue_id,
            q.queue_code,
            q.status AS queue_status,
            q.counter_id,
            sc.counter_name,
            q.category_id,
            qc.category_name,
            q.queue_date,
            q.queued_at
        FROM appointments a
        JOIN queue q ON q.appointment_id = a.appointment_id
        LEFT JOIN service_counters sc ON sc.counter_id = q.counter_id
        LEFT JOIN queue_categories qc ON qc.category_id = q.category_id
        WHERE a.user_id = ?
          AND q.queue_date = ?
        ORDER BY q.queue_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function format_queue_status_reply(array $row): string
{
    $code    = $row['queue_code'] ?? '—';
    $status0 = (string)($row['queue_status'] ?? 'unknown');
    $status  = strtoupper(trim($status0));
    $counter = $row['counter_name'] ?? null;

    $counterLine = $counter ? "\n• Counter: {$counter}" : "";

    $statusHint = match (strtolower($status0)) {
        'waiting' => "Nasa waiting list po kayo.",
        'serving' => "Kasalukuyang sine-serve na po kayo.",
        'done'    => "Tapos na po ang inyong transaction.",
        default   => "Status: {$status}"
    };

    return "Narito ang real-time queue information ninyo ngayon:\n"
        . "• Queue Number: {$code}\n"
        . "• {$statusHint}"
        . $counterLine
        . "\n\nAuto-update ito habang naka-open ang chat.";
}

/* =========================================================
   OpenAI request
   ========================================================= */
function getOpenAIResponse(string $systemPrompt, string $userMessage): ?string
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        error_log('OPENAI_API_KEY not set. Check .env path + load_env().');
        return null;
    }

    $model = getenv('OPENAI_MODEL') ?: 'ft:gpt-4.1-mini-2025-04-14:personal::DCd6cV35';
    $url   = 'https://api.openai.com/v1/responses';

    $payload = [
        'model' => $model,
        'input' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage],
        ],
        'temperature'       => 0.7,
        'max_output_tokens' => 500,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT    => 30,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        error_log('OpenAI cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        error_log("OpenAI HTTP {$http}: {$raw}");
        return null;
    }

    $json = json_decode($raw, true);

    $text = '';
    if (isset($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'message' && isset($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $c) {
                    if (($c['type'] ?? '') === 'output_text') {
                        $text .= (string)($c['text'] ?? '');
                    }
                }
            }
        }
    }

    $text = trim($text);
    return $text !== '' ? $text : null;
}

/* =========================================================
   Fixed replies for guided buttons and typed node answers
   ========================================================= */
function nodeResponseText(string $nodeId, bool $isUser): string
{
    return match ($nodeId) {
        "benefits" =>
        "Mga benepisyo ng PhilHealth (general):\n" .
            "• Inpatient / Outpatient\n" .
            "• Z Benefits\n" .
            "• Konsulta\n" .
            "• Emergency Care\n\n" .
            "Pumili ng specific na benepisyo sa ibaba o mag-type ng inyong tanong.",

        "benefits_inpatient" =>
        "Ang Inpatient at Outpatient benefits ay tumutukoy sa mga serbisyong maaaring gamitin ng miyembro para sa pagpapagamot sa ospital o para sa serbisyong hindi nangangailangan ng admission. Maaaring magkaiba ang saklaw depende sa benepisyong ina-avail at sa accredited facility.",

        "benefits_z" =>
        "Ang Z Benefits ay pakete ng benepisyo para sa malulubha at magagastos na karamdaman na nangangailangan ng espesyal na gamutan, alinsunod sa patakaran at saklaw ng PhilHealth.",

        "benefits_konsulta" =>
        "Ang Konsulta ay primary care benefit package ng PhilHealth na nagbibigay ng konsultasyon, preventive care, at piling laboratory at gamot sa accredited provider.",

        "benefits_emergency" =>
        "Ang Emergency Care ay benepisyong maaaring magamit sa oras ng agarang pangangailangang medikal, alinsunod sa saklaw at patakaran ng PhilHealth.",

        "membership" =>
        "Para sa membership concerns, maaari kayong magtanong tungkol sa:\n" .
            "• Registration\n" .
            "• Membership Categories\n" .
            "• Updating Records\n" .
            "• Informal Economy / Voluntary membership\n\n" .
            "Pumili ng paksa sa ibaba o mag-type ng inyong tanong.",

        "membership_registration" =>
        "Para sa membership registration, karaniwang kailangan ang tamang application form at valid supporting documents depende sa inyong kategorya. Maaari rin kayong magtanong ng specific na category para sa mas eksaktong requirements.",

        "membership_categories" =>
        "Ang membership categories ay maaaring kabilang ang employed, self-employed, voluntary, senior citizen, indigent, at iba pang kategorya ayon sa patakaran ng PhilHealth.",

        "membership_update" =>
        "Para sa updating of records, maaaring kailanganin ang kaukulang form at supporting documents depende sa impormasyong babaguhin, tulad ng personal details, dependents, o membership record.",

        "membership_informal" =>
        "Para sa Informal Economy o Voluntary membership, karaniwang kailangan ang PMRF at valid ID. Maaaring magtungo sa pinakamalapit na PhilHealth office para sa kumpletong gabay at updated requirements.",

        "appointment_process" =>
        "Proseso ng appointment:\n" .
            "1) Login\n" .
            "2) Book Appointment\n" .
            "3) Piliin ang service at category\n" .
            "4) Piliin ang date at time\n" .
            "5) Confirm ang booking\n\n" .
            "Sa araw ng appointment, mag-check in sa kiosk para makakuha ng queue number.",

        "book_how" =>
        "Para mag-book ng online appointment:\n\n" .
            "1) Mag-login sa inyong account.\n" .
            "2) Pumunta sa Book Appointment.\n" .
            "3) Piliin ang serbisyo, petsa, at oras.\n" .
            "4) I-confirm ang booking.\n\n" .
            "Sa araw ng appointment, mag-check in sa kiosk para makakuha ng queue number.",

        "book_services" =>
        "Maaari ninyong i-book ang mga sumusunod na serbisyo:\n\n" .
            "• Membership Registration\n" .
            "• Membership Renewal\n" .
            "• Amendment of Member Data Record\n" .
            "• Hospitalization Verification\n" .
            "• Benefit Coverage Assessment\n" .
            "• Other Benefit Claims",

        "stepbystep" =>
        "Step-by-step procedure:\n\n" .
            "• New User Registration\n" .
            "• Booking Procedure\n" .
            "• Appointment Day\n\n" .
            "Pumili ng specific na hakbang sa ibaba o mag-type ng inyong tanong.",

        "step_new_user" =>
        "New User Registration:\n" .
            "1) Register ng account\n" .
            "2) Fill out the required form\n" .
            "3) Login gamit ang inyong account pagkatapos ng registration.",

        "step_booking" =>
        "Booking Procedure:\n" .
            "1) Pumunta sa Book Appointment\n" .
            "2) Piliin ang service at category\n" .
            "3) Piliin ang date at time\n" .
            "4) I-confirm ang booking.",

        "step_day" =>
        "Appointment Day:\n" .
            "1) Mag-check in sa kiosk\n" .
            "2) Kunin ang inyong queue number\n" .
            "3) Hintayin na matawag ang inyong number sa tamang counter.",

        "req" =>
        "Depende sa serbisyo ang requirements. Sabihin ang specific appointment type at category upang maibigay ko ang tamang listahan.",

        "req_docs" =>
        "Depende sa serbisyo ang mga kinakailangang dokumento. Sabihin lamang kung anong serbisyo ang inyong a-avail upang maibigay ko ang tamang requirements.",

        "req_per_service" =>
        "Maaaring magkaiba ang requirements sa bawat serbisyo. Sabihin lamang ang specific na serbisyo, tulad ng Membership Registration o Benefit Claims, upang maibigay ko ang tamang listahan.",

        "queue" =>
        "Para makita ang queue number ninyo ngayon, piliin ang “My Queue Number Today”. Kung wala pa kayong queue entry, mag-check in sa kiosk sa araw ng appointment.",

        "queue_meaning" =>
        "Narito ang kahulugan ng queue status:\n\n" .
            "• Waiting - naghihintay pa kayo na tawagin.\n" .
            "• Serving - kasalukuyan na kayong inaasikaso.\n" .
            "• Done - tapos na ang inyong transaction.",

        "agent" =>
        $isUser
            ? "Kung kailangan ng staff, pwedeng magtanong sa front desk o sa assigned counter. Sabihin ang inyong concern para maituro kayo sa tamang serbisyo."
            : "Para sa staff support, mag-login muna o bumisita sa opisina.",

        "login" =>
        "Para mag-login o mag-register:\n\n" .
            "• Book Appointment – para sa bagong user (register)\n" .
            "• User Login – para sa may account\n",

        default => OUT_OF_SCOPE_REPLY,
    };
}

/* =========================================================
   Main chatbot logic
   ========================================================= */
$faq = loadFAQ();
$requirements = loadRequirements();

// Get current node
$metaNode = isset($meta['node_id']) ? trim((string)$meta['node_id']) : '';
$nodeId   = $metaNode !== '' ? $metaNode : detectNode($userMsg, $isUser);

// Check if from button click
$buttonDriven = ($metaSource === 'button' && $metaNode !== '');

// Handle exact guided buttons first for deterministic flow
if ($buttonDriven) {
    // Queue number needs realtime check
    if ($metaNode === "queue_num") {
        if (!$isUser || $user_id <= 0) {
            echo json_encode([
                "response" => "Kailangan munang mag-login upang makita ang inyong queue number.",
                "node_id" => "LOGIN_REQUIRED",
                "quick_replies" => quickReplies("root", false),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $row = getLatestQueueToday($conn, $user_id);

        echo json_encode([
            "response" => $row
                ? format_queue_status_reply($row)
                : "Wala pa kayong queue entry ngayong araw. Kung may booking kayo, mag-check in sa kiosk para makakuha ng queue number.",
            "node_id" => "queue_num",
            "quick_replies" => quickReplies("queue", true),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fixedButtonNodes = [
        "benefits",
        "benefits_inpatient",
        "benefits_z",
        "benefits_konsulta",
        "benefits_emergency",
        "membership",
        "membership_registration",
        "membership_categories",
        "membership_update",
        "membership_informal",
        "appointment_process",
        "book_how",
        "book_services",
        "stepbystep",
        "step_new_user",
        "step_booking",
        "step_day",
        "req",
        "req_docs",
        "req_per_service",
        "queue",
        "queue_meaning",
        "agent",
        "login",
        "root"
    ];

    if (in_array($metaNode, $fixedButtonNodes, true)) {
        echo json_encode([
            "response" => $metaNode === "root"
                ? "Hi! Maaari kayong mag-type ng tanong o pumili mula sa quick options sa ibaba."
                : nodeResponseText($metaNode, $isUser),
            "node_id" => $metaNode,
            "quick_replies" => quickReplies($metaNode, $isUser),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Block non-PhilHealth questions before AI
if (!$buttonDriven && !isPhilhealthRelated($userMsg)) {
    error_log("OUT OF SCOPE (pre-AI gate): " . $userMsg);

    echo json_encode([
        "response"      => OUT_OF_SCOPE_REPLY,
        "node_id"       => "root",
        "quick_replies" => quickReplies("root", $isUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check queue request before AI
$wantsQueueRealtime =
    is_queue_status_question($userMsg)
    || in_array($metaNode, ['queue', 'queue_num'], true)
    || ($nodeId === 'queue' && str_contains(mb_strtolower($userMsg), 'today'));

if ($wantsQueueRealtime) {
    if (!$isUser || $user_id <= 0) {
        echo json_encode([
            "response"      => "Kailangan munang mag-login upang makita ang inyong queue number.",
            "node_id"       => "LOGIN_REQUIRED",
            "quick_replies" => quickReplies("root", false),
            "realtime"      => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $row = getLatestQueueToday($conn, $user_id);

    if (!$row) {
        echo json_encode([
            "response"      => "Wala pa kayong queue entry ngayong araw. Kung may booking kayo, mag-check in sa kiosk para makakuha ng queue number.",
            "node_id"       => "NO_QUEUE_TODAY",
            "quick_replies" => quickReplies("queue", true),
            "realtime"      => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "response"      => format_queue_status_reply($row),
        "node_id"       => "QUEUE_STATUS",
        "quick_replies" => quickReplies("queue", true),
        "realtime"      => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Strong deterministic answers for typed known nodes before AI
$fixedTypedNodes = [
    "benefits",
    "benefits_inpatient",
    "benefits_z",
    "benefits_konsulta",
    "benefits_emergency",
    "membership",
    "membership_registration",
    "membership_categories",
    "membership_update",
    "membership_informal",
    "appointment_process",
    "book_how",
    "book_services",
    "stepbystep",
    "step_new_user",
    "step_booking",
    "step_day",
    "req",
    "req_docs",
    "req_per_service",
    "queue_meaning",
    "agent",
    "login"
];

if (in_array($nodeId, $fixedTypedNodes, true)) {
    echo json_encode([
        "response"      => nodeResponseText($nodeId, $isUser),
        "node_id"       => $nodeId,
        "quick_replies" => quickReplies($nodeId, $isUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Prompt for AI
$systemPrompt = <<<PROMPT
You are Sagip Assistant, a strictly PhilHealth-only chatbot.

RULES YOU MUST FOLLOW — NO EXCEPTIONS:
1. You ONLY answer questions that are directly related to PhilHealth, its benefits, membership,
   appointments, queue system, requirements, or the Philippine National Health Insurance Program (NHIP).
2. If the user's question is about ANYTHING else (weather, cooking, programming, general knowledge,
   celebrities, jokes, politics, other countries, other topics not related to PhilHealth), you must
   reply with EXACTLY the single word: OUT_OF_SCOPE
   Do NOT explain. Do NOT apologize in English. Just reply: OUT_OF_SCOPE
3. Be accurate and concise for all PhilHealth-related answers.
4. If unsure about a PhilHealth topic, say you are not sure and suggest contacting the nearest
   PhilHealth office or visiting the official PhilHealth website.
5. Never break character or answer non-PhilHealth topics even if the user insists.
PROMPT;

// Try AI first for general PhilHealth questions
$openaiAnswer = getOpenAIResponse($systemPrompt, $userMsg);

if ($openaiAnswer !== null) {
    if (trim($openaiAnswer) === OUT_OF_SCOPE_SIGNAL) {
        error_log("OUT OF SCOPE (OpenAI signal): " . $userMsg);

        echo json_encode([
            "response"      => OUT_OF_SCOPE_REPLY,
            "node_id"       => "root",
            "quick_replies" => quickReplies("root", $isUser),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "response"      => $openaiAnswer,
        "node_id"       => $nodeId,
        "quick_replies" => quickReplies($nodeId, $isUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback to FAQ
$faqMatch = findBestFAQ($userMsg, $faq, 0.3);
if ($faqMatch) {
    echo json_encode([
        "response"      => $faqMatch['answer'],
        "node_id"       => $nodeId,
        "quick_replies" => quickReplies($nodeId, $isUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback to requirements
$reqMatch = findRequirements($userMsg, $requirements);
if ($reqMatch) {
    echo json_encode([
        "response"      => formatRequirements($reqMatch, $requirements),
        "node_id"       => "req",
        "quick_replies" => quickReplies("req", $isUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Last fallback by node
$responseText = match ($nodeId) {
    "benefits",
    "benefits_inpatient",
    "benefits_z",
    "benefits_konsulta",
    "benefits_emergency",
    "membership",
    "membership_registration",
    "membership_categories",
    "membership_update",
    "membership_informal",
    "appointment_process",
    "book_how",
    "book_services",
    "stepbystep",
    "step_new_user",
    "step_booking",
    "step_day",
    "req",
    "req_docs",
    "req_per_service",
    "queue_meaning",
    "agent",
    "login" => nodeResponseText($nodeId, $isUser),
    default => OUT_OF_SCOPE_REPLY,
};

echo json_encode([
    "response"      => $responseText,
    "node_id"       => $nodeId === '' ? 'root' : $nodeId,
    "quick_replies" => quickReplies($nodeId === '' ? 'root' : $nodeId, $isUser),
], JSON_UNESCAPED_UNICODE);
exit;
