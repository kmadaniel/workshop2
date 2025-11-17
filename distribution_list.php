<?php include "config.php"; ?>
<link rel="stylesheet" href="styles.css">

<div class="container">
    <h2>All Distributions</h2>

    <table class="table">
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Qty Sent</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php
        $query = $conn->query("SELECT * FROM distribution ORDER BY distribution_id DESC");

        while($row = $query->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['distribution_id'] ?></td>
            <td><?= $row['date'] ?></td>
            <td><?= $row['quantity_sent'] ?></td>
            <td><?= $row['status'] ?></td>
            <td>
                <a href="distribution_view.php?id=<?= $row['distribution_id'] ?>">View</a> |
                <a href="volunteer_assign.php?id=<?= $row['distribution_id'] ?>">Assign Volunteer</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
