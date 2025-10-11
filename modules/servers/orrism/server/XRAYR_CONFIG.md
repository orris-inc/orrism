# XrayR Integration Guide

Complete guide for integrating ORRISM Server API with XrayR node management tool.

## Overview

XrayR is a popular Xray backend framework supporting multiple panel protocols. This guide shows how to configure XrayR to work with ORRISM's Server API.

## Prerequisites

1. ORRISM Server API installed and configured
2. XrayR installed on your node server
3. API key generated (global or node-specific)
4. Node created in ORRISM with proper configuration

---

## Quick Start

### 1. Generate API Key

#### Option A: Node-Specific Key (Recommended)

```sql
-- Connect to ORRISM database
mysql -u your_user -p orrism_database

-- Generate unique API key for node
UPDATE nodes
SET api_key = SHA2(CONCAT(id, UUID(), RAND()), 256)
WHERE id = 1;  -- Replace with your node ID

-- Retrieve the generated key
SELECT id, name, api_key FROM nodes WHERE id = 1;
```

#### Option B: Global API Key

In WHMCS Admin:
1. Go to **System Settings → Addon Modules → ORRISM Admin**
2. Set **Server API Key** field
3. Copy the key for XrayR configuration

### 2. Configure XrayR

Create or edit `/etc/XrayR/config.yml`:

```yaml
Log:
  Level: warning # Log level: none, error, warning, info, debug
  AccessPath: # /etc/XrayR/access.Log
  ErrorPath: # /etc/XrayR/error.log

DnsConfigPath: # /etc/XrayR/dns.json # Path to dns config

Nodes:
  - PanelType: "V2board"  # Panel type: V2board, NewV2board, SSpanel, etc.
    ApiConfig:
      ApiHost: "https://your-whmcs.com"
      ApiKey: "your_api_key_here"  # Your ORRISM API key
      NodeID: 1  # Your node ID in ORRISM
      NodeType: Vmess  # Node type: V2ray, Trojan, Shadowsocks
      Timeout: 30  # API timeout in seconds
      EnableVless: false
      EnableXTLS: false
      SpeedLimit: 0  # Mbps, Local settings override remote settings. 0 means disable
      DeviceLimit: 0  # Local settings override remote settings. 0 means disable
      RuleListPath: # /etc/XrayR/rulelist # Path to local rulelist file
    ControllerConfig:
      ListenIP: 0.0.0.0
      SendIP: 0.0.0.0
      UpdatePeriodic: 60  # Seconds to update node info and users
      CertConfig:
        CertMode: dns  # Option about how to get certificate: none, file, http, dns
        CertDomain: "node1.example.com"
        CertFile: /etc/XrayR/cert/node1.example.com.cert
        KeyFile: /etc/XrayR/cert/node1.example.com.key
        Provider: cloudflare  # DNS provider for ACME
        Email: admin@example.com
        DNSEnv:
          CLOUDFLARE_EMAIL: your_email@example.com
          CLOUDFLARE_API_KEY: your_cloudflare_api_key
```

### 3. Configure API Adapter

XrayR needs a custom API adapter for ORRISM. Create `/etc/XrayR/orrism_adapter.go`:

