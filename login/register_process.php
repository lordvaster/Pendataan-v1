<?php
// Author: Zeday @join.co.id
// Deprecated — user registration is handled via manage_users.php (admin only)
http_response_code(410);
header("Content-Type: application/json");
echo json_encode(["status" => "error", "message" => "Endpoint ini tidak tersedia."]);
exit;
