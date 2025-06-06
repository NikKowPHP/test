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

    public function login(string $login, string $password, int $device_id, string $order_id): bool
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
            return true;
        }

        error_log("Login failed: " . json_encode($response));

        return false;
    }
}

$ip = getenv('IP_ADRESS') ?: '172.16.2.51'; // Fallback to localhost if not set
$port = (int)getenv('PORT') ?: 2387;     // Fallback to 8080 if not set

$connection = new Connection($ip, $port);

// Attempt to log in with dummy data
$loggedIn = $connection->login('test_user', 'test_password', 123, 'ORDER_ABC');
echo "$loggedIn";

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
// For this example to fully work, you would need a server running at '127.0.0.1:8080'
// that can handle the "LOGIN" payload and return a "LOGIN_ID".
// Without a server, the `fsockopen` will fail, and you will see "Login failed."

