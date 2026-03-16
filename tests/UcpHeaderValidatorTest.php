<?php

require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpHeaderValidator.php';

class UcpHeaderValidatorTest
{
    private $validator;
    private $test_results = [];

    public function __construct()
    {
        $this->validator = new UcpHeaderValidator();
    }

    public function runAllTests()
    {
        echo "Running UCP Header Validator Tests...\n\n";

        $this->testMissingHeaders();
        $this->testValidRequest();
        $this->testMalformedRequestId();
        $this->testEmptyUcpAgent();
        $this-> testCaseInsensitiveHeaders();
        $this->testResponseHeaders();

        $this->printResults();
    }

    private function testMissingHeaders()
    {
        $test_name = "Missing Headers Test";
        
        // Mock empty headers
        $_SERVER['HTTP_HEADERS'] = [];
        
        try {
            $this->validator->extractHeaders();
            $validation = $this->validator->validateHeaders();
            
            if (!$validation['valid'] && count($validation['errors']) === 4) {
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Expected 4 errors for missing headers';
            }
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testValidRequest()
    {
        $test_name = "Valid Request Test";
        
        // Mock valid headers
        $this->mockHeaders([
            'UCP-Agent' => 'test-client/1.0',
            'request-id' => '550e8400-e29b-41d4-a716-446655440000',
            'idempotency-key' => 'order-12345-unique-key',
            'request-signature' => 'sha256=abc123def456...'
        ]);
        
        try {
            $this->validator->extractHeaders();
            $validation = $this->validator->validateHeaders();
            
            if ($validation['valid']) {
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Valid request should pass validation';
            }
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testMalformedRequestId()
    {
        $test_name = "Malformed Request ID Test";
        
        // Mock headers with invalid UUID
        $this->mockHeaders([
            'UCP-Agent' => 'test-client/1.0',
            'request-id' => 'invalid-uuid-format',
            'idempotency-key' => 'order-12345-unique-key',
            'request-signature' => 'sha256=abc123def456...'
        ]);
        
        try {
            $this->validator->extractHeaders();
            $validation = $this->validator->validateHeaders();
            
            if (!$validation['valid']) {
                $request_id_error = false;
                foreach ($validation['errors'] as $error) {
                    if ($error['header'] === 'request-id') {
                        $request_id_error = true;
                        break;
                    }
                }
                
                if ($request_id_error) {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Expected request-id validation error';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Invalid UUID should fail validation';
            }
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testEmptyUcpAgent()
    {
        $test_name = "Empty UCP-Agent Test";
        
        // Mock headers with empty UCP-Agent
        $this->mockHeaders([
            'UCP-Agent' => '',
            'request-id' => '550e8400-e29b-41d4-a716-446655440000',
            'idempotency-key' => 'order-12345-unique-key',
            'request-signature' => 'sha256=abc123def456...'
        ]);
        
        try {
            $this->validator->extractHeaders();
            $validation = $this->validator->validateHeaders();
            
            if (!$validation['valid']) {
                $agent_error = false;
                foreach ($validation['errors'] as $error) {
                    if ($error['header'] === 'UCP-Agent') {
                        $agent_error = true;
                        break;
                    }
                }
                
                if ($agent_error) {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Expected UCP-Agent validation error';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Empty UCP-Agent should fail validation';
            }
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testCaseInsensitiveHeaders()
    {
        $test_name = "Case-Insensitive Headers Test";
        
        // Mock headers with different cases
        $this->mockHeaders([
            'ucp-agent' => 'test-client/1.0',
            'REQUEST-ID' => '550e8400-e29b-41d4-a716-446655440000',
            'Idempotency-Key' => 'order-12345-unique-key',
            'request-signature' => 'sha256=abc123def456...'
        ]);
        
        try {
            $this->validator->extractHeaders();
            $headers = $this->validator->getExtractedHeaders();
            
            if (isset($headers['ucp-agent']) && 
                isset($headers['request-id']) && 
                isset($headers['idempotency-key']) && 
                isset($headers['request-signature'])) {
                
                $validation = $this->validator->validateHeaders();
                if ($validation['valid']) {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Case-insensitive headers should validate';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Headers not extracted case-insensitively';
            }
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testResponseHeaders()
    {
        $test_name = "Response Headers Test";
        
        // Mock valid headers
        $this->mockHeaders([
            'UCP-Agent' => 'test-client/1.0',
            'request-id' => '550e8400-e29b-41d4-a716-446655440000',
            'idempotency-key' => 'order-12345-unique-key',
            'request-signature' => 'sha256=abc123def456...'
        ]);
        
        try {
            $this->validator->extractHeaders();
            $response_headers = $this->validator->prepareResponseHeaders();
            
            if (isset($response_headers['request-id']) && 
                $response_headers['request-id'] === '550e8400-e29b-41d4-a716-446655440000' &&
                isset($response_headers['UCP-Version']) &&
                isset($response_headers['UCP-Server'])) {
                
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Response headers not properly prepared';
            }
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function mockHeaders($headers)
    {
        // Backup original function if exists
        if (!function_exists('getallheaders_backup')) {
            if (function_exists('getallheaders')) {
                function getallheaders_backup() {
                    return call_user_func('getallheaders_original');
                }
            }
        }

        // Override getallheaders function for testing
        if (!function_exists('getallheaders')) {
            eval('
                function getallheaders() {
                    global $test_headers;
                    return $test_headers;
                }
            ');
        }

        global $test_headers;
        $test_headers = $headers;
    }

    private function printResults()
    {
        echo "Test Results:\n";
        echo "============\n\n";

        $pass_count = 0;
        $total_count = count($this->test_results);

        foreach ($this->test_results as $test => $result) {
            echo "$test: $result\n";
            if ($result === 'PASS') {
                $pass_count++;
            }
        }

        echo "\nSummary: $pass_count/$total_count tests passed\n";
        
        if ($pass_count === $total_count) {
            echo "All tests PASSED! ✓\n";
        } else {
            echo "Some tests FAILED! ✗\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UcpHeaderValidatorTest();
    $test->runAllTests();
}
