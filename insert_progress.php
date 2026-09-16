<?php
// Author: Zeday @join.co.id
// Deprecated middleware — form now submits directly to api/insert_pajak_secure.php
http_response_code(410);
header("Content-Type: application/json");
echo json_encode(["status" => "error", "message" => "Endpoint ini tidak tersedia."]);
exit;
