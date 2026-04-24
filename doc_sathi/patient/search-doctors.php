<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
require_once __DIR__ . "/patient-ui.php";

session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || ($_SESSION["usertype"] ?? "") !== "p") {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Unauthorized",
    ]);
    exit();
}

include("../connection.php");

try {
    $query = doc_sathi_normalize_search_query($_GET["q"] ?? "");
    $doctors = doc_sathi_search_doctors($database, $query, 50);
    $isSearch = $query !== "";

    echo json_encode([
        "ok" => true,
        "query" => $query,
        "count" => count($doctors),
        "title" => $isSearch ? "Matching Doctors" : "Available Doctors",
        "description" => $isSearch
            ? 'Results for "' . $query . '".'
            : "Browse approved doctors and view their available sessions.",
        "html" => patient_portal_doctor_list_html($doctors, [
            "source_href" => "doctors.php",
            "reset_href" => "doctors.php",
            "empty_message" => "We could not find an approved doctor matching your search. Try another name, specialty, or clinic/hospital.",
        ]),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Search failed",
    ]);
}
