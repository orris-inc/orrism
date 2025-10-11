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
require_once __DIR__ . '/utils.php';

/**
 * 配置生成器基类
 */
abstract class ConfigGenerator {
    protected $data;
    protected $user;
    protected $timestamp_due_date;
    
    public function __construct($data, $user, $timestamp_due_date = null) {
        $this->data = $data;
        $this->user = $user;
        $this->timestamp_due_date = $timestamp_due_date;
    }
    
    /**
     * 获取节点密码
     * @param array $node 节点数据
     * @return string 密码
     */
    protected function getNodePassword($node) {
        // Support both 'method' and 'node_method' for backward compatibility
        $method = $node['method'] ?? $node['node_method'] ?? '';

        if (in_array($method, ['2022-blake3-aes-128-gcm', '2022-blake3-aes-256-gcm'])) {
            $len = $method === '2022-blake3-aes-128-gcm' ? 16 : 32;
            $ctime_ts = is_numeric($node['created_at'] ?? $node['ctime'] ?? time())
                ? intval($node['created_at'] ?? $node['ctime'])
                : strtotime($node['created_at'] ?? $node['ctime'] ?? 'now');
            $serverKey = orris_get_server_key($ctime_ts, $len);
            $userKey = orris_uuidToBase64($this->user['uuid'], $len);
            return "{$serverKey}:{$userKey}";
        }
        return $this->user['uuid'];
    }
    
    /**
     * 生成配置内容
     * @return mixed 配置内容
     */
    abstract public function generate();
}

/**
 * SS配置生成器
 */
class SSGenerator extends ConfigGenerator {
    public function generate() {
        $node_url = '';
        foreach ($this->data as $nodes) {
            $user_info = base64_encode($nodes['node_method'] . ":" . $this->getNodePassword($nodes));
            $node_url .= "ss://" . $user_info . "@" . $nodes['address'] . ":" . $nodes['port'] . "#" . rawurlencode($nodes['node_name']) . "\n";
        }
        return base64_encode($node_url);
    }
}

/**
 * Shadowrocket配置生成器
 */
class ShadowrocketGenerator extends ConfigGenerator {
    public function generate() {
        $url = '';
        $tot = Utils::convertByte($this->user['bandwidth_limit']);
        $upload = Utils::convertByte($this->user['upload_bytes']);
        $download = Utils::convertByte($this->user['download_bytes']);
        $time = $this->timestamp_due_date ? date('Y-m-d', $this->timestamp_due_date) : 'N/A';
        $url .= "STATUS=🚀↑:{$upload},↓:{$download},TOT:{$tot}💡Expires:{$time}\r\n";

        foreach ($this->data as $node) {
            $name = rawurlencode($node['node_name']);
            $str = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode("{$node['node_method']}:{$this->user['uuid']}"));
            $url .= "ss://{$str}@{$node['address']}:{$node['port']}#{$name}\r\n";
        }
        return base64_encode($url);
    }
}

/**
 * Surge配置生成器
 */
class SurgeGenerator extends ConfigGenerator {
    public function generate() {
        $proxies = '';
        $proxyGroup = '';
        $defaultConfig = __DIR__ . '/rules/default.surge.conf';
        $config_values = orris_get_config();
        $subsDomain = $config_values['subscribe_url'] ?? '';
        if (empty($subsDomain)) {
            error_log("ORRIS API Services Error (surge_generate for SID: {$this->user['service_id']}): subscribe_url not found in config.");
        }
        $subsURL = 'https://' . $subsDomain . '/services?token=' . $this->user['uuid'] . '&type=surge&sid=' . $this->user['service_id'];
        header("content-disposition:attachment; filename=Milus_Surge.conf; filename*=UTF-8''Milus_Surge.conf");

        foreach ($this->data as $node) {
            $proxy = $this->buildProxy($node);
            if ($proxy) {
                $proxies .= $proxy;
                $proxyGroup .= $node['name'] . ', ';
            }
        }

        $config_content = file_get_contents($defaultConfig);
        $config_content = str_replace(
            ['$subs_link', '$subs_domain', '$proxies', '$proxy_group'],
            [$subsURL, $subsDomain, $proxies, rtrim($proxyGroup, ', ')],
            $config_content
        );
        return $config_content;
    }

    /**
     * Build proxy configuration based on node type
     * @param array $node Node data
     * @return string Proxy configuration line
     */
    protected function buildProxy($node) {
        $type = $node['type'] ?? 'shadowsocks';

        switch ($type) {
            case 'shadowsocks':
            case 'ss':
                return $this->buildShadowsocks($node);
            case 'snell':
                return $this->buildSnell($node);
            case 'trojan':
                return $this->buildTrojan($node);
            case 'vmess':
            case 'v2ray':
                return $this->buildVMess($node);
            default:
                OrrisHelper::log('warning', 'Unsupported node type for Surge', [
                    'node_type' => $type,
                    'node_name' => $node['name'] ?? 'unknown'
                ]);
                return '';
        }
    }

