# UCP Protocol Module for PrestaShop

This PrestaShop module implements the UCP (Universal Commerce Protocol) for handling checkout sessions and API operations with proper header validation.

## Features

- **Header Validation**: Validates UCP-specific HTTP headers with proper format checking
- **Checkout Sessions**: Create temporary shopping carts with validation and error handling
- **Buyer Identity Management**: Automatic customer creation/retrieval with comprehensive validation
- **Promo Code Management**: Apply and remove promotional codes with comprehensive validation
- **Product Validation**: Check product existence, stock availability, and pricing
- **Cart Management**: Create and manage PrestaShop carts with guest support
- **Structured Logging**: Logs request information for debugging distributed agents
- **Error Handling**: Returns appropriate HTTP errors with detailed messages
- **Response Headers**: Prepares UCP protocol response headers

## Supported Headers

### Required Headers
- `UCP-Agent`: Non-empty string identifying client agent
- `request-id`: Unique request identifier (UUID format preferred)
- `idempotency-key`: String used to avoid duplicate operations
- `request-signature`: Cryptographic signature string

### Response Headers
- `request-id`: Echoed back from request
- `UCP-Version`: Protocol version (2026-03-13)
- `UCP-Server`: Server identification

## File Structure

```
ucpwellknown/
├── README.md                    # Documentation
├── info.txt                     # Module information
├── ucpwellknown.php             # Main module file
├── classes/                     # Core classes
│   ├── UcpHeaderValidator.php
│   ├── UcpCheckoutSessionValidator.php
│   ├── UcpCartManager.php
│   ├── UcpBuyerManager.php
│   ├── UcpBuyerConverter.php
│   ├── UcpItemConverter.php
│   └── UcpOrderConverter.php
└── controllers/
    └── front/                   # Front controllers
        ├── api.php
        ├── buyers.php
        ├── checkout_sessions.php
        ├── items.php
        ├── orders.php
        └── ucp.php
```

## API Endpoints

### Checkout Sessions API

#### Create Checkout Session
**Endpoint**: `POST /prestashop/module/ucpwellknown/checkout_sessions`

Creates a new checkout session with validated products and returns a unique checkout ID.

#### Update Checkout Session (NEW)
**Endpoint**: `PUT /prestashop/module/ucpwellknown/checkout_sessions?checkout_session_id={checkoutSessionId}`

**Note**: Due to PrestaShop routing limitations, PUT requests are handled as POST requests with the checkout session ID passed as a query parameter.

Updates an existing checkout session to apply or remove promotional codes.

**Request Headers Required**
- `UCP-Agent`: Client identifier
- `request-id`: UUID v4 format
- `idempotency-key`: Unique operation key
- `request-signature`: Request signature

**Request Body**
```json
{
  "promo_code": "PROMO123"
}
```

**Empty promo code removes all existing promotional codes:**
```json
{
  "promo_code": ""
}
```

#### Request Body
```json
{
  "line_items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 5,
      "quantity": 1,
      "customization_data": {
        "fields": [
          {
            "type": 0,
            "value": "Custom text",
            "required": 0
          }
        ]
      }
    }
  ],
  "buyer": {
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+33612345678",
    "company": "ACME Corp",
    "address": "123 Main St",
    "city": "Paris",
    "postal_code": "75001",
    "country": "France"
  }
}
```

**Required Buyer Fields:**
- `email`: Customer email address (must be valid format)
- `first_name`: Customer first name
- `last_name`: Customer last name  
- `address`: Street address (required for shipping)
- `city`: City name (required for shipping)
- `postal_code`: Postal code (4-10 digits, required for shipping)
- `country`: Country code (ISO format, required for shipping)

**Optional Buyer Fields:**
- `phone`: Phone number (minimum 10 digits)
- `company`: Company name

