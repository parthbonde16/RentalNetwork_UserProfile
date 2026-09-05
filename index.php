<?php

require "database/connection.php";

$user_id = 1;


// Get user information

$sql = "SELECT name, email, phone, address, role, profile_photo
        FROM users
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


// Get user's rental history

$sql = "SELECT item_name, rental_date, status
        FROM bookings
        WHERE user_id = ?
        ORDER BY rental_date DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$bookings_result = $stmt->get_result();

$stmt->close();


// Get user's listed items

$sql = "SELECT item_name, price_per_day
        FROM items
        WHERE owner_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$items_result = $stmt->get_result();

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

    <div class="card mx-auto" style="max-width: 500px;">

        <div class="card-body">


            <!-- Profile Photo -->

            <?php if (!empty($user["profile_photo"])): ?>

                <img src="<?php echo htmlspecialchars($user["profile_photo"]); ?>"
                     alt="Profile Photo"
                     width="150"
                     height="150"
                     class="d-block mx-auto mb-3 rounded-circle">

            <?php else: ?>

                <p class="text-center">
                    No profile photo uploaded.
                </p>

            <?php endif; ?>


            <h1 class="card-title text-center mb-4">
                My Profile
            </h1>


            <!-- Profile Information -->

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


            <!-- Edit Profile -->

            <div class="text-center mt-4">

                <a href="edit_profile.php"
                   class="btn btn-primary">

                    Edit Profile

                </a>

            </div>


            <hr class="my-4">


            <!-- Rental History -->

            <h3 class="mb-3">
                Rental History
            </h3>


            <?php if ($bookings_result->num_rows > 0): ?>

                <?php while ($booking = $bookings_result->fetch_assoc()): ?>

                    <div class="border rounded p-3 mb-3">

                        <h5>
                            <?php echo htmlspecialchars($booking["item_name"]); ?>
                        </h5>

                        <p class="mb-1">

                            <strong>Date:</strong>

                            <?php echo htmlspecialchars($booking["rental_date"]); ?>

                        </p>

                        <p class="mb-0">

                            <strong>Status:</strong>

                            <?php echo htmlspecialchars($booking["status"]); ?>

                        </p>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <p>
                    No rental history found.
                </p>

            <?php endif; ?>


            <hr class="my-4">


            <!-- Listed Items -->

            <h3 class="mb-3">
                My Listed Items
            </h3>


            <?php if ($items_result->num_rows > 0): ?>

                <?php while ($item = $items_result->fetch_assoc()): ?>

                    <div class="border rounded p-3 mb-3">

                        <h5>
                            <?php echo htmlspecialchars($item["item_name"]); ?>
                        </h5>

                        <p class="mb-0">

                            <strong>Price:</strong>
                            ₹<?php echo htmlspecialchars($item["price_per_day"]); ?>/day

                        </p>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <p>
                    No items listed.
                </p>

            <?php endif; ?>


        </div>

    </div>

</div>

</body>

</html>