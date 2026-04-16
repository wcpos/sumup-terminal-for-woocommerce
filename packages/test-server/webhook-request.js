function isLocalDevelopmentHost(hostname) {
  return hostname === 'localhost'
    || hostname === '127.0.0.1'
    || hostname === '::1'
    || hostname.endsWith('.local');
}

function shouldAllowInsecureTls(url) {
  return url.protocol === 'https:'
    && process.env.ALLOW_INSECURE_LOCAL_WEBHOOK_TLS === '1'
    && isLocalDevelopmentHost(url.hostname);
}

function buildWebhookRequestOptions(url, postData) {
  return {
    hostname: url.hostname,
    port: url.port || (url.protocol === 'https:' ? 443 : 80),
    path: url.pathname + url.search,
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(postData),
      'User-Agent': 'SumUp-Mock-Server/1.0'
    },
    rejectUnauthorized: !shouldAllowInsecureTls(url)
  };
}

module.exports = {
  buildWebhookRequestOptions,
  shouldAllowInsecureTls,
};
