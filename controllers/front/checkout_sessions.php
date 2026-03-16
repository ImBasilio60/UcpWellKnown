<?php

require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpHeaderValidator.php';
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpCheckoutSessionValidator.php';
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpCartManager.php';

class Ucpwellknowncheckout_sessionsModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;

    private $validator;
    private $session_validator;
    private $cart_manager;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new UcpHeaderValidator();
        $this->session_validator = new UcpCheckoutSessionValidator();
        $this->cart_manager = new UcpCartManager();
    }

    public function initContent()
    {
        header('Content-Type: application/json');

        try {
            // Extract and validate UCP headers
            $this->validator->extractHeaders();
            $validation = $this->validator->validateHeaders();

            if (!$validation['valid']) {
                $this->validator->sendErrorResponse($validation['errors']);
                return;
            }

            // Log the request
            $endpoint = $this->getEndpointPath();
            $log_data = $this->validator->logRequest($endpoint);

            // Set response headers
            $response_headers = $this->validator->prepareResponseHeaders();
            foreach ($response_headers as $name => $value) {
                header($name . ': ' . $value);
            }

            // Process the request based on method
            $method = $_SERVER['REQUEST_METHOD'];
            $response = $this->processRequest($method, $log_data);

            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        } catch (Exception $e) {
            $this->sendServerError($e->getMessage());
        }

        exit;
    }

    private function getEndpointPath()
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $parsed_url = parse_url($request_uri);
        return $parsed_url['path'] ?? 'unknown';
    }

    private function processRequest($method, $log_data)
    {
        $headers = $this->validator->getExtractedHeaders();
        
        switch ($method) {
            case 'POST':
                $input = $this->getJsonInput();
                return $this->handlePostCheckoutSession($headers, $input, $log_data);
            
            case 'GET':
                return $this->handleGetCheckoutSession($headers, $log_data);
            
            default:
                header('HTTP/1.1 405 Method Not Allowed');
                return [
                    'error' => 'Method not allowed',
                    'allowed_methods' => ['GET', 'POST'],
                    'timestamp' => date('c')
                ];
        }
    }

    private function handlePostCheckoutSession($headers, $input, $log_data)
    {
        try {
            // Validate input structure
            $validation_result = $this->session_validator->validateCheckoutSessionRequest($input);
            
            if (!$validation_result['valid']) {
                header('HTTP/1.1 400 Bad Request');
                return [
                    'error' => 'Invalid request data',
                    'code' => 400,
                    'details' => $validation_result['errors'],
                    'timestamp' => date('c')
                ];
            }

            // Process line items and validate products
            $validated_items = $this->session_validator->validateLineItems($input['line_items']);
            
            if (!$validated_items['valid']) {
                header('HTTP/1.1 400 Bad Request');
                return [
                    'error' => 'Invalid line items',
                    'code' => 400,
                    'details' => $validated_items['errors'],
                    'timestamp' => date('c')
                ];
            }

            // Validate buyer information
            $buyer_validation = $this->session_validator->validateBuyer($input['buyer']);
            
            if (!$buyer_validation['valid']) {
                header('HTTP/1.1 400 Bad Request');
                return [
                    'error' => 'Invalid buyer information',
                    'code' => 400,
                    'details' => $buyer_validation['errors'],
                    'timestamp' => date('c')
                ];
            }

            // Create cart and add products
            $cart_result = $this->cart_manager->createCartWithItems(
                $validated_items['items'],
                $input['buyer']
            );

            if (!$cart_result['success']) {
                header('HTTP/1.1 500 Internal Server Error');
                return [
                    'error' => 'Failed to create cart',
                    'code' => 500,
                    'message' => $cart_result['error'],
                    'timestamp' => date('c')
                ];
            }

            // Calculate totals
            $totals = $this->cart_manager->calculateCartTotals($cart_result['cart_id']);

            // Generate unique checkout ID
            $checkout_id = $this->generateCheckoutId($cart_result['cart_id']);

            // Log successful checkout session creation
            PrestaShopLogger::addLog(
                'UCP Checkout Session Created: ' . json_encode([
                    'checkout_id' => $checkout_id,
                    'cart_id' => $cart_result['cart_id'],
                    'request_id' => $headers['request-id'],
                    'items_count' => count($validated_items['items'])
                ]),
                1, // Info level
                null,
                'UcpWellKnown',
                0,
                true
            );

            return [
                'status' => 'success',
                'checkout_id' => $checkout_id,
                'cart_id' => $cart_result['cart_id'],
                'line_items' => $validated_items['items'],
                'buyer' => $input['buyer'],
                'totals' => $totals,
                'created_at' => date('c'),
                'expires_at' => date('c', strtotime('+1 hour')),
                'request_info' => [
                    'request_id' => $headers['request-id'],
                    'idempotency_key' => $headers['idempotency-key']
                ]
            ];

        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'UCP Checkout Session Error: ' . $e->getMessage(),
                3, // Error level
                null,
                'UcpWellKnown',
                0,
                true
            );
            
            throw $e;
        }
    }

    private function handleGetCheckoutSession($headers, $log_data)
    {
        return [
            'status' => 'success',
            'message' => 'UCP Checkout Sessions endpoint',
            'request_info' => [
                'request_id' => $headers['request-id'],
                'ucp_agent' => $headers['ucp-agent'],
                'idempotency_key' => $headers['idempotency-key'],
                'timestamp' => $log_data['timestamp']
            ],
            'endpoints' => [
                'POST /checkout-sessions' => 'Create a new checkout session',
                'GET /checkout-sessions/{id}' => 'Retrieve checkout session details'
            ]
        ];
    }

    private function generateCheckoutId($cart_id)
    {
        return 'ucs_' . uniqid() . '_' . $cart_id . '_' . time();
    }

    private function getJsonInput()
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            throw new Exception('Empty request body');
        }

        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function sendServerError($message)
    {
        header('Content-Type: application/json');
        header('HTTP/1.1 500 Internal Server Error');

        $error_response = [
            'error' => 'Internal Server Error',
            'code' => 500,
            'message' => $message,
            'timestamp' => date('c')
        ];

        echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
