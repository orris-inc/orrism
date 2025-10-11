<style>
.orrism-error-container {
    padding: 40px 20px;
    text-align: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.orrism-error-icon {
    font-size: 64px;
    color: #dc3545;
    margin-bottom: 20px;
}

.orrism-error-title {
    font-size: 24px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 10px;
}

.orrism-error-message {
    font-size: 16px;
    color: #6c757d;
    margin-bottom: 30px;
}

.orrism-error-btn {
    display: inline-block;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: transform 0.2s;
}

.orrism-error-btn:hover {
    transform: translateY(-2px);
    color: #fff;
    text-decoration: none;
}
</style>

<div class="orrism-error-container">
    <div class="orrism-error-icon">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    <div class="orrism-error-title">Service Error</div>
    <div class="orrism-error-message">
        {if $errormessage}
            {$errormessage}
        {else}
            An error occurred while loading your service information.
        {/if}
    </div>
    <a href="clientarea.php" class="orrism-error-btn">
        <i class="fas fa-arrow-left"></i> Back to Client Area
    </a>
</div>