```go
package panel

import (
    "encoding/json"
    "fmt"
    "net/http"
    "time"
)

type ORRISMClient struct {
    client    *http.Client
    APIHost   string
    NodeID    int
    Key       string
}

func NewORRISMClient(apiConfig *ApiConfig) *ORRISMClient {
    return &ORRISMClient{
        client: &http.Client{
            Timeout: time.Duration(apiConfig.Timeout) * time.Second,
        },
        APIHost: apiConfig.APIHost,
        NodeID:  apiConfig.NodeID,
        Key:     apiConfig.ApiKey,
    }
}

// GetNodeInfo fetches node configuration
func (c *ORRISMClient) GetNodeInfo() (*NodeInfo, error) {
    url := fmt.Sprintf("%s/modules/servers/orrism/server/v1/nodes/%d", c.APIHost, c.NodeID)

    req, err := http.NewRequest("GET", url, nil)
    if err != nil {
        return nil, err
    }
    req.Header.Set("X-API-Key", c.Key)

    resp, err := c.client.Do(req)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()

    var result struct {
        Success bool `json:"success"`
        Data    struct {
            ID         int     `json:"id"`
            Name       string  `json:"name"`
            Server     string  `json:"server"`
            ServerPort int     `json:"server_port"`
            NodeType   string  `json:"node_type"`
            Rate       float64 `json:"rate"`
            Settings   string  `json:"settings"`
        } `json:"data"`
    }

    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return nil, err
    }

    if !result.Success {
        return nil, fmt.Errorf("API returned error")
    }

    // Parse settings JSON
    var settings map[string]interface{}
    json.Unmarshal([]byte(result.Data.Settings), &settings)

    return &NodeInfo{
        NodeID:   result.Data.ID,
        NodeType: result.Data.NodeType,
        Host:     result.Data.Server,
        Port:     result.Data.ServerPort,
        Rate:     result.Data.Rate,
        Settings: settings,
    }, nil
}

// GetUserList fetches users for this node
func (c *ORRISMClient) GetUserList() ([]*UserInfo, error) {
    url := fmt.Sprintf("%s/modules/servers/orrism/server/v1/nodes/%d/users", c.APIHost, c.NodeID)

    req, err := http.NewRequest("GET", url, nil)
    if err != nil {
        return nil, err
    }
    req.Header.Set("X-API-Key", c.Key)

    resp, err := c.client.Do(req)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()

    var result struct {
        Success bool `json:"success"`
        Data    struct {
            Users []struct {
                UserID         int    `json:"user_id"`
                UUID           string `json:"uuid"`
                Email          string `json:"email"`
                TransferEnable int64  `json:"transfer_enable"`
                U              int64  `json:"u"`
                D              int64  `json:"d"`
                Enable         int    `json:"enable"`
                ExpiredAt      int64  `json:"expired_at"`
            } `json:"users"`
        } `json:"data"`
    }

    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return nil, err
    }

    var users []*UserInfo
    for _, u := range result.Data.Users {
        users = append(users, &UserInfo{
            UID:         u.UserID,
            UUID:        u.UUID,
            Email:       u.Email,
            TransferEnable: u.TransferEnable,
            Upload:      u.U,
            Download:    u.D,
            Enable:      u.Enable == 1,
            ExpireTime:  u.ExpiredAt,
        })
    }

    return users, nil
}

// ReportUserTraffic reports traffic back to panel
func (c *ORRISMClient) ReportUserTraffic(traffic []*UserTraffic) error {
    url := fmt.Sprintf("%s/modules/servers/orrism/server/v1/traffic/report", c.APIHost)

    data := map[string]interface{}{
        "node_id": c.NodeID,
        "data":    traffic,
    }

    jsonData, err := json.Marshal(data)
    if err != nil {
        return err
    }

    req, err := http.NewRequest("POST", url, bytes.NewBuffer(jsonData))
    if err != nil {
        return err
    }
    req.Header.Set("X-API-Key", c.Key)
    req.Header.Set("Content-Type", "application/json")

    resp, err := c.client.Do(req)
    if err != nil {
        return err
    }
    defer resp.Body.Close()

    var result struct {
        Success bool `json:"success"`
    }

    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return err
    }

    if !result.Success {
        return fmt.Errorf("failed to report traffic")
    }

    return nil
}

// ReportNodeStatus reports node online status and load
func (c *ORRISMClient) ReportNodeStatus(online int, load float64) error {
    url := fmt.Sprintf("%s/modules/servers/orrism/server/v1/nodes/%d/heartbeat", c.APIHost, c.NodeID)

    data := map[string]interface{}{
        "online": online,
        "load":   load,
    }

    jsonData, err := json.Marshal(data)
    if err != nil {
        return err
    }

    req, err := http.NewRequest("POST", url, bytes.NewBuffer(jsonData))
    if err != nil {
        return err
    }
    req.Header.Set("X-API-Key", c.Key)
    req.Header.Set("Content-Type", "application/json")

    resp, err := c.client.Do(req)
    if err != nil {
        return err
    }
    defer resp.Body.Close()

    return nil
}
```

