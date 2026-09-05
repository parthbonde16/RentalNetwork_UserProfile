<?php

session_start();

require "database/connection.php";

$user_id = 1;


// =====================================================
// FLASH MESSAGE
// =====================================================

$message = "";
$message_type = "";

if (isset($_SESSION["message"])) {

    $message = $_SESSION["message"];
    $message_type = $_SESSION["message_type"];

    unset($_SESSION["message"]);
    unset($_SESSION["message_type"]);
}


// =====================================================
// GET CURRENT USER DATA
// =====================================================

$sql = "SELECT name, phone, address, profile_photo
        FROM users
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

$current_photo = $user["profile_photo"];


// =====================================================
// UPDATE PROFILE
// =====================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);


    // =================================================
    // REMOVE PHOTO
    // =================================================

    if (isset($_POST["remove_photo"])) {

        if (!empty($current_photo) && file_exists($current_photo)) {

            unlink($current_photo);
        }


        $photo_sql = "UPDATE users
                      SET profile_photo = NULL
                      WHERE id = ?";

        $photo_stmt = $conn->prepare($photo_sql);

        $photo_stmt->bind_param(
            "i",
            $user_id
        );

        $photo_stmt->execute();

        $photo_stmt->close();


        $_SESSION["message"] =
            "Profile photo removed successfully.";

        $_SESSION["message_type"] =
            "success";


        header("Location: edit_profile.php");

        exit;
    }


    // =================================================
    // SERVER-SIDE VALIDATION
    // =================================================

    if ($name == "") {

        $_SESSION["message"] =
            "Name is required.";

        $_SESSION["message_type"] =
            "danger";

    } elseif (!preg_match("/^[a-zA-Z ]+$/", $name)) {

        $_SESSION["message"] =
            "Name can contain only letters and spaces.";

        $_SESSION["message_type"] =
            "danger";

    } elseif (strlen($name) < 2) {

        $_SESSION["message"] =
            "Name must contain at least 2 characters.";

        $_SESSION["message_type"] =
            "danger";

    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $_SESSION["message"] =
            "Phone number must contain exactly 10 digits.";

        $_SESSION["message_type"] =
            "danger";

    } elseif ($address == "") {

        $_SESSION["message"] =
            "Address is required.";

        $_SESSION["message_type"] =
            "danger";

    } elseif (strlen($address) < 5) {

        $_SESSION["message"] =
            "Address must contain at least 5 characters.";

        $_SESSION["message_type"] =
            "danger";

    } else {


        // =================================================
        // UPDATE BASIC INFORMATION
        // =================================================

        $sql = "UPDATE users
                SET name = ?, phone = ?, address = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sssi",
            $name,
            $phone,
            $address,
            $user_id
        );


        if ($stmt->execute()) {

            $_SESSION["message"] =
                "Profile updated successfully!";

            $_SESSION["message_type"] =
                "success";

        } else {

            $_SESSION["message"] =
                "Failed to update profile.";

            $_SESSION["message_type"] =
                "danger";
        }


        $stmt->close();


        // =================================================
        // PROFILE PHOTO UPLOAD
        // =================================================

        if (
            isset($_FILES["profile_photo"]) &&
            $_FILES["profile_photo"]["error"] != UPLOAD_ERR_NO_FILE
        ) {

            $file_name =
                $_FILES["profile_photo"]["name"];

            $file_tmp =
                $_FILES["profile_photo"]["tmp_name"];

            $file_size =
                $_FILES["profile_photo"]["size"];


            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];


            $file_extension =
                strtolower(
                    pathinfo(
                        $file_name,
                        PATHINFO_EXTENSION
                    )
                );


            // =================================================
            // FILE SIZE
            // =================================================

            if ($file_size > 5 * 1024 * 1024) {

                $_SESSION["message"] =
                    "Profile photo must be less than 5 MB.";

                $_SESSION["message_type"] =
                    "danger";


            // =================================================
            // FILE EXTENSION
            // =================================================

            } elseif (
                !in_array(
                    $file_extension,
                    $allowed_extensions
                )
            ) {

                $_SESSION["message"] =
                    "Only JPG, JPEG, PNG and WEBP images are allowed.";

                $_SESSION["message_type"] =
                    "danger";


            // =================================================
            // REAL IMAGE CHECK
            // =================================================

            } elseif (
                getimagesize($file_tmp) === false
            ) {

                $_SESSION["message"] =
                    "The uploaded file is not a valid image.";

                $_SESSION["message_type"] =
                    "danger";


            } else {


                // =================================================
                // CREATE UNIQUE FILE NAME
                // =================================================

                $new_file_name =
                    "profile_" .
                    $user_id .
                    "_" .
                    time() .
                    "." .
                    $file_extension;


                $upload_path =
                    "images/" . $new_file_name;


                // =================================================
                // MOVE FILE
                // =================================================

                if (
                    move_uploaded_file(
                        $file_tmp,
                        $upload_path
                    )
                ) {


                    // =================================================
                    // DELETE OLD PHOTO
                    // =================================================

                    if (
                        !empty($current_photo) &&
                        file_exists($current_photo)
                    ) {

                        unlink($current_photo);
                    }


                    // =================================================
                    // SAVE NEW PHOTO PATH
                    // =================================================

                    $photo_sql =
                        "UPDATE users
                         SET profile_photo = ?
                         WHERE id = ?";


                    $photo_stmt =
                        $conn->prepare($photo_sql);


                    $photo_stmt->bind_param(
                        "si",
                        $upload_path,
                        $user_id
                    );


                    $photo_stmt->execute();

                    $photo_stmt->close();


                    $_SESSION["message"] =
                        "Profile and profile photo updated successfully!";

                    $_SESSION["message_type"] =
                        "success";


                } else {

                    $_SESSION["message"] =
                        "Profile updated, but photo upload failed.";

                    $_SESSION["message_type"] =
                        "warning";
                }
            }
        }
    }


    // =================================================
    // REDIRECT AFTER POST
    // =================================================

    header("Location: edit_profile.php");

    exit;
}


