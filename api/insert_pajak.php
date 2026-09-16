<?php
// Author: Zeday @join.co.id
// Deprecated — use insert_pajak_secure.php
http_response_code(410);
header("Content-Type: application/json");
echo json_encode(["status" => "error", "message" => "Endpoint ini tidak tersedia. Gunakan insert_pajak_secure.php."]);
exit;
