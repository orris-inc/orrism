# ORRISM Server API

RESTful API for node management tools integration (e.g., XrayR, V2Board Panel Nodes)

## Overview

This API provides endpoints for external node management tools to:
- Fetch node configurations and connection details
- Retrieve user/service information with traffic limits
- Report traffic usage from nodes
- Support incremental synchronization
- Handle traffic reset operations

## Architecture

```
modules/servers/orrism/server/
├── v1/                          # API version 1
│   ├── index.php                # Unified entry point
│   ├── routes.php               # Route definitions
│   ├── .htaccess                # Clean URL rewriting
│   └── handlers/                # Request handlers
│       ├── NodeHandler.php      # Node operations
│       ├── UserHandler.php      # User operations
│       ├── TrafficHandler.php   # Traffic management
│       └── HealthHandler.php    # Health checks
└── lib/                         # Core libraries
    ├── Router.php               # Request routing
    ├── Request.php              # HTTP request wrapper
    ├── Response.php             # JSON response handler
    ├── Auth.php                 # Authentication middleware
    └── RateLimit.php            # Rate limiting middleware
```

## Installation

### 1. Database Setup

Run the database enhancement script:

```bash
mysql -u your_user -p your_database < server/database_enhancements.sql
```

This will:
- Add `api_key`, `enable`, `load` columns to nodes table
- Create necessary indexes for performance
- Create `server_api_logs` table (optional)

### 2. Configure Apache

Ensure `.htaccess` support is enabled in your Apache configuration:

```apache
<Directory "/path/to/whmcs/modules/servers/orrism/server">
    AllowOverride All
    Require all granted
</Directory>
```

Verify `mod_rewrite` is enabled:
```bash
apache2ctl -M | grep rewrite
```

### 3. Configure Authentication

#### Option A: Global API Key (Recommended for testing)

In WHMCS Admin Area:
1. Go to **System Settings → Addon Modules → ORRISM Admin**
2. Set **Server API Key**: Generate a secure key (e.g., `openssl rand -hex 32`)
3. Save changes

#### Option B: Node-Specific API Keys

For each node in your ORRISM database:

```sql
UPDATE nodes
SET api_key = MD5(CONCAT(id, UNIX_TIMESTAMP(), RAND()))
WHERE id = YOUR_NODE_ID;
```

Or generate secure keys:
```sql
UPDATE nodes
SET api_key = SHA2(CONCAT(id, UUID(), RAND()), 256)
WHERE api_key IS NULL;
```

### 4. Configure IP Whitelist (Optional)

In WHMCS Admin Area → ORRISM Admin settings:
- Set **Server API IP Whitelist**: Comma-separated IPs or CIDR blocks
- Example: `192.168.1.0/24,10.0.0.5`

### 5. Configure Rate Limiting

Default: 60 requests per minute per IP

To adjust, edit `/server/lib/RateLimit.php`:
```php
private $maxRequests = 100;  // Max requests
private $window = 60;        // Time window in seconds
```

## Authentication

All API requests require authentication via API key.

### Method 1: Header (Recommended)

```bash
curl -H "X-API-Key: your_api_key_here" \
     https://your-whmcs.com/modules/servers/orrism/server/v1/nodes
```

### Method 2: Bearer Token

```bash
curl -H "Authorization: Bearer your_api_key_here" \
     https://your-whmcs.com/modules/servers/orrism/server/v1/nodes
```

### Method 3: Query Parameter

```bash
curl "https://your-whmcs.com/modules/servers/orrism/server/v1/nodes?api_key=your_api_key_here"
```

## API Endpoints

Base URL: `https://your-whmcs.com/modules/servers/orrism/server/v1`

### Health Check

Check API and system status.

```http
GET /health
```

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "status": "healthy",
    "version": "1.0.0",
    "timestamp": 1673458800,
    "database": "connected",
    "redis": "connected"
  },
  "timestamp": 1673458800
}
```

---

### List All Nodes

Get all enabled nodes.

```http
GET /nodes
```

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "US Node 01",
      "server": "us1.example.com",
      "node_type": "vmess",
      "node_group": 1,
      "rate": 1.0,
      "enable": 1,
      "load": 0.45,
      "sort": 1
    }
  ],
  "timestamp": 1673458800
}
```

---

### Get Node Details

Get specific node information with caching.

```http
GET /nodes/{id}
```

