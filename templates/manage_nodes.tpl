<h2>Manage Nodes</h2>
<form method="post" action="addonmodules.php?module=ssmManage&action=save_node">
    <label for="node_name">Node Name:</label>
    <input type="text" name="node_name" id="node_name" required>
    <br>
    <label for="address">Address:</label>
    <input type="text" name="address" id="address" required>
    <br>
    <label for="port">Port:</label>
    <input type="number" name="port" id="port" required>
    <br>
    <label for="node_method">Node Method:</label>
    <input type="text" name="node_method" id="node_method" required>
    <br>
    <label for="rate">Rate:</label>
    <input type="number" name="rate" id="rate" required>
    <br>
    <label for="network_type">Network Type:</label>
    <input type="text" name="network_type" id="network_type" required>
    <br>
    <label for="tag">Tag:</label>
    <input type="text" name="tag" id="tag" required>
    <br>
    <label for="enable">Enable:</label>
    <select name="enable" id="enable">
        <option value="1">Yes</option>
        <option value="0">No</option>
    </select>
    <br>
    <input type="submit" value="Save Node">
</form>

<table>
    <tr>
        <th>ID</th>
        <th>Node Name</th>
        <th>Address</th>
        <th>Port</th>
        <th>Node Method</th>
        <th>Rate</th>
        <th>Network Type</th>
        <th>Tag</th>
        <th>Enable</th>
        <th>Actions</th>
    </tr>
    <!-- ...existing code to list and manage existing nodes... -->
</table>
<a href="addonmodules.php?module=ssmManage&action=add_node">Add New Node</a>