// =====================================================
// GET UPDATED USER DATA
// =====================================================

$sql = "SELECT name, phone, address, profile_photo
        FROM users
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();

$current_photo = $user["profile_photo"];

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Profile</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>


<body>


<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-7">

            <div class="card shadow">

                <div class="card-body">


                    <h2 class="text-center mb-4">
                        Edit Profile
                    </h2>


                    <!-- =================================================
                         FLASH MESSAGE
                    ================================================== -->

                    <?php if ($message != ""): ?>

                        <div
                            class="alert alert-<?php
                            echo htmlspecialchars($message_type);
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars($message);
                            ?>

                        </div>

                    <?php endif; ?>


                    <!-- =================================================
                         CURRENT PROFILE PHOTO
                    ================================================== -->

                    <div class="text-center mb-4">

                        <?php if (!empty($current_photo)): ?>

                            <img
                                id="photoPreview"
                                src="<?php
                                echo htmlspecialchars($current_photo);
                                ?>"
                                alt="Profile Photo"
                                class="rounded-circle"
                                width="150"
                                height="150"
                                style="object-fit: cover;"
                            >

                        <?php else: ?>

                            <div
                                id="photoPlaceholder"
                                class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto"
                                style="width:150px;height:150px;"
                            >

                                No Photo

                            </div>


                            <img
                                id="photoPreview"
                                src=""
                                alt="Photo Preview"
                                class="rounded-circle d-none"
                                width="150"
                                height="150"
                                style="object-fit: cover;"
                            >

                        <?php endif; ?>

                    </div>


                    <!-- =================================================
                         FORM
                    ================================================== -->

                    <form
                        id="profileForm"
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <!-- NAME -->

                        <div class="mb-3">

                            <label class="form-label">
                                Name
                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                value="<?php
                                echo htmlspecialchars($user["name"]);
                                ?>"
                            >


                            <div
                                id="nameMessage"
                                class="mt-1"
                            ></div>

                        </div>


                        <!-- PHONE -->

                        <div class="mb-3">

                            <label class="form-label">
                                Phone Number
                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="phone"
                                name="phone"
                                maxlength="10"
                                value="<?php
                                echo htmlspecialchars($user["phone"]);
                                ?>"
                            >


                            <div
                                id="phoneMessage"
                                class="mt-1"
                            ></div>

                        </div>


                        <!-- ADDRESS -->

                        <div class="mb-3">

                            <label class="form-label">
                                Address
                            </label>


                            <textarea
                                class="form-control"
                                id="address"
                                name="address"
                                rows="3"
                            ><?php
                            echo htmlspecialchars($user["address"]);
                            ?></textarea>


                            <div
                                id="addressMessage"
                                class="mt-1"
                            ></div>

                        </div>


                        <!-- PROFILE PHOTO -->

                        <div class="mb-3">

                            <label class="form-label">
                                Profile Photo
                            </label>


                            <input
                                type="file"
                                class="form-control"
                                id="profile_photo"
                                name="profile_photo"
                                accept=".jpg,.jpeg,.png,.webp"
                            >


                            <div
                                id="photoMessage"
                                class="mt-2"
                            ></div>

                        </div>


                        <!-- REMOVE PHOTO -->

                        <?php if (!empty($current_photo)): ?>

                            <div class="mb-3">

                                <button
                                    type="submit"
                                    name="remove_photo"
                                    value="1"
                                    class="btn btn-outline-danger w-100"
                                    onclick="return confirm('Are you sure you want to remove your profile photo?');"
                                >

                                    🗑️ Remove Profile Photo

                                </button>

                            </div>

                        <?php endif; ?>


                        <!-- SAVE -->

                        <button
                            type="submit"
                            id="saveButton"
                            class="btn btn-primary w-100"
                        >

                            Save Changes

                        </button>


                        <!-- CANCEL -->

                        <a
                            href="index.php"
                            class="btn btn-secondary w-100 mt-2"
                        >

                            Cancel

                        </a>


                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<script>