**Parameters:**
- `id` (integer, required): Node ID

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 1,
    "name": "US Node 01",
    "server": "us1.example.com",
    "server_port": 443,
    "node_type": "vmess",
    "node_group": 1,
    "rate": 1.0,
    "enable": 1,
    "load": 0.45,
    "settings": "{\"network\":\"ws\",\"path\":\"/\"}"
  },
  "timestamp": 1673458800
}
```

---

### Node Heartbeat

Update node status and online user count.

```http
POST /nodes/{id}/heartbeat
```

**Parameters:**
- `id` (integer, required): Node ID

**Request Body:**
```json
{
  "load": 0.65,
  "online": 125
}
```

**Response:**
```json
{
  "success": true,
  "message": "Heartbeat recorded",
  "data": {
    "node_id": 1,
    "load": 0.65,
    "online": 125,
    "timestamp": 1673458800
  },
  "timestamp": 1673458800
}
```

---

### Get Users for Node

Get all active users for a specific node. Supports incremental sync.

```http
GET /nodes/{id}/users?timestamp=1673458800
```

**Parameters:**
- `id` (integer, required): Node ID
- `timestamp` (integer, optional): Unix timestamp for incremental sync

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "node_id": 1,
    "count": 2,
    "timestamp": 1673458900,
    "incremental": true,
    "users": [
      {
        "user_id": 123,
        "uuid": "a1b2c3d4-e5f6-4789-0123-456789abcdef",
        "email": "user@example.com",
        "transfer_enable": 107374182400,
        "u": 5368709120,
        "d": 10737418240,
        "enable": 1,
        "node_group": 1,
        "expired_at": 1704989999
      }
    ]
  },
  "timestamp": 1673458900
}
```

**Traffic Fields:**
- `transfer_enable`: Total allowed traffic in bytes
- `u`: Uploaded bytes (current cycle)
- `d`: Downloaded bytes (current cycle)
- `expired_at`: Service expiration Unix timestamp

---

### Get Users by Node Group

Get all active users in a node group.

```http
GET /groups/{id}/users?timestamp=1673458800
```

**Parameters:**
- `id` (integer, required): Node group ID
- `timestamp` (integer, optional): Unix timestamp for incremental sync

**Response:** Same format as `/nodes/{id}/users`

---

### Get User Details

Get detailed information for a specific user.

```http
GET /users/{sid}
```

**Parameters:**
- `sid` (integer, required): Service ID (WHMCS service ID)

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "user_id": 123,
    "service_id": 456,
    "uuid": "a1b2c3d4-e5f6-4789-0123-456789abcdef",
    "email": "user@example.com",
    "password": "encrypted_password",
    "transfer_enable": 107374182400,
    "u": 5368709120,
    "d": 10737418240,
    "total_used": 16106127360,
    "enable": 1,
    "node_group": 1,
    "expired_at": 1704989999,
    "status": "active"
  },
  "timestamp": 1673458800
}
```

---

### Get User Traffic Stats

Get detailed traffic statistics for a user.

```http
GET /users/{sid}/traffic
```

**Parameters:**
- `sid` (integer, required): Service ID

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "service_id": 456,
    "user_id": 123,
    "current_cycle": {
      "upload": 5368709120,
      "download": 10737418240,
      "total": 16106127360
    },
    "limits": {
      "transfer_enable": 107374182400,
      "remaining": 91268055040,
      "usage_percent": 15.0
    },
    "previous_usage": 32212254720,
    "all_time_total": 48318382080
  },
  "timestamp": 1673458800
}
```

---

### Report Traffic (Batch)

Batch upload traffic data from nodes.

```http
POST /traffic/report
```

**Request Body:**
```json
{
  "node_id": 1,
  "data": [
    {
      "user_id": 123,
      "u": 1048576,
      "d": 2097152
    },
    {
      "user_id": 456,
      "u": 524288,
      "d": 1048576
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Traffic reported successfully",
  "data": {
    "processed": 2,
    "failed": 0,
    "total": 2,
    "errors": []
  },
  "timestamp": 1673458800
}
```

---

### Reset User Traffic

Reset traffic counters for a user (new billing cycle).

```http
POST /traffic/reset
```

**Request Body:**
```json
{
  "service_id": 456
}
```

**Response:**
```json
{
  "success": true,
  "message": "Traffic reset successfully",
  "data": {
    "service_id": 456,
    "user_id": 123,
    "previous_usage": {
      "upload": 5368709120,
      "download": 10737418240,
      "total": 16106127360
    },
    "reset_at": 1673458800
  },
  "timestamp": 1673458800
}
```

---

## Error Handling

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message",
  "timestamp": 1673458800
}
```

### HTTP Status Codes

- `200 OK`: Request successful
- `400 Bad Request`: Invalid parameters
- `401 Unauthorized`: Invalid or missing API key
- `403 Forbidden`: IP not whitelisted
- `404 Not Found`: Resource not found
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Common Error Messages

**Authentication Errors:**
```json
{
  "success": false,
  "message": "Authentication failed",
  "error": "Invalid API key",
  "timestamp": 1673458800
}
```

**Rate Limit Errors:**
```json
{
  "success": false,
  "message": "Rate limit exceeded",
  "error": "Too many requests. Please try again later.",
  "timestamp": 1673458800
}
```

**Validation Errors:**
```json
{
  "success": false,
  "message": "Validation failed",
  "error": "Missing required field: node_id",
  "timestamp": 1673458800
}
```

---

## Integration Examples

### XrayR Configuration

See [XRAYR_CONFIG.md](./XRAYR_CONFIG.md) for complete XrayR integration guide.

### Python Client Example

```python
import requests

