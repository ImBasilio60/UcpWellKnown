<?php

require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpBuyerConverter.php';

class UcpBuyerConverterTest
{
    private $converter;
    private $test_results = [];

    public function __construct()
    {
        $this->converter = new UcpBuyerConverter();
    }

    public function runAllTests()
    {
        echo "Running UCP Buyer Converter Tests...\n\n";

        $this->testCustomerWithBothAddresses();
        $this->testCustomerWithOnlyBillingAddress();
        $this->testCustomerMissingOptionalFields();
        $this->testCustomerAnonymization();
        $this->testMultipleCustomersConversion();
        $this->testCustomerSearch();
        $this->testInvalidCustomer();

        $this->printResults();
    }

    private function testCustomerWithBothAddresses()
    {
        $test_name = "Customer with Both Addresses Test";
        
        try {
            // Mock a customer with both billing and shipping addresses
            $this->mockCustomerWithBothAddresses();
            
            $customer = new Customer(1); // Assuming customer ID 1 exists
            if (!Validate::isLoadedObject($customer)) {
                $this->test_results[$test_name] = 'SKIP - Customer not found';
                return;
            }
            
            $buyer = $this->converter->convertCustomerToUcpBuyer($customer);
            
            // Validate structure
            $required_fields = ['id', 'name', 'email', 'addresses', 'metadata'];
            $missing_fields = [];
            
            foreach ($required_fields as $field) {
                if (!array_key_exists($field, $buyer)) {
                    $missing_fields[] = $field;
                }
            }
            
            if (empty($missing_fields) && 
                isset($buyer['name']['first']) && 
                isset($buyer['name']['last']) && 
                isset($buyer['addresses']['billing']) && 
                isset($buyer['addresses']['shipping'])) {
                
                // Check address structure
                if ($buyer['addresses']['billing'] && 
                    isset($buyer['addresses']['billing']['street']) &&
                    isset($buyer['addresses']['billing']['city']) &&
                    isset($buyer['addresses']['billing']['country'])) {
                    
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Billing address structure invalid';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Missing fields: ' . implode(', ', $missing_fields);
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testCustomerWithOnlyBillingAddress()
    {
        $test_name = "Customer with Only Billing Address Test";
        
        try {
            // Mock a customer with only billing address
            $this->mockCustomerWithOnlyBillingAddress();
            
            $customer = new Customer(2); // Assuming customer ID 2 exists
            if (!Validate::isLoadedObject($customer)) {
                $this->test_results[$test_name] = 'SKIP - Customer not found';
                return;
            }
            
            $options = [
                'include_billing_address' => true,
                'include_shipping_address' => false
            ];
            
            $buyer = $this->converter->convertCustomerToUcpBuyer($customer, $options);
            
            if (isset($buyer['addresses']['billing']) && 
                !isset($buyer['addresses']['shipping'])) {
                
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Should only have billing address';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testCustomerMissingOptionalFields()
    {
        $test_name = "Customer Missing Optional Fields Test";
        
        try {
            // Mock a customer with minimal data
            $this->mockCustomerWithMinimalData();
            
            $customer = new Customer(3); // Assuming customer ID 3 exists
            if (!Validate::isLoadedObject($customer)) {
                $this->test_results[$test_name] = 'SKIP - Customer not found';
                return;
            }
            
            $buyer = $this->converter->convertCustomerToUcpBuyer($customer);
            
            // Check that required fields are present
            if (isset($buyer['id']) && 
                isset($buyer['name']) && 
                isset($buyer['email']) && 
                isset($buyer['metadata'])) {
                
                // Check that optional fields are null or properly handled
                if ($buyer['phone'] === null || $buyer['phone'] === '') {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Optional fields should be null when missing';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Required fields missing';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testCustomerAnonymization()
    {
        $test_name = "Customer Anonymization Test";
        
        try {
            $customer = new Customer(1); // Use existing customer
            if (!Validate::isLoadedObject($customer)) {
                $this->test_results[$test_name] = 'SKIP - Customer not found';
                return;
            }
            
            $options = ['anonymize' => true];
            $buyer = $this->converter->convertCustomerToUcpBuyer($customer, $options);
            
            // Check that email is anonymized
            if (strpos($buyer['email'], '@') !== false && 
                strpos($buyer['email'], '*') !== false) {
                
                // Check that name contains asterisks
                if (strpos($buyer['name']['first'], '*') !== false || 
                    strpos($buyer['name']['last'], '*') !== false) {
                    
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Name not properly anonymized';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Email not properly anonymized';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testMultipleCustomersConversion()
    {
        $test_name = "Multiple Customers Conversion Test";
        
        try {
            $customer_ids = [1, 2, 3];
            $buyers = $this->converter->convertMultipleCustomers($customer_ids);
            
            if (is_array($buyers) && count($buyers) > 0) {
                // Check that each buyer has required structure
                $valid_buyers = 0;
                foreach ($buyers as $buyer) {
                    if (isset($buyer['id']) && isset($buyer['name']) && isset($buyer['email'])) {
                        $valid_buyers++;
                    }
                }
                
                if ($valid_buyers === count($buyers)) {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Some buyers missing required fields';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Should return array of buyers';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testCustomerSearch()
    {
        $test_name = "Customer Search Test";
        
        try {
            $results = $this->converter->searchCustomers('test');
            
            if (is_array($results)) {
                // Check that results are properly formatted
                $valid_results = 0;
                foreach ($results as $buyer) {
                    if (isset($buyer['id']) && isset($buyer['name']) && isset($buyer['email'])) {
                        $valid_results++;
                    }
                }
                
                if ($valid_results === count($results)) {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Search results not properly formatted';
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Search should return array';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testInvalidCustomer()
    {
        $test_name = "Invalid Customer Test";
        
        try {
            $buyer = $this->converter->convertCustomerToUcpBuyer(null);
            
            $this->test_results[$test_name] = 'FAIL - Should throw exception for invalid customer';
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invalid customer object') !== false) {
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Wrong exception: ' . $e->getMessage();
            }
        }
    }

    // Mock methods for testing
    private function mockCustomerWithBothAddresses()
    {
        // In a real test environment, you would create test data
        // For this example, we assume test data exists
    }

    private function mockCustomerWithOnlyBillingAddress()
    {
        // Mock customer with only billing address
    }

    private function mockCustomerWithMinimalData()
    {
        // Mock customer with minimal data
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

// Example usage demonstration
class UcpBuyerConverterDemo
{
    public static function demonstrateUsage()
    {
        echo "\n=== UCP Buyer Converter Usage Demo ===\n\n";
        
        try {
            $converter = new UcpBuyerConverter();
            
            // Convert a single customer
            echo "1. Converting single customer (ID: 1):\n";
            $customer = new Customer(1);
            if (Validate::isLoadedObject($customer)) {
                $buyer = $converter->convertCustomerToUcpBuyer($customer);
                echo "Customer converted to UCP Buyer format\n";
                echo "Buyer ID: " . $buyer['id'] . "\n";
                echo "Name: " . $buyer['name']['full'] . "\n";
                echo "Email: " . $buyer['email'] . "\n";
                echo "Addresses: " . (isset($buyer['addresses']['billing']) ? 'Billing' : '') . 
                     (isset($buyer['addresses']['shipping']) ? ', Shipping' : '') . "\n\n";
            } else {
                echo "Customer not found\n\n";
            }
            
            // Convert multiple customers
            echo "2. Converting multiple customers (IDs: [1, 2, 3]):\n";
            $customer_ids = [1, 2, 3];
            $buyers = $converter->convertMultipleCustomers($customer_ids);
            echo "Converted " . count($buyers) . " customers\n\n";
            
            // Search customers
            echo "3. Searching customers with 'test':\n";
            $search_results = $converter->searchCustomers('test');
            echo "Found " . count($search_results) . " customers\n\n";
            
            // Anonymized conversion
            echo "4. Anonymized conversion:\n";
            if (Validate::isLoadedObject($customer)) {
                $anonymized_buyer = $converter->convertCustomerToUcpBuyer($customer, ['anonymize' => true]);
                echo "Anonymized Email: " . $anonymized_buyer['email'] . "\n";
                echo "Anonymized Name: " . $anonymized_buyer['name']['full'] . "\n\n";
            }
            
        } catch (Exception $e) {
            echo "Demo failed: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UcpBuyerConverterTest();
    $test->runAllTests();
    
    // Run demo
    UcpBuyerConverterDemo::demonstrateUsage();
}