#### Success Response (POST - Checkout Session Created)
```json
{
  "status": "success",
  "checkout_id": "ucs_69b90f2e80dff_130_1773735726",
  "cart_id": "130",
  "customer_id": "13",
  "customer_info": {
    "id": "13",
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "is_new_customer": true
  },
  "line_items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 19.12,
      "total_price": 38.24,
      "product_name": "T-shirt imprimé colibri",
      "product_reference": "demo_1",
      "available_stock": 2400
    }
  ],
  "buyer": {
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+33612345678",
    "company": "ACME Corp",
    "address": "123 Main St",
    "city": "Paris",
    "postal_code": "75001",
    "country": "France"
  },
  "totals": {
    "subtotal": {
      "amount": 67,
      "currency": "MGA",
      "formatted": "67,00 MGA"
    },
    "tax": {
      "amount": 14,
      "currency": "MGA",
      "formatted": "14,00 MGA"
    },
    "shipping": {
      "amount": 0,
      "currency": "MGA",
      "formatted": "0,00 MGA"
    },
    "discount": {
      "amount": 0,
      "currency": "MGA",
      "formatted": "0,00 MGA"
    },
    "total": {
      "amount": 81,
      "currency": "MGA",
      "formatted": "81,00 MGA"
    },
    "items_count": 2,
    "items_quantity": 3
  },
  "created_at": "2026-03-17T11:22:06+03:00",
  "expires_at": "2026-03-17T12:22:06+03:00",
  "request_info": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "idempotency_key": "test-integration-1773735726"
  }
}
```

#### Success Response (PUT - Promo Code Applied)
```json
{
  "status": "success",
  "checkout_id": "ucs_69b7f29920f12_98_1773662873",
  "cart_id": "98",
  "items": [
    {
      "product_id": 1,
      "product_attribute_id": 0,
      "name": "T-shirt imprimé colibri",
      "reference": "demo_1",
      "quantity": 2,
      "unit_price": {
        "amount": 19.12,
        "currency": "MGA",
        "formatted": "19,12 MGA"
      },
      "total_with_tax": {
        "amount": 38.24,
        "currency": "MGA",
        "formatted": "38,24 MGA"
      }
    }
  ],
  "subtotal": 67.00,
  "discount": 10.00,
  "total": 71.00,
  "applied_rules": [
    {
      "id": 5,
      "name": "10% Discount",
      "code": "PROMO123",
      "description": "Special 10% off",
      "discount_type": "percentage",
      "discount_value": 10.0,
      "free_shipping": false,
      "applied_at": "2026-03-17T10:15:30+03:00"
    }
  ],
  "totals": {
    "subtotal": {
      "amount": 67,
      "currency": "MGA",
      "formatted": "67,00 MGA"
    },
    "discount": {
      "amount": 10,
      "currency": "MGA",
      "formatted": "10,00 MGA"
    },
    "total": {
      "amount": 71,
      "currency": "MGA",
      "formatted": "71,00 MGA"
    }
  },
  "updated_at": "2026-03-17T10:15:30+03:00",
  "request_info": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "idempotency_key": "unique-key-12345"
  }
}
```

#### Example PUT Request
```bash
curl -X PUT "http://localhost/prestashop/module/ucpwellknown/checkout_sessions/ucs_69b7f29920f12_98_1773662873" \
  -H "Content-Type: application/json" \
  -H "UCP-Agent: TestClient/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440001" \
  -H "idempotency-key: unique-key-12346" \
  -H "request-signature: signature-here" \
  -d '{
    "promo_code": "PROMO123"
  }'
```

#### Alternative POST Request (Recommended for PrestaShop)
```bash
curl -X POST "http://localhost/prestashop/module/ucpwellknown/checkout_sessions?checkout_session_id=ucs_69b7f29920f12_98_1773662873" \
  -H "Content-Type: application/json" \
  -H "UCP-Agent: TestClient/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440001" \
  -H "idempotency-key: unique-key-12346" \
  -H "request-signature: signature-here" \
  -d '{
    "promo_code": "PROMO123"
  }'
```

