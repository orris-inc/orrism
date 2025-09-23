<?php
/**
 * ORRISM Node Management UI Functions
 * Contains all node-related UI rendering functions
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    1.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Render navigation tabs
 * 
 * @param string $activeAction Current active action
 * @return string
 */
function renderNavigationTabs($activeAction)
{
    $tabs = [
        'dashboard' => 'Dashboard',
        'nodes' => 'Node Management',
        'users' => 'User Management',
        'settings' => 'Settings'
    ];
    
    $nav = '<div class="orrism-nav-tabs">';
    foreach ($tabs as $action => $label) {
        $isActive = ($action === $activeAction);
        $classes = $isActive ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
        $nav .= '<a href="?module=orrism_admin&action=' . $action . '" class="' . $classes . '">' . $label . '</a>';
    }
    $nav .= '</div>';
    
    return $nav;
}

/**
 * Render node management page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderNodeManagement($vars)
{
    try {
        // Include NodeManager class
        require_once __DIR__ . '/node_manager.php';
        
        $nodeManager = new NodeManager();
        
        // Get current page and filters
        $page = isset($_GET['node_page']) ? max(1, (int)$_GET['node_page']) : 1;
        $filters = [
            'status' => $_GET['node_status'] ?? '',
            'type' => $_GET['node_type'] ?? '',
            'group_id' => $_GET['node_group'] ?? '',
            'search' => $_GET['node_search'] ?? ''
        ];
        
        // Get nodes with stats
        $result = $nodeManager->getNodesWithStats($page, 20, $filters);
        $nodes = $result['nodes'] ?? [];
        $totalPages = $result['totalPages'] ?? 1;
        
        // Get node groups and types for filters
        $nodeGroups = $nodeManager->getNodeGroups();
        $nodeTypes = $nodeManager->getNodeTypes();
        
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>Node Management</h2>';
        
        // Navigation
        $content .= renderNavigationTabs('nodes');
        
        // Toolbar
        $content .= renderNodeToolbar($filters, $nodeTypes, $nodeGroups);
        
        // Nodes table
        $content .= renderNodeTable($nodes);
        
        // Pagination
        if ($totalPages > 1) {
            $content .= renderNodePagination($page, $totalPages);
        }
        
        // Add/Edit Node Modal
        $content .= renderNodeModal($nodeTypes, $nodeGroups);
        
        // JavaScript
        $content .= renderNodeJavaScript();
        
        $content .= '</div>';
        
        return $content;
        
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Node Management Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Render node toolbar with filters and actions
 */
function renderNodeToolbar($filters, $nodeTypes, $nodeGroups)
{
    $content = '<div class="row" style="margin-bottom: 20px;">';
    $content .= '<div class="col-md-8">';
    
    // Search box
    $content .= '<div class="form-inline">';
    $content .= '<input type="text" id="nodeSearchInput" class="form-control" placeholder="Search by name or address" value="' . htmlspecialchars($filters['search']) . '" style="margin-right: 10px;">';
    
    // Type filter
    $content .= '<select id="nodeTypeFilter" class="form-control" style="margin-right: 10px;">';
    $content .= '<option value="">All Types</option>';
    foreach ($nodeTypes as $key => $label) {
        $selected = ($filters['type'] == $key) ? 'selected' : '';
        $content .= '<option value="' . $key . '" ' . $selected . '>' . $label . '</option>';
    }
    $content .= '</select>';
    
    // Status filter
    $content .= '<select id="nodeStatusFilter" class="form-control" style="margin-right: 10px;">';
    $content .= '<option value="">All Status</option>';
    $content .= '<option value="1" ' . ($filters['status'] === '1' ? 'selected' : '') . '>Active</option>';
    $content .= '<option value="0" ' . ($filters['status'] === '0' ? 'selected' : '') . '>Inactive</option>';
    $content .= '</select>';
    
    // Group filter
    $content .= '<select id="nodeGroupFilter" class="form-control" style="margin-right: 10px;">';
    $content .= '<option value="">All Groups</option>';
    foreach ($nodeGroups as $group) {
        $selected = ($filters['group_id'] == $group->id) ? 'selected' : '';
        $content .= '<option value="' . $group->id . '" ' . $selected . '>' . htmlspecialchars($group->name) . '</option>';
    }
    $content .= '</select>';
    
    $content .= '<button class="btn btn-default" onclick="filterNodes()"><i class="fa fa-filter"></i> Filter</button>';
    $content .= ' <button class="btn btn-default" onclick="clearFilters()"><i class="fa fa-times"></i> Clear</button>';
    $content .= '</div>';
    $content .= '</div>';
    
    // Action buttons
    $content .= '<div class="col-md-4 text-right">';
    $content .= '<button class="btn btn-primary" onclick="showAddNodeModal()"><i class="fa fa-plus"></i> Add Node</button> ';
    $content .= '<button class="btn btn-default" onclick="refreshNodeList()"><i class="fa fa-refresh"></i> Refresh</button> ';
    
    // Bulk actions dropdown
    $content .= '<div class="btn-group">';
    $content .= '<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">';
    $content .= 'Bulk Actions <span class="caret"></span>';
    $content .= '</button>';
    $content .= '<ul class="dropdown-menu dropdown-menu-right">';
    $content .= '<li><a href="#" onclick="bulkNodeAction(\'enable\')">Enable Selected</a></li>';
    $content .= '<li><a href="#" onclick="bulkNodeAction(\'disable\')">Disable Selected</a></li>';
    $content .= '<li class="divider"></li>';
    $content .= '<li><a href="#" onclick="bulkNodeAction(\'delete\')" class="text-danger">Delete Selected</a></li>';
    $content .= '</ul>';
    $content .= '</div>';
    
    $content .= '</div>';
    $content .= '</div>';
    
    return $content;
}

