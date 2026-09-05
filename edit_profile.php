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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile | Rental Network</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f6f8fc;
            color: #212529;
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;
        }

        .edit-page {
            max-width: 760px;
            margin: 45px auto;
        }

        .top-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .top-link:hover {
            color: #0d6efd;
        }

        .page-heading {
            margin-bottom: 22px;
        }

        .page-title {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 0;
        }

        .edit-card {
            background: #ffffff;
            border: 1px solid #e7ebf0;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 8px 28px rgba(33, 37, 41, 0.06);
        }

        .photo-section {
            text-align: center;
            padding-bottom: 25px;
            margin-bottom: 25px;
            border-bottom: 1px solid #edf0f4;
        }

        .photo-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 12px;
        }

        .profile-photo,
        .photo-placeholder {
            width: 145px;
            height: 145px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-photo {
            object-fit: cover;
            border: 4px solid #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.14);
        }

        .photo-placeholder {
            background: #212529;
            color: #ffffff;
            font-size: 45px;
            font-weight: 600;
            border: 4px solid #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.14);
        }

        .photo-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .photo-help {
            color: #89919a;
            font-size: 12px;
            margin: 0;
        }

        .form-section-title {
            font-size: 17px;
            font-weight: 650;
            margin-bottom: 18px;
        }

        .form-section-title i {
            margin-right: 7px;
            color: #495057;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 7px;
        }

        .form-control {
            min-height: 45px;
            border: 1px solid #dfe4ea;
            border-radius: 9px;
            font-size: 14px;
            padding: 10px 13px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.10);
        }

        textarea.form-control {
            min-height: 95px;
            resize: vertical;
        }

        .input-group-text {
            background: #f8f9fb;
            border: 1px solid #dfe4ea;
            color: #6c757d;
            border-radius: 9px 0 0 9px;
            padding-left: 13px;
            padding-right: 13px;
        }

        .input-group .form-control {
            border-radius: 0 9px 9px 0;
        }

        .validation-message {
            min-height: 19px;
            margin-top: 6px;
            font-size: 12px;
        }

        .validation-message .text-success {
            color: #23834a !important;
        }

        .validation-message .text-danger {
            color: #c0392b !important;
        }

        .photo-message {
            min-height: 20px;
            font-size: 12px;
            color: #68717d;
        }

        .upload-help {
            color: #89919a;
            font-size: 11px;
            margin-top: 6px;
        }

        .remove-photo-btn {
            border-radius: 9px;
            font-size: 13px;
            font-weight: 500;
            padding: 9px 14px;
        }

        .action-area {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #edf0f4;
        }

        .save-button,
        .cancel-button {
            min-height: 45px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
        }

        .save-button {
            box-shadow: none;
        }

        .save-button:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .cancel-button {
            background: #ffffff;
            color: #495057;
            border: 1px solid #dfe4ea;
        }

        .cancel-button:hover {
            background: #f8f9fb;
            color: #212529;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
            border-width: 1px;
        }

        @media (max-width: 768px) {

            .edit-page {
                margin: 25px 15px;
            }

            .page-title {
                font-size: 26px;
            }

            .edit-card {
                padding: 24px 20px;
                border-radius: 15px;
            }

            .profile-photo,
            .photo-placeholder {
                width: 130px;
                height: 130px;
            }

            .action-area .btn {
                width: 100%;
            }
        }

    </style>

</head>


<body>

