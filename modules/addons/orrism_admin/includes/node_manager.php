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
            } else {
                error_log('NodeManager: OrrisDB class not found');
            }
        } catch (Exception $e) {
            error_log('NodeManager: Failed to initialize database - ' . $e->getMessage());
        }
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
                    COALESCE(user_stats.user_count, 0) as current_users,
                    COALESCE(traffic_stats.total_traffic, 0) as total_traffic
                FROM nodes n
                LEFT JOIN node_groups ng ON n.group_id = ng.id
                LEFT JOIN (
                    SELECT node_group_id, COUNT(*) as user_count
                    FROM users
                    WHERE status = 'active'
                    GROUP BY node_group_id
                ) user_stats ON user_stats.node_group_id = ng.id
                LEFT JOIN (
                    SELECT node_id, 
                           SUM(upload_bytes + download_bytes) as total_traffic
                    FROM user_usage
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
                $totalResult = \OrrisDB::select($countQuery, $bindings);
                $total = $totalResult[0]->total ?? 0;
            } else {
                $total = 0;
            }
            
            // Add pagination
            $query .= " LIMIT ? OFFSET ?";
            $bindings[] = $perPage;
            $bindings[] = $offset;
            
            // Execute main query
            if ($this->db) {
                $nodes = \OrrisDB::select($query, $bindings);
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
            
            $node = \OrrisDB::table('nodes')
                ->leftJoin('node_groups', 'nodes.group_id', '=', 'node_groups.id')
                ->select('nodes.*', 'node_groups.name as group_name')
                ->where('nodes.id', $nodeId)
                ->first();
            
            if ($node) {
                // Get current users count
                $userCount = \OrrisDB::table('users')
                    ->where('node_group_id', $node->group_id)
                    ->where('status', 'active')
                    ->count();
                
                $node->current_users = $userCount;
                
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
            if (!$this->db) {
                throw new Exception('Database connection not available');
            }
            
            // Validate required fields
            $required = ['node_type', 'node_name', 'address', 'port'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Prepare data
            $nodeData = [
                'node_type' => $data['node_type'],
                'node_name' => $data['node_name'],
                'address' => $data['address'],
                'port' => (int)$data['port'],
                'group_id' => $data['group_id'] ?? 1,
                'rate' => $data['rate'] ?? 1.0,
                'node_method' => $data['node_method'] ?? 'aes-256-gcm',
                'network_type' => $data['network_type'] ?? 'tcp',
                'status' => $data['status'] ?? 1,
                'sort_order' => $data['sort_order'] ?? 0,
                'tag' => $data['tag'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Insert node
            $nodeId = \OrrisDB::table('nodes')->insertGetId($nodeData);
            
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
            if (!$this->db) {
                throw new Exception('Database connection not available');
            }
            
            // Prepare update data
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Only update provided fields
            $allowedFields = [
                'node_type', 'node_name', 'address', 'port', 
                'group_id', 'rate', 'node_method', 'network_type',
                'status', 'sort_order', 'tag'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            // Update node
            $affected = \OrrisDB::table('nodes')
                ->where('id', $nodeId)
                ->update($updateData);
            
            return [
                'success' => true,
                'message' => 'Node updated successfully',
                'affected' => $affected
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
            if (!$this->db) {
                throw new Exception('Database connection not available');
            }
            
            // Begin transaction
            \OrrisDB::beginTransaction();
            
            // Delete related usage records first
            \OrrisDB::table('user_usage')
                ->where('node_id', $nodeId)
                ->delete();
            
            // Delete node
            $deleted = \OrrisDB::table('nodes')
                ->where('id', $nodeId)
                ->delete();
            
            \OrrisDB::commit();
            
            return [
                'success' => true,
                'message' => 'Node deleted successfully',
                'deleted' => $deleted
            ];
            
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
            if (!$this->db) {
                throw new Exception('Database connection not available');
            }
            
            // Get current status
            $node = \OrrisDB::table('nodes')
                ->select('status')
                ->where('id', $nodeId)
                ->first();
            
            if (!$node) {
                throw new Exception('Node not found');
            }
            
            // Toggle status
            $newStatus = $node->status ? 0 : 1;
            
            \OrrisDB::table('nodes')
                ->where('id', $nodeId)
                ->update([
                    'status' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
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
            if (!$this->db) {
                throw new Exception('Database connection not available');
            }
            
            if (empty($nodeIds)) {
                throw new Exception('No nodes selected');
            }
            
            \OrrisDB::beginTransaction();
            
            switch ($action) {
                case 'enable':
                    \OrrisDB::table('nodes')
                        ->whereIn('id', $nodeIds)
                        ->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
                    $message = 'Nodes enabled successfully';
                    break;
                    
                case 'disable':
                    \OrrisDB::table('nodes')
                        ->whereIn('id', $nodeIds)
                        ->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
                    $message = 'Nodes disabled successfully';
                    break;
                    
                case 'delete':
                    // Delete usage records first
                    \OrrisDB::table('user_usage')
                        ->whereIn('node_id', $nodeIds)
                        ->delete();
                    
                    \OrrisDB::table('nodes')
                        ->whereIn('id', $nodeIds)
                        ->delete();
                    $message = 'Nodes deleted successfully';
                    break;
                    
                case 'change_group':
                    if (empty($data['group_id'])) {
                        throw new Exception('Group ID is required');
                    }
                    \OrrisDB::table('nodes')
                        ->whereIn('id', $nodeIds)
                        ->update([
                            'group_id' => $data['group_id'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    $message = 'Node group changed successfully';
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            
            \OrrisDB::commit();
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            \OrrisDB::rollback();
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
            if (!$this->db) {
                return [];
            }
            
            $groups = \OrrisDB::table('node_groups')
                ->select('id', 'name', 'description', 'status')
                ->where('status', 1)
                ->orderBy('name', 'asc')
                ->get();
            
            return $groups;
            
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
            'v2ray' => 'V2Ray',
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
                'aes-256-gcm' => 'AES-256-GCM',
                'aes-128-gcm' => 'AES-128-GCM',
                'chacha20-ietf-poly1305' => 'ChaCha20-IETF-Poly1305'
            ],
            'v2ray' => [
                'auto' => 'Auto',
                'aes-128-gcm' => 'AES-128-GCM',
                'chacha20-poly1305' => 'ChaCha20-Poly1305',
                'none' => 'None'
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