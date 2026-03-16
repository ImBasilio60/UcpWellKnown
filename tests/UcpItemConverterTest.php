<?php

require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpItemConverter.php';

class UcpItemConverterTest
{
    private $converter;
    private $test_results = [];
    private $mock_product_id = 1;
    private $mock_combination_id = 1;

    public function __construct()
    {
        $this->converter = new UcpItemConverter();
    }

    public function runAllTests()
    {
        echo "Running UCP Item Converter Tests...\n\n";

        $this->testSimpleProductConversion();
        $this->testProductWithCombinations();
        $this->testProductWithMissingImages();
        $this->testProductWithMissingDescription();
        $this->testPriceFormatting();
        $this->testAvailabilityStatus();
        $this->testMultipleProductConversion();
        $this->testInvalidProductId();

        $this->printResults();
    }

    private function testSimpleProductConversion()
    {
        $test_name = "Simple Product Conversion Test";
        
        try {
            // Mock a simple product without combinations
            $this->mockSimpleProduct();
            
            $ucp_item = $this->converter->convertProductToUcpItem($this->mock_product_id, 1, false);
            
            // Validate structure
            $required_fields = ['id', 'title', 'description', 'price', 'currency', 'availability', 'images', 'metadata'];
            $missing_fields = [];
            
            foreach ($required_fields as $field) {
                if (!array_key_exists($field, $ucp_item)) {
                    $missing_fields[] = $field;
                }
            }
            
            if (empty($missing_fields) && 
                $ucp_item['id'] === (string) $this->mock_product_id &&
                !empty($ucp_item['title']) &&
                isset($ucp_item['price']['amount']) &&
                isset($ucp_item['price']['currency']) &&
                !isset($ucp_item['variants'])) {
                
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Missing fields: ' . implode(', ', $missing_fields);
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testProductWithCombinations()
    {
        $test_name = "Product with Combinations Test";
        
        try {
            // Mock a product with combinations
            $this->mockProductWithCombinations();
            
            $ucp_item = $this->converter->convertProductToUcpItem($this->mock_product_id, 1, true);
            
            if (isset($ucp_item['variants']) && 
                is_array($ucp_item['variants']) && 
                !empty($ucp_item['variants']) &&
                $ucp_item['has_variants'] === true) {
                
                // Check variant structure
                $variant = $ucp_item['variants'][0];
                $variant_fields = ['id', 'title', 'price', 'currency', 'attributes'];
                $missing_variant_fields = [];
                
                foreach ($variant_fields as $field) {
                    if (!array_key_exists($field, $variant)) {
                        $missing_variant_fields[] = $field;
                    }
                }
                
                if (empty($missing_variant_fields)) {
                    $this->test_results[$test_name] = 'PASS';
                } else {
                    $this->test_results[$test_name] = 'FAIL - Variant missing fields: ' . implode(', ', $missing_variant_fields);
                }
            } else {
                $this->test_results[$test_name] = 'FAIL - Product should have variants';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testProductWithMissingImages()
    {
        $test_name = "Product with Missing Images Test";
        
        try {
            // Mock a product without images
            $this->mockProductWithoutImages();
            
            $ucp_item = $this->converter->convertProductToUcpItem($this->mock_product_id, 1, false);
            
            if (isset($ucp_item['images']) && is_array($ucp_item['images']) && empty($ucp_item['images'])) {
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Should handle missing images gracefully';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testProductWithMissingDescription()
    {
        $test_name = "Product with Missing Description Test";
        
        try {
            // Mock a product without description
            $this->mockProductWithoutDescription();
            
            $ucp_item = $this->converter->convertProductToUcpItem($this->mock_product_id, 1, false);
            
            if (isset($ucp_item['description']) && 
                (empty($ucp_item['description']) || is_string($ucp_item['description']))) {
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Should handle missing description gracefully';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testPriceFormatting()
    {
        $test_name = "Price Formatting Test";
        
        try {
            // Mock a product with specific price
            $this->mockProductWithPrice(29.99);
            
            $ucp_item = $this->converter->convertProductToUcpItem($this->mock_product_id, 1, false);
            
            $price = $ucp_item['price'];
            
            if (isset($price['amount']) && 
                isset($price['currency']) && 
                isset($price['formatted']) &&
                $price['amount'] === 29.99 &&
                !empty($price['currency'])) {
                
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Price format incorrect';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testAvailabilityStatus()
    {
        $test_name = "Availability Status Test";
        
        try {
            // Mock a product in stock
            $this->mockProductInStock();
            
            $ucp_item = $this->converter->convertProductToUcpItem($this->mock_product_id, 1, false);
            
            $availability = $ucp_item['availability'];
            
            if (isset($availability['status']) && 
                isset($availability['quantity']) &&
                $availability['status'] === 'in_stock' &&
                $availability['quantity'] > 0) {
                
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Availability status incorrect';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testMultipleProductConversion()
    {
        $test_name = "Multiple Product Conversion Test";
        
        try {
            // Mock multiple products
            $this->mockMultipleProducts();
            
            $product_ids = [1, 2, 3];
            $ucp_items = $this->converter->convertMultipleProducts($product_ids, 1, false);
            
            if (is_array($ucp_items) && 
                count($ucp_items) === 3 &&
                isset($ucp_items[0]['id']) &&
                isset($ucp_items[1]['id']) &&
                isset($ucp_items[2]['id'])) {
                
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Multiple products not converted correctly';
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = 'FAIL - Exception: ' . $e->getMessage();
        }
    }

    private function testInvalidProductId()
    {
        $test_name = "Invalid Product ID Test";
        
        try {
            $ucp_item = $this->converter->convertProductToUcpItem(99999, 1, false);
            
            $this->test_results[$test_name] = 'FAIL - Should throw exception for invalid product ID';
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                $this->test_results[$test_name] = 'PASS';
            } else {
                $this->test_results[$test_name] = 'FAIL - Wrong exception: ' . $e->getMessage();
            }
        }
    }

    // Mock methods for testing
    private function mockSimpleProduct()
    {
        // In a real test environment, you would use dependency injection
        // or mocking framework. For this example, we'll simulate the behavior.
        // These would typically be mocked using PHPUnit or similar.
    }

    private function mockProductWithCombinations()
    {
        // Mock product with combinations
    }

    private function mockProductWithoutImages()
    {
        // Mock product without images
    }

    private function mockProductWithoutDescription()
    {
        // Mock product without description
    }

    private function mockProductWithPrice($price)
    {
        // Mock product with specific price
    }

    private function mockProductInStock()
    {
        // Mock product in stock
    }

    private function mockMultipleProducts()
    {
        // Mock multiple products
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
class UcpItemConverterDemo
{
    public static function demonstrateUsage()
    {
        echo "\n=== UCP Item Converter Usage Demo ===\n\n";
        
        try {
            $converter = new UcpItemConverter();
            
            // Convert a single product
            echo "1. Converting single product (ID: 1):\n";
            $ucp_item = $converter->convertProductToUcpItem(1);
            echo json_encode($ucp_item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
            
            // Convert multiple products
            echo "2. Converting multiple products (IDs: [1, 2, 3]):\n";
            $product_ids = [1, 2, 3];
            $ucp_items = $converter->convertMultipleProducts($product_ids);
            echo "Converted " . count($ucp_items) . " products\n\n";
            
            // Convert product without combinations
            echo "3. Converting product without combinations:\n";
            $simple_item = $converter->convertProductToUcpItem(1, 1, false);
            echo "Product has variants: " . (isset($simple_item['variants']) ? 'Yes' : 'No') . "\n\n";
            
        } catch (Exception $e) {
            echo "Demo failed: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UcpItemConverterTest();
    $test->runAllTests();
    
    // Run demo
    UcpItemConverterDemo::demonstrateUsage();
}