    /**
     * Build Shadowsocks configuration
     * @param array $node Node data
     * @return string Configuration string
     */
    protected function buildShadowsocks($node) {
        $password = $this->getNodePassword($node);
        $config = [
            "{$node['name']}=ss",
            "{$node['address']}",
            "{$node['port']}",
            "encrypt-method={$node['method']}",
            "password={$password}",
            'tfo=true',
            'udp-relay=true'
        ];
        return implode(',', array_filter($config)) . "\r\n";
    }

    /**
     * Build Snell configuration
     * @param array $node Node data
     * @return string Configuration string
     */
    protected function buildSnell($node) {
        $password = $this->getNodePassword($node);
        // For Snell nodes, the 'method' field stores the version number
        $version = $node['method'] ?? '4';

        $config = [
            "{$node['name']}=snell",
            "{$node['address']}",
            "{$node['port']}",
            "psk={$password}",
            "version={$version}",
            'tfo=true'
        ];

        // Parse config JSON for additional Snell parameters
        if (!empty($node['config'])) {
            $nodeConfig = is_string($node['config']) ? json_decode($node['config'], true) : $node['config'];

            // Add obfs if available
            if (!empty($nodeConfig['obfs'])) {
                $config[] = "obfs={$nodeConfig['obfs']}";
                if (!empty($nodeConfig['obfs_host'])) {
                    $config[] = "obfs-host={$nodeConfig['obfs_host']}";
                }
            }
        }

        return implode(',', array_filter($config)) . "\r\n";
    }

    /**
     * Build Trojan configuration
     * @param array $node Node data
     * @return string Configuration string
     */
    protected function buildTrojan($node) {
        $password = $this->getNodePassword($node);

        $config = [
            "{$node['name']}=trojan",
            "{$node['address']}",
            "{$node['port']}",
            "password={$password}"
        ];

        // Parse config JSON for additional Trojan parameters
        $nodeConfig = [];
        if (!empty($node['config'])) {
            $nodeConfig = is_string($node['config']) ? json_decode($node['config'], true) : $node['config'];
        }

        // Add SNI if available
        if (!empty($nodeConfig['sni'])) {
            $config[] = "sni={$nodeConfig['sni']}";
        }

        // Add skip-cert-verify option
        $skipVerify = isset($nodeConfig['skip_cert_verify']) ? $nodeConfig['skip_cert_verify'] : true;
        $config[] = 'skip-cert-verify=' . ($skipVerify ? 'true' : 'false');

        return implode(',', array_filter($config)) . "\r\n";
    }

    /**
     * Build VMess configuration
     * @param array $node Node data
     * @return string Configuration string
     */
    protected function buildVMess($node) {
        $uuid = $this->user['uuid'];

        $config = [
            "{$node['name']}=vmess",
            "{$node['address']}",
            "{$node['port']}",
            "username={$uuid}"
        ];

        // Parse config JSON for additional VMess parameters
        $nodeConfig = [];
        if (!empty($node['config'])) {
            $nodeConfig = is_string($node['config']) ? json_decode($node['config'], true) : $node['config'];
        }

        // Add encryption method (use method field or config)
        $security = $node['method'] ?? $nodeConfig['security'] ?? 'auto';
        if ($security && $security !== 'auto') {
            $config[] = "encrypt-method={$security}";
        }

        // Add TLS configuration
        if (!empty($nodeConfig['tls'])) {
            $config[] = 'tls=true';
            if (!empty($nodeConfig['sni'])) {
                $config[] = "sni={$nodeConfig['sni']}";
            }
        }

        // Add WebSocket configuration
        if (!empty($nodeConfig['network']) && $nodeConfig['network'] === 'ws') {
            $config[] = 'ws=true';
            if (!empty($nodeConfig['ws_path'])) {
                $config[] = "ws-path={$nodeConfig['ws_path']}";
            }
            if (!empty($nodeConfig['ws_headers'])) {
                // ws_headers should be a JSON string or array
                $headers = is_array($nodeConfig['ws_headers'])
                    ? json_encode($nodeConfig['ws_headers'])
                    : $nodeConfig['ws_headers'];
                $config[] = "ws-headers={$headers}";
            }
        }

        return implode(',', array_filter($config)) . "\r\n";
    }
}

/**
 * Surge节点列表生成器
 */
class SurgeNodelistGenerator extends ConfigGenerator {
    public function generate() {
        header('Content-Type:text/plain; charset=utf-8');
        $output = '';
        foreach ($this->data as $node) {
            $line = $this->buildNodeLine($node);
            if ($line) {
                $output .= $line;
            }
        }
        return $output;
    }