// =====================================================
// ELEMENTS
// =====================================================

const nameInput =
    document.getElementById("name");

const phoneInput =
    document.getElementById("phone");

const addressInput =
    document.getElementById("address");

const photoInput =
    document.getElementById("profile_photo");


const nameMessage =
    document.getElementById("nameMessage");

const phoneMessage =
    document.getElementById("phoneMessage");

const addressMessage =
    document.getElementById("addressMessage");

const photoMessage =
    document.getElementById("photoMessage");


const saveButton =
    document.getElementById("saveButton");


const photoPreview =
    document.getElementById("photoPreview");

const photoPlaceholder =
    document.getElementById("photoPlaceholder");


// =====================================================
// VALIDATION STATUS
// =====================================================

let nameValid = false;

let phoneValid = false;

let addressValid = false;

let photoValid = true;


// =====================================================
// NAME VALIDATION
// =====================================================

function validateName() {

    const name =
        nameInput.value.trim();

    const namePattern =
        /^[a-zA-Z ]+$/;


    if (name === "") {

        nameMessage.innerHTML =
            '<span class="text-danger">❌ Name is required.</span>';

        nameValid = false;

    } else if (!namePattern.test(name)) {

        nameMessage.innerHTML =
            '<span class="text-danger">❌ Only letters and spaces are allowed.</span>';

        nameValid = false;

    } else if (name.length < 2) {

        nameMessage.innerHTML =
            '<span class="text-danger">❌ Name must contain at least 2 characters.</span>';

        nameValid = false;

    } else {

        nameMessage.innerHTML =
            '<span class="text-success">✅ Name is valid.</span>';

        nameValid = true;
    }


    updateSaveButton();
}


