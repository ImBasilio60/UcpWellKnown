# UCP Protocol Module for PrestaShop

This PrestaShop module implements the UCP (Universal Commerce Protocol) for handling checkout sessions and API operations with proper header validation.

## Features

- **Header Validation**: Validates UCP-specific HTTP headers with proper format checking
- **Checkout Sessions**: Create temporary shopping carts with validation and error handling
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
├── classes/
│   ├── UcpHeaderValidator.php           # Header validation middleware
│   ├── UcpCheckoutSessionValidator.php  # Checkout session validation
│   ├── UcpCartManager.php             # Cart creation and management
│   ├── UcpItemConverter.php           # Product to UCP Item converter
│   ├── UcpOrderConverter.php          # Cart to UCP Order converter
│   └── UcpBuyerConverter.php         # Customer to UCP Buyer converter
├── controllers/
│   └── front/
│       ├── ucp.php                    # Well-known endpoint
│       ├── api.php                    # UCP API endpoint with header validation
│       ├── checkout_sessions.php        # Checkout sessions API endpoint
│       ├── items.php                  # UCP Items API endpoint
│       ├── orders.php                 # UCP Orders API endpoint
│       └── buyers.php                 # UCP Buyers API endpoint
├── tests/
│   ├── UcpHeaderValidatorTest.php      # Unit tests for header validation
│   ├── UcpCheckoutSessionValidatorTest.php # Unit tests for checkout validation
│   ├── UcpItemConverterTest.php       # Unit tests for item conversion
│   ├── UcpOrderConverterTest.php      # Unit tests for order conversion
│   └── UcpBuyerConverterTest.php      # Unit tests for buyer conversion
└── README.md
```

## API Endpoints

### Checkout Sessions API
**Endpoint**: `POST /prestashop/module/ucpwellknown/checkout_sessions`

Creates a new checkout session with validated products and returns a unique checkout ID.

#### Request Headers Required
- `UCP-Agent`: Client identifier
- `request-id`: UUID v4 format
- `idempotency-key`: Unique operation key
- `request-signature`: Request signature

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

## Implementation Details

### UcpCheckoutSessionValidator Class
Provides comprehensive validation for checkout session requests:
- `validateCheckoutSessionRequest()`: Validates request structure
- `validateLineItems()`: Validates products and stock
- `validateBuyer()`: Validates buyer information
- `validateCheckoutSessionId()`: Validates checkout ID format

### UcpCartManager Class
Handles PrestaShop cart operations:
- `createCartWithItems()`: Creates cart with products
- `calculateCartTotals()`: Computes totals with tax breakdown
- `getCartDetails()`: Retrieves cart information
- `deleteCart()`: Removes cart from system

### UcpHeaderValidator Class
Provides header validation functionality:
- `extractHeaders()`: Extracts and normalizes HTTP headers
- `validateHeaders()`: Validates header presence and format
- `logRequest()`: Logs request information
- `prepareResponseHeaders()`: Prepares response headers
- `sendErrorResponse()`: Sends standardized error responses

## Testing

### Run Checkout Session Tests
```bash
php tests/UcpCheckoutSessionValidatorTest.php
```

### Run Header Validation Tests
```bash
php tests/UcpHeaderValidatorTest.php
```

### Test Coverage
- Request structure validation
- Product existence and stock validation
- Buyer information validation
- Error handling and response formatting
- Cart creation and management
- Price calculation with taxes
- **PUT**: Update UCP resources with JSON payload
- **DELETE**: Remove UCP resources

## Error Codes

- `400 Bad Request`: Missing or invalid headers
- `405 Method Not Allowed`: Unsupported HTTP method
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
