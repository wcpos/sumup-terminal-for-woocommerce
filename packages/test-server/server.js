const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const { v4: uuidv4 } = require('uuid');
const crypto = require('crypto');
const https = require('https');
const http = require('http');

const app = express();
const PORT = process.env.PORT || 3001;

/**
 * Send a webhook request to the specified URL
 * @param {string} webhookUrl - The webhook URL to send to
 * @param {object} payload - The webhook payload
 * @param {string} orderId - Order ID for logging
 */
function sendWebhook(webhookUrl, payload, orderId = '') {
  const url = new URL(webhookUrl);
  const isHttps = url.protocol === 'https:';
  const client = isHttps ? https : http;
  
  const postData = JSON.stringify(payload);
  
  const options = {
    hostname: url.hostname,
    port: url.port || (isHttps ? 443 : 80),
    path: url.pathname + url.search,
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(postData),
      'User-Agent': 'SumUp-Mock-Server/1.0'
    },
    // Allow self-signed certificates in development
    rejectUnauthorized: false
  };

  const orderInfo = orderId ? ` (Order ID: ${orderId})` : '';
  console.log(`\n🔔 SENDING WEBHOOK to: ${webhookUrl}${orderInfo}`);
  
  // Warn about SSL certificate handling
  if (isHttps && url.hostname.includes('.local')) {
    console.log('⚠️  SSL certificate verification disabled for .local development');
  }
  
  console.log('Webhook payload:');
  console.log(JSON.stringify(payload, null, 2));
  
  const req = client.request(options, (res) => {
    let responseBody = '';
    
    res.on('data', (chunk) => {
      responseBody += chunk;
    });
    
    res.on('end', () => {
      if (res.statusCode >= 200 && res.statusCode < 300) {
        console.log(`✅ Webhook response: ${res.statusCode} ${res.statusMessage}`);
        if (responseBody) {
          console.log('Response body (unexpected - should be empty):', responseBody);
        } else {
          console.log('Response body: (empty - correct per SumUp spec)');
        }
      } else {
        console.log(`❌ Webhook response: ${res.statusCode} ${res.statusMessage}`);
        if (responseBody) {
          console.log('Response body:', responseBody);
        }
      }
      console.log('🔔 END WEBHOOK\n');
    });
  });
  
  req.on('error', (error) => {
    console.error(`❌ Webhook failed: ${error.message}`);
    console.log('🔔 END WEBHOOK\n');
  });
  
  req.setTimeout(5000, () => {
    req.destroy();
    console.error('❌ Webhook timeout after 5 seconds');
    console.log('🔔 END WEBHOOK\n');
  });
  
  req.write(postData);
  req.end();
}

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Mock data storage
let mockData = {
  profile: {
    "account": {
      "username": "test@example.com",
      "type": "business"
    },
    "personal_profile": {
      "first_name": "John",
      "last_name": "Doe",
      "date_of_birth": "1985-06-15",
      "mobile_phone": "+1234567890",
      "address": {
        "address_line1": "123 Main Street",
        "address_line2": "Suite 100",
        "city": "New York",
        "country": "US",
        "region_id": 36,
        "region_name": "New York",
        "region_code": "NY",
        "post_code": "10001",
        "landline": "+1234567891",
        "first_name": "John",
        "last_name": "Doe",
        "company": "Test Company LLC",
        "country_details": {
          "currency": "USD",
          "iso_code": "US",
          "en_name": "United States",
          "native_name": "United States"
        },
        "timeoffset_details": {
          "post_code": "10001",
          "offset": "-05:00",
          "dst": true
        },
        "state_id": 36
      },
      "complete": true
    },
    "merchant_profile": {
      "merchant_code": "TEST_MERCHANT_123",
      "company_name": "Test Company LLC",
      "website": "https://testcompany.com",
      "legal_type": {
        "id": 1,
        "full_description": "Limited Liability Company",
        "description": "LLC",
        "sole_trader": false
      },
      "merchant_category_code": "5812",
      "mobile_phone": "+1234567890",
      "company_registration_number": "123456789",
      "vat_id": "US123456789",
      "permanent_certificate_access_code": null,
      "nature_and_purpose": "Software development and consulting",
      "address": {
        "address_line1": "123 Main Street",
        "address_line2": "Suite 100",
        "city": "New York",
        "country": "US",
        "region_id": 36,
        "region_name": "New York",
        "region_code": "NY",
        "post_code": "10001",
        "landline": "+1234567891",
        "first_name": "John",
        "last_name": "Doe",
        "company": "Test Company LLC",
        "country_details": {
          "currency": "USD",
          "iso_code": "US",
          "en_name": "United States",
          "native_name": "United States"
        },
        "timeoffset_details": {
          "post_code": "10001",
          "offset": "-05:00",
          "dst": true
        },
        "state_id": 36
      },
      "business_owners": [
        {
          "first_name": "John",
          "last_name": "Doe",
          "date_of_birth": "1985-06-15",
          "mobile_phone": "+1234567890",
          "landline": "+1234567891",
          "ownership": 100
        }
      ],
      "doing_business_as": {
        "business_name": "Test Tech Solutions",
        "company_registration_number": "123456789",
        "vat_id": "US123456789",
        "website": "https://testtechsolutions.com",
        "email": "info@testtechsolutions.com",
        "address": {
          "address_line1": "123 Main Street",
          "address_line2": "Suite 100",
          "city": "New York",
          "country": "US",
          "region_id": 36,
          "region_name": "New York",
          "post_code": "10001"
        }
      },
      "settings": {
        "tax_enabled": true,
        "payout_type": "bank_account",
        "payout_period": "daily",
        "payout_on_demand_available": true,
        "payout_on_demand": false,
        "printers_enabled": true,
        "payout_instrument": "bank_account",
        "moto_payment": true,
        "stone_merchant_code": null,
        "daily_payout_email": true,
        "monthly_payout_email": true,
        "gross_settlement": false
      },
      "vat_rates": {
        "id": 1,
        "description": "Standard Rate",
        "rate": 8.25,
        "ordering": 1,
        "country": "US"
      },
      "locale": "en-US",
      "bank_accounts": [
        {
          "bank_code": "021000021",
          "branch_code": null,
          "swift": "CHASUS33",
          "account_number": "****1234",
          "iban": null,
          "account_type": "checking",
          "account_category": "primary",
          "account_holder_name": "Test Company LLC",
          "status": "active",
          "primary": true,
          "created_at": "2024-01-01T00:00:00Z",
          "bank_name": "Chase Bank"
        }
      ],
      "extdev": null,
      "payout_zone_migrated": true,
      "country": "US"
    },
    "app_settings": {
      "checkout_preference": "reader",
      "include_vat": true,
      "manual_entry_tutorial": false,
      "mobile_payment_tutorial": false,
      "tax_enabled": true,
      "mobile_payment": true,
      "reader_payment": true,
      "cash_payment": false,
      "advanced_mode": true,
      "expected_max_transaction_amount": 1000.00,
      "manual_entry": true,
      "terminal_mode_tutorial": false,
      "tipping": true,
      "tip_rates": [10, 15, 20],
      "barcode_scanner": false,
      "referral": null
    },
    "permissions": {
      "create_moto_payments": true,
      "full_transaction_history_view": true,
      "refund_transactions": true,
      "create_referral": false
    },
    "is_migrated_payleven_br": false
  },
  readers: [
    {
      "id": "rdr_3MSAFM23CK82VSTT4BN6RWSQ65",
      "name": "Frontdesk",
      "status": "paired",
      "device": {
        "identifier": "U1DT3NA00-CN",
        "model": "solo"
      },
      "meta": {},
      "created_at": "2023-01-18T15:16:17Z",
      "updated_at": "2023-01-20T15:16:17Z"
    },
    {
      "id": "rdr_7QSAFM23CK82VSTT4BN6RWSQ99",
      "name": "Mobile Reader",
      "status": "unpaired",
      "device": {
        "identifier": "A2RT5NB11-US",
        "model": "air"
      },
      "meta": {},
      "created_at": "2023-01-15T10:30:45Z",
      "updated_at": "2023-01-19T14:22:33Z"
    }
  ],
  checkouts: []
};