---

## Configuration Examples

### VMess Node

```yaml
Nodes:
  - PanelType: "V2board"
    ApiConfig:
      ApiHost: "https://your-whmcs.com"
      ApiKey: "your_node_api_key"
      NodeID: 1
      NodeType: V2ray
      Timeout: 30
      EnableVless: false
      EnableXTLS: false
    ControllerConfig:
      ListenIP: 0.0.0.0
      SendIP: 0.0.0.0
      UpdatePeriodic: 60
      CertConfig:
        CertMode: dns
        CertDomain: "vmess.example.com"
        Provider: cloudflare
        Email: admin@example.com
```

### Trojan Node

```yaml
Nodes:
  - PanelType: "V2board"
    ApiConfig:
      ApiHost: "https://your-whmcs.com"
      ApiKey: "your_node_api_key"
      NodeID: 2
      NodeType: Trojan
      Timeout: 30
    ControllerConfig:
      ListenIP: 0.0.0.0
      SendIP: 0.0.0.0
      UpdatePeriodic: 60
      CertConfig:
        CertMode: dns
        CertDomain: "trojan.example.com"
        Provider: cloudflare
        Email: admin@example.com
```

### Shadowsocks Node

```yaml
Nodes:
  - PanelType: "V2board"
    ApiConfig:
      ApiHost: "https://your-whmcs.com"
      ApiKey: "your_node_api_key"
      NodeID: 3
      NodeType: Shadowsocks
      Timeout: 30
    ControllerConfig:
      ListenIP: 0.0.0.0
      SendIP: 0.0.0.0
      UpdatePeriodic: 60
      CipherMethod: aes-256-gcm
```

### Multiple Nodes

```yaml
Nodes:
  - PanelType: "V2board"
    ApiConfig:
      ApiHost: "https://your-whmcs.com"
      ApiKey: "node1_api_key"
      NodeID: 1
      NodeType: V2ray
    ControllerConfig:
      ListenIP: 0.0.0.0
      UpdatePeriodic: 60

  - PanelType: "V2board"
    ApiConfig:
      ApiHost: "https://your-whmcs.com"
      ApiKey: "node2_api_key"
      NodeID: 2
      NodeType: Trojan
    ControllerConfig:
      ListenIP: 0.0.0.0
      UpdatePeriodic: 60
```

---

## ORRISM Node Configuration

In your ORRISM database, ensure nodes are properly configured:

```sql
-- VMess node example
INSERT INTO nodes (name, server, server_port, node_type, node_group, rate, enable, settings)
VALUES (
    'US Node 01',
    'us1.example.com',
    443,
    'vmess',
    1,
    1.0,
    1,
    '{"network":"ws","path":"/vmess","tls":"tls","host":"us1.example.com"}'
);

-- Trojan node example
INSERT INTO nodes (name, server, server_port, node_type, node_group, rate, enable, settings)
VALUES (
    'EU Node 01',
    'eu1.example.com',
    443,
    'trojan',
    1,
    1.0,
    1,
    '{"network":"tcp","security":"tls","host":"eu1.example.com"}'
);

-- Shadowsocks node example
INSERT INTO nodes (name, server, server_port, node_type, node_group, rate, enable, settings)
VALUES (
    'Asia Node 01',
    'asia1.example.com',
    8388,
    'shadowsocks',
    2,
    0.8,
    1,
    '{"method":"aes-256-gcm","plugin":"obfs","plugin_opts":"obfs=http;obfs-host=cloudflare.com"}'
);
```

---

## Testing XrayR Connection

### 1. Test API Connectivity

```bash
# Test health endpoint
curl -H "X-API-Key: your_api_key" \
  https://your-whmcs.com/modules/servers/orrism/server/v1/health

# Test node info
curl -H "X-API-Key: your_api_key" \
  https://your-whmcs.com/modules/servers/orrism/server/v1/nodes/1

# Test user list
curl -H "X-API-Key: your_api_key" \
  https://your-whmcs.com/modules/servers/orrism/server/v1/nodes/1/users
```

