<script src="https://milus.one/modules/servers/orris/frontend/3.4.15"></script>
<link href="https://milus.one/modules/servers/orris/frontend/all.min.css" rel="stylesheet">
<script src="https://milus.one/modules/servers/orris/frontend/clipboard.js"></script>
<script src="https://milus.one/modules/servers/orris/frontend/layer.min.js"></script>
<style>
    .section-title {
        margin: 2rem 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #6366f1;
    }
    .card {
        transition: all 0.3s;
        border-radius: 0.75rem;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .stats-card {
        height: 100%;
    }
    .subscribe-item {
        padding: 1rem;
        border-radius: 0.5rem;
        transition: all 0.3s;
        text-align: center;
    }
    .subscribe-item:hover {
        background-color: #f7f7f7;
        transform: translateY(-5px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .subscribe-item img {
        margin: 0 auto;
        display: block;
        width: 3rem;
        height: 3rem;
    }
    .table-container {
        border-radius: 0.5rem;
        overflow: hidden;
        overflow-x: auto; /* Add horizontal scroll for small screens */
    }
    .container-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
    .traffic-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f3f4f6;
    }
    .traffic-item:last-child {
        border-bottom: none;
    }
    .traffic-label {
        color: #4b5563;
        font-size: 0.875rem;
    }
    .traffic-value {
        font-weight: 600;
        color: #1f2937;
        word-break: break-all; /* Prevent long values from overflowing on mobile */
        max-width: 70%; /* Limit value width on mobile */
        text-align: right;
    }
    .progress-container {
        margin-top: 1.75rem;
        padding-top: 1.25rem;
        border-top: 1px solid #f3f4f6;
    }
    .progress-outer {
        background-color: #f3f4f6;
        border-radius: 9999px;
        height: 0.5rem;
        position: relative;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
    }
    .progress-inner {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        background: linear-gradient(90deg, #3b82f6, #6366f1);
        border-radius: 9999px;
        transition: width 0.5s ease;
    }
    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    .progress-text {
        font-size: 0.8rem;
        color: #4b5563;
        font-weight: 500;
    }
    .progress-percentage {
        font-size: 0.8rem;
        font-weight: 600;
        color: #3b82f6;
    }
    .traffic-card {
        border: none;
        border-radius: 0.75rem;
        padding: 1rem;
        background-color: white;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        transition: all 0.25s ease;
    }
    .traffic-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    }
    .traffic-card-content {
        display: flex;
        align-items: center;
        width: 100%;
    }
    .traffic-card-icon {
        background-color: rgba(99, 102, 241, 0.08);
        height: 1.75rem;
        width: 1.75rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.7rem;
        flex-shrink: 0;
    }
    .traffic-card-icon i {
        color: #6366f1;
        font-size: 0.75rem;
    }
    .traffic-card-label {
        color: #6b7280;
        font-size: 0.85rem;
        font-weight: 400;
        letter-spacing: 0.01em;
        margin-right: auto;
        white-space: nowrap;
        flex: 1;
        min-width: 0;
    }
    .traffic-card-value {
        font-size: 0.95rem;
        font-weight: normal;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: right;
        padding-left: 0.5rem;
        flex: 0 0 auto;
        min-width: 50px;
        color: #374151;
    }
    .expire-date {
        background: #f0f9ff;
        border-left: 3px solid #3b82f6;
        padding: 0.75rem;
        margin-bottom: 1rem;
        border-radius: 0.25rem;
        display: flex;
        align-items: center;
    }
    .expire-date-card {
        background: #fff3cd;
        border-left: 3px solid #ffc107;
        padding: 0.75rem;
        margin-bottom: 1rem;
        border-radius: 0.25rem;
        display: flex;
        align-items: center;
    }
    .expire-date-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.06);
    }
    .expire-date-content {
        display: flex;
        align-items: center;
        width: 100%;
        gap: 0.5rem;
    }
    .expire-date-icon {
        background-color: rgba(255, 193, 7, 0.08);
        height: 1.75rem;
        width: 1.75rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.7rem;
        flex-shrink: 0;
    }
    .expire-date-icon i {
        color: #ffc107;
        font-size: 0.75rem;
    }
    .expire-date-label {
        color: #b68d40;
        font-size: 0.95rem;
        font-weight: 500;
    }
    .expire-date-value {
        color: #d4a017;
        font-size: 1rem;
        font-weight: 600;
        margin-left: auto;
    }
    /* Mobile-specific styles */
    @media (max-width: 640px) {
        .container-card {
            padding: 0.85rem;
        }
        .p-6 {
            padding: 1rem !important;
        }
        .p-4 {
            padding: 0.75rem !important;
        }
        .text-2xl {
            font-size: 1.25rem !important;
        }
        .subscribe-item {
            padding: 0.75rem;
        }
        .subscribe-item img {
            width: 2.5rem;
            height: 2.5rem;
        }
        .traffic-card {
            padding: 0.75rem;
        }
        .traffic-card-icon {
            height: 1.5rem;
            width: 1.5rem;
            margin-right: 0.4rem;
        }
        .traffic-card-value {
            font-size: 0.85rem;
        }
        .traffic-card-label {
            font-size: 0.8rem;
        }
        .expire-date-card {
            padding: 0.6rem 0.5rem;
        }
        .expire-date-icon {
            height: 1.5rem;
            width: 1.5rem;
            margin-right: 0.4rem;
        }
        .expire-date-value {
            font-size: 1rem;
        }
        .expire-date-label {
            font-size: 0.8rem;
        }
        .progress-text, .progress-percentage {
            font-size: 0.75rem;
        }
    }
