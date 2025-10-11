<?php
/**
 * ORRISM Server API Routes Definition
 * Defines all API endpoints and their handlers
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

// ============================================
// Node Routes
// ============================================

// Get node list
$router->get('/nodes', 'NodeHandler@index');

// Get single node information
$router->get('/nodes/{id}', 'NodeHandler@show');

// Node heartbeat (status update)
$router->post('/nodes/{id}/heartbeat', 'NodeHandler@heartbeat');

// Get users for a specific node
$router->get('/nodes/{id}/users', 'NodeHandler@users');

// ============================================
// Group Routes
// ============================================

// Get users for a specific node group
$router->get('/groups/{id}/users', 'UserHandler@byGroup');

// ============================================
// User Routes
// ============================================

// Get single user information
$router->get('/users/{sid}', 'UserHandler@show');

// Get user traffic statistics
$router->get('/users/{sid}/traffic', 'UserHandler@traffic');

// ============================================
// Traffic Routes
// ============================================

// Batch report traffic from node
$router->post('/traffic/report', 'TrafficHandler@report');

// Reset user traffic
$router->post('/traffic/reset', 'TrafficHandler@reset');

// ============================================
// Health Check Route
// ============================================

// API health check
$router->get('/health', 'HealthHandler@check');
