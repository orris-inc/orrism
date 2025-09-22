{if $errormessage}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> {$errormessage}
    </div>
{else}
    <!-- Service Overview -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-server"></i> 
                Service Overview
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Account Information</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Service ID:</strong></td>
                            <td>{$serviceid}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{$email}</td>
                        </tr>
                        <tr>
                            <td><strong>UUID:</strong></td>
                            <td>
                                <code id="uuid-display">{$uuid}</code>
                                <button type="button" class="btn btn-sm btn-outline-secondary ml-2" onclick="copyToClipboard('{$uuid}')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5>Bandwidth Usage</h5>
                    <div class="progress mb-2" style="height: 25px;">
                        <div class="progress-bar {if $usagePercent > 80}bg-danger{elseif $usagePercent > 60}bg-warning{else}bg-success{/if}" 
                             role="progressbar" 
                             style="width: {$usagePercent}%" 
                             aria-valuenow="{$usagePercent}" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            {$usagePercent}%
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">Used</small><br>
                            <strong>{$usedBandwidth} GB</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Total</small><br>
                            <strong>{$totalBandwidth} GB</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Remaining</small><br>
                            <strong>{$remainingBandwidth} GB</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Details -->
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar"></i> 
                Traffic Details
            </h3>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <i class="fas fa-upload fa-2x text-primary mb-2"></i>
                            <h4>{$uploadGB} GB</h4>
                            <p class="mb-0">Upload</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <i class="fas fa-download fa-2x text-success mb-2"></i>
                            <h4>{$downloadGB} GB</h4>
                            <p class="mb-0">Download</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscription & Import -->
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-link"></i> 
                Subscription & Import
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="subscription-url" value="{$subscriptionUrl}" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('{$subscriptionUrl}')">
                                <i class="fas fa-copy"></i> Copy URL
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="btn-group" role="group">
                        <a href="clash://install-config?url={$subscriptionUrl|urlencode}" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> Clash
                        </a>
                        <a href="quantumult-x://add-resource?url={$subscriptionUrl|urlencode}" class="btn btn-info">
                            <i class="fas fa-external-link-alt"></i> QuantumultX
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Nodes -->
    {if $nodes}
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-network-wired"></i> 
                Available Nodes
            </h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Node Name</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Rate</th>
                            <th>Load</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$nodes item=node}
                        <tr>
                            <td>
                                <i class="fas fa-server text-muted mr-2"></i>
                                {$node->name}
                            </td>
                            <td>{$node->node_ip}</td>
                            <td>
                                {if $node->status == 1}
                                    <span class="badge badge-success">Online</span>
                                {else}
                                    <span class="badge badge-danger">Offline</span>
                                {/if}
                            </td>
                            <td>{$node->rate}x</td>
                            <td>
                                <div class="progress" style="height: 15px;">
                                    <div class="progress-bar bg-info" 
                                         role="progressbar" 
                                         style="width: {$node->load|default:0}%">
                                    </div>
                                </div>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {/if}

    <!-- Actions -->
    {if $allowReset}
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-cog"></i> 
                Actions
            </h3>
        </div>
        <div class="card-body">
            <form method="post" action="{$modulelink}" onsubmit="return confirm('Are you sure you want to reset your traffic? {if $resetCost > 0}This will cost {$resetCost}% of your service price.{/if}')">
                <input type="hidden" name="modop" value="custom">
                <input type="hidden" name="a" value="ClientResetTraffic">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-redo"></i> 
                    Reset Traffic
                    {if $resetCost > 0}
                        (Cost: {$resetCost}%)
                    {/if}
                </button>
            </form>
        </div>
    </div>
    {/if}
{/if}

<script>
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showAlert('Copied to clipboard!', 'success');
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        var successful = document.execCommand('copy');
        showAlert(successful ? 'Copied to clipboard!' : 'Copy failed', successful ? 'success' : 'danger');
    } catch (err) {
        console.error('Fallback: Could not copy text: ', err);
        showAlert('Copy failed', 'danger');
    }
    document.body.removeChild(textArea);
}

function showAlert(message, type) {
    // Simple alert implementation
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
    alertDiv.innerHTML = message + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';
    
    var container = document.querySelector('.card');
    if (container) {
        container.parentNode.insertBefore(alertDiv, container);
        setTimeout(function() {
            alertDiv.remove();
        }, 3000);
    }
}
</script>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.card-title {
    margin-bottom: 0;
    font-size: 1.1rem;
    font-weight: 500;
}

.progress {
    background-color: #e9ecef;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.btn-group .btn {
    margin-right: 0.25rem;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>