### 2. Start XrayR with Debug Logging

```bash
# Edit config to enable debug
sudo nano /etc/XrayR/config.yml

# Set Log Level to debug
Log:
  Level: debug

# Restart XrayR
sudo systemctl restart XrayR

# Check logs
sudo journalctl -u XrayR -f
```

### 3. Verify Node Registration

Check ORRISM logs in WHMCS:
- **Admin Area → System Logs → Module Log**
- Filter by: `orrism_server_api`

Expected log entries:
```
Node info request - Node: 1
User list request - Node: 1, Count: 25
Traffic report - Node: 1, Users: 25, Processed: 25
Heartbeat received - Node: 1, Load: 0.45, Online: 23
```

---

## Synchronization Workflow

XrayR synchronizes with ORRISM on a periodic schedule:

### 1. Node Initialization (on startup)
```
XrayR → GET /nodes/{id}
      ← Node config (server, port, settings)
```

### 2. User Sync (every UpdatePeriodic seconds)
```
XrayR → GET /nodes/{id}/users?timestamp=previous_sync_time
      ← User list (incremental if timestamp provided)
```

### 3. Traffic Reporting (every UpdatePeriodic seconds)
```
XrayR → POST /traffic/report
      ← Success/failure response
```

### 4. Heartbeat (every UpdatePeriodic seconds)
```
XrayR → POST /nodes/{id}/heartbeat
      ← Acknowledgment
```

---

## Performance Tuning

### Optimize Update Period

```yaml
ControllerConfig:
  UpdatePeriodic: 60  # Recommended: 60-120 seconds
```