/**
 * Render node table
 */
function renderNodeTable($nodes)
{
    $content = '<div class="orrism-table-responsive">';
    $content .= '<table class="table table-striped table-bordered">';
    $content .= '<thead>';
    $content .= '<tr>';
    $content .= '<th width="30"><input type="checkbox" id="selectAllNodes"></th>';
    $content .= '<th width="50">ID</th>';
    $content .= '<th>Type</th>';
    $content .= '<th>Name</th>';
    $content .= '<th>Address</th>';
    $content .= '<th>Traffic Rate</th>';
    $content .= '<th>Users</th>';
    $content .= '<th>Traffic</th>';
    $content .= '<th>Status</th>';
    $content .= '<th>Last Check</th>';
    $content .= '<th width="120">Actions</th>';
    $content .= '</tr>';
    $content .= '</thead>';
    $content .= '<tbody id="nodesTableBody">';
    
    if (!empty($nodes)) {
        foreach ($nodes as $node) {
            $content .= '<tr data-node-id="' . $node->id . '">';
            $content .= '<td><input type="checkbox" class="node-checkbox" value="' . $node->id . '"></td>';
            $content .= '<td>' . $node->id . '</td>';
            $content .= '<td><span class="label label-default">' . strtoupper($node->node_type) . '</span></td>';
            $content .= '<td>' . htmlspecialchars($node->node_name) . '</td>';
            $content .= '<td>' . htmlspecialchars($node->address . ':' . $node->port) . '</td>';
            $content .= '<td>' . $node->formatted_rate . '</td>';
            $content .= '<td>' . $node->current_users . '</td>';
            $content .= '<td>' . $node->formatted_traffic . '</td>';
            $content .= '<td>';
            if ($node->status) {
                $content .= '<span class="text-success">Active</span>';
            } else {
                $content .= '<span class="text-muted">Inactive</span>';
            }
            $content .= '</td>';
            $content .= '<td>' . $node->formatted_time . '</td>';
            $content .= '<td>';
            $content .= '<div class="btn-group btn-group-sm">';
            $content .= '<button class="btn btn-default" onclick="editNode(' . $node->id . ')" title="Edit"><i class="fa fa-pencil"></i></button>';
            $content .= '<button class="btn btn-default" onclick="toggleNode(' . $node->id . ')" title="Toggle Status"><i class="fa fa-power-off"></i></button>';
            $content .= '<button class="btn btn-danger" onclick="deleteNode(' . $node->id . ')" title="Delete"><i class="fa fa-trash"></i></button>';
            $content .= '</div>';
            $content .= '</td>';
            $content .= '</tr>';
        }
    } else {
        $content .= '<tr><td colspan="11" class="text-center">No nodes found</td></tr>';
    }
    
    $content .= '</tbody>';
    $content .= '</table>';
    $content .= '</div>';
    
    return $content;
}

/**
 * Render pagination
 */