// Authentication middleware
const authenticateToken = (req, res, next) => {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];

  if (!token) {
    return res.status(401).json({ error: 'Access token required' });
  }

  // For testing, accept any token that starts with 'Bearer '
  if (!authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Invalid token format' });
  }

  req.token = token;
  next();
};

// Enhanced logging middleware
app.use((req, res, next) => {
  const timestamp = new Date().toISOString();
  console.log(`\n${'='.repeat(80)}`);
  console.log(`${timestamp} - ${req.method} ${req.url}`);
  console.log(`${'='.repeat(80)}`);
  
  // Log request headers
  console.log('REQUEST HEADERS:');
  const sanitizedHeaders = { ...req.headers };
  if (sanitizedHeaders.authorization) {
    sanitizedHeaders.authorization = sanitizedHeaders.authorization.replace(/Bearer .+/, 'Bearer ***');
  }
  console.log(JSON.stringify(sanitizedHeaders, null, 2));
  
  // Log request body
  if (req.body && Object.keys(req.body).length > 0) {
    console.log('\nREQUEST BODY:');
    console.log(JSON.stringify(req.body, null, 2));
  }
  
  // Store original json method
  const originalJson = res.json;
  
  // Override res.json to log response
  res.json = function(data) {
    console.log(`\nRESPONSE STATUS: ${res.statusCode}`);
    console.log('RESPONSE BODY:');
    console.log(JSON.stringify(data, null, 2));
    console.log(`${'='.repeat(80)}\n`);
    
    // Call original json method
    return originalJson.call(this, data);
  };
  
  next();
});

