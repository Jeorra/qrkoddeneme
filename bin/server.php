<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use Clue\React\Redis\RedisClient;
use Clue\React\Redis\Factory as RedisFactory;
use Ratchet\Http\OriginCheck; // Add OriginCheck

class QrLoginServer implements MessageComponentInterface
{
    protected $clients;
    protected $clientSessionMap; // [sessionId => connection]
    protected $redisSubscriber;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->clientSessionMap = [];
        echo "WebSocket server starting...\n";
        $this->initializeRedisSubscriber();
    }

    protected function initializeRedisSubscriber()
    {
        $factory = new RedisFactory();

        // IMPORTANT: Update with your Redis password if you have one.
        // Example: 'redis://:your_very_strong_password@127.0.0.1:6379'
        $clientPromise = $factory->createClient('redis://127.0.0.1:6379');

        $clientPromise->then(
            function (RedisClient $client) {
                $this->redisSubscriber = $client;
                echo "Successfully connected to Redis.\n";

                $this->redisSubscriber->on('close', function () {
                    echo "Redis connection closed. Attempting to reconnect in 5 seconds...\n";
                    // Note: A more robust solution would use exponential backoff.
                    $loop = \React\EventLoop\Loop::get();
                    $loop->addTimer(5, function() {
                         $this->initializeRedisSubscriber();
                    });
                });

                $this->redisSubscriber->subscribe('qr-login-events')->then(function () {
                    echo "Subscribed to 'qr-login-events' channel.\n";
                });

                $this->redisSubscriber->on('message', function ($channel, $payload) {
                    echo "Message received from Redis on channel '$channel': $payload\n";
                    $data = json_decode($payload, true);

                    if (isset($data['sessionId']) && isset($this->clientSessionMap[$data['sessionId']])) {
                        $connection = $this->clientSessionMap[$data['sessionId']];
                        echo "Found target client for sessionId: {$data['sessionId']}. Sending message.\n";
                        $connection->send($payload);
                    }
                });
            },
            function (Exception $e) {
                echo "Redis connection error: " . $e->getMessage() . "\n";
                // Optional: Add reconnection logic here
                $loop = \React\EventLoop\Loop::get();
                $loop->addTimer(5, function() {
                     $this->initializeRedisSubscriber();
                });
            }
        );
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        parse_str($conn->httpRequest->getUri()->getQuery(), $queryParams);
        $sessionId = $queryParams['sessionId'] ?? null;

        if ($sessionId) {
            $this->clientSessionMap[$sessionId] = $conn;
            echo "New connection! ({$conn->resourceId}) with sessionId: {$sessionId}\n";
        } else {
            echo "Warning: Connection ({$conn->resourceId}) opened without a sessionId. Closing.\n";
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Not used in this scenario, as communication is one-way from server to client.
    }

    public function onClose(ConnectionInterface $conn)
    {
        foreach ($this->clientSessionMap as $sessionId => $connection) {
            if ($connection === $conn) {
                unset($this->clientSessionMap[$sessionId]);
                echo "Connection for sessionId: {$sessionId} has disconnected.\n";
                break;
            }
        }
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected.\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

    // Setting up the server
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new OriginCheck(
                    new QrLoginServer(),
                    ['localhost', '127.0.0.1'] // Allowed origins
                )
            )
        ),
        8080
    );

    echo "Server running with OriginCheck on port 8080\n";
    $server->run();