#### Success Response (201 Created)
```json
{
  "status": "success",
  "checkout_id": "ucs_69b7f29920f12_98_1773662873",
  "cart_id": "98",
  "line_items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 19.12,
      "total_price": 38.24,
      "product_name": "T-shirt imprimé colibri",
      "product_reference": "demo_1",
      "available_stock": 2400
    }
  ],
  "buyer": {
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+33612345678",
    "company": "ACME Corp",
    "address": "123 Main St",
    "city": "Paris",
    "postal_code": "75001",
    "country": "France"
  },
  "totals": {
    "subtotal": {
      "amount": 67,
      "currency": "MGA",
      "formatted": "67,00 MGA"
    },
    "tax": {
      "amount": 14,
      "currency": "MGA",
      "formatted": "14,00 MGA"
    },
    "shipping": {
      "amount": 0,
      "currency": "MGA",
      "formatted": "0,00 MGA"
    },
    "discount": {
      "amount": 0,
      "currency": "MGA",
      "formatted": "0,00 MGA"
    },
    "total": {
      "amount": 81,
      "currency": "MGA",
      "formatted": "81,00 MGA"
    },
    "items_count": 2,
    "items_quantity": 3
  },
  "created_at": "2026-03-16T15:07:53+03:00",
  "expires_at": "2026-03-16T16:07:53+03:00",
  "request_info": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "idempotency_key": "unique-key-12345"
  }
}
```

#### Example Request
```bash
curl -X POST "http://localhost/prestashop/module/ucpwellknown/checkout_sessions" \
  -H "Content-Type: application/json" \
  -H "UCP-Agent: TestClient/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440000" \
  -H "idempotency-key: unique-key-12345" \
  -H "request-signature: signature-here" \
  -d '{
    "line_items": [
      {
        "product_id": 1,
        "quantity": 2
      },
      {
        "product_id": 5,
        "quantity": 1,
        "customization_data": {
          "fields": [
            {
              "type": 0,
              "value": "Custom text",
              "required": 0
            }
          ]
        }
      }
    ],
    "buyer": {
      "email": "customer@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+33612345678",
      "company": "ACME Corp",
      "address": "123 Main St",
      "city": "Paris",
      "postal_code": "75001",
      "country": "France"
    }
  }'
```

### Other API Endpoints

#### Header Validation API
Access UCP API at: `/prestashop/module/ucpwellknown/api`

#### Items API
Access UCP Items API at: `/prestashop/module/ucpwellknown/items`

#### Orders API
Access UCP Orders API at: `/prestashop/module/ucpwellknown/orders`

#### Buyers API
Access UCP Buyers API at: `/prestashop/module/ucpwellknown/buyers`

## Validation Rules

### Product Validation
- `product_id` must be a positive integer
- Product must exist in PrestaShop catalog
- Product must be active
- Sufficient stock must be available

### Quantity Validation
- `quantity` must be a positive integer
- Stock availability is checked for each item

### Buyer Validation
- `email` must be valid email format
- `first_name` and `last_name` are required (max 32 characters)
- `phone` must be valid phone number format if provided

### Promo Code Validation (NEW)
- `promo_code` must be string (max 100 characters)
- Code must exist in PrestaShop CartRule system
- Code must be active and within valid date range
- Customer usage limits are enforced
- Minimum amount requirements are checked
- Product restrictions are validated
- Duplicate application is prevented

## Error Handling

### 400 Bad Request Examples

#### Missing Headers
```json
{
  "error": "Invalid UCP Headers",
  "code": 400,
  "details": [
    {
      "header": "UCP-Agent",
      "message": "Missing or empty required header"
    }
  ],
  "timestamp": "2026-03-16T15:07:53+03:00"
}
```

#### Invalid Request Data
```json
{
  "error": "Invalid request data",
  "code": 400,
  "details": [
    {
      "field": "line_items",
      "message": "line_items cannot be empty"
    }
  ],
  "timestamp": "2026-03-16T15:07:53+03:00"
}
```

