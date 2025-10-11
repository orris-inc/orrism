<style>
.orrism-client-area {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.orrism-panel {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 20px;
    overflow: hidden;
}

.orrism-panel-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 15px 20px;
    font-size: 16px;
    font-weight: 600;
}

.orrism-panel-body {
    padding: 20px;
}

.orrism-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.orrism-stat-card {
    background: #f8f9fa;
    border-left: 4px solid #667eea;
    padding: 15px;
    border-radius: 4px;
}

.orrism-stat-label {
    color: #6c757d;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.orrism-stat-value {
    color: #212529;
    font-size: 24px;
    font-weight: 600;
}

.orrism-stat-unit {
    color: #6c757d;
    font-size: 14px;
    font-weight: 400;
}

.orrism-progress-bar {
    background: #e9ecef;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
    position: relative;
    margin: 10px 0;
}

.orrism-progress-fill {
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    height: 100%;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}

.orrism-progress-fill.warning {
    background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
}

.orrism-progress-fill.danger {
    background: linear-gradient(90deg, #fa709a 0%, #fee140 100%);
}

.orrism-subscription-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.orrism-url-display {
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 10px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    word-break: break-all;
    margin: 10px 0;
}

.orrism-client-tabs {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.orrism-client-tab {
    background: #fff;
    border: 2px solid #667eea;
    color: #667eea;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.orrism-client-tab:hover {
    background: #667eea;
    color: #fff;
    text-decoration: none;
}

.orrism-qr-container {
    text-align: center;
    padding: 15px;
    background: #fff;
    border-radius: 6px;
    margin: 15px 0;
}

.orrism-alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.orrism-alert-info {
    background: #d1ecf1;
    border-left: 4px solid #0c5460;
    color: #0c5460;
}

.orrism-alert-warning {
    background: #fff3cd;
    border-left: 4px solid #856404;
    color: #856404;
}

.orrism-alert-success {
    background: #d4edda;
    border-left: 4px solid #155724;
    color: #155724;
}

.orrism-btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.orrism-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.orrism-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: #fff;
    text-decoration: none;
}

.orrism-btn-outline {
    background: transparent;
    border: 2px solid #667eea;
    color: #667eea;
}

.orrism-btn-outline:hover {
    background: #667eea;
    color: #fff;
}

.orrism-node-list {
    list-style: none;
    padding: 0;
    margin: 15px 0;
}

.orrism-node-item {
    background: #f8f9fa;
    border-left: 3px solid #667eea;
    padding: 12px 15px;
    margin-bottom: 8px;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.orrism-node-name {
    font-weight: 600;
    color: #212529;
}

.orrism-node-info {
    color: #6c757d;
    font-size: 14px;
}

.orrism-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.orrism-badge-success {
    background: #d4edda;
    color: #155724;
}

.orrism-badge-warning {
    background: #fff3cd;
    color: #856404;
}

.orrism-badge-danger {
    background: #f8d7da;
    color: #721c24;
}

@media (max-width: 768px) {
    .orrism-stats-grid {
        grid-template-columns: 1fr;
    }

    .orrism-client-tabs {
        flex-direction: column;
    }

    .orrism-client-tab {
        text-align: center;
    }
}
</style>

<div class="orrism-client-area">
    <!-- Account Status -->
    <div class="orrism-panel">
        <div class="orrism-panel-header">
            <i class="fas fa-info-circle"></i> Account Information
        </div>
        <div class="orrism-panel-body">
            <div class="orrism-stats-grid">
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Account Email</div>
                    <div class="orrism-stat-value" style="font-size: 16px;">{$email}</div>
                </div>
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Status</div>
                    <div class="orrism-stat-value">
                        {if $status eq 'active'}
                            <span class="orrism-badge orrism-badge-success">Active</span>
                        {elseif $status eq 'suspended'}
                            <span class="orrism-badge orrism-badge-warning">Suspended</span>
                        {else}
                            <span class="orrism-badge orrism-badge-danger">{$status|ucfirst}</span>
                        {/if}
                    </div>
                </div>
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Max Devices</div>
                    <div class="orrism-stat-value">{$maxDevices}</div>
                </div>
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Last Reset</div>
                    <div class="orrism-stat-value" style="font-size: 14px;">
                        {if $lastReset}
                            {$lastReset}
                        {else}
                            Never
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Usage -->
    <div class="orrism-panel">
        <div class="orrism-panel-header">
            <i class="fas fa-chart-line"></i> Traffic Usage
        </div>
        <div class="orrism-panel-body">
            <div class="orrism-stats-grid">
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Total Bandwidth</div>
                    <div class="orrism-stat-value">{$totalBandwidth} <span class="orrism-stat-unit">GB</span></div>
                </div>
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Used</div>
                    <div class="orrism-stat-value">{$usedBandwidth} <span class="orrism-stat-unit">GB</span></div>
                </div>
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Remaining</div>
                    <div class="orrism-stat-value">{$remainingBandwidth} <span class="orrism-stat-unit">GB</span></div>
                </div>
                <div class="orrism-stat-card">
                    <div class="orrism-stat-label">Upload / Download</div>
                    <div class="orrism-stat-value" style="font-size: 16px;">
                        {$uploadGB} GB / {$downloadGB} GB
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="orrism-progress-bar">
                <div class="orrism-progress-fill {if $usagePercent >= 90}danger{elseif $usagePercent >= 75}warning{/if}"
                     style="width: {$usagePercent}%">
                    {$usagePercent}%
                </div>
            </div>

            {if $lastReset}
                <p style="color: #6c757d; font-size: 14px; margin-top: 10px;">
                    <i class="fas fa-redo"></i> Last Reset: {$lastReset}
                </p>
            {/if}

            {if $allowReset}
                <div class="orrism-alert orrism-alert-info">
                    <i class="fas fa-info-circle"></i> Manual traffic reset is available.
                    {if $resetCost > 0}Cost: {$resetCost}% of monthly fee.{/if}
                </div>
            {/if}
        </div>
    </div>

    <!-- Subscription Links -->
    <div class="orrism-panel">
        <div class="orrism-panel-header">
            <i class="fas fa-link"></i> Subscription Links
        </div>
        <div class="orrism-panel-body">
            <div class="orrism-subscription-box">
                <h4 style="margin-top: 0;">Universal Subscription URL</h4>
                <div class="orrism-url-display" id="subUrl">{$subscriptionUrl}</div>
                <button class="orrism-btn orrism-btn-primary" onclick="copyToClipboard('subUrl')">
                    <i class="fas fa-copy"></i> Copy URL
                </button>
            </div>

            <h4><i class="fas fa-mobile-alt"></i> Client-Specific Links</h4>
            <div class="orrism-client-tabs">
                <a href="{$subscriptionUrl}?client=clash" class="orrism-client-tab" target="_blank">
                    <i class="fas fa-download"></i> Clash
                </a>
                <a href="{$subscriptionUrl}?client=v2ray" class="orrism-client-tab" target="_blank">
                    <i class="fas fa-download"></i> V2Ray
                </a>
                <a href="{$subscriptionUrl}?client=shadowrocket" class="orrism-client-tab" target="_blank">
                    <i class="fas fa-download"></i> Shadowrocket
                </a>
                <a href="{$subscriptionUrl}?client=surge" class="orrism-client-tab" target="_blank">
                    <i class="fas fa-download"></i> Surge
                </a>
                <a href="{$subscriptionUrl}?client=quantumult" class="orrism-client-tab" target="_blank">
                    <i class="fas fa-download"></i> Quantumult X
                </a>
                <a href="{$subscriptionUrl}?client=sing-box" class="orrism-client-tab" target="_blank">
                    <i class="fas fa-download"></i> Sing-Box
                </a>
            </div>

            <div class="orrism-alert orrism-alert-info">
                <i class="fas fa-info-circle"></i> <strong>How to use:</strong> Copy the subscription URL and paste it into your client application's subscription settings.
            </div>

            <!-- QR Code -->
            <div class="orrism-qr-container">
                <h4><i class="fas fa-qrcode"></i> QR Code for Mobile</h4>
                <div id="qrcode" style="display: inline-block;"></div>
                <p style="color: #6c757d; font-size: 14px; margin-top: 10px;">
                    Scan with your mobile client to import subscription
                </p>
            </div>
        </div>
    </div>

    <!-- Available Nodes -->
    {if $nodes}
    <div class="orrism-panel">
        <div class="orrism-panel-header">
            <i class="fas fa-server"></i> Available Nodes ({$nodes|@count})
        </div>
        <div class="orrism-panel-body">
            <ul class="orrism-node-list">
                {foreach from=$nodes item=node}
                <li class="orrism-node-item">
                    <div>
                        <div class="orrism-node-name">
                            <i class="fas fa-globe"></i> {$node->node_name}
                        </div>
                        <div class="orrism-node-info">
                            {$node->type|ucfirst} | {$node->address}:{$node->port}
                        </div>
                    </div>
                    <div>
                        {if $node->status eq 1 || $node->status eq 'active'}
                            <span class="orrism-badge orrism-badge-success">Online</span>
                        {else}
                            <span class="orrism-badge orrism-badge-warning">Offline</span>
                        {/if}
                    </div>
                </li>
                {/foreach}
            </ul>
        </div>
    </div>
    {/if}

    <!-- Client Download Links -->
    <div class="orrism-panel">
        <div class="orrism-panel-header">
            <i class="fas fa-download"></i> Client Downloads
        </div>
        <div class="orrism-panel-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <h5><i class="fab fa-windows"></i> Windows</h5>
                    <a href="https://github.com/2dust/v2rayN/releases" target="_blank" class="orrism-btn orrism-btn-outline" style="width: 100%; text-align: center; margin-top: 10px;">
                        V2RayN
                    </a>
                </div>
                <div style="padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <h5><i class="fab fa-apple"></i> macOS</h5>
                    <a href="https://github.com/yichengchen/clashX/releases" target="_blank" class="orrism-btn orrism-btn-outline" style="width: 100%; text-align: center; margin-top: 10px;">
                        ClashX
                    </a>
                </div>
                <div style="padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <h5><i class="fab fa-android"></i> Android</h5>
                    <a href="https://github.com/2dust/v2rayNG/releases" target="_blank" class="orrism-btn orrism-btn-outline" style="width: 100%; text-align: center; margin-top: 10px;">
                        V2RayNG
                    </a>
                </div>
                <div style="padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <h5><i class="fab fa-app-store-ios"></i> iOS</h5>
                    <a href="https://apps.apple.com/app/shadowrocket/id932747118" target="_blank" class="orrism-btn orrism-btn-outline" style="width: 100%; text-align: center; margin-top: 10px;">
                        Shadowrocket
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
{literal}
// Generate QR Code
new QRCode(document.getElementById("qrcode"), {
    text: "{/literal}{$subscriptionUrl}{literal}",
    width: 200,
    height: 200,
    colorDark: "#667eea",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

// Copy to Clipboard Function
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Subscription URL copied to clipboard!');
        }).catch(err => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        showNotification('Subscription URL copied to clipboard!');
    } catch (err) {
        showNotification('Failed to copy. Please copy manually.', 'error');
    }
    document.body.removeChild(textArea);
}

function showNotification(message, type) {
    var bgColor = (type === 'success') ? '#d4edda' : '#f8d7da';
    var textColor = (type === 'success') ? '#155724' : '#721c24';

    const notification = document.createElement('div');
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10000; font-weight: 500;';
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(function() {
        notification.style.transition = 'opacity 0.3s';
        notification.style.opacity = '0';
        setTimeout(function() {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}
{/literal}
</script>