API_BASE = "https://your-whmcs.com/modules/servers/orrism/server/v1"
API_KEY = "your_api_key_here"
HEADERS = {"X-API-Key": API_KEY}

# Get all nodes
response = requests.get(f"{API_BASE}/nodes", headers=HEADERS)
nodes = response.json()['data']

# Get users for node
node_id = 1
response = requests.get(f"{API_BASE}/nodes/{node_id}/users", headers=HEADERS)
users = response.json()['data']['users']

# Report traffic
traffic_data = {
    "node_id": node_id,
    "data": [
        {"user_id": 123, "u": 1048576, "d": 2097152}
    ]
}
response = requests.post(f"{API_BASE}/traffic/report", json=traffic_data, headers=HEADERS)
print(response.json())
```

### Bash/cURL Example

```bash
#!/bin/bash
API_BASE="https://your-whmcs.com/modules/servers/orrism/server/v1"
API_KEY="your_api_key_here"

# Health check
curl -H "X-API-Key: $API_KEY" "$API_BASE/health"

# Get users for node
curl -H "X-API-Key: $API_KEY" "$API_BASE/nodes/1/users"

# Report traffic
curl -X POST \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"node_id":1,"data":[{"user_id":123,"u":1048576,"d":2097152}]}' \
  "$API_BASE/traffic/report"
```

---

## Performance Considerations

### Caching

- Node details are cached in Redis for 5 minutes
- Use incremental sync (`?timestamp=`) to reduce data transfer
- Rate limiting uses Redis for distributed tracking

### Database Indexes

The enhancement script creates these indexes for optimal performance:

- `nodes`: `idx_api_key`, `idx_enable`, `idx_node_group`
- `user`: `idx_node_group_enable`, `idx_updated_at`, `idx_uuid`

### Rate Limiting

Default: 60 requests/minute per IP address

For high-traffic nodes, consider:
1. Increasing rate limit in `RateLimit.php`
2. Using incremental sync to reduce request payload
3. Batch traffic reporting (up to 100 users per request)

---

## Monitoring and Logging

### WHMCS Module Log

All API requests are logged via WHMCS standard logging:

**Admin Area → System Logs → Module Log**

Filter by: `orrism_server_api`

### Custom API Logs (Optional)

If you created `server_api_logs` table:

```sql
SELECT * FROM server_api_logs
WHERE node_id = 1
ORDER BY created_at DESC
LIMIT 100;
```

### Health Monitoring

Set up automated health checks:

```bash
# Add to crontab
*/5 * * * * curl -f -H "X-API-Key: YOUR_KEY" https://your-whmcs.com/modules/servers/orrism/server/v1/health || echo "API down"
```

---

## Security Best Practices

1. **Use HTTPS Only**: Never expose API over HTTP
2. **Rotate API Keys**: Change keys periodically
3. **IP Whitelist**: Restrict access to known node IPs
4. **Monitor Logs**: Check for suspicious activity
5. **Rate Limiting**: Keep enabled to prevent abuse
6. **Secure Storage**: Store API keys securely (environment variables, secrets manager)

---

## Troubleshooting

### 404 Not Found on all endpoints

**Cause**: `.htaccess` not being processed

**Solution**:
1. Check `AllowOverride All` in Apache config
2. Verify `mod_rewrite` is enabled
3. Check file permissions on `.htaccess` (644)

### 401 Unauthorized

**Cause**: Invalid or missing API key

**Solution**:
1. Verify API key in WHMCS addon settings
2. Check header format: `X-API-Key: your_key`
3. Ensure node has `api_key` set in database

### 429 Rate Limit Exceeded

**Cause**: Too many requests from IP

**Solution**:
1. Wait 60 seconds and retry
2. Implement exponential backoff
3. Use incremental sync to reduce requests
4. Increase rate limit if legitimate traffic

### 500 Internal Server Error

**Cause**: Server-side error

**Solution**:
1. Check WHMCS Module Log (System Logs → Module Log)
2. Check PHP error log
3. Verify database connection
4. Check Redis availability

### Empty User List

**Cause**: No users match criteria

**Solution**:
1. Verify node group ID matches user assignments
2. Check `enable = 1` status for users
3. Verify service is active in WHMCS

---

## API Versioning

Current version: **v1**

Future versions will be accessible at:
- `/modules/servers/orrism/server/v2/`
- `/modules/servers/orrism/server/v3/`

v1 will remain supported for backward compatibility.

---

## Support and Contributing

For issues or feature requests:
1. Check WHMCS Module Log first
2. Review this documentation
3. Contact ORRISM support team

---

## License

Copyright (c) 2024 ORRISM Development Team
