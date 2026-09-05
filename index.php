<?php

require "database/connection.php";

$user_id = 1;

$sql = "SELECT name, email, phone, address, role, profile_photo
        FROM users
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html>

<head>

    <title>User Profile</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body>

<div class="container mt-5">

    <div class="card mx-auto" style="max-width: 400px;">

        <div class="card-body">

            <img src="https://via.placeholder.com/150"
                 alt="Profile Photo"
                 class="d-block mx-auto mb-3 rounded-circle">

            <h1 class="card-title text-center mb-4">
                My Profile
            </h1>

            <p>
                <strong>Name:</strong>
                <?php echo htmlspecialchars($user["name"]); ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?php echo htmlspecialchars($user["email"]); ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?php echo htmlspecialchars($user["phone"]); ?>
            </p>

            <p>
                <strong>Address:</strong>
                <?php echo htmlspecialchars($user["address"]); ?>
            </p>

            <p>
                <strong>Role:</strong>
                <?php echo htmlspecialchars($user["role"]); ?>
            </p>

            <div class="text-center mt-4">

                <a href="edit_profile.php"
                   class="btn btn-primary">
                    Edit Profile
                </a>

            </div>

        </div>

    </div>

</div>

</body>

</html>