#### Invalid Line Items
```json
{
  "error": "Invalid line items",
  "code": 400,
  "details": [
    {
      "field": "line_items[0].quantity",
      "message": "Insufficient stock. Available: 2, Requested: 5"
    }
  ],
  "timestamp": "2026-03-16T15:07:53+03:00"
}
```

#### Invalid Promo Code
```json
{
  "error": "Invalid promo code",
  "code": 400,
  "details": [
    {
      "field": "promo_code",
      "message": "Promo code not found"
    }
  ],
  "timestamp": "2026-03-17T10:15:30+03:00"
}
```

#### Checkout Session Not Found
```json
{
  "error": "Checkout session not found",
  "code": 404,
  "timestamp": "2026-03-17T10:15:30+03:00"
}
```

## Implementation Details

### UcpCheckoutSessionValidator Class
Provides comprehensive validation for checkout session requests:
- `validateCheckoutSessionRequest()`: Validates request structure
- `validateLineItems()`: Validates products and stock
- `validateBuyer()`: Validates buyer information
- `validateCheckoutSessionId()`: Validates checkout ID format
- `validateCheckoutSessionUpdate()`: Validates update request structure (NEW)
- `validatePromoCode()`: Validates promotional codes (NEW)

### UcpCartManager Class
Handles PrestaShop cart operations:
- `createCartWithItems()`: Creates cart with products
- `calculateCartTotals()`: Computes totals with tax breakdown
- `getCartDetails()`: Retrieves cart information
- `deleteCart()`: Removes cart from system
- `getCartByCheckoutSessionId()`: Retrieves cart from session ID (NEW)
- `applyPromoCode()`: Applies promotional code to cart (NEW)
- `removePromoCode()`: Removes promotional code from cart (NEW)
- `getAppliedRules()`: Returns applied promotional rules (NEW)

### UcpBuyerManager Class
Handles buyer identity management and customer operations:
- `handleBuyerIdentity()`: Main method for buyer validation and customer management
- `validateBuyerPayload()`: Validates buyer information structure and required fields
- `validateUcpAuthentication()`: Validates UCP headers for authentication
- `validateIdempotency()`: Validates idempotency key requirements
- `normalizeBuyerData()`: Normalizes and cleans buyer data
- `getOrCreateCustomer()`: Retrieves existing customer or creates new one
- `findCustomerByEmail()`: Searches for customer by email address
- `validateCustomerReuse()`: Validates if existing customer can be reused
- `updateCustomerSafely()`: Updates customer information safely
- `createNewCustomer()`: Creates new customer with address
- `createCustomerAddress()`: Creates customer address if provided

## Testing

### Manual Testing

The module can be tested manually using curl or any HTTP client:

#### Create Checkout Session with Buyer Management
```bash
curl -X POST "http://localhost/prestashop/module/ucpwellknown/checkout_sessions" \
  -H "Content-Type: application/json" \
  -H "UCP-Agent: TestClient/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440000" \
  -H "idempotency-key: unique-key-$(date +%s)" \
  -H "request-signature: test-signature" \
  -d '{
    "line_items": [
      {
        "product_id": 1,
        "quantity": 2
      }
    ],
    "buyer": {
      "email": "customer@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+33612345678",
      "company": "ACME Corp",
      "address": "123 Main St",
      "city": "Paris",
      "postal_code": "75001",
      "country": "France"
    }
  }'
```

**Note:** All buyer fields (email, first_name, last_name, address, city, postal_code, country) are now required. Missing fields will result in a 400 Bad Request error.

#### Apply Promo Code
```bash
curl -X POST "http://localhost/prestashop/module/ucpwellknown/checkout_sessions?checkout_session_id={checkoutSessionId}" \
  -H "Content-Type: application/json" \
  -H "UCP-Agent: TestClient/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440001" \
  -H "idempotency-key: unique-key-$(date +%s)" \
  -H "request-signature: test-signature" \
  -d '{
    "promo_code": "PROMO10"
  }'
```

