<?php

require "database/connection.php";

$user_id = 1;
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];


    // Update basic profile information

    $sql = "UPDATE users
            SET name = ?, phone = ?, address = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("sssi", $name, $phone, $address, $user_id);

    $stmt->execute();

    $stmt->close();


    // Check whether a photo was uploaded

    if (isset($_FILES["profile_photo"]) &&
        $_FILES["profile_photo"]["error"] == 0) {

        $file = $_FILES["profile_photo"];

        $file_name = $file["name"];
        $file_tmp = $file["tmp_name"];
        $file_size = $file["size"];


        // Check file size
        if ($file_size > 5 * 1024 * 1024) {

            $message = "Image must be less than 5 MB.";

        } else {

            // Check whether the file is really an image

            $image_info = getimagesize($file_tmp);

            if ($image_info === false) {

                $message = "Please upload a valid image.";

            } else {

                // Get file extension

                $extension = strtolower(
                    pathinfo($file_name, PATHINFO_EXTENSION)
                );


                // Allowed extensions

                $allowed_extensions = ["jpg", "jpeg", "png", "webp"];


                if (!in_array($extension, $allowed_extensions)) {

                    $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";

                } else {

                    // Create a unique filename

                    $new_file_name =
                        "profile_" . $user_id . "_" . time() . "." . $extension;


                    // Location where image will be saved

                    $upload_path = "images/" . $new_file_name;


                    // Move uploaded image

                    if (move_uploaded_file(
                        $file_tmp,
                        $upload_path
                    )) {

                        // Save image path in database

                        $sql = "UPDATE users
                                SET profile_photo = ?
                                WHERE id = ?";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "si",
                            $upload_path,
                            $user_id
                        );

                        $stmt->execute();

                        $stmt->close();

                        $message =
                            "Profile and photo updated successfully!";

                    } else {

                        $message = "Failed to upload image.";

                    }
                }
            }
        }

    } else {

        $message = "Profile updated successfully!";
    }
}


/*
   Get current user information
*/

$sql = "SELECT name, phone, address, profile_photo
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

                <div class="alert alert-info">
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php endif; ?>


            <form method="POST"
                  enctype="multipart/form-data">


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


                <div class="mb-3">

                    <label class="form-label">
                        Profile Photo
                    </label>

                    <input type="file"
                           name="profile_photo"
                           class="form-control"
                           accept="image/*">

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