// Profile endpoints
app.get('/v0.1/me', authenticateToken, (req, res) => {
  res.json(mockData.profile);
});

app.get('/v0.1/merchants/:merchantCode', authenticateToken, (req, res) => {
  const { merchantCode } = req.params;
  
  const merchantProfile = {
    merchant_code: merchantCode,
    company_name: 'Test Company',
    address: {
      line1: '123 Test Street',
      city: 'Test City',
      state: 'TS',
      postal_code: '12345',
      country: 'US'
    },
    settings: {
      currency: 'USD',
      timezone: 'America/New_York'
    }
  };
  
  res.json(merchantProfile);
});

// Reader endpoints
app.get('/v0.1/merchants/:merchantCode/readers', authenticateToken, (req, res) => {
  res.json({
    items: mockData.readers
  });
});

app.get('/v0.1/merchants/:merchantCode/readers/:readerId', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  const reader = mockData.readers.find(r => r.id === readerId);
  
  if (!reader) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  res.json(reader);
});

app.post('/v0.1/merchants/:merchantCode/readers', authenticateToken, (req, res) => {
  const { pairing_code } = req.body;
  
  if (!pairing_code) {
    return res.status(400).json({ error: 'Pairing code required' });
  }
  
  // Simulate pairing success/failure based on code
  if (pairing_code === 'FAIL') {
    return res.status(400).json({ error: 'Invalid pairing code' });
  }
  
  const newReader = {
    id: `rdr_${Math.random().toString(36).toUpperCase().substr(2, 25)}`,
    name: `Paired Reader ${pairing_code}`,
    status: 'paired',
    device: {
      identifier: `${Math.random().toString(36).toUpperCase().substr(2, 10)}-${Math.random().toString(36).toUpperCase().substr(2, 2)}`,
      model: 'solo'
    },
    meta: {},
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  };
  
  mockData.readers.push(newReader);
  res.status(201).json(newReader);
});

app.delete('/v0.1/merchants/:merchantCode/readers/:readerId', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  const readerIndex = mockData.readers.findIndex(r => r.id === readerId);
  
  if (readerIndex === -1) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  mockData.readers.splice(readerIndex, 1);
  res.json({ success: true });
});

// Reader operations
app.post('/v0.1/merchants/:merchantCode/readers/:readerId/checkout', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  const { total_amount, description, return_url } = req.body;
  
  const reader = mockData.readers.find(r => r.id === readerId);
  if (!reader) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  if (reader.status !== 'paired') {
    return res.status(400).json({ error: 'Reader is not paired' });
  }
  
  // Validate required total_amount field
  if (!total_amount || !total_amount.value || !total_amount.currency) {
    return res.status(400).json({ error: 'total_amount with value and currency is required' });
  }
  
  const checkoutId = `checkout-${uuidv4()}`;
  const transactionId = uuidv4();
  
  const checkout = {
    id: checkoutId,
    reader_id: readerId,
    total_amount: {
      value: total_amount.value,
      currency: total_amount.currency,
      minor_unit: total_amount.minor_unit || 2
    },
    description: description || '',
    return_url: return_url || '',
    status: 'pending',
    created_at: new Date().toISOString()
  };
  
  mockData.checkouts.push(checkout);
  
  // Simulate checkout completion after 3 seconds
  setTimeout(() => {
    const checkoutIndex = mockData.checkouts.findIndex(c => c.id === checkoutId);
    if (checkoutIndex !== -1) {
      const checkout = mockData.checkouts[checkoutIndex];
      checkout.status = 'completed';
      checkout.completed_at = new Date().toISOString();
      
      // Send successful payment webhook
      if (checkout.return_url) {
        // Extract order ID from webhook URL query params if present
        const url = new URL(checkout.return_url);
        const orderIdFromParams = url.searchParams.get('order_id');
        
        const webhookPayload = {
          id: `webhook-${crypto.randomUUID()}`,
          event_type: 'checkout.status.updated',
          payload: {
            checkout_id: checkout.id,
            client_transaction_id: checkout.client_transaction_id,
            status: 'PAID'
          },
          timestamp: new Date().toISOString()
        };
        
        sendWebhook(checkout.return_url, webhookPayload, orderIdFromParams);
      }
    }
  }, 3000);
  
  // Return response in correct SumUp API format - only transaction ID
  res.json({
    data: {
      client_transaction_id: transactionId
    }
  });
});

