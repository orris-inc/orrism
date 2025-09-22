# ORRISM WHMCS Module - Installation Guide

## Overview

The ORRISM WHMCS Module has been completely refactored to follow WHMCS standards and eliminate external database dependencies. This guide covers the installation process for the new version.

## Key Changes in Version 2.0

### Database Architecture
- **Before**: Required separate ShadowSocks database with manual configuration
- **After**: Uses WHMCS native database with `mod_orrism_` table prefix
- **Benefits**: Better integration, automatic backups, simplified maintenance

### Installation Process
- **Before**: Manual database creation and config file editing
- **After**: Web-based setup wizard with automatic table creation
- **Benefits**: One-click installation, guided setup, error handling

## System Requirements

- WHMCS 7.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Sufficient database permissions for table creation

## Installation Steps

### 1. Upload Module Files

Upload the `orrism` folder to your WHMCS installation:
```
/path/to/whmcs/modules/servers/orrism/
```

### 2. Run Setup Wizard

1. Access the setup page in your WHMCS admin panel:
   ```
   https://your-whmcs-domain.com/admin/modules/servers/orrism/admin/setup.php
   ```

2. The setup wizard will:
   - Test database connection
   - Install required tables
   - Configure default settings
   - Verify installation

### 3. Configure Module

1. Go to **Setup → Products/Services → Servers**
2. Add a new server or edit existing one
3. Set **Type** to "ORRISM ShadowSocks Manager"
4. Configure server details (these are now used for API endpoints, not database)

### 4. Create Product/Service

1. Go to **Setup → Products/Services → Products/Services**
2. Create a new product
3. Set **Module** to "ORRISM ShadowSocks Manager"
4. Configure module settings:
   - **Reset Strategy**: How traffic should be reset
   - **Node List**: Available node IDs (deprecated in v2.0)
   - **Bandwidth**: Monthly bandwidth limit in GB
   - **Manual Reset**: Allow customers to reset traffic manually
   - **Reset Cost**: Cost percentage for manual resets
   - **Node Group**: Default node group for new users

## Database Schema

The new schema includes the following tables:

### mod_orrism_users
Stores user accounts linked to WHMCS services
- Replaces old `user` table
- Links directly to WHMCS services and clients
- Tracks bandwidth usage and limits

### mod_orrism_nodes
Stores ShadowSocks node configurations
- Replaces old `nodes` table
- Enhanced with status tracking and grouping
- Better organization and management

### mod_orrism_user_usage
Detailed usage logging (optional)
- Replaces old `user_usage` table
- Enhanced with session tracking
- Better analytics capabilities

### mod_orrism_node_groups
Node access control groups
- New feature for better access management
- Allows different service tiers
- Bandwidth multipliers per group

### mod_orrism_config
Module configuration storage
- Centralized configuration management
- Type-safe value storage
- System vs user settings separation

### mod_orrism_migrations
Database version tracking
- Automatic migration support
- Version history tracking
- Rollback capability

## Migration from Legacy Version

If you're upgrading from the old version that used external databases:

### 1. Backup Your Data
```bash
# Backup your legacy ShadowSocks database
mysqldump -u username -p old_shadowsocks_db > backup.sql

# Backup WHMCS database
mysqldump -u username -p whmcs_db > whmcs_backup.sql
```

### 2. Install New Version
Follow the installation steps above to install the new version.

### 3. Run Migration Script
```php
// Include migration script
require_once '/path/to/whmcs/modules/servers/orrism/migration/legacy_data_migration.php';

// Configure legacy database connection
$legacyConfig = [
    'mysql_host' => 'localhost',
    'mysql_db' => 'old_shadowsocks_db',
    'mysql_user' => 'username',
    'mysql_pass' => 'password',
    'mysql_port' => 3306
];

// Run migration
$result = orrism_run_legacy_migration($legacyConfig);

if ($result['success']) {
    echo "Migration completed successfully!";
} else {
    echo "Migration failed: " . $result['message'];
}
```

### 4. Verify Migration
1. Check that all users appear in the new system
2. Verify node configurations
3. Test service creation and management
4. Confirm bandwidth tracking works

