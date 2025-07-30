<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Predis\Client as PredisClient;

class ApiController
{
    private $db;
    private $redis;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        // IMPORTANT: Update with your Redis password if you have one.
        $this->redis = new PredisClient(['scheme' => 'tcp', 'host' => '127.0.0.1', 'port' => 6379]);
    }

    public function getNewSession()
    {
        $sessionId = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        date_default_timezone_set('UTC');
        $expiresAt = date('Y-m-d H:i:s', time() + 120); // 2-minute expiry

        try {
            $stmt = $this->db->prepare("INSERT INTO qr_login_sessions (session_id, nonce, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $nonce, $expiresAt, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $qrData = json_encode(['sessionId' => $sessionId, 'nonce' => $nonce]);

            echo json_encode([
                'sessionId' => $sessionId,
                'qrCodeUrl' => '/api/qr-image?data=' . urlencode($qrData)
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not create a new session.', 'details' => $e->getMessage()]);
        }
    }

    public function generateQrImage()
    {
        $data = $_GET['data'] ?? '';
        if (empty($data)) {
            http_response_code(400);
            die('QR code data is missing.');
        }

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data(urldecode($data))
            ->build();

        header('Content-Type: ' . $result->getMimeType());
        echo $result->getString();
    }

    public function confirmScan()
    {
        $sessionId = $_POST['sessionId'] ?? null;
        $userId = $_POST['userId'] ?? null; // In a real app, this would come from a secure mobile session

        if (!$sessionId || !$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Session ID and User ID are required.']);
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM qr_login_sessions WHERE session_id = ? AND status = 'pending' AND expires_at > NOW()");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if ($session) {
                $this->db->prepare("UPDATE qr_login_sessions SET status = 'confirmed', user_id = ? WHERE id = ?")->execute([$userId, $session['id']]);

                // In a real app, you would generate a proper JWT or session token here.
                $payload = json_encode([
                    'event' => 'loginSuccess',
                    'sessionId' => $sessionId,
                    'userId' => $userId,
                    'token' => 'dummy-auth-token-' . bin2hex(random_bytes(16))
                ]);

                $this->redis->publish('qr-login-events', $payload);

                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Login confirmed.']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Invalid, expired, or already used session ID.']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'An internal error occurred.', 'details' => $e->getMessage()]);
        }
    }
}