    /**
     * Build node line based on node type
     * @param array $node Node data
     * @return string Node configuration line
     */
    protected function buildNodeLine($node) {
        $type = $node['type'] ?? 'shadowsocks';
        $name = $node['name'] ?? 'Unknown';
        $address = $node['address'] ?? '';
        $port = $node['port'] ?? '';

        switch ($type) {
            case 'shadowsocks':
            case 'ss':
                $password = $this->getNodePassword($node);
                $method = $node['method'] ?? 'aes-256-gcm';
                return "{$name} = ss, {$address}, {$port}, encrypt-method={$method}, password={$password}, tfo=true, udp-relay=true\r\n";

            case 'snell':
                $password = $this->getNodePassword($node);
                // For Snell nodes, the 'method' field stores the version number
                $version = $node['method'] ?? '4';
                $line = "{$name} = snell, {$address}, {$port}, psk={$password}, version={$version}, tfo=true";

                // Parse config JSON for additional Snell parameters
                if (!empty($node['config'])) {
                    $nodeConfig = is_string($node['config']) ? json_decode($node['config'], true) : $node['config'];
                    if (!empty($nodeConfig['obfs'])) {
                        $line .= ", obfs={$nodeConfig['obfs']}";
                        if (!empty($nodeConfig['obfs_host'])) {
                            $line .= ", obfs-host={$nodeConfig['obfs_host']}";
                        }
                    }
                }
                return $line . "\r\n";

            case 'trojan':
                $password = $this->getNodePassword($node);
                $line = "{$name} = trojan, {$address}, {$port}, password={$password}";

                // Parse config JSON for additional Trojan parameters
                if (!empty($node['config'])) {
                    $nodeConfig = is_string($node['config']) ? json_decode($node['config'], true) : $node['config'];
                    if (!empty($nodeConfig['sni'])) {
                        $line .= ", sni={$nodeConfig['sni']}";
                    }
                    $skipVerify = isset($nodeConfig['skip_cert_verify']) ? $nodeConfig['skip_cert_verify'] : true;
                    $line .= ', skip-cert-verify=' . ($skipVerify ? 'true' : 'false');
                } else {
                    $line .= ', skip-cert-verify=true';
                }
                return $line . "\r\n";

            case 'vmess':
            case 'v2ray':
                $uuid = $this->user['uuid'];
                $line = "{$name} = vmess, {$address}, {$port}, username={$uuid}";

                // Parse config JSON for additional VMess parameters
                $nodeConfig = [];
                if (!empty($node['config'])) {
                    $nodeConfig = is_string($node['config']) ? json_decode($node['config'], true) : $node['config'];
                }

                // Add encryption method
                $security = $node['method'] ?? $nodeConfig['security'] ?? 'auto';
                if ($security && $security !== 'auto') {
                    $line .= ", encrypt-method={$security}";
                }

                // Add TLS configuration
                if (!empty($nodeConfig['tls'])) {
                    $line .= ', tls=true';
                    if (!empty($nodeConfig['sni'])) {
                        $line .= ", sni={$nodeConfig['sni']}";
                    }
                }

                // Add WebSocket configuration
                if (!empty($nodeConfig['network']) && $nodeConfig['network'] === 'ws') {
                    $line .= ', ws=true';
                    if (!empty($nodeConfig['ws_path'])) {
                        $line .= ", ws-path={$nodeConfig['ws_path']}";
                    }
                }
                return $line . "\r\n";

            default:
                OrrisHelper::log('warning', 'Unsupported node type for Surge nodelist', [
                    'node_type' => $type,
                    'node_name' => $name
                ]);
                return '';
        }
    }
}

/**
 * Clash配置生成器
 */
class ClashGenerator extends ConfigGenerator {
    public function generate() {
        header("subscription-userinfo: upload={$this->user['upload_bytes']}; download={$this->user['download_bytes']}; total={$this->user['bandwidth_limit']}; expire={$this->timestamp_due_date}");
        header('profile-update-interval: 24');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'Milus');
        $defaultConfig = __DIR__ . '/rules/default.clash.yaml';
        $config = Yaml::parseFile($defaultConfig);
        $proxy = [];
        $proxies = [];

        foreach ($this->data as $node) {
            $proxy[] = $this->generateSsClash($node);
            $proxies[] = $node['node_name'];
        }

