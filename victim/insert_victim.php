<?php
include "../db.php";

try {
    $sql = "INSERT INTO victim 
    (name, age, gender, phone, address, postal_code, city, district, country, email,
     date_of_birth, family_members, has_baby, has_elderly, has_disabled, created_at)
    VALUES
    (:name, :age, :gender, :phone, :address, :postal_code, :city, :district, :country, :email,
     :date_of_birth, :family_members, :has_baby, :has_elderly, :has_disabled, NOW())";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':name' => $_POST['name'],
        ':age' => $_POST['age'],
        ':gender' => $_POST['gender'],
        ':phone' => $_POST['phone'],
        ':address' => $_POST['address'],
        ':postal_code' => $_POST['postal_code'],
        ':city' => $_POST['city'],
        ':district' => $_POST['district'],
        ':country' => $_POST['country'],
        ':email' => $_POST['email'],
        ':date_of_birth' => $_POST['date_of_birth'],
        ':family_members' => $_POST['family_members'],
        ':has_baby' => isset($_POST['has_baby']) ? 1 : 0,
        ':has_elderly' => isset($_POST['has_elderly']) ? 1 : 0,
        ':has_disabled' => isset($_POST['has_disabled']) ? 1 : 0
    ]);

    header("Location: list_victims.php?success=1");
    exit;

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
