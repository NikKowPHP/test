<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

class Connection
{
    private string $ip;
    private int $port;
    private ?string $login_id = null;

    public function __construct(string $ip, int $port)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    private function send(array $payload): ?array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        error_log("Connecting to {$this->ip}:{$this->port} with payload: $json");
        $socket = @fsockopen($this->ip, $this->port, $errno, $errstr, 3);
        if (!$socket) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
            error_log("Socket error: $errstr ($errno) [IP: {$this->ip}, Port: {$this->port}, Caller: $caller]");
            error_log("Full debug: " . print_r($backtrace, true));
            return null;
        }

        fwrite($socket, $json . "\n");
        stream_set_timeout($socket, 1);
        $response = fgets($socket);
        fclose($socket);

        error_log("Received response: " . print_r($response, true));
        return json_decode($response, true);
    }

    public function login(string $login, string $password, int $device_id, string $order_id): ?array
    {
        $response = $this->send([
            "METHOD" => "LOGIN",
            "ORDER_ID" => $order_id,
            "LOGIN" => $login,
            "PASSWORD" => $password,
            "DEVICE_ID" => $device_id
        ]);

        error_log("Login response: " . json_encode($response));

        if (!empty($response['LOGIN_ID'])) {
            error_log("Try to login");
            $this->login_id = $response['LOGIN_ID'];
            error_log("Login successful: " . json_encode($response));
            return $response; // Return the full response array on success
        }

        error_log("Login failed: " . json_encode($response));

        return $response; // Return the full response array on failure (or null if send failed)
    }
}

$ip = getenv('IP_ADRESS') ?: '172.16.2.51'; // Fallback to localhost if not set
$port = (int)getenv('PORT') ?: 2387;     // Fallback to 8080 if not set

$connection = new Connection($ip, $port);

// Check if the request method is POST for the API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set content type to JSON
    header('Content-Type: application/json');

    // Get parameters from the POST request
    $login = $_POST['login'] ?? null;
    $password = $_POST['password'] ?? null;
    $deviceId = $_POST['device_id'] ?? null;
    $orderId = $_POST['order_id'] ?? null;
    $postIp = $_POST['ip_address'] ?? null; // New: Get IP from POST
    $postPort = $_POST['port'] ?? null;     // New: Get Port from POST

    // Validate required parameters
    if ($login === null || $password === null || $deviceId === null || $orderId === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (login, password, device_id, order_id).']);
        exit;
    }

    // Use POSTed IP and Port if available, otherwise use environment variables
    $currentIp = $postIp ?? $ip;
    $currentPort = (int)($postPort ?? $port);

    // Create a new connection with potentially overridden IP and Port
    $dynamicConnection = new Connection($currentIp, $currentPort);

    // Attempt to log in
    $loggedIn = $dynamicConnection->login($login, $password, (int)$deviceId, $orderId);

    if ($loggedIn) {
        http_response_code(200); // OK
        echo json_encode($loggedIn);
    } else {
        http_response_code(200); // Unauthorized
        echo json_encode($loggedIn);
    }
    exit; // Stop further execution for API requests
}

// The following code is for direct script execution (e.g., via `php index.php`)
// and can be removed if this file is solely for the API endpoint.
// For now, it's kept for demonstration/testing purposes.

// Attempt to log in with dummy data for direct execution
$loggedIn = $connection->login('test_user', 'test_password', 123, 'ORDER_ABC');

if ($loggedIn) {
    echo "Login successful!\n";
} else {
    echo "Login failed.\n";
}

// To run this file:
// 1. Make sure you have PHP installed.
// 2. Open your terminal or command prompt.
// 3. Navigate to the directory where 'index.php' is saved.
// 4. Run the command: php index.php
//
// Note: The `send` method attempts to open a socket connection.
// For this example to fully work, you would need a server running at '172.16.2.51:2387'
// that can handle the "LOGIN" payload and return a "LOGIN_ID".
// Without a server, the `fsockopen` will fail, and you will see "Login failed."