</style>

<!--Overlay Effect-->
<div class="fixed hidden inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" id="mask"></div>
<div id="kurenai_ss_manage" systemurl="{$systemurl}" class="p-2 sm:p-4">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white shadow-xl rounded-xl p-4 sm:p-6 text-gray-700">
            
            <!-- User Info -->
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900 border-b border-gray-200 pb-3 mb-4 sm:mb-6">
                <i class="fa-solid fa-user-circle mr-2 text-indigo-500"></i>{$product}
                <span class="text-sm text-gray-500 ml-2">#{$serviceid}</span>
            </h1>
            
            
            <!-- Statistics -->
            <h2 class="section-title text-lg sm:text-xl font-semibold text-gray-900">
                <i class="fa-solid fa-chart-line mr-2 text-indigo-500"></i>流量统计
            </h2>
            
            <div class="container-card">
                <!-- Expiration Date Banner -->
                <div class="expire-date-card">
                    <div class="expire-date-content">
                        <div class="expire-date-icon">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                        <div class="expire-date-label">到期时间：</div>
                        <div class="expire-date-value ml-auto pr-2">{$nextduedate}</div>
                    </div>
                </div>
                
                <!-- Traffic Stats Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                    <div class="traffic-card">
                        <div class="traffic-card-content">
                            <div class="traffic-card-icon">
                                <i class="fa-solid fa-database"></i>
                            </div>
                            <div class="traffic-card-label">服务总流量</div>
                            <div class="traffic-card-value">{$user['bandwidth']}</div>
                        </div>
                    </div>
                    
                    <div class="traffic-card">
                        <div class="traffic-card-content">
                            <div class="traffic-card-icon">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <div class="traffic-card-label">已用流量</div>
                            <div class="traffic-card-value">{$user['total_used']}</div>
                        </div>
                    </div>
                    
                    <div class="traffic-card">
                        <div class="traffic-card-content">
                            <div class="traffic-card-icon">
                                <i class="fa-solid fa-arrow-up"></i>
                            </div>
                            <div class="traffic-card-label">已用上传</div>
                            <div class="traffic-card-value">{$user['upload']}</div>
                        </div>
                    </div>
                    
                    <div class="traffic-card">
                        <div class="traffic-card-content">
                            <div class="traffic-card-icon">
                                <i class="fa-solid fa-arrow-down"></i>
                            </div>
                            <div class="traffic-card-label">已用下载</div>
                            <div class="traffic-card-value">{$user['download']}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress bar for bandwidth usage -->
                <div class="progress-container">
                    <div class="progress-label">
                        <span class="progress-text">流量使用情况</span>
                        <span class="progress-percentage" id="progress-percentage">0%</span>
                    </div>
                    <div class="progress-outer">
                        <div class="progress-inner" id="progress-bar"></div>
                    </div>
                    <div class="flex justify-between mt-3 text-sm font-normal text-gray-600">
                        <span>已用: {$user['total_used']}</span>
                        <span>总计: {$user['bandwidth']}</span>
                    </div>
                </div>
            </div>
            
            <!-- Subscribe -->
            <h2 class="section-title text-lg sm:text-xl font-semibold text-gray-900">
                <i class="fa-solid fa-link mr-2 text-indigo-500"></i>Subscribe
            </h2>
            
            <div class="container-card">
                <div class="grid grid-cols-3 md:grid-cols-6 gap-2 sm:gap-4">
                    <div class="subscribe-item">
                        <a data-clipboard-text="https://{$subscribe_url}/checkmate/?token={$user['token']}&sid={$user['sid']}&app=ss" class="copy flex flex-col items-center text-gray-500 hover:text-indigo-500">
                            <img src="https://milus.one/assets/img/ssm/Shadowsocks.svg" class="w-8 h-8 sm:w-12 sm:h-12 mb-1 sm:mb-2" alt="Shadowsocks">
                            <span class="text-center text-xs sm:text-sm">Shadowsocks</span>
                        </a>
                    </div>
                    
                    <div class="subscribe-item">
                        <a id="Clash" onclick="set_url(this)" data-clipboard-text="https://{$subscribe_url}/checkmate/?token={$user['token']}&sid={$user['sid']}&app=clash" class="open-btn-2 flex flex-col items-center text-gray-500 hover:text-indigo-500">
                            <img src="https://milus.one/assets/img/ssm/Clash.svg" class="w-8 h-8 sm:w-12 sm:h-12 mb-1 sm:mb-2" alt="Clash">
                            <span class="text-center text-xs sm:text-sm">Clash</span>
                        </a>
                    </div>
                    
                    <div class="subscribe-item">
                        <a id="Surge" onclick="set_url(this)" data-clipboard-text="https://{$subscribe_url}/checkmate/?token={$user['token']}&sid={$user['sid']}&app=surge" class="open-btn-2 flex flex-col items-center text-gray-500 hover:text-indigo-500">
                            <img src="https://milus.one/assets/img/ssm/Surge.svg" class="w-8 h-8 sm:w-12 sm:h-12 mb-1 sm:mb-2" alt="Surge">
                            <span class="text-center text-xs sm:text-sm">Surge</span>
                        </a>
                    </div>
                    
                    <div class="subscribe-item">
                        <a data-clipboard-text="https://{$subscribe_url}/checkmate/?token={$user['token']}&sid={$user['sid']}&app=nodelist" class="copy flex flex-col items-center text-gray-500 hover:text-indigo-500">
                            <img src="https://milus.one/assets/img/ssm/Surge NodeList.svg" class="w-8 h-8 sm:w-12 sm:h-12 mb-1 sm:mb-2" alt="Surge NodeList">
                            <span class="text-center text-xs sm:text-sm">Surge NodeList</span>
                        </a>
                    </div>
                    
                    <div class="subscribe-item">
                        <a id="Shadowrocket" onclick="set_url(this)" data-clipboard-text="https://{$subscribe_url}/checkmate/?token={$user['token']}&sid={$user['sid']}&app=shadowrocket" class="open-btn-2 flex flex-col items-center text-gray-500 hover:text-indigo-500">
                            <img src="https://milus.one/assets/img/ssm/Shadowrocket.svg" class="w-8 h-8 sm:w-12 sm:h-12 mb-1 sm:mb-2" alt="Shadowrocket">
                            <span class="text-center text-xs sm:text-sm">Shadowrocket</span>
                        </a>
                    </div>
                    
                    <div class="subscribe-item">
                        <a id="Quantumult X" onclick="set_url(this)" data-clipboard-text="https://{$subscribe_url}/checkmate/?token={$user['token']}&sid={$user['sid']}&app=qx" class="open-btn-2 flex flex-col items-center text-gray-500 hover:text-indigo-500">
                            <img src="https://milus.one/assets/img/ssm/Quantumult%20X.svg" class="w-8 h-8 sm:w-12 sm:h-12 mb-1 sm:mb-2" alt="Quantumult X">
                            <span class="text-center text-xs sm:text-sm">Quantumult X</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Subscription Records -->
            <h2 class="section-title text-lg sm:text-xl font-semibold text-gray-900">
                <i class="fa-solid fa-history mr-2 text-indigo-500"></i>{ORRIS_L::client_subscription_records}
            </h2>
            
            <div class="container-card">
                <div class="table-container">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {ORRIS_L::client_access_time}
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {ORRIS_L::client_ip_address}
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {ORRIS_L::client_app_type}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {foreach from=$subscription_records item=record}
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                        {$record.time|date_format:"%Y-%m-%d %H:%M:%S"}
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                        {$record.ip}
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                        {$record.app}
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="3" class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 text-center">
                                        {ORRIS_L::client_no_records}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dialog -->