## Configuration Options

### Module Configuration
Available in **Setup → Products/Services → Products/Services → [Your Product] → Module Settings**:

| Option | Description | Default |
|--------|-------------|---------|
| Reset Strategy | Traffic reset behavior | No Reset |
| Bandwidth | Monthly bandwidth limit (GB) | 100 |
| Manual Reset | Allow customer traffic resets | No |
| Reset Cost | Cost percentage for manual resets | 0 |
| Node Group | Default node group ID | 1 |

### Advanced Configuration
Access via admin setup page or database configuration table:

| Setting | Description | Type |
|---------|-------------|------|
| auto_reset_traffic | Enable automatic monthly resets | Boolean |
| reset_day | Day of month for auto reset (1-28) | Integer |
| max_devices_per_user | Device limit per account | Integer |
| enable_usage_logging | Detailed usage tracking | Boolean |
| subscription_base_url | Base URL for subscriptions | String |
| default_encryption | Default encryption method | String |

## Node Management

### Adding Nodes
1. Use the WHMCS admin panel or direct database access
2. Insert into `mod_orrism_nodes` table:
```sql
INSERT INTO mod_orrism_nodes (
    node_name, address, port, node_method, 
    group_id, status, sort_order
) VALUES (
    'Node Name', 'server.example.com', 443, 
    'aes-256-gcm', 1, 1, 0
);
```

### Node Groups
Create different service tiers using node groups:
```sql
INSERT INTO mod_orrism_node_groups (
    name, description, bandwidth_ratio, max_devices
) VALUES (
    'Premium', 'High-speed premium nodes', 1.5, 5
);
```

## Troubleshooting

### Common Issues

#### Database Connection Failed
- Verify WHMCS database credentials
- Check MySQL user permissions
- Ensure PHP MySQL extension is installed

#### Tables Not Created
- Run setup wizard again
- Check database user has CREATE permissions
- Review WHMCS system logs

#### Migration Failed
- Verify legacy database accessibility
- Check data consistency in old database
- Review migration logs for specific errors

#### Service Creation Failed
- Ensure database tables are installed
- Verify module configuration
- Check WHMCS module logs

### Debug Mode
Enable debug logging in module configuration:
```php
// In configuration
'debug_mode' => true,
'log_level' => 'debug'
```

### Log Files
Check these locations for error information:
- WHMCS Activity Log
- Module Call Logs
- PHP Error Logs
- Database Error Logs

## Security Considerations

### Database Security
- All data stored in WHMCS database with proper access controls
- Sensitive fields encrypted where appropriate
- Regular backup integration with WHMCS

### API Security
- Module uses WHMCS authentication
- No separate API credentials required
- Inherits WHMCS security policies

### Data Privacy
- User data properly linked to WHMCS clients
- GDPR compliance through WHMCS features
- Audit trail for all operations

## Performance Optimization

### Database Optimization
- Indexed tables for fast queries
- Efficient data structures
- Optional usage logging to reduce overhead

### Caching
- Configuration caching
- Node list caching
- Usage statistics caching

### Monitoring
- Built-in performance metrics
- Usage tracking and analytics
- Resource utilization monitoring

## Support and Maintenance

### Regular Maintenance
- Database cleanup scripts included
- Automated traffic resets
- Log rotation and archival

### Updates
- Migration system for schema changes
- Backward compatibility maintenance
- Version tracking and rollback

### Support Channels
- GitHub Issues: [Repository URL]
- Documentation: This file and inline comments
- Community Forum: [Forum URL]

## API Reference

### Core Functions
- `orrism_CreateAccount()` - Create new user account
- `orrism_SuspendAccount()` - Suspend user access
- `orrism_TerminateAccount()` - Delete user account
- `orrism_ResetTraffic()` - Reset user bandwidth usage

### Database Helper
- `orrism_db()` - Get database helper instance
- `orrism_db_manager()` - Get database manager instance

### Configuration
- Module configuration via WHMCS admin panel
- Advanced settings via setup wizard
- Runtime configuration API

This completes the installation and migration guide. The new version provides a much more robust and maintainable solution that follows WHMCS best practices.