**Note**: This uses POST method but is handled as PUT by the server due to PrestaShop routing limitations.

#### Remove Promo Code
```bash
curl -X POST "http://localhost/prestashop/module/ucpwellknown/checkout_sessions?checkout_session_id={checkoutSessionId}" \
  -H "Content-Type: application/json" \
  -H "UCP-Agent: TestClient/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440002" \
  -H "idempotency-key: unique-key-$(date +%s)" \
  -H "request-signature: test-signature" \
  -d '{
    "promo_code": ""
  }'
```

**Note**: This uses POST method but is handled as PUT by the server due to PrestaShop routing limitations.

## Error Codes

- `400 Bad Request`: Missing or invalid headers/request data, buyer information required
- `401 Unauthorized`: Invalid or missing authentication headers
- `404 Not Found`: Checkout session not found
- `405 Method Not Allowed`: Unsupported HTTP method
- `409 Conflict`: Idempotency key validation failed
- `500 Internal Server Error`: Server processing error

## Security Considerations

- All required headers must be present and valid
- Request signatures should be validated according to UCP specification
- Idempotency keys prevent duplicate operations
- Structured logging helps with security auditing

## Integration

### Header Validation Integration

To integrate with existing PrestaShop controllers:

```php
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpHeaderValidator.php';

$validator = new UcpHeaderValidator();
$validator->extractHeaders();
$validation = $validator->validateHeaders();

if (!$validation['valid']) {
    $validator->sendErrorResponse($validation['errors']);
}

// Continue with your controller logic
$validator->logRequest($endpoint);
$response_headers = $validator->prepareResponseHeaders();
```

### Item Converter Integration

To integrate UCP Item conversion in your controllers:

```php
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpItemConverter.php';

$converter = new UcpItemConverter();

// Convert single product
$ucp_item = $converter->convertProductToUcpItem($product_id, $language_id, $include_combinations);

// Convert multiple products
$product_ids = [1, 2, 3];
$ucp_items = $converter->convertMultipleProducts($product_ids, $language_id, $include_combinations);
```

### Combined Integration Example

```php
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpHeaderValidator.php';
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpItemConverter.php';

// Validate headers first
$validator = new UcpHeaderValidator();
$validator->extractHeaders();
$validation = $validator->validateHeaders();

if (!$validation['valid']) {
    $validator->sendErrorResponse($validation['errors']);
}

// Log request
$validator->logRequest($endpoint);

// Convert products
$converter = new UcpItemConverter();
$product_ids = [1, 2, 3];
$ucp_items = $converter->convertMultipleProducts($product_ids);

// Prepare response
$response = [
    'status' => 'success',
    'data' => $ucp_items,
    'request_info' => [
        'request_id' => $validator->getExtractedHeaders()['request-id'],
        'timestamp' => date('c')
    ]
];

// Set response headers
$response_headers = $validator->prepareResponseHeaders();
foreach ($response_headers as $name => $value) {
    header($name . ': ' . $value);
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
```

### Promo Code Integration Example

```php
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpCartManager.php';
require_once _PS_MODULE_DIR_ . 'ucpwellknown/classes/UcpCheckoutSessionValidator.php';

$cart_manager = new UcpCartManager();
$validator = new UcpCheckoutSessionValidator();

// Apply promo code to existing cart
$cart_id = 123;
$promo_code = 'SUMMER2023';

// Validate promo code first
$validation = $validator->validatePromoCode($promo_code, $cart_id);
if (!$validation['valid']) {
    // Handle validation errors
    return ['error' => 'Invalid promo code', 'details' => $validation['errors']];
}

// Apply promo code
$result = $cart_manager->applyPromoCode($cart_id, $promo_code);
if ($result['success']) {
    // Get updated cart details
    $cart_details = $cart_manager->getCartDetails($cart_id);
    $applied_rules = $cart_manager->getAppliedRules($cart_id);
    
    return [
        'success' => true,
        'cart' => $cart_details,
        'applied_rules' => $applied_rules
    ];
}
```
