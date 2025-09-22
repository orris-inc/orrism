<?php
/**
 * ORRISM Module Setup Page
 * Database installation and configuration management
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

use WHMCS\Database\Capsule;

// Check admin authentication
if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once dirname(__DIR__) . '/includes/database_manager.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $dbManager = orrism_db_manager();
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    switch ($_POST['action']) {
        case 'test_connection':
            $response = $dbManager->testConnection();
            break;
            
        case 'install_database':
            $response = $dbManager->install();
            break;
            
        case 'migrate_database':
            $response = $dbManager->migrate();
            break;
            
        case 'uninstall_database':
            $keepData = isset($_POST['keep_data']) && $_POST['keep_data'] === '1';
            $response = $dbManager->uninstall($keepData);
            break;
            
        case 'get_status':
            $status = $dbManager->getStatus();
            $response = ['success' => true, 'data' => $status];
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Get current status
$dbManager = orrism_db_manager();
$status = $dbManager->getStatus();

// Page title
$pagetitle = 'ORRISM Module Setup';

?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">ORRISM Module Setup</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">ORRISM Setup</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Status Alert -->
            <div id="status-alert" class="alert" style="display: none;"></div>
            
            <!-- Database Status Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database"></i> Database Status
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="refreshStatus()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Installation Status:</strong></td>
                                    <td>
                                        <span id="install-status" class="badge">
                                            <?php echo $status['installed'] ? 'Installed' : 'Not Installed'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Current Version:</strong></td>
                                    <td id="current-version"><?php echo $status['current_version'] ?: 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Latest Version:</strong></td>
                                    <td id="latest-version"><?php echo $status['latest_version']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Migration Required:</strong></td>
                                    <td>
                                        <span id="migration-status" class="badge">
                                            <?php echo $status['needs_migration'] ? 'Yes' : 'No'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Database Tables</h5>
                            <div id="table-status">
                                <?php foreach ($status['tables'] as $table => $info): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?php echo $table; ?></span>
                                    <span>
                                        <?php if ($info['exists']): ?>
                                            <span class="badge badge-success">
                                                <?php echo $info['records']; ?> records
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Missing</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tools"></i> Database Actions
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-info btn-block" onclick="testConnection()">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                        </div>
                        <div class="col-md-3">
                            <?php if (!$status['installed']): ?>
                            <button type="button" class="btn btn-success btn-block" onclick="installDatabase()">
                                <i class="fas fa-download"></i> Install Database
                            </button>
                            <?php elseif ($status['needs_migration']): ?>
                            <button type="button" class="btn btn-warning btn-block" onclick="migrateDatabase()">
                                <i class="fas fa-arrow-up"></i> Migrate Database
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-block" disabled>
                                <i class="fas fa-check"></i> Up to Date
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <?php if ($status['installed']): ?>
                            <button type="button" class="btn btn-warning btn-block" onclick="showUninstallModal()">
                                <i class="fas fa-trash"></i> Uninstall
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-block" disabled>
                                <i class="fas fa-trash"></i> Uninstall
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <a href="../orris.php" class="btn btn-primary btn-block">
                                <i class="fas fa-cog"></i> Module Config
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Installation Guide Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-book"></i> Installation Guide
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>First Time Setup</h5>
                            <ol>
                                <li>Click "Test Connection" to verify database access</li>
                                <li>Click "Install Database" to create all required tables</li>
                                <li>Configure your first ShadowSocks nodes</li>
                                <li>Create product packages in WHMCS</li>
                                <li>Assign the ORRISM module to your products</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h5>Upgrading</h5>
                            <ol>
                                <li>Backup your database before upgrading</li>
                                <li>Update module files</li>
                                <li>Click "Migrate Database" if required</li>
                                <li>Test functionality with existing accounts</li>
                                <li>Clear any cached configurations</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Uninstall Confirmation Modal -->
<div class="modal fade" id="uninstallModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Uninstallation</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will remove ORRISM database tables.
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="keepDataCheck">
                        <label class="custom-control-label" for="keepDataCheck">
                            Keep user data (recommended for maintenance)
                        </label>
                    </div>
                </div>
                <p>Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="uninstallDatabase()">
                    Uninstall
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initial status update
    updateStatusDisplay();
});

function showAlert(message, type = 'info') {
    const alertDiv = $('#status-alert');
    alertDiv.removeClass('alert-success alert-danger alert-warning alert-info')
           .addClass('alert-' + type)
           .html(message)
           .show();
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertDiv.fadeOut();
    }, 5000);
}

function refreshStatus() {
    $.post('', {action: 'get_status'}, function(response) {
        if (response.success) {
            updateStatusDisplayFromData(response.data);
            showAlert('Status refreshed successfully', 'success');
        } else {
            showAlert('Failed to refresh status', 'danger');
        }
    }, 'json');
}

function updateStatusDisplay() {
    // This function updates the display based on current PHP status
    // Called on page load
}

function updateStatusDisplayFromData(status) {
    // Update install status
    $('#install-status').removeClass('badge-success badge-danger')
                       .addClass(status.installed ? 'badge-success' : 'badge-danger')
                       .text(status.installed ? 'Installed' : 'Not Installed');
    
    // Update version info
    $('#current-version').text(status.current_version || 'N/A');
    $('#latest-version').text(status.latest_version);
    
    // Update migration status
    $('#migration-status').removeClass('badge-success badge-warning')
                          .addClass(status.needs_migration ? 'badge-warning' : 'badge-success')
                          .text(status.needs_migration ? 'Yes' : 'No');
    
    // Update table status
    let tableHtml = '';
    for (const [table, info] of Object.entries(status.tables)) {
        const badgeClass = info.exists ? 'badge-success' : 'badge-danger';
        const badgeText = info.exists ? `${info.records} records` : 'Missing';
        
        tableHtml += `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>${table}</span>
                <span class="badge ${badgeClass}">${badgeText}</span>
            </div>
        `;
    }
    $('#table-status').html(tableHtml);
}

function testConnection() {
    showAlert('Testing database connection...', 'info');
    
    $.post('', {action: 'test_connection'}, function(response) {
        const type = response.success ? 'success' : 'danger';
        showAlert(response.message, type);
    }, 'json');
}

function installDatabase() {
    if (!confirm('This will create all ORRISM database tables. Continue?')) {
        return;
    }
    
    showAlert('Installing database tables...', 'info');
    
    $.post('', {action: 'install_database'}, function(response) {
        const type = response.success ? 'success' : 'danger';
        showAlert(response.message, type);
        
        if (response.success) {
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    }, 'json');
}

function migrateDatabase() {
    if (!confirm('This will update the database schema. Continue?')) {
        return;
    }
    
    showAlert('Migrating database...', 'info');
    
    $.post('', {action: 'migrate_database'}, function(response) {
        const type = response.success ? 'success' : 'danger';
        showAlert(response.message, type);
        
        if (response.success) {
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    }, 'json');
}

function showUninstallModal() {
    $('#uninstallModal').modal('show');
}

function uninstallDatabase() {
    const keepData = $('#keepDataCheck').is(':checked') ? 1 : 0;
    
    showAlert('Uninstalling database...', 'warning');
    $('#uninstallModal').modal('hide');
    
    $.post('', {
        action: 'uninstall_database',
        keep_data: keepData
    }, function(response) {
        const type = response.success ? 'success' : 'danger';
        showAlert(response.message, type);
        
        if (response.success) {
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    }, 'json');
}
</script>

<style>
.badge {
    font-size: 0.875em;
}

.card-tools .btn {
    padding: 0.25rem 0.5rem;
}

.table td {
    padding: 0.5rem 0.75rem;
}

.modal-body .alert {
    margin-bottom: 1rem;
}
</style>