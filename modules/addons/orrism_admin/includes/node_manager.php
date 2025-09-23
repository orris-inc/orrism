<?php
/**
 * ORRISM Node Manager
 * Core logic for node management
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    1.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use PDO;

// Include OrrisDB connection manager
$orrisDbPath = dirname(__DIR__, 3) . '/servers/orrism/includes/orris_db.php';
if (file_exists($orrisDbPath)) {
    require_once $orrisDbPath;
}

/**
 * NodeManager Class
 * Handles all node-related operations
 */
class NodeManager
{
    private $db = null;
    private $queryLog = [];
    private $pdo = null;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize database connection
        $this->initDatabase();
    }
    
    /**
     * Initialize database connection
     */
    private function initDatabase()
    {
        try {
            // Check if OrrisDB class is available
            if (class_exists('OrrisDB')) {
                $this->db = OrrisDB::connection();
                if ($this->db) {
                    $this->pdo = $this->db->getPdo();
                }
            } else {
                error_log('NodeManager: OrrisDB class not found');
            }
        } catch (Exception $e) {
            error_log('NodeManager: Failed to initialize database - ' . $e->getMessage());
        }
    }
    
    /**
     * Execute a query and return results
     */
    private function query($sql, $bindings = [])
    {
        if (!$this->pdo) {
            return false;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }
    
    /**
     * Execute a query and return all results
     */
    private function selectAll($sql, $bindings = [])
    {
        $stmt = $this->query($sql, $bindings);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : [];
    }
    
    /**
     * Execute a query and return first result
     */
    private function selectOne($sql, $bindings = [])
    {
        $stmt = $this->query($sql, $bindings);
        return $stmt ? $stmt->fetch(PDO::FETCH_OBJ) : null;
    }
    
    /**
     * Execute an insert/update/delete query
     */
    private function execute($sql, $bindings = [])
    {
        if (!$this->pdo) {
            return false;
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bindings);
    }
    
    /**
     * Get nodes list with statistics
     * Optimized single query to avoid N+1 problem
     */
    public function getNodesWithStats($page = 1, $perPage = 50, $filters = [])
    {
        $startTime = microtime(true);
        
        try {
            // Calculate offset
            $offset = ($page - 1) * $perPage;
            
            // Build optimized query with all necessary data in single query
            $query = "
                SELECT 
                    n.id,
                    n.node_type,
                    n.node_name,
                    n.address,
                    n.port,
                    n.rate as traffic_rate,
                    n.status,
                    n.sort_order,
                    n.updated_at as last_check,
                    ng.id as group_id,
                    ng.name as group_name,
                    COALESCE(service_stats.service_count, 0) as current_services,
                    COALESCE(traffic_stats.total_traffic, 0) as total_traffic
                FROM nodes n
                LEFT JOIN node_groups ng ON n.group_id = ng.id
                LEFT JOIN (
                    SELECT node_group_id, COUNT(*) as service_count
                    FROM services
                    WHERE status = 'active'
                    GROUP BY node_group_id
                ) service_stats ON service_stats.node_group_id = ng.id
                LEFT JOIN (
                    SELECT node_id, 
                           SUM(upload_bytes + download_bytes) as total_traffic
                    FROM service_usage
                    WHERE DATE(created_at) = CURDATE()
                    GROUP BY node_id
                ) traffic_stats ON traffic_stats.node_id = n.id
            ";
            
            // Add filters
            $where = [];
            $bindings = [];
            
            if (!empty($filters['status'])) {
                $where[] = "n.status = ?";
                $bindings[] = $filters['status'];
            }
            
            if (!empty($filters['type'])) {
                $where[] = "n.node_type = ?";
                $bindings[] = $filters['type'];
            }
            
            if (!empty($filters['group_id'])) {
                $where[] = "n.group_id = ?";
                $bindings[] = $filters['group_id'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = "(n.node_name LIKE ? OR n.address LIKE ?)";
                $bindings[] = '%' . $filters['search'] . '%';
                $bindings[] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($where)) {
                $query .= " WHERE " . implode(' AND ', $where);
            }
            
            // Add ordering
            $query .= " ORDER BY n.sort_order ASC, n.id ASC";
            
            // Get total count for pagination
            $countQuery = "SELECT COUNT(DISTINCT n.id) as total " . 
                         "FROM nodes n " . 
                         (!empty($where) ? " WHERE " . implode(' AND ', $where) : "");
            
            // Execute count query
            if ($this->db) {
                $stmt = $this->db->getPdo()->prepare($countQuery);
                foreach ($bindings as $index => $value) {
                    $stmt->bindValue($index + 1, $value);
                }
                $stmt->execute();
                $totalResult = $stmt->fetch(PDO::FETCH_OBJ);
                $total = $totalResult ? $totalResult->total : 0;
            } else {
                $total = 0;
            }
            
            // Add pagination
            $query .= " LIMIT ? OFFSET ?";
            $bindings[] = $perPage;
            $bindings[] = $offset;
            
            // Execute main query
            if ($this->db) {
                $stmt = $this->db->getPdo()->prepare($query);
                foreach ($bindings as $index => $value) {
                    $stmt->bindValue($index + 1, $value);
                }
                $stmt->execute();
                $nodes = $stmt->fetchAll(PDO::FETCH_OBJ);
            } else {
                $nodes = [];
            }
            
            // Log query performance
            $executionTime = microtime(true) - $startTime;
            $this->logQuery($query, $executionTime);
            
            // Format traffic for display
            foreach ($nodes as &$node) {
                $node->formatted_traffic = $this->formatBytes($node->total_traffic);
                $node->formatted_rate = number_format($node->traffic_rate, 1) . 'x';
                $node->formatted_time = $this->formatLastCheckTime($node->last_check);
            }
            
            return [
                'success' => true,
                'nodes' => $nodes,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ];
            
        } catch (Exception $e) {
            error_log('NodeManager::getNodesWithStats error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch nodes: ' . $e->getMessage(),
                'nodes' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * Get single node details
     */
    public function getNode($nodeId)
    {
        try {
            if (!$this->db) {
                throw new Exception('Database connection not available');
            }
            
            $query = "SELECT n.*, ng.name as group_name 
                     FROM nodes n 
                     LEFT JOIN node_groups ng ON n.group_id = ng.id 
                     WHERE n.id = ?";
            
            $stmt = $this->db->getPdo()->prepare($query);
            $stmt->execute([$nodeId]);
            $node = $stmt->fetch(PDO::FETCH_OBJ);
            
            if ($node) {
                // Get current users count
                $countQuery = "SELECT COUNT(*) as count FROM services WHERE node_group_id = ? AND status = 'active'";
                $stmt = $this->db->getPdo()->prepare($countQuery);
                $stmt->execute([$node->group_id]);
                $result = $stmt->fetch(PDO::FETCH_OBJ);
                
                $node->current_users = $result ? $result->count : 0;
                
                return [
                    'success' => true,
                    'node' => $node
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Node not found'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching node: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create new node
     */
    public function createNode($data)
    {
        try {
            if (!$this->pdo) {
                throw new Exception('Database connection not available');
            }
            
            // Validate required fields
            $required = ['node_type', 'node_name', 'address', 'port'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Prepare insert query
            $sql = "INSERT INTO nodes (node_type, node_name, address, port, group_id, rate, node_method, network_type, status, sort_order, tag, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $bindings = [
                $data['node_type'],
                $data['node_name'],
                $data['address'],
                (int)$data['port'],
                $data['group_id'] ?? 1,
                $data['rate'] ?? 1.0,
                $data['node_method'] ?? 'aes-256-gcm',
                $data['network_type'] ?? 'tcp',
                $data['status'] ?? 1,
                $data['sort_order'] ?? 0,
                $data['tag'] ?? '',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ];
            
            // Insert node
            $this->execute($sql, $bindings);
            $nodeId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Node created successfully',
                'node_id' => $nodeId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create node: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update existing node
     */
    public function updateNode($nodeId, $data)
    {
        try {
            if (!$this->pdo) {
                throw new Exception('Database connection not available');
            }
            
            // Build update query dynamically
            $updates = [];
            $bindings = [];
            
            // Only update provided fields
            $allowedFields = [
                'node_type', 'node_name', 'address', 'port', 
                'group_id', 'rate', 'node_method', 'network_type',
                'status', 'sort_order', 'tag'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $bindings[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return [
                    'success' => false,
                    'message' => 'No fields to update'
                ];
            }
            
            // Add updated_at
            $updates[] = "updated_at = ?";
            $bindings[] = date('Y-m-d H:i:s');
            
            // Add nodeId for WHERE clause
            $bindings[] = $nodeId;
            
            // Update node
            $sql = "UPDATE nodes SET " . implode(', ', $updates) . " WHERE id = ?";
            $this->execute($sql, $bindings);
            
            return [
                'success' => true,
                'message' => 'Node updated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update node: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete node
     */
    public function deleteNode($nodeId)
    {
        try {
            if (!$this->pdo) {
                throw new Exception('Database connection not available');
            }
            
            // Begin transaction
            $this->pdo->beginTransaction();
            
            try {
                // Delete related usage records first
                $sql = "DELETE FROM service_usage WHERE node_id = ?";
                $this->execute($sql, [$nodeId]);
                
                // Delete node
                $sql = "DELETE FROM nodes WHERE id = ?";
                $this->execute($sql, [$nodeId]);
                
                $this->pdo->commit();
                
                return [
                    'success' => true,
                    'message' => 'Node deleted successfully'
                ];
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            \OrrisDB::rollback();
            return [
                'success' => false,
                'message' => 'Failed to delete node: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Toggle node status
     */
    public function toggleNodeStatus($nodeId)
    {
        try {
            if (!$this->pdo) {
                throw new Exception('Database connection not available');
            }
            
            // Get current status
            $sql = "SELECT status FROM nodes WHERE id = ?";
            $node = $this->selectOne($sql, [$nodeId]);
            
            if (!$node) {
                throw new Exception('Node not found');
            }
            
            // Toggle status
            $newStatus = $node->status ? 0 : 1;
            
            $sql = "UPDATE nodes SET status = ?, updated_at = ? WHERE id = ?";
            $this->execute($sql, [$newStatus, date('Y-m-d H:i:s'), $nodeId]);
            
            return [
                'success' => true,
                'message' => 'Node status updated',
                'new_status' => $newStatus
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Batch update nodes
     */
    public function batchUpdateNodes($nodeIds, $action, $data = [])
    {
        try {
            if (!$this->pdo) {
                throw new Exception('Database connection not available');
            }
            
            if (empty($nodeIds)) {
                throw new Exception('No nodes selected');
            }
            
            // Create placeholders for IN clause
            $placeholders = str_repeat('?,', count($nodeIds) - 1) . '?';
            
            $this->pdo->beginTransaction();
            
            try {
                switch ($action) {
                    case 'enable':
                        $sql = "UPDATE nodes SET status = 1, updated_at = ? WHERE id IN ($placeholders)";
                        $bindings = array_merge([date('Y-m-d H:i:s')], $nodeIds);
                        $this->execute($sql, $bindings);
                        $message = 'Nodes enabled successfully';
                        break;
                        
                    case 'disable':
                        $sql = "UPDATE nodes SET status = 0, updated_at = ? WHERE id IN ($placeholders)";
                        $bindings = array_merge([date('Y-m-d H:i:s')], $nodeIds);
                        $this->execute($sql, $bindings);
                        $message = 'Nodes disabled successfully';
                        break;
                        
                    case 'delete':
                        // Delete usage records first
                        $sql = "DELETE FROM service_usage WHERE node_id IN ($placeholders)";
                        $this->execute($sql, $nodeIds);
                        
                        $sql = "DELETE FROM nodes WHERE id IN ($placeholders)";
                        $this->execute($sql, $nodeIds);
                        $message = 'Nodes deleted successfully';
                        break;
                        
                    case 'change_group':
                        if (empty($data['group_id'])) {
                            throw new Exception('Group ID is required');
                        }
                        $sql = "UPDATE nodes SET group_id = ?, updated_at = ? WHERE id IN ($placeholders)";
                        $bindings = array_merge([$data['group_id'], date('Y-m-d H:i:s')], $nodeIds);
                        $this->execute($sql, $bindings);
                        $message = 'Node group changed successfully';
                        break;
                        
                    default:
                        throw new Exception('Invalid action');
                }
                
                $this->pdo->commit();
                
                return [
                    'success' => true,
                    'message' => $message
                ];
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Batch operation failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get node groups
     */
    public function getNodeGroups()
    {
        try {
            if (!$this->pdo) {
                return [];
            }
            
            $sql = "SELECT id, name, description, status FROM node_groups WHERE status = 1 ORDER BY name ASC";
            return $this->selectAll($sql);
            
        } catch (Exception $e) {
            error_log('NodeManager::getNodeGroups error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get node types
     */
    public function getNodeTypes()
    {
        return [
            'shadowsocks' => 'Shadowsocks',
            'vless' => 'VLESS',
            'vmess' => 'VMESS',
            'trojan' => 'Trojan'
        ];
    }
    
    /**
     * Get encryption methods by node type
     */
    public function getEncryptionMethods($nodeType)
    {
        $methods = [
            'shadowsocks' => [
                'aes-128-gcm' => 'AES-128-GCM',
                'aes-192-gcm' => 'AES-192-GCM',
                'aes-256-gcm' => 'AES-256-GCM',
                'chacha20-ietf-poly1305' => 'ChaCha20-IETF-Poly1305'
            ],
            'vless' => [
                'none' => 'None'
            ],
            'vmess' => [
                'auto' => 'Auto',
                'aes-128-gcm' => 'AES-128-GCM',
                'chacha20-poly1305' => 'ChaCha20-Poly1305',
                'none' => 'None'
            ],
            'trojan' => [
                'none' => 'None'
            ]
        ];
        
        return $methods[$nodeType] ?? [];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes == 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Format last check time
     */
    private function formatLastCheckTime($timestamp)
    {
        if (empty($timestamp)) {
            return 'Never';
        }
        
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 300) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return round($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return round($diff / 3600) . ' hours ago';
        } else {
            return date('Y-m-d H:i', $time);
        }
    }
    
    /**
     * Log query for performance monitoring
     */
    private function logQuery($query, $executionTime)
    {
        $this->queryLog[] = [
            'query' => $query,
            'time' => $executionTime,
            'timestamp' => microtime(true)
        ];
        
        // Log slow queries
        if ($executionTime > 0.5) {
            error_log("Slow node query ({$executionTime}s): " . substr($query, 0, 200));
        }
    }
    
    /**
     * Get query log
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }
}