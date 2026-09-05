<?php

require "database/connection.php";

$user_id = 1;
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];

    $sql = "UPDATE users
            SET name = ?, phone = ?, address = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("sssi", $name, $phone, $address, $user_id);

    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
    } else {
        $message = "Error updating profile.";
    }

    $stmt->close();
}


$sql = "SELECT name, phone, address FROM users WHERE id = ?";

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

    <title>Edit Profile</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body>

<div class="container mt-5">

    <div class="card mx-auto" style="max-width: 500px;">

        <div class="card-body">

            <h2 class="text-center mb-4">
                Edit Profile
            </h2>

            <?php if ($message != ""): ?>

                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="mb-3">

                    <label class="form-label">
                        Name
                    </label>

                    <input type="text"
                           name="name"
                           class="form-control"
                           value="<?php echo htmlspecialchars($user["name"]); ?>"
                           required>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Phone
                    </label>

                    <input type="tel"
                           name="phone"
                           class="form-control"
                           value="<?php echo htmlspecialchars($user["phone"]); ?>"
                           required>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Address
                    </label>

                    <textarea name="address"
                              class="form-control"
                              rows="3"
                              required><?php echo htmlspecialchars($user["address"]); ?></textarea>

                </div>


                <div class="text-center">

                    <button type="submit"
                            class="btn btn-primary">

                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>

</html>