**Guidelines:**
- **High traffic nodes**: 60 seconds (more frequent updates)
- **Low traffic nodes**: 120 seconds (reduce API load)
- **Minimum**: 30 seconds (don't go lower)

### Enable Incremental Sync

XrayR automatically uses incremental sync when available. ORRISM tracks `updated_at` timestamps to return only changed users.

**Benefits:**
- Reduced bandwidth usage
- Faster sync times
- Lower database load

### Connection Pooling

```yaml
ApiConfig:
  Timeout: 30  # API timeout
  # Keep connections alive
  KeepAlive: true
  MaxIdleConns: 10
```

---

## Troubleshooting

### XrayR Can't Connect to API

**Symptoms:**
- XrayR logs show connection errors
- No user list fetched

**Solutions:**
1. Verify API URL is accessible from node:
   ```bash
   curl https://your-whmcs.com/modules/servers/orrism/server/v1/health
   ```

2. Check firewall rules allow outbound HTTPS

3. Verify SSL certificate is valid:
   ```bash
   openssl s_client -connect your-whmcs.com:443
   ```

### Authentication Failed

**Symptoms:**
- XrayR logs show 401 errors
- "Invalid API key" in WHMCS logs

**Solutions:**
1. Verify API key in config matches ORRISM:
   ```sql
   SELECT id, name, api_key FROM nodes WHERE id = 1;
   ```

2. Check for extra spaces or newlines in API key

3. Regenerate API key if corrupted

### No Users Returned

**Symptoms:**
- XrayR receives empty user list
- Users exist in WHMCS

**Solutions:**
1. Verify users have correct node group:
   ```sql
   SELECT user_id, email, node_group_id, enable
   FROM user
   WHERE enable = 1;
   ```

2. Check service status in WHMCS (must be Active)

3. Verify node group ID matches between node and users

### Traffic Not Being Reported

**Symptoms:**
- Usage shows 0 in WHMCS
- XrayR logs show successful reports

**Solutions:**
1. Check XrayR user_id matches WHMCS user_id:
   ```bash
   # In XrayR logs, note user_id values
   # Compare with ORRISM database
   SELECT id, email FROM user;
   ```

2. Verify traffic report format:
   ```json
   {
     "node_id": 1,
     "data": [
       {"user_id": 123, "u": 1048576, "d": 2097152}
     ]
   }
   ```

3. Check WHMCS Module Log for errors

### High API Latency

**Symptoms:**
- XrayR sync takes long time
- Users experience connection delays

**Solutions:**
1. Enable Redis caching (ORRISM requirement)

2. Add database indexes (run enhancement SQL)

3. Use incremental sync

4. Increase `UpdatePeriodic` to reduce frequency

---

## Security Recommendations

### 1. Use Node-Specific API Keys

Instead of a global key, generate unique keys per node:

```sql
UPDATE nodes
SET api_key = SHA2(CONCAT(id, UUID(), RAND()), 256)
WHERE id IN (1, 2, 3);
```

**Benefits:**
- Key compromise only affects one node
- Easy to revoke individual node access
- Better audit trail

### 2. IP Whitelist

Configure ORRISM to only accept requests from node IPs:

**WHMCS Admin → ORRISM Settings:**
```
Server API IP Whitelist: 203.0.113.5,198.51.100.0/24
```

### 3. Monitor API Usage

Regular monitoring via WHMCS logs:

```sql
-- Check API activity from server_api_logs
SELECT
    node_id,
    endpoint,
    COUNT(*) as requests,
    AVG(execution_time) as avg_time
FROM server_api_logs
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY node_id, endpoint;
```

### 4. Rotate API Keys

Schedule periodic key rotation:

```bash
#!/bin/bash
# rotate_keys.sh - Run monthly via cron

mysql -u user -p orrism_db <<EOF
UPDATE nodes
SET api_key = SHA2(CONCAT(id, UUID(), RAND()), 256)
WHERE enable = 1;
EOF

# Update XrayR configs on each node
# (Implementation depends on your setup)
```

---

## Advanced Configuration

### Custom User Filters

If you need to filter users based on custom criteria, modify the API query in:

`/modules/servers/orrism/server/v1/handlers/NodeHandler.php`

Example - Only sync users with specific plan:

```php
// In NodeHandler::users() method
$sql = "SELECT u.* FROM user u
        JOIN tblhosting h ON u.service_id = h.id
        WHERE u.node_group_id = :node_group
        AND u.enable = 1
        AND h.packageid IN (1, 2, 3)";  -- Specific package IDs
```

### Custom Rate Limiting Per Node

Implement node-based rate limits in `RateLimit.php`:

```php
// Get node ID from auth middleware
$nodeId = $request->nodeId ?? 0;

// Set limit based on node
$limits = [
    1 => 120,  // Node 1: 120 req/min
    2 => 60,   // Node 2: 60 req/min
];
$this->maxRequests = $limits[$nodeId] ?? 60;
```

### Traffic Compression

For high-traffic nodes, compress API responses:

In `/server/v1/index.php`:

```php
// Enable gzip compression
if (extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}
```

---

## Migration from Other Panels

### From V2Board

V2Board uses a similar API structure. Main differences:

1. **Authentication**: V2Board uses token, ORRISM uses API key header
2. **Endpoints**: V2Board uses `/api/v1/`, ORRISM uses `/server/v1/`
3. **User ID field**: V2Board uses `id`, ORRISM uses `user_id`

Migration is straightforward - update XrayR config and adapter.

### From SSPanel

SSPanel has different API structure. You'll need to:

1. Map SSPanel fields to ORRISM fields
2. Adjust XrayR adapter for ORRISM endpoints
3. Migrate user database structure

Contact support for migration assistance.

---

## Support

For XrayR-specific issues:
- XrayR Documentation: https://xrayr-project.github.io/XrayR-doc/
- XrayR GitHub: https://github.com/XrayR-project/XrayR

For ORRISM API issues:
- Check WHMCS Module Log
- Review `README.md` in `/server/` directory
- Contact ORRISM support team

---

## Changelog

### v1.0.0 (2024-01-10)
- Initial release
- Support for VMess, Trojan, Shadowsocks
- Incremental sync support
- Redis caching
- Rate limiting
- Comprehensive logging