function renderNodePagination($page, $totalPages)
{
    $content = '<div class="text-center">';
    $content .= '<ul class="pagination">';
    
    // Previous button
    if ($page > 1) {
        $content .= '<li><a href="?module=orrism_admin&action=nodes&node_page=' . ($page - 1) . '">&laquo; Previous</a></li>';
    } else {
        $content .= '<li class="disabled"><span>&laquo; Previous</span></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= min($totalPages, 10); $i++) {
        if ($i == $page) {
            $content .= '<li class="active"><span>' . $i . '</span></li>';
        } else {
            $content .= '<li><a href="?module=orrism_admin&action=nodes&node_page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($page < $totalPages) {
        $content .= '<li><a href="?module=orrism_admin&action=nodes&node_page=' . ($page + 1) . '">Next &raquo;</a></li>';
    } else {
        $content .= '<li class="disabled"><span>Next &raquo;</span></li>';
    }
    
    $content .= '</ul>';
    $content .= '</div>';
    
    return $content;
}

/**
 * Render node modal for add/edit
 */
function renderNodeModal($nodeTypes, $nodeGroups)
{
    $content = '
    <!-- Node Modal -->
    <div class="modal fade" id="nodeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="nodeModalTitle">Add Node</h4>
                </div>
                <div class="modal-body">
                    <form id="nodeForm">
                        <input type="hidden" id="nodeId" name="node_id" value="">
                        
                        <div class="form-group">
                            <label for="nodeType">Node Type *</label>
                            <select class="form-control" id="nodeType" name="node_type" required>';
    
    foreach ($nodeTypes as $key => $label) {
        $content .= '<option value="' . $key . '">' . $label . '</option>';
    }
    
    $content .= '
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="nodeName">Node Name *</label>
                            <input type="text" class="form-control" id="nodeName" name="node_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nodeAddress">Server Address *</label>
                            <input type="text" class="form-control" id="nodeAddress" name="address" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nodePort">Port *</label>
                            <input type="number" class="form-control" id="nodePort" name="port" min="1" max="65535" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nodeGroup">Node Group</label>
                            <select class="form-control" id="nodeGroup" name="group_id">';
    
    foreach ($nodeGroups as $group) {
        $content .= '<option value="' . $group->id . '">' . htmlspecialchars($group->name) . '</option>';
    }
    
    $content .= '
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="nodeMethod">Encryption Method</label>
                            <select class="form-control" id="nodeMethod" name="node_method">
                                <!-- Options will be loaded dynamically based on node type -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="nodeRate">Traffic Rate</label>
                            <input type="number" class="form-control" id="nodeRate" name="rate" value="1.0" step="0.1" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="nodeSortOrder">Sort Order</label>
                            <input type="number" class="form-control" id="nodeSortOrder" name="sort_order" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="nodeStatus" name="status" value="1" checked> Active
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveNode()">Save</button>
                </div>
            </div>
        </div>
    </div>';
    
    return $content;
}

/**
 * Render node JavaScript
 */
function renderNodeJavaScript()
{
    return '
    <script>
    // Node management JavaScript
    var currentNodeId = null;
    
    // Select all checkbox
    $("#selectAllNodes").on("change", function() {
        $(".node-checkbox").prop("checked", $(this).prop("checked"));
    });
    
    // Filter nodes
    function filterNodes() {
        var params = {
            node_search: $("#nodeSearchInput").val(),
            node_type: $("#nodeTypeFilter").val(),
            node_status: $("#nodeStatusFilter").val(),
            node_group: $("#nodeGroupFilter").val()
        };
        
        var queryString = $.param(params);
        window.location.href = "?module=orrism_admin&action=nodes&" + queryString;
    }
    
    // Clear filters
    function clearFilters() {
        window.location.href = "?module=orrism_admin&action=nodes";
    }
    
    // Refresh node list
    function refreshNodeList() {
        window.location.reload();
    }
    
    // Show add node modal
    function showAddNodeModal() {
        currentNodeId = null;
        $("#nodeModalTitle").text("Add Node");
        $("#nodeForm")[0].reset();
        $("#nodeId").val("");
        
        // Trigger node type change to load encryption methods
        $("#nodeType").trigger("change");
        
        $("#nodeModal").modal("show");
    }
    
    // Edit node
    function editNode(nodeId) {
        currentNodeId = nodeId;
        $("#nodeModalTitle").text("Edit Node");
        
        // Load node data
        $.post("addonmodules.php?module=orrism_admin&action=node_get", {
            node_id: nodeId
        }, function(response) {
            if (response.success && response.node) {
                var node = response.node;
                $("#nodeId").val(node.id);
                $("#nodeType").val(node.node_type);
                $("#nodeName").val(node.node_name);
                $("#nodeAddress").val(node.address);
                $("#nodePort").val(node.port);
                $("#nodeGroup").val(node.group_id);
                $("#nodeRate").val(node.rate);
                $("#nodeSortOrder").val(node.sort_order);
                $("#nodeStatus").prop("checked", node.status == 1);
                
                // Load encryption methods for this node type, then set the value
                loadEncryptionMethods(node.node_type, function() {
                    $("#nodeMethod").val(node.node_method);
                });
                
                $("#nodeModal").modal("show");
            } else {
                alert("Failed to load node: " + (response.message || "Unknown error"));
            }
        }, "json");
    }
    
    // Load encryption methods for a specific node type
    function loadEncryptionMethods(nodeType, callback) {
        var methodSelect = $("#nodeMethod");
        methodSelect.empty().append($("<option>").text("Loading..."));
        
        $.post("addonmodules.php?module=orrism_admin&action=node_get_methods", {
            node_type: nodeType
        }, function(response) {
            methodSelect.empty();
            
            if (response.success && response.methods) {
                $.each(response.methods, function(value, label) {
                    methodSelect.append($("<option>").attr("value", value).text(label));
                });
            } else {
                // Fallback methods
                var fallback = {
                    shadowsocks: {
                        "aes-128-gcm": "AES-128-GCM",
                        "aes-192-gcm": "AES-192-GCM",
                        "aes-256-gcm": "AES-256-GCM",
                        "chacha20-ietf-poly1305": "ChaCha20-IETF-Poly1305"
                    },
                    vless: {"none": "None"},
                    vmess: {
                        "auto": "Auto",
                        "aes-128-gcm": "AES-128-GCM",
                        "chacha20-poly1305": "ChaCha20-Poly1305",
                        "none": "None"
                    },
                    trojan: {"none": "None"}
                }[nodeType] || {"none": "None"};
                
                $.each(fallback, function(value, label) {
                    methodSelect.append($("<option>").attr("value", value).text(label));
                });
            }
            
            if (callback) callback();
        }, "json");
    }
    
    // Save node
    function saveNode() {
        var formData = $("#nodeForm").serialize();
        var action = currentNodeId ? "node_update" : "node_create";
        
        // Add status if unchecked
        if (!$("#nodeStatus").prop("checked")) {
            formData += "&status=0";
        }
        
        $.post("addonmodules.php?module=orrism_admin&action=" + action, formData, function(response) {
            if (response.success) {
                $("#nodeModal").modal("hide");
                alert("Node saved successfully!");
                refreshNodeList();
            } else {
                alert("Failed to save node: " + (response.message || "Unknown error"));
            }
        }, "json");
    }
    
    // Toggle node status
    function toggleNode(nodeId) {
        if (confirm("Are you sure you want to toggle this node status?")) {
            $.post("addonmodules.php?module=orrism_admin&action=node_toggle", {
                node_id: nodeId
            }, function(response) {
                if (response.success) {
                    refreshNodeList();
                } else {
                    alert("Failed to toggle node: " + (response.message || "Unknown error"));
                }
            }, "json");
        }
    }
    
    // Delete node
    function deleteNode(nodeId) {
        if (confirm("Are you sure you want to delete this node? This action cannot be undone.")) {
            $.post("addonmodules.php?module=orrism_admin&action=node_delete", {
                node_id: nodeId
            }, function(response) {
                if (response.success) {
                    alert("Node deleted successfully!");
                    refreshNodeList();
                } else {
                    alert("Failed to delete node: " + (response.message || "Unknown error"));
                }
            }, "json");
        }
    }
    
    // Bulk node action
    function bulkNodeAction(action) {
        var selectedNodes = [];
        $(".node-checkbox:checked").each(function() {
            selectedNodes.push($(this).val());
        });
        
        if (selectedNodes.length === 0) {
            alert("Please select at least one node");
            return;
        }
        
        var confirmMsg = "";
        switch(action) {
            case "enable":
                confirmMsg = "Enable " + selectedNodes.length + " selected node(s)?";
                break;
            case "disable":
                confirmMsg = "Disable " + selectedNodes.length + " selected node(s)?";
                break;
            case "delete":
                confirmMsg = "Delete " + selectedNodes.length + " selected node(s)? This cannot be undone.";
                break;
        }
        
        if (confirm(confirmMsg)) {
            $.post("addonmodules.php?module=orrism_admin&action=node_batch", {
                node_ids: selectedNodes,
                batch_action: action
            }, function(response) {
                if (response.success) {
                    alert(response.message || "Batch operation completed");
                    refreshNodeList();
                } else {
                    alert("Batch operation failed: " + (response.message || "Unknown error"));
                }
            }, "json");
        }
    }
    
    // Update encryption methods based on node type
    $("#nodeType").on("change", function() {
        var nodeType = $(this).val();
        loadEncryptionMethods(nodeType);
    });
    </script>';
}