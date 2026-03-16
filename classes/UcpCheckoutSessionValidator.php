<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class UcpCheckoutSessionValidator
{
    public function validateCheckoutSessionRequest($input)
    {
        $errors = [];

        // Check if input is an array
        if (!is_array($input)) {
            $errors[] = [
                'field' => 'request_body',
                'message' => 'Request body must be a JSON object'
            ];
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate required fields
        $required_fields = ['line_items', 'buyer'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $errors[] = [
                    'field' => $field,
                    'message' => 'Missing or empty required field: ' . $field
                ];
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate line_items structure
        if (!is_array($input['line_items'])) {
            $errors[] = [
                'field' => 'line_items',
                'message' => 'line_items must be an array'
            ];
        } elseif (empty($input['line_items'])) {
            $errors[] = [
                'field' => 'line_items',
                'message' => 'line_items cannot be empty'
            ];
        }

        // Validate buyer structure
        if (!is_array($input['buyer'])) {
            $errors[] = [
                'field' => 'buyer',
                'message' => 'buyer must be an object'
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateLineItems($line_items)
    {
        $errors = [];
        $validated_items = [];

        if (!is_array($line_items)) {
            return [
                'valid' => false,
                'errors' => [['field' => 'line_items', 'message' => 'line_items must be an array']],
                'items' => []
            ];
        }

        foreach ($line_items as $index => $item) {
            $item_errors = [];
            $validated_item = [];

            // Validate item structure
            if (!is_array($item)) {
                $errors[] = [
                    'field' => 'line_items[' . $index . ']',
                    'message' => 'Each line item must be an object'
                ];
                continue;
            }

            // Validate required fields for each item
            $item_required_fields = ['product_id', 'quantity'];
            foreach ($item_required_fields as $field) {
                if (!isset($item[$field]) || $item[$field] === '' || $item[$field] === null) {
                    $item_errors[] = [
                        'field' => 'line_items[' . $index . '].' . $field,
                        'message' => 'Missing required field: ' . $field
                    ];
                }
            }

            if (!empty($item_errors)) {
                $errors = array_merge($errors, $item_errors);
                continue;
            }

            // Validate product_id
            $product_id = (int)$item['product_id'];
            if ($product_id <= 0) {
                $errors[] = [
                    'field' => 'line_items[' . $index . '].product_id',
                    'message' => 'product_id must be a positive integer'
                ];
                continue;
            }

            // Validate quantity
            $quantity = (int)$item['quantity'];
            if ($quantity <= 0) {
                $errors[] = [
                    'field' => 'line_items[' . $index . '].quantity',
                    'message' => 'quantity must be a positive integer'
                ];
                continue;
            }

            // Check if product exists and is active
            $product = new Product($product_id, false, Context::getContext()->language->id);
            if (!Validate::isLoadedObject($product)) {
                $errors[] = [
                    'field' => 'line_items[' . $index . '].product_id',
                    'message' => 'Product with ID ' . $product_id . ' does not exist'
                ];
                continue;
            }

            if (!$product->active) {
                $errors[] = [
                    'field' => 'line_items[' . $index . '].product_id',
                    'message' => 'Product with ID ' . $product_id . ' is not active'
                ];
                continue;
            }

            // Check stock availability
            $stock_available = StockAvailable::getQuantityAvailableByProduct($product_id);
            if ($stock_available < $quantity) {
                $errors[] = [
                    'field' => 'line_items[' . $index . '].quantity',
                    'message' => 'Insufficient stock. Available: ' . $stock_available . ', Requested: ' . $quantity
                ];
                continue;
            }

            // Get product price
            $price = Product::getPriceStatic($product_id, false, null, 6);
            
            $validated_item = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $price * $quantity,
                'product_name' => $product->name,
                'product_reference' => $product->reference,
                'available_stock' => $stock_available
            ];

            // Handle optional fields
            if (isset($item['customization_data']) && is_array($item['customization_data'])) {
                $validated_item['customization_data'] = $item['customization_data'];
            }

            $validated_items[] = $validated_item;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'items' => $validated_items
        ];
    }

    public function validateBuyer($buyer)
    {
        $errors = [];
        $validated_buyer = [];

        if (!is_array($buyer)) {
            return [
                'valid' => false,
                'errors' => [['field' => 'buyer', 'message' => 'buyer must be an object']],
                'buyer' => []
            ];
        }

        // Validate required buyer fields
        $required_fields = ['email', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (!isset($buyer[$field]) || empty(trim($buyer[$field]))) {
                $errors[] = [
                    'field' => 'buyer.' . $field,
                    'message' => 'Missing required field: ' . $field
                ];
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors, 'buyer' => []];
        }

        // Validate email format
        $email = trim($buyer['email']);
        if (!Validate::isEmail($email)) {
            $errors[] = [
                'field' => 'buyer.email',
                'message' => 'Invalid email format'
            ];
        }

        // Validate name fields
        $first_name = trim($buyer['first_name']);
        $last_name = trim($buyer['last_name']);
        
        if (strlen($first_name) > 32) {
            $errors[] = [
                'field' => 'buyer.first_name',
                'message' => 'First name must be 32 characters or less'
            ];
        }

        if (strlen($last_name) > 32) {
            $errors[] = [
                'field' => 'buyer.last_name',
                'message' => 'Last name must be 32 characters or less'
            ];
        }

        // Build validated buyer object
        $validated_buyer = [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name
        ];

        // Handle optional fields
        $optional_fields = ['phone', 'company', 'address', 'city', 'postal_code', 'country'];
        foreach ($optional_fields as $field) {
            if (isset($buyer[$field]) && !empty(trim($buyer[$field]))) {
                $validated_buyer[$field] = trim($buyer[$field]);
            }
        }

        // Validate phone if provided
        if (isset($validated_buyer['phone']) && !Validate::isPhoneNumber($validated_buyer['phone'])) {
            $errors[] = [
                'field' => 'buyer.phone',
                'message' => 'Invalid phone number format'
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'buyer' => $validated_buyer
        ];
    }

    public function validateCheckoutSessionId($checkout_id)
    {
        $errors = [];

        if (empty($checkout_id)) {
            $errors[] = [
                'field' => 'checkout_id',
                'message' => 'checkout_id is required'
            ];
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate checkout_id format (ucs_prefix + unique_id + cart_id + timestamp)
        if (!preg_match('/^ucs_[a-zA-Z0-9]+_\d+_\d+$/', $checkout_id)) {
            $errors[] = [
                'field' => 'checkout_id',
                'message' => 'Invalid checkout_id format'
            ];
        }

        // Extract cart_id from checkout_id
        $parts = explode('_', $checkout_id);
        if (count($parts) < 4) {
            $errors[] = [
                'field' => 'checkout_id',
                'message' => 'Invalid checkout_id structure'
            ];
        } else {
            $cart_id = (int)$parts[count($parts) - 2];
            $cart = new Cart($cart_id);
            
            if (!Validate::isLoadedObject($cart)) {
                $errors[] = [
                    'field' => 'checkout_id',
                    'message' => 'Associated cart not found'
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'cart_id' => isset($cart_id) ? $cart_id : null
        ];
    }
}
