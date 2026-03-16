# UCP Protocol Header Validation Module

This PrestaShop module implements middleware and controller logic for handling UCP (Universal Commerce Protocol) specific HTTP headers.

## Features

- **Header Extraction**: Reads and normalizes UCP-specific HTTP headers
- **Validation**: Validates required headers with proper format checking
- **Structured Logging**: Logs request information for debugging distributed agents
- **Error Handling**: Returns appropriate HTTP errors for missing/malformed headers
- **Response Headers**: Prepares UCP protocol response headers

## Supported Headers

### Required Headers
- `UCP-Agent`: Non-empty string identifying the client agent
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
│   └── UcpHeaderValidator.php     # Header validation middleware
├── controllers/
│   ├── front/
│   │   ├── ucp.php               # Well-known endpoint
│   │   └── api.php               # UCP API endpoint with header validation
├── tests/
│   └── UcpHeaderValidatorTest.php # Unit tests
└── README.md
```

## Usage

### API Endpoint
Access the UCP API at: `/prestashop/module/ucpwellknown/api`

### Example Request

```bash
curl -X GET "http://localhost/prestashop/module/ucpwellknown/api" \
  -H "UCP-Agent: test-client/1.0" \
  -H "request-id: 550e8400-e29b-41d4-a716-446655440000" \
  -H "idempotency-key: order-12345-unique-key" \
  -H "request-signature: sha256=abc123def456..."
```

### Valid Response
```json
{
    "status": "success",
    "message": "UCP API endpoint",
    "request_info": {
        "request_id": "550e8400-e29b-41d4-a716-446655440000",
        "ucp_agent": "test-client/1.0",
        "idempotency_key": "order-12345-unique-key",
        "timestamp": "2026-03-16T08:30:00+00:00"
    },
    "server_info": {
        "ucp_version": "2026-03-13",
        "prestashop_version": "1.7.8.0",
        "module_version": "1.0.0"
    }
}
```

### Error Response (Missing Headers)
```json
{
    "error": "Invalid UCP Headers",
    "code": 400,
    "details": [
        {
            "header": "UCP-Agent",
            "message": "Missing or empty required header"
        },
        {
            "header": "request-id",
            "message": "Missing or empty required header"
        }
    ],
    "timestamp": "2026-03-16T08:30:00+00:00"
}
```

## Validation Rules

### UCP-Agent
- Must be a non-empty string
- Identifies the client agent making the request

### Request-ID
- Must be a valid UUID format (RFC 4122)
- Pattern: `^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$`
- Used for request tracing

### Idempotency-Key
- Non-empty string
- Used to prevent duplicate operations
- Should be unique per operation

### Request-Signature
- Non-empty string
- Cryptographic signature for request authentication

## Logging

All requests are logged with the following information:
- Timestamp (ISO 8601 format)
- Request ID
- UCP Agent
- Endpoint being called
- Idempotency Key

Logs are stored in PrestaShop's logging system for debugging distributed requests.

## Testing

Run the unit tests to validate header validation functionality:

```bash
php tests/UcpHeaderValidatorTest.php
```

### Test Coverage
- Missing required headers
- Valid request processing
- Malformed request-id validation
- Empty UCP-Agent validation
- Case-insensitive header handling
- Response header preparation

## Implementation Details

### UcpHeaderValidator Class

The `UcpHeaderValidator` class provides:

- `extractHeaders()`: Extracts and normalizes HTTP headers
- `validateHeaders()`: Validates header presence and format
- `logRequest()`: Logs request information
- `prepareResponseHeaders()`: Prepares response headers
- `sendErrorResponse()`: Sends standardized error responses

### UcpWellKnownApiModuleFrontController

The API controller integrates header validation with request processing:

- Validates headers before processing
- Logs all requests
- Supports GET, POST, PUT, DELETE methods
- Returns structured JSON responses
- Handles errors gracefully

## HTTP Methods Supported

- **GET**: Retrieve API information and request details
- **POST**: Process UCP operations with JSON payload
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