app.post('/v0.1/merchants/:merchantCode/readers/:readerId/terminate', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  
  const reader = mockData.readers.find(r => r.id === readerId);
  if (!reader) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  // Simulate real API behavior: reader must be paired to accept terminate
  if (reader.status !== 'paired') {
    return res.status(400).json({ error: 'Reader is not online or not in a terminable state' });
  }
  
  // Find any pending checkouts for this reader
  const pendingCheckouts = mockData.checkouts.filter(
    checkout => checkout.reader_id === readerId && checkout.status === 'pending'
  );
  
  // API accepts the terminate request immediately (but termination is asynchronous)
  // Real SumUp API returns no response body
  res.status(204).end();
  
  // Simulate asynchronous termination after 2-5 seconds
  if (pendingCheckouts.length > 0) {
    const delay = Math.random() * 3000 + 2000; // 2-5 seconds
    
    setTimeout(() => {
      pendingCheckouts.forEach(checkout => {
        checkout.status = 'failed';
        checkout.failed_at = new Date().toISOString();
        checkout.failure_reason = 'terminated_by_user';
        
        // Send termination webhook
        if (checkout.return_url) {
          // Extract order ID from webhook URL query params if present
          const url = new URL(checkout.return_url);
          const orderIdFromParams = url.searchParams.get('order_id');
          
          const webhookPayload = {
            id: `webhook-${crypto.randomUUID()}`,
            event_type: 'checkout.status.updated',
            payload: {
              checkout_id: checkout.id,
              client_transaction_id: checkout.client_transaction_id,
              status: 'FAILED',
              failure_reason: 'terminated_by_user'
            },
            timestamp: new Date().toISOString()
          };
          
          sendWebhook(checkout.return_url, webhookPayload, orderIdFromParams);
        }
      });
    }, delay);
  }
});

app.get('/v0.1/merchants/:merchantCode/readers/:readerId/status', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  const reader = mockData.readers.find(r => r.id === readerId);
  
  if (!reader) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  res.json({
    id: reader.id,
    status: reader.status,
    battery_level: Math.floor(Math.random() * 100),
    last_seen: new Date().toISOString()
  });
});

app.post('/v0.1/merchants/:merchantCode/readers/:readerId/connect', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  const reader = mockData.readers.find(r => r.id === readerId);
  
  if (!reader) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  reader.status = 'paired';
  reader.updated_at = new Date().toISOString();
  res.json({ success: true, status: 'connected' });
});

app.post('/v0.1/merchants/:merchantCode/readers/:readerId/disconnect', authenticateToken, (req, res) => {
  const { readerId } = req.params;
  const reader = mockData.readers.find(r => r.id === readerId);
  
  if (!reader) {
    return res.status(404).json({ error: 'Reader not found' });
  }
  
  reader.status = 'unpaired';
  reader.updated_at = new Date().toISOString();
  res.json({ success: true, status: 'disconnected' });
});



// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ error: 'Endpoint not found' });
});

// Error handler
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(500).json({ error: 'Internal server error' });
});

app.listen(PORT, () => {
  console.log(`\n${'='.repeat(60)}`);
  console.log(`🚀 SumUp API Mock Server running on port ${PORT}`);
  console.log(`${'='.repeat(60)}`);
  console.log(`📋 Health check: http://localhost:${PORT}/health`);
  console.log(`🌐 Base URL: http://localhost:${PORT}`);
  console.log(`📊 Enhanced logging: ENABLED`);
  console.log(`${'='.repeat(60)}\n`);
  console.log('Ready to receive requests...\n');
});

module.exports = app; 