<div class="dialog-2 hidden">
    <div class="fixed flex justify-center items-center inset-0 z-50 outline-none focus:outline-none backdrop-blur-sm">
        <div class="flex flex-col p-4 sm:p-6 bg-white shadow-lg rounded-xl dialog-mark-2 max-w-md mx-auto m-4">
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2 sm:mb-3 text-center">Subscribe Options</h3>
            <div class="grid gap-2 sm:gap-3 grid-cols-2">
                <button id="copy-button" class="copy w-full inline-flex justify-center items-center px-3 sm:px-4 py-2 border border-gray-300 shadow-sm text-xs sm:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fa-solid fa-copy mr-1 sm:mr-2 text-gray-500"></i>
                    {ORRIS_L::client_copy}
                </button>

                <button id="one_click" class="w-full inline-flex justify-center items-center px-3 sm:px-4 py-2 border border-transparent shadow-sm text-xs sm:text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fa-solid fa-file-import mr-1 sm:mr-2"></i>
                    {ORRIS_L::client_import}
                </button>
                
                <button id="Choc" class="hidden w-full col-span-2 inline-flex justify-center items-center px-3 sm:px-4 py-2 border border-gray-300 shadow-sm text-xs sm:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fa-solid fa-file-import mr-1 sm:mr-2 text-gray-500"></i>
                    {ORRIS_L::client_import_to_choc}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Calculate traffic percentage
    document.addEventListener('DOMContentLoaded', function() {
        var totalStr = "{$user['bandwidth']}";
        var usedStr = "{$user['total_used']}";
        
        // Extract numeric values
        var totalValue = parseFloat(totalStr);
        var usedValue = parseFloat(usedStr);
        
        // Handle units (GB, MB, etc.)
        var totalUnit = totalStr.replace(/[0-9.]/g, '').trim();
        var usedUnit = usedStr.replace(/[0-9.]/g, '').trim();
        
        // Convert to same unit if needed
        if (totalUnit !== usedUnit) {
            // Simple conversion logic - expand as needed
            if (totalUnit === 'GB' && usedUnit === 'MB') {
                usedValue = usedValue / 1024;
            } else if (totalUnit === 'MB' && usedUnit === 'GB') {
                usedValue = usedValue * 1024;
            }
        }
        
        // Calculate percentage (cap at 100%)
        var percentage = Math.min((usedValue / totalValue) * 100, 100);
        
        // Set progress bar width
        document.getElementById('progress-bar').style.width = percentage + '%';
        
        // Set percentage text
        document.getElementById('progress-percentage').innerText = Math.round(percentage) + '%';
        
        // Adjust color based on usage
        var progressBar = document.getElementById('progress-bar');
        if (percentage > 90) {
            progressBar.style.background = 'linear-gradient(90deg, #ef4444, #f59e0b)';
        } else if (percentage > 70) {
            progressBar.style.background = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
        }
    });
    
    const dialog_2 = document.querySelector('.dialog-2');
    var one_click = document.getElementById('one_click');
    var copy_span = document.getElementById('copy-span');
    var copy_button = document.getElementById('copy-button');
    var import_to_choc = document.getElementById('Choc');
    var current_url = '';
    
    function set_url(obj){
        var subscribe_url = obj.getAttribute('data-clipboard-text');
        // 存储URL但不直接显示
        current_url = subscribe_url;
        dialog_2.classList.remove('hidden');
        
        function open_new(url){
            one_click.addEventListener('click',function(){
                console.log('使用URL：' + url);
                window.location.replace(url);
            },{
                once: true
            });
        }
        // 设置复制按钮的数据但不显示
        copy_button.setAttribute('data-clipboard-text', subscribe_url);
        switch (true) {
            case obj.id === 'Clash':
                open_new('clash://install-config?url=' + encodeURIComponent(subscribe_url) + "&name=" + encodeURIComponent('Milus'));
                import_to_choc.classList.remove('hidden');
                import_to_choc.addEventListener('click',function(){
                    console.log('导入到Choc');
                    window.location.replace('choc://install-config?url=' + encodeURIComponent(subscribe_url)+ "&name=" + encodeURIComponent('Milus'));
                },{
                    once: true
                });
                copy_button.innerHTML = '<i class="fa-solid fa-copy mr-1 sm:mr-2 text-gray-500"></i>{ORRIS_L::client_copy_subscribe_url}';
                one_click.innerHTML = '<i class="fa-solid fa-file-import mr-1 sm:mr-2"></i>{ORRIS_L::client_import_to_clash}';
                break;
            case obj.id === 'Surge':
                open_new('surge:///install-config?url=' + encodeURIComponent(subscribe_url) + "&name=" + encodeURIComponent('Milus'));
                break;
            case obj.id === 'Quantumult X':
                var subscribe_json = {
                    "server_remote": [
                        subscribe_url + ", tag=Milus, update-interval=172800, opt-parser=false, enabled=true, img-url=https://raw.githubusercontent.com/Kurenai-Network/staticfile/main/kurenai_quantumultx.png"
                    ]
                }
                open_new('quantumult-x:///update-configuration?remote-resource=' + encodeURIComponent(JSON.stringify(subscribe_json)));
                break;
            case obj.id === 'Shadowrocket':
                open_new('shadowrocket://add/sub://' + btoa(subscribe_url) + '#Milus');
                break;
        }
    }
    
    $(document).mouseup(function(e){
        var _con = $('.dialog-mark-2');   // 设置目标区域
        if(!_con.is(e.target) && _con.has(e.target).length === 0){ // Mark 1
            dialog_2.classList.add('hidden');
            import_to_choc.classList.add('hidden');
            copy_button.innerHTML = '<i class="fa-solid fa-copy mr-1 sm:mr-2 text-gray-500"></i>{ORRIS_L::client_copy}';
            one_click.innerHTML = '<i class="fa-solid fa-file-import mr-1 sm:mr-2"></i>{ORRIS_L::client_import}';
        }
    });
    
    var clipboard = new ClipboardJS('.copy');
    clipboard.on('success', function (e) {
        layer.msg('{ORRIS_L::client_copy_success}')
        console.log(e);
    });
    clipboard.on('error', function (e) {
        console.log(e);
    });
</script>
