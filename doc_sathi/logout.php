<?php 

	require_once __DIR__ . "/session_config.php";

	$requestedRole = $_GET['role'] ?? null;
	$rolesToClear = in_array($requestedRole, ['a', 'd', 'p'], true)
		? [$requestedRole]
		: ['a', 'd', 'p', null];

	foreach ($rolesToClear as $roleToClear) {
		$sessionName = doc_sathi_session_name_for_role($roleToClear);

		if (isset($_COOKIE[$sessionName])) {
			doc_sathi_expire_session_cookie($roleToClear);
		}
	}

	// redirecting the user to the login page
	header('Location: login.php?action=logout');
	exit();

 ?>
