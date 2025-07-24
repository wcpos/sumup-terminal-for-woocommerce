# SumUp API Mock Server

This is a mock server that simulates the SumUp API endpoints for testing the SumUp Terminal for WooCommerce plugin.

## Setup

1. Install dependencies:
```bash
cd packages/test-server
npm install
```

2. Start the server:
```bash
npm start
```

Or for development with auto-reload:
```bash
npm run dev
```

The server will start on port 3001 by default. You can change this by setting the `PORT` environment variable.

## Configuration

To use this mock server with your plugin, update your HttpClient constructor in your test environment to use:

```php
$base_url = 'http://localhost:3001';
```

## Available Endpoints

All endpoints require authentication with a Bearer token. For testing, any token is accepted as long as it follows the `Bearer <token>` format.

### Profile Endpoints
- `GET /v0.1/me` - Get user profile
- `GET /v0.1/merchants/{merchantCode}` - Get merchant profile

### Reader Endpoints
- `GET /v0.1/merchants/{merchantCode}/readers` - Get all readers
- `GET /v0.1/merchants/{merchantCode}/readers/{readerId}` - Get specific reader
- `POST /v0.1/merchants/{merchantCode}/readers` - Pair a new reader
- `DELETE /v0.1/merchants/{merchantCode}/readers/{readerId}` - Unpair reader
- `POST /v0.1/merchants/{merchantCode}/readers/{readerId}/checkout` - Start checkout
- `POST /v0.1/merchants/{merchantCode}/readers/{readerId}/terminate` - Cancel checkout
- `GET /v0.1/merchants/{merchantCode}/readers/{readerId}/status` - Get reader status
- `POST /v0.1/merchants/{merchantCode}/readers/{readerId}/connect` - Connect to reader
- `POST /v0.1/merchants/{merchantCode}/readers/{readerId}/disconnect` - Disconnect from reader




## Testing Features

### Mock Data
The server comes with pre-configured mock data:
- A comprehensive user profile with merchant code `TEST_MERCHANT_123`
- Two test readers:
  - `rdr_3MSAFM23CK82VSTT4BN6RWSQ65` (Frontdesk - paired)
  - `rdr_7QSAFM23CK82VSTT4BN6RWSQ99` (Mobile Reader - unpaired)
- Actual webhook requests sent to return_url after 3 seconds for successful payments
- Actual webhook requests sent to return_url after 2-5 seconds for terminated payments

### Webhook Testing
The mock server actually sends HTTP POST requests to webhook URLs provided in the `return_url` field of checkout requests. This allows you to test your webhook handling code end-to-end.

**Webhook Events Sent:**
- **Successful Payment**: After 3 seconds, sends `checkout.status.updated` with status `PAID`
- **Terminated Payment**: After 2-5 seconds delay, sends `checkout.status.updated` with status `FAILED` and `failure_reason: "terminated_by_user"`

**Webhook Request Details:**
- **Method**: POST
- **Content-Type**: application/json
- **User-Agent**: SumUp-Mock-Server/1.0
- **Timeout**: 5 seconds
- **Console Logging**: Full request/response details logged for debugging

### Special Testing Codes
- Use pairing code `FAIL` to simulate pairing failure
- Any other pairing code will succeed

### Health Check
- `GET /health` - Returns server status (no authentication required)

## Example Usage

### Test Authentication
```bash
curl -H "Authorization: Bearer test-token" http://localhost:3001/v0.1/me
```

### Test Reader Pairing
```bash
curl -X POST \
  -H "Authorization: Bearer test-token" \
  -H "Content-Type: application/json" \
  -d '{"pairing_code":"12345"}' \
  http://localhost:3001/v0.1/merchants/TEST_MERCHANT_123/readers
```

### Test Checkout
```bash
curl -X POST \
  -H "Authorization: Bearer test-token" \
  -H "Content-Type: application/json" \
  -d '{"total_amount":{"value":1050,"currency":"USD","minor_unit":2},"description":"Test Order #123"}' \
  http://localhost:3001/v0.1/merchants/TEST_MERCHANT_123/readers/rdr_3MSAFM23CK82VSTT4BN6RWSQ65/checkout
```

**Response:**
```json
{
  "data": {
    "client_transaction_id": "123e4567-e89b-12d3-a456-426614174000"
  }
}
```

## Enhanced Logging

The server provides comprehensive logging of all API interactions:

- **Request Details**: Method, URL, timestamp, headers (with sanitized auth tokens)
- **Request Bodies**: Full JSON payload for POST/PUT requests  
- **Response Status**: HTTP status codes
- **Response Bodies**: Complete JSON responses
- **Visual Separation**: Clear formatting with separators for easy reading

This makes it very easy to debug API interactions during development and see exactly what your plugin is sending and receiving. 