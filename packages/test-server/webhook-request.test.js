const test = require('node:test');
const assert = require('node:assert/strict');

const { buildWebhookRequestOptions } = require('./webhook-request');

test('keeps TLS verification enabled by default for https webhooks', () => {
  const options = buildWebhookRequestOptions(
    new URL('https://checkout.local/webhook?token=abc'),
    '{"ok":true}',
  );

  assert.equal(options.rejectUnauthorized, true);
  assert.equal(options.hostname, 'checkout.local');
  assert.equal(options.path, '/webhook?token=abc');
});

test('allows insecure TLS only for local development hosts when explicitly enabled', () => {
  const previous = process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS;
  process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS = '1';

  try {
    const options = buildWebhookRequestOptions(
      new URL('https://checkout.local/webhook'),
      '{"ok":true}',
    );

    assert.equal(options.rejectUnauthorized, false);
  } finally {
    if (previous === undefined) {
      delete process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS;
    } else {
      process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS = previous;
    }
  }
});

test('refuses insecure TLS for non-local hosts even when explicitly enabled', () => {
  const previous = process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS;
  process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS = '1';

  try {
    const options = buildWebhookRequestOptions(
      new URL('https://example.com/webhook'),
      '{"ok":true}',
    );

    assert.equal(options.rejectUnauthorized, true);
  } finally {
    if (previous === undefined) {
      delete process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS;
    } else {
      process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS = previous;
    }
  }
});
