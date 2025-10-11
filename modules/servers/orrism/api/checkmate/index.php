<?php
/**
 * ORRISM Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

use Symfony\Component\Yaml\Yaml;

// Include required files
require_once __DIR__ . '/../service.php';
require_once __DIR__ . '/../product.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/generators.php';

// Include WHMCS server module functions for HMAC token verification
require_once __DIR__ . '/../../orrism.php';

/**
 * 配置订阅服务类
 */
class SubscriptionService {
    private $allowedTypes;
    private $token;
    private $sid;
    private $app;
    private $clientIp;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->allowedTypes = ["ss", "nodelist", "clash", "surge", "qx", "shadowrocket", "stash", "sip008"];
        $this->token = isset($_GET['token']) ? htmlspecialchars(trim($_GET['token'])) : '';
        $this->app = isset($_GET['app']) ? htmlspecialchars(trim($_GET['app'])) : 'clash';
        $this->clientIp = Security::getClientIp();
    }
    
    /**
     * 处理请求
     */
    public function handleRequest() {
        // 设置安全响应头
        Security::setSecureHeaders();
        
        // 请求频率限制
        if (!RequestHandler::checkRateLimit($this->sid, $this->clientIp)) {
            RequestHandler::handleError(
                '429 Too Many Requests', 
                "Rate Limit: Too many requests for SID: {$this->sid}", 
                $this->clientIp, 
                'Error: Too many requests. Please try again later.'
            );
        }
        
        // 验证请求参数
        if (!RequestHandler::isValidRequest($this->app, $this->token, $this->sid, $this->allowedTypes)) {
            RequestHandler::handleError(
                '400 Bad Request', 
                "Invalid request parameters. App: {$this->app}, SID: {$this->sid}", 
                $this->clientIp, 
                'Error: Invalid request parameters.'
            );
        }
        
        // 执行核心处理逻辑
        $this->processValidRequest();
    }
    
    /**
     * 处理有效请求的核心逻辑
     */
    private function processValidRequest() {
        // Step 1: 验证HMAC token并提取service_id
        // This must be first because it populates $this->sid from the token
        $userData = $this->authenticateUser();

        // Step 2: 获取用户产品信息（需要$this->sid）
        $clientProductResult = $this->getClientProducts();

        // Step 3: 获取和验证节点数据
        $nodeData = $this->getNodeData();

        // Step 4: 生成配置响应
        $this->generateConfigResponse($userData, $nodeData, $clientProductResult['timestamp_due_date']);
    }
    
    /**
     * 获取用户产品信息
     * @return array 产品信息
     */
    private function getClientProducts() {
        $clientProductResult = orris_get_client_products($this->sid);
        
        // 对API调用结果进行健壮的错误处理
        if (isset($clientProductResult['result']) && $clientProductResult['result'] === 'error') {
            RequestHandler::handleError(
                '500 Internal Server Error', 
                "(GetClientsProducts for SID: {$this->sid}): " . ($clientProductResult['message'] ?? 'Unknown error from localAPI.'), 
                $this->clientIp, 
                'Error: Unable to process your request. Please try again later.'
            );
        }
        
        if (!isset($clientProductResult['products']['product'][0])) {
            RequestHandler::handleError(
                '500 Internal Server Error', 
                "(GetClientsProducts for SID: {$this->sid}): Products data not found or in unexpected format. API Response: " . print_r($clientProductResult, true), 
                $this->clientIp, 
                'Error: Product data not available. Please contact support.'
            );
        }
        
        $clientProduct = $clientProductResult['products']['product'][0];
        $timestampDueDate = (isset($clientProduct['nextduedate']) && $clientProduct['nextduedate'] !== '0000-00-00' && !empty($clientProduct['nextduedate'])) 
            ? strtotime($clientProduct['nextduedate']) 
            : '';
            
        return [
            'product' => $clientProduct,
            'timestamp_due_date' => $timestampDueDate
        ];
    }
    
    /**
     * 验证用户身份并获取用户数据
     * @return array 用户数据
     */
    private function authenticateUser() {
        // Verify HMAC token signature and extract service_id
        $tokenData = verify_subscription_token($this->token, 0, true);

        if ($tokenData === false) {
            RequestHandler::handleError(
                '401 Unauthorized',
                "(HMAC Token): Invalid or expired subscription token from IP: {$this->clientIp}",
                $this->clientIp,
                'Error: Invalid or expired subscription token.'
            );
        }

        // Extract service_id from token
        $this->sid = $tokenData['service_id'];

        // Log successful HMAC authentication
        error_log("ORRISM API Services Access [IP: {$this->clientIp}]: HMAC token authenticated for SID: {$this->sid}, App: {$this->app}");

        // Record IP to Redis
        Security::recordIpToRedis($this->sid, $this->clientIp, $this->app);

        // Get user data
        $userDataArray = orris_get_user($this->sid);
        if (empty($userDataArray) || !isset($userDataArray[0])) {
            RequestHandler::handleError(
                '500 Internal Server Error',
                "(orris_get_user for SID: {$this->sid}): User data not found.",
                $this->clientIp,
                'Error: User data unavailable. Please contact support.'
            );
        }

        $user = $userDataArray[0];

        // Check user status
        if (isset($user['enable']) && $user['enable'] == 0) {
            RequestHandler::handleError(
                '403 Forbidden',
                "(SID: {$this->sid}): User account is disabled (enable=0).",
                $this->clientIp,
                'Error: Account disabled. Please contact support.'
            );
        }

        return $user;
    }
    
    /**
     * 获取节点数据
     * @return array 节点数据
     */
    private function getNodeData() {
        $data = orris_get_nodes($this->sid);
        if (empty($data)) {
            RequestHandler::handleError(
                '404 Not Found', 
                "(orris_get_nodes for SID: {$this->sid}): No nodes available.", 
                $this->clientIp, 
                'Error: No service nodes available. Please contact support.'
            );
        }
        return $data;
    }
    
    /**
     * 检查账户是否过期
     * @param int $timestampDueDate 到期时间戳
     */
    private function checkAccountExpiration($timestampDueDate) {
        if (!empty($timestampDueDate) && $timestampDueDate < time()) {
            RequestHandler::handleError(
                '403 Forbidden', 
                "(SID: {$this->sid}): User account has expired.", 
                $this->clientIp, 
                'Error: Account expired. Please renew your subscription.'
            );
        }
    }
    
    /**
     * 生成配置响应
     * @param array $userData 用户数据
     * @param array $nodeData 节点数据
     * @param int $timestampDueDate 到期时间戳
     */
    private function generateConfigResponse($userData, $nodeData, $timestampDueDate) {
        // 检查账户是否过期
        $this->checkAccountExpiration($timestampDueDate);
        
        // 生成响应数据
        header('Content-Type:text/html; charset=utf-8');
        echo orrism_generate_response($this->app, $nodeData, $userData, $timestampDueDate);
    }
}

// 创建服务并处理请求
$service = new SubscriptionService();
$service->handleRequest();