<div class="container edit-page">

    <!-- Back to profile -->

    <a href="index.php" class="top-link">
        <i class="bi bi-arrow-left"></i>
        Back to Profile
    </a>


    <!-- Page Heading -->

    <div class="page-heading">

        <h1 class="page-title">
            Edit Profile
        </h1>

        <p class="page-subtitle">
            Update your personal information and profile photo
        </p>

    </div>


    <!-- Main Card -->

    <div class="edit-card">


        <!-- Flash Message -->

        <?php if ($message != ""): ?>

            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> mb-4">

                <i class="bi bi-info-circle me-1"></i>

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- Profile Photo -->

        <div class="photo-section">

            <div class="photo-wrapper">

                <?php if (!empty($current_photo)): ?>

                    <img
                        id="photoPreview"
                        src="<?php echo htmlspecialchars($current_photo); ?>"
                        alt="Profile Photo"
                        class="profile-photo"
                    >

                <?php else: ?>

                    <div
                        id="photoPlaceholder"
                        class="photo-placeholder"
                    >
                        <?php echo strtoupper(substr($user["name"], 0, 1)); ?>
                    </div>

                    <img
                        id="photoPreview"
                        src=""
                        alt="Photo Preview"
                        class="profile-photo d-none"
                    >

                <?php endif; ?>

            </div>

            <div class="photo-title">
                Profile Photo
            </div>

            <p class="photo-help">
                JPG, PNG or WEBP · Maximum 5 MB
            </p>

        </div>


        <!-- Form -->

        <form
            id="profileForm"
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="form-section-title">
                <i class="bi bi-person"></i>
                Personal Information
            </div>


            <!-- NAME -->

            <div class="mb-4">

                <label class="form-label" for="name">
                    Name
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        value="<?php echo htmlspecialchars($user["name"]); ?>"
                        autocomplete="name"
                    >

                </div>

                <div id="nameMessage" class="validation-message"></div>

            </div>


            <!-- PHONE -->

            <div class="mb-4">

                <label class="form-label" for="phone">
                    Phone Number
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-telephone"></i>
                    </span>

                    <input
                        type="text"
                        class="form-control"
                        id="phone"
                        name="phone"
                        maxlength="10"
                        value="<?php echo htmlspecialchars($user["phone"]); ?>"
                        inputmode="numeric"
                        autocomplete="tel"
                    >

                </div>

                <div id="phoneMessage" class="validation-message"></div>

            </div>


            <!-- ADDRESS -->

            <div class="mb-4">

                <label class="form-label" for="address">
                    Address
                </label>

                <div class="input-group">

                    <span class="input-group-text align-items-start pt-3">
                        <i class="bi bi-geo-alt"></i>
                    </span>

                    <textarea
                        class="form-control"
                        id="address"
                        name="address"
                        rows="3"
                        autocomplete="street-address"
                    ><?php echo htmlspecialchars($user["address"]); ?></textarea>

                </div>

                <div id="addressMessage" class="validation-message"></div>

            </div>


            <!-- PROFILE PHOTO UPLOAD -->

            <div class="mb-3">

                <label class="form-label" for="profile_photo">
                    Change Profile Photo
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-image"></i>
                    </span>

                    <input
                        type="file"
                        class="form-control"
                        id="profile_photo"
                        name="profile_photo"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                </div>

                <div class="upload-help">
                    Select a new image only if you want to replace the current photo.
                </div>

                <div id="photoMessage" class="photo-message mt-2"></div>

            </div>


            <!-- REMOVE PHOTO -->

            <?php if (!empty($current_photo)): ?>

                <div class="mt-3">

                    <button
                        type="submit"
                        name="remove_photo"
                        value="1"
                        class="btn btn-outline-danger remove-photo-btn w-100"
                        onclick="return confirm('Are you sure you want to remove your profile photo?');"
                    >
                        <i class="bi bi-trash3 me-1"></i>
                        Remove Profile Photo
                    </button>

                </div>

            <?php endif; ?>


            <!-- ACTION BUTTONS -->

            <div class="action-area">

                <button
                    type="submit"
                    id="saveButton"
                    class="btn btn-primary save-button w-100"
                >
                    <i class="bi bi-check2-circle me-1"></i>
                    Save Changes
                </button>


                <a
                    href="index.php"
                    class="btn cancel-button w-100 mt-2"
                >
                    Cancel
                </a>

            </div>


        </form>

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