        $config['proxies'] = array_merge($config['proxies'] ?? [], $proxy);
        foreach ($config['proxy-groups'] as $k => $v) {
            if (!is_array($config['proxy-groups'][$k]['proxies'])) continue;
            $isFilter = false;
            foreach ($config['proxy-groups'][$k]['proxies'] as $src) {
                foreach ($proxies as $dst) {
                    if ($this->isMatch($src, $dst)) {
                        $isFilter = true;
                        $config['proxy-groups'][$k]['proxies'] = array_diff($config['proxy-groups'][$k]['proxies'], [$src]);
                        $config['proxy-groups'][$k]['proxies'][] = $dst;
                    }
                }
            }
            if (!$isFilter) {
                $config['proxy-groups'][$k]['proxies'] = array_merge($config['proxy-groups'][$k]['proxies'], $proxies);
            }
        }

        $yaml = Yaml::dump($config);
        return str_replace('$app_name', 'Milus', $yaml);
    }
    
    /**
     * 生成Clash的SS节点配置
     * @param array $node 节点数据
     * @return array 节点配置
     */
    protected function generateSsClash($node) {
        return [
            'name' => $node['node_name'],
            'type' => 'ss',
            'server' => $node['address'],
            'port' => $node['port'],
            'cipher' => $node['node_method'],
            'password' => $this->getNodePassword($node),
            'udp' => true
        ];
    }
    
    /**
     * 检查字符串是否匹配正则表达式
     * @param string $exp 正则表达式
     * @param string $str 待检查字符串
     * @return bool 是否匹配
     */
    protected function isMatch($exp, $str) {
        try {
            return preg_match($exp, $str);
        } catch (\Exception $e) {
            return false;
        }
    }
}

/**
 * QuantumultX配置生成器
 */
class QuantumultXGenerator extends ConfigGenerator {
    public function generate() {
        header("subscription-userinfo: upload={$this->user['upload_bytes']}; download={$this->user['download_bytes']}; total={$this->user['bandwidth_limit']}; expire={$this->timestamp_due_date}");
        $uri = '';
        foreach ($this->data as $node) {
            $config = [
                "shadowsocks={$node['address']}:{$node['port']}",
                "method={$node['node_method']}",
                "password=" . $this->getNodePassword($node),
                'fast-open=true',
                'udp-relay=true',
                "tag={$node['node_name']}"
            ];
            $uri .= implode(',', array_filter($config)) . "\r\n";
        }
        return $uri;
    }
}

/**
 * SIP008格式配置生成器
 */
class SIP008Generator extends ConfigGenerator {
    public function generate() {
        $node_info = [];
        foreach ($this->data as $nodes) {
            $node_info[] = [
                'id' => $nodes['id'],
                "remarks" => $nodes['node_name'],
                "server" => $nodes['address'],
                "server_port" => $nodes['port'],
                "password" => $this->getNodePassword($nodes),
                "method" => $nodes['node_method']
            ];
        }
        return [
            'version' => 1,
            'servers' => $node_info,
            'bytes_used' => $this->user['upload_bytes'] + $this->user['download_bytes'],
            'bytes_remaining' => $this->user['bandwidth_limit'] - ($this->user['upload_bytes'] + $this->user['download_bytes'])
        ];
    }
}

/**
 * 配置生成器工厂类
 */
class ConfigGeneratorFactory {
    /**
     * 创建配置生成器实例
     * @param string $type 配置类型
     * @param array $data 节点数据
     * @param array $user 用户数据
     * @param int $timestamp_due_date 到期时间戳
     * @return ConfigGenerator|null 配置生成器实例
     */
    public static function create($type, $data, $user, $timestamp_due_date = null) {
        switch ($type) {
            case 'ss':
                return new SSGenerator($data, $user, $timestamp_due_date);
            case 'shadowrocket':
                return new ShadowrocketGenerator($data, $user, $timestamp_due_date);
            case 'nodelist':
                return new SurgeNodelistGenerator($data, $user, $timestamp_due_date);
            case 'clash':
            case 'stash':
                return new ClashGenerator($data, $user, $timestamp_due_date);
            case 'qx':
                return new QuantumultXGenerator($data, $user, $timestamp_due_date);
            case 'surge':
                return new SurgeGenerator($data, $user, $timestamp_due_date);
            case 'sip008':
                return new SIP008Generator($data, $user, $timestamp_due_date);
            default:
                return null;
        }
    }
}

/**
 * 根据请求类型生成配置
 * @param string $app 请求类型
 * @param array $data 节点数据
 * @param array $user 用户数据
 * @param int $timestamp_due_date 到期时间戳
 * @return mixed 生成的配置内容
 */
function orrism_generate_response($app, $data, $user, $timestamp_due_date) {
    $generator = ConfigGeneratorFactory::create($app, $data, $user, $timestamp_due_date);
    
    if ($generator) {
        $config = $generator->generate();
        if ($app === 'sip008') {
            return json_encode($config);
        }
        return $config;
    }
    
    return '不支持的配置类型';
} 
