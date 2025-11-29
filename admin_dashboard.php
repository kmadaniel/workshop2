<?php
session_start();
include 'db.php';

// Ensure only admin can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch users
$users = $conn->query("SELECT * FROM accounts ORDER BY account_id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch disasters
$disasters = $conn->query("SELECT * FROM disaster ORDER BY disaster_id ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; }
        header { background: #007bff; color: white; padding: 15px 20px; }
        header h1 { margin: 0; font-size: 24px; }
        .container { display: flex; min-height: 90vh; }
        nav { width: 250px; background: #fff; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        nav h3 { margin-top: 0; }
        nav a { display: block; padding: 10px 0; text-decoration: none; color: #007bff; border-bottom: 1px solid #eee; }
        main { flex: 1; padding: 20px; }
        h2 { margin-top: 0; }
        .card { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);}
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background: #007bff; color: white; }
        input, select, button, textarea { padding: 6px; border-radius: 6px; margin: 2px 0; width: 100%; }
        button { background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>

<header>
    <h1>Admin Dashboard</h1>
</header>

<div class="container">
    <nav>
        <h3>Navigation</h3>
        <a href="#users">Manage Users</a>
        <a href="#disasters">Manage Disasters</a>
        <a href="logout.php">Logout</a>
    </nav>

    <main>
        <!-- Users Section -->
        <div id="users" class="card">
            <h2>Users</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?= $u['account_id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $u['account_id'] ?>">Edit</a> |
                        <a href="delete_user.php?id=<?= $u['account_id'] ?>" onclick="return confirm('Delete this user?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3>Add New User</h3>
            <form method="POST" action="add_user.php">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="victim">Victim</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit">Add User</button>
            </form>
        </div>

        <!-- Disasters Section -->
        <div id="disasters" class="card">
            <h2>Disasters</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Alert Message</th>
                    <th>Actions</th>
                </tr>
                <?php foreach($disasters as $d): ?>
                <tr>
                    <td><?= $d['disaster_id'] ?></td>
                    <td><?= htmlspecialchars($d['disaster_name']) ?></td>
                    <td><?= htmlspecialchars($d['description']) ?></td>
                    <td><?= htmlspecialchars($d['alert_message'] ?? '') ?></td>
                    <td>
                        <a href="edit_disaster.php?id=<?= $d['disaster_id'] ?>">Edit</a> |
                        <a href="delete_disaster.php?id=<?= $d['disaster_id'] ?>" onclick="return confirm('Delete this disaster?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3>Add New Disaster</h3>
            <form method="POST" action="add_disaster.php">
                <input type="text" name="disaster_name" placeholder="Disaster Name" required>
                <input type="text" name="description" placeholder="Description" required>
                <textarea name="alert_message" placeholder="Alert Message for Victims (optional)"></textarea>
                <button type="submit">Add Disaster</button>
            </form>
        </div>
    </main>
</div>

</body>
</html>
