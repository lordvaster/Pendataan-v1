<?php
// Author: Zeday @join.co.id
// This endpoint is deprecated and disabled.
// Authentication is handled by loginpage.php + includes/usersession.php.
http_response_code(410);
header("Content-Type: application/json");
echo json_encode(["status" => "error", "message" => "Endpoint ini tidak tersedia."]);
exit;