// =====================================================
// PHONE VALIDATION
// =====================================================

function validatePhone() {

    const phone =
        phoneInput.value.trim();

    const phonePattern =
        /^[0-9]{10}$/;


    if (phone === "") {

        phoneMessage.innerHTML =
            '<span class="text-danger">❌ Phone number is required.</span>';

        phoneValid = false;

    } else if (!phonePattern.test(phone)) {

        phoneMessage.innerHTML =
            '<span class="text-danger">❌ Enter exactly 10 digits.</span>';

        phoneValid = false;

    } else {

        phoneMessage.innerHTML =
            '<span class="text-success">✅ Phone number is valid.</span>';

        phoneValid = true;
    }


    updateSaveButton();
}


// =====================================================
// ADDRESS VALIDATION
// =====================================================

function validateAddress() {

    const address =
        addressInput.value.trim();


    if (address === "") {

        addressMessage.innerHTML =
            '<span class="text-danger">❌ Address is required.</span>';

        addressValid = false;

    } else if (address.length < 5) {

        addressMessage.innerHTML =
            '<span class="text-danger">❌ Address must contain at least 5 characters.</span>';

        addressValid = false;

    } else {

        addressMessage.innerHTML =
            '<span class="text-success">✅ Address is valid.</span>';

        addressValid = true;
    }


    updateSaveButton();
}


// =====================================================
// PHOTO VALIDATION + PREVIEW
// =====================================================

function validatePhoto() {

    const file =
        photoInput.files[0];


    // No new photo selected

    if (!file) {

        photoMessage.innerHTML = "";

        photoValid = true;

        updateSaveButton();

        return;
    }


    // Show filename

    photoMessage.innerHTML =
        "Selected file: <strong>" +
        file.name +
        "</strong><br>";


    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];


    const maxSize =
        5 * 1024 * 1024;


    // Check type

    if (!allowedTypes.includes(file.type)) {

        photoMessage.innerHTML +=
            '<span class="text-danger">❌ Invalid image type. Use JPG, JPEG, PNG or WEBP.</span>';

        photoValid = false;

        updateSaveButton();

        return;
    }


    // Check size

    if (file.size > maxSize) {

        photoMessage.innerHTML +=
            '<span class="text-danger">❌ Image must be less than 5 MB.</span>';

        photoValid = false;

        updateSaveButton();

        return;
    }


    // Valid image

    photoMessage.innerHTML +=
        '<span class="text-success">✅ Valid image (' +
        (file.size / (1024 * 1024)).toFixed(2) +
        ' MB).</span>';


    photoValid = true;


    // =================================================
    // IMAGE PREVIEW
    // =================================================

    const reader =
        new FileReader();


    reader.onload =
        function(event) {

            photoPreview.src =
                event.target.result;

            photoPreview.classList.remove("d-none");


            if (photoPlaceholder) {

                photoPlaceholder.classList.add("d-none");
            }
        };


    reader.readAsDataURL(file);


    updateSaveButton();
}


// =====================================================
// SAVE BUTTON
// =====================================================

function updateSaveButton() {

    if (
        nameValid &&
        phoneValid &&
        addressValid &&
        photoValid
    ) {

        saveButton.disabled = false;

    } else {

        saveButton.disabled = true;
    }
}


// =====================================================
// LIVE VALIDATION
// =====================================================

nameInput.addEventListener(
    "input",
    validateName
);


phoneInput.addEventListener(
    "input",
    validatePhone
);


addressInput.addEventListener(
    "input",
    validateAddress
);


photoInput.addEventListener(
    "change",
    validatePhoto
);


// =====================================================
// INITIAL VALIDATION
// =====================================================

validateName();

validatePhone();

validateAddress();

validatePhoto();


</script>


</body>

</html>