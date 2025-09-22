<?php
/**
 * ORRISM - Unified Database Access Layer
 * Consolidated database operations with connection pooling and caching
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helper.php';

/**
 * Unified Database Manager
 * Handles both legacy shadowsocks database and WHMCS database operations
 */
class OrrisDatabase
{
    private static $instance = null;
    private $legacyConnection = null;
    private $redisConnection = null;
    private $config = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize database connections
     */
    private function __construct()
    {
        $this->config = orrism_get_config();
    }

    /**
     * Get legacy shadowsocks database connection
     */
    public function getLegacyConnection(): PDO
    {
        if ($this->legacyConnection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $this->config['mysql_host'],
                    $this->config['mysql_port'],
                    $this->config['mysql_db']
                );

                $this->legacyConnection = new PDO(
                    $dsn,
                    $this->config['mysql_user'],
                    $this->config['mysql_pass'],
                    [
                        PDO::ATTR_TIMEOUT => 10,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
            } catch (PDOException $e) {
                OrrisHelper::log('error', 'Legacy database connection failed', [
                    'error' => $e->getMessage(),
                    'host' => $this->config['mysql_host']
                ]);
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        return $this->legacyConnection;
    }

    /**
     * Get Redis connection with connection pooling
     */
    public function getRedisConnection(int $database = 0): ?Redis
    {
        if (!extension_loaded('redis')) {
            OrrisHelper::log('warning', 'Redis extension not available');
            return null;
        }

        $key = "redis_{$database}";
        if (!isset($this->redisConnection[$key])) {
            try {
                $redis = new Redis();
                $connected = $redis->connect(
                    $this->config['redis_host'],
                    (int)$this->config['redis_port'],
                    2.0
                );

                if (!$connected) {
                    throw new Exception('Redis connection failed');
                }

                if (!empty($this->config['redis_pass'])) {
                    $redis->auth($this->config['redis_pass']);
                }

                $redis->select($database);
                $this->redisConnection[$key] = $redis;

                OrrisHelper::log('debug', 'Redis connection established', ['database' => $database]);
            } catch (Exception $e) {
                OrrisHelper::log('error', 'Redis connection failed', [
                    'error' => $e->getMessage(),
                    'database' => $database
                ]);
                return null;
            }
        }

        return $this->redisConnection[$key];
    }

    /**
     * Execute Redis operation with fallback
     */
    public function redisOperation(string $operation, string $key, $value = null, int $database = 0, int $ttl = 300)
    {
        $redis = $this->getRedisConnection($database);
        if (!$redis) {
            return false;
        }

        try {
            switch ($operation) {
                case 'get':
                    return $redis->get($key);
                case 'set':
                    return $redis->setex($key, $ttl, $value);
                case 'del':
                    return $redis->del($key);
                case 'exists':
                    return $redis->exists($key);
                case 'expire':
                    return $redis->expire($key, $value);
                case 'incr':
                    return $redis->incr($key);
                case 'incrBy':
                    return $redis->incrBy($key, $value);
                case 'hget':
                    return $redis->hGet($key, $value);
                case 'hset':
                    return $redis->hSet($key, $value[0], $value[1]);
                case 'hgetall':
                    return $redis->hGetAll($key);
                case 'hdel':
                    return $redis->hDel($key, $value);
                default:
                    throw new InvalidArgumentException("Unsupported Redis operation: {$operation}");
            }
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Redis operation failed', [
                'operation' => $operation,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user by service ID with caching
     */
    public function getUser(int $serviceId): ?array
    {
        $cacheKey = "user_data_{$serviceId}";
        
        // Try cache first
        $cached = $this->redisOperation('get', $cacheKey, null, 0, 300);
        if ($cached) {
            return json_decode($cached, true);
        }

        // Query database
        try {
            $stmt = $this->getLegacyConnection()->prepare(
                'SELECT * FROM user WHERE sid = :sid'
            );
            $stmt->bindValue(':sid', $serviceId, PDO::PARAM_INT);
            $stmt->execute();
            
            $user = $stmt->fetch();
            if ($user) {
                // Cache for 5 minutes
                $this->redisOperation('set', $cacheKey, json_encode($user), 0, 300);
                return $user;
            }
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Failed to get user', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Create user account with transaction
     */
    public function createUser(array $data): bool
    {
        $connection = $this->getLegacyConnection();
        
        try {
            $connection->beginTransaction();

            $stmt = $connection->prepare(
                'INSERT INTO user (email, uuid, u, d, bandwidth, created_at, updated_at, need_reset, sid, package_id, enable, telegram_id, token, node_group_id) 
                 VALUES (:email, :uuid, 0, 0, :bandwidth, UNIX_TIMESTAMP(), 0, :need_reset, :sid, :package_id, :enable, :telegram_id, :token, :node_group_id)'
            );

            $params = [
                ':email' => $data['email'],
                ':uuid' => $data['uuid'],
                ':bandwidth' => $data['bandwidth'],
                ':need_reset' => $data['need_reset'],
                ':sid' => $data['sid'],
                ':package_id' => $data['package_id'],
                ':enable' => $data['enable'],
                ':telegram_id' => $data['telegram_id'],
                ':token' => $data['token'],
                ':node_group_id' => $data['node_group_id']
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $result = $stmt->execute();
            
            if ($result) {
                // Update cache
                $this->redisOperation('set', "user_data_{$data['sid']}", json_encode($data), 0, 300);
                $this->redisOperation('set', $data['sid'], $data['token'], 0, 3600);
                $this->redisOperation('set', "uuid{$data['sid']}", $data['uuid'], 0, 3600);
                
                $connection->commit();
                
                OrrisHelper::log('info', 'User created successfully', ['service_id' => $data['sid']]);
                return true;
            }
            
            $connection->rollback();
            return false;
            
        } catch (Exception $e) {
            $connection->rollback();
            OrrisHelper::log('error', 'Failed to create user', [
                'service_id' => $data['sid'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update user status with cache invalidation
     */
    public function updateUserStatus(int $serviceId, int $status): bool
    {
        try {
            $stmt = $this->getLegacyConnection()->prepare(
                'UPDATE user SET enable = :enable WHERE sid = :sid'
            );
            $stmt->bindValue(':enable', $status, PDO::PARAM_INT);
            $stmt->bindValue(':sid', $serviceId, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Invalidate cache
                $this->redisOperation('del', "user_data_{$serviceId}");
                OrrisHelper::log('info', 'User status updated', [
                    'service_id' => $serviceId,
                    'status' => $status
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Failed to update user status', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete user with cache cleanup
     */
    public function deleteUser(int $serviceId): bool
    {
        try {
            $stmt = $this->getLegacyConnection()->prepare(
                'DELETE FROM user WHERE sid = :sid'
            );
            $stmt->bindValue(':sid', $serviceId, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Clean up cache
                $this->redisOperation('del', "user_data_{$serviceId}");
                $this->redisOperation('del', $serviceId);
                $this->redisOperation('del', "uuid{$serviceId}");
                
                OrrisHelper::log('info', 'User deleted successfully', ['service_id' => $serviceId]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Failed to delete user', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Reset user UUID with transaction and cache update
     */
    public function resetUserUuid(int $serviceId, string $newUuid): bool
    {
        $connection = $this->getLegacyConnection();
        
        try {
            $connection->beginTransaction();
            
            $newToken = OrrisHelper::generateMd5Token();
            
            $stmt = $connection->prepare(
                'UPDATE user SET uuid = :uuid, token = :token WHERE sid = :sid'
            );
            $stmt->bindValue(':uuid', $newUuid);
            $stmt->bindValue(':token', $newToken);
            $stmt->bindValue(':sid', $serviceId);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Update cache
                $this->redisOperation('del', "user_data_{$serviceId}");
                $this->redisOperation('del', "uuid{$serviceId}");
                $this->redisOperation('del', $serviceId);
                
                $this->redisOperation('set', "uuid{$serviceId}", $newUuid, 0, 3600);
                $this->redisOperation('set', $serviceId, $newToken, 0, 3600);
                
                $connection->commit();
                
                OrrisHelper::log('info', 'User UUID reset successfully', [
                    'service_id' => $serviceId,
                    'new_uuid' => $newUuid
                ]);
                return true;
            }
            
            $connection->rollback();
            return false;
            
        } catch (Exception $e) {
            $connection->rollback();
            OrrisHelper::log('error', 'Failed to reset user UUID', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get nodes for user with caching
     */
    public function getNodesForUser(int $serviceId): array
    {
        $user = $this->getUser($serviceId);
        if (!$user) {
            return [];
        }

        $nodeGroupId = $user['node_group_id'] ?? 0;
        $cacheKey = "nodes_group_{$nodeGroupId}";
        
        // Try cache first
        $cached = $this->redisOperation('get', $cacheKey, null, 1, 600);
        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            if ($nodeGroupId > 0) {
                $stmt = $this->getLegacyConnection()->prepare(
                    'SELECT * FROM nodes WHERE group_id = :group_id AND enable = 1 ORDER BY rate'
                );
                $stmt->bindValue(':group_id', $nodeGroupId);
            } else {
                $stmt = $this->getLegacyConnection()->prepare(
                    'SELECT * FROM nodes WHERE enable = 1 ORDER BY rate'
                );
            }
            
            $stmt->execute();
            $nodes = $stmt->fetchAll();
            
            // Cache for 10 minutes
            if ($nodes) {
                $this->redisOperation('set', $cacheKey, json_encode($nodes), 1, 600);
            }
            
            return $nodes;
            
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Failed to get nodes for user', [
                'service_id' => $serviceId,
                'node_group_id' => $nodeGroupId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Update user bandwidth with optimistic locking
     */
    public function updateUserBandwidth(int $serviceId, int $upload, int $download): bool
    {
        $connection = $this->getLegacyConnection();
        
        try {
            $connection->beginTransaction();
            
            $stmt = $connection->prepare(
                'UPDATE user SET u = u + :upload, d = d + :download, updated_at = UNIX_TIMESTAMP() 
                 WHERE sid = :sid'
            );
            $stmt->bindValue(':upload', $upload, PDO::PARAM_INT);
            $stmt->bindValue(':download', $download, PDO::PARAM_INT);
            $stmt->bindValue(':sid', $serviceId, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Invalidate cache to force refresh
                $this->redisOperation('del', "user_data_{$serviceId}");
                
                $connection->commit();
                return true;
            }
            
            $connection->rollback();
            return false;
            
        } catch (Exception $e) {
            $connection->rollback();
            OrrisHelper::log('error', 'Failed to update user bandwidth', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Reset user traffic
     */
    public function resetUserTraffic(int $serviceId): bool
    {
        try {
            $stmt = $this->getLegacyConnection()->prepare(
                'UPDATE user SET u = 0, d = 0 WHERE sid = :sid'
            );
            $stmt->bindValue(':sid', $serviceId, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Invalidate cache
                $this->redisOperation('del', "user_data_{$serviceId}");
                OrrisHelper::log('info', 'User traffic reset', ['service_id' => $serviceId]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Failed to reset user traffic', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Close all connections
     */
    public function close(): void
    {
        if ($this->legacyConnection) {
            $this->legacyConnection = null;
        }
        
        if ($this->redisConnection) {
            foreach ($this->redisConnection as $redis) {
                if ($redis instanceof Redis) {
                    $redis->close();
                }
            }
            $this->redisConnection = null;
        }
    }

    /**
     * Destructor to clean up connections
     */
    public function __destruct()
    {
        $this->close();
    }
}

// Global helper functions for backward compatibility
function orrism_get_db_connection(): PDO
{
    return OrrisDatabase::getInstance()->getLegacyConnection();
}

function orrism_get_redis_connection(int $database = 0): ?Redis
{
    return OrrisDatabase::getInstance()->getRedisConnection($database);
}

function orrism_set_redis(string $key, $value, string $action, int $database = 0, int $ttl = 300)
{
    return OrrisDatabase::getInstance()->redisOperation($action, $key, $value, $database, $ttl);
}

function orrism_get_user(int $serviceId): ?array
{
    $user = OrrisDatabase::getInstance()->getUser($serviceId);
    return $user ? [$user] : []; // Return array format for compatibility
}

function orrism_get_nodes(int $serviceId): array
{
    return OrrisDatabase::getInstance()->getNodesForUser($serviceId);
}