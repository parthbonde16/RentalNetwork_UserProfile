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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 8% 8%, rgba(37, 99, 235, .055), transparent 28%),
                radial-gradient(circle at 92% 88%, rgba(99, 102, 241, .045), transparent 30%),
                #f5f7fb;
            color: #172033;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .edit-page {
            max-width: 1000px;
            margin: 0 auto;
            padding: 44px 22px 70px;
            position: relative;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #667085;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            margin-bottom: 18px;
            transition: color .18s ease, transform .18s ease;
        }

        .back-link:hover {
            color: #2563eb;
            transform: translateX(-2px);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .eyebrow i { color: #2563eb; }

        .page-title {
            margin: 0;
            font-size: clamp(30px, 4vw, 38px);
            line-height: 1.08;
            font-weight: 780;
            letter-spacing: -1px;
            background: linear-gradient(100deg, #172033 0%, #263d69 58%, #2563eb 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .page-subtitle {
            margin: 8px 0 25px;
            color: #748094;
            font-size: 13.5px;
        }

        .edit-card {
            position: relative;
            background: rgba(255, 255, 255, .94);
            border: 1px solid rgba(224, 230, 238, .9);
            border-radius: 24px;
            box-shadow:
                0 24px 55px rgba(15, 23, 42, .075),
                0 4px 14px rgba(15, 23, 42, .035);
            overflow: hidden;
            backdrop-filter: blur(8px);
        }

        .edit-card::before {
            content: "";
            display: block;
            height: 3px;
            background: linear-gradient(90deg, #2563eb, #60a5fa, #2563eb);
            opacity: .9;
        }

        .alert {
            margin: 22px 24px 0;
            border-radius: 11px;
            font-size: 12.5px;
            border-width: 1px;
            opacity: 1;
            transform: translateY(0);
            transition: opacity .55s ease, transform .55s ease, max-height .55s ease,
                        margin .55s ease, padding .55s ease;
            max-height: 100px;
            overflow: hidden;
        }

        .alert.alert-fade-out {
            opacity: 0;
            transform: translateY(-5px);
            max-height: 0;
            margin-top: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-width: 0;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 290px 1fr;
            min-height: 560px;
        }

        /* Photo panel */
        .photo-panel {
            background:
                radial-gradient(circle at 50% 22%, rgba(37, 99, 235, .055), transparent 30%),
                linear-gradient(180deg, #fbfcfe 0%, #f8fafc 100%);
            border-right: 1px solid #edf0f4;
            padding: 34px 26px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .photo-heading {
            width: 100%;
            text-align: left;
            margin-bottom: 24px;
        }

        .photo-heading h2,
        .form-heading h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 720;
            letter-spacing: -.15px;
        }

        .photo-heading p {
            margin: 5px 0 0;
            color: #8791a0;
            font-size: 11.5px;
            line-height: 1.5;
        }

        .photo-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 4px 0 13px;
        }

        .profile-photo,
        .photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
        }

        .profile-photo {
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow:
                0 0 0 1px rgba(37, 99, 235, .12),
                0 12px 30px rgba(15, 23, 42, .16);
        }

        .photo-placeholder {
            background: linear-gradient(145deg, #172033 0%, #344b77 65%, #2563eb 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 5px solid #fff;
            box-shadow: 0 10px 27px rgba(15, 23, 42, .15);
            font-size: 46px;
            font-weight: 700;
        }

        /* Camera is the actual upload trigger */
        .camera-button {
            position: absolute;
            right: 2px;
            bottom: 6px;
            width: 39px;
            height: 39px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid #e1e7ef;
            background: #fff;
            color: #2563eb;
            box-shadow: 0 6px 15px rgba(15, 23, 42, .14);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .camera-button::after {
            content: "";
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            border: 1px solid rgba(37, 99, 235, .10);
            pointer-events: none;
        }

        .camera-button:hover {
            transform: scale(1.06);
            background: #eff6ff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .17);
        }

        .camera-button:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .18);
            outline-offset: 2px;
        }

        .photo-action-text {
            color: #475467;
            font-size: 12px;
            font-weight: 650;
            margin-bottom: 4px;
        }

        .photo-choice {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .42);
            backdrop-filter: blur(5px);
        }

        .photo-choice.show {
            display: flex;
            animation: choiceFade .16s ease-out;
        }

        .photo-choice-card {
            width: min(360px, 100%);
            background: #fff;
            border: 1px solid #e5e9ef;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 22px 55px rgba(15, 23, 42, .20);
            animation: choiceUp .18s ease-out;
        }

        .photo-choice-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 17px;
        }

        .photo-choice-title {
            margin: 0;
            font-size: 16px;
            font-weight: 750;
            color: #172033;
        }

        .photo-choice-subtitle {
            margin: 4px 0 0;
            color: #8791a0;
            font-size: 11px;
        }

        .choice-close {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 50%;
            background: #f4f6f8;
            color: #667085;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .choice-option {
            width: 100%;
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            margin-bottom: 8px;
            border: 1px solid #e6eaf0;
            border-radius: 11px;
            background: #fff;
            color: #263244;
            text-align: left;
            cursor: pointer;
            transition: background .16s ease, border-color .16s ease, transform .16s ease;
        }

        .choice-option:hover {
            background: #f8fbff;
            border-color: #cddcf5;
            transform: translateX(2px);
        }

        .choice-option:last-child {
            margin-bottom: 0;
        }

        .choice-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 9px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .choice-text strong {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
        }

        .choice-text span {
            display: block;
            margin-top: 2px;
            color: #8a94a3;
            font-size: 10.5px;
        }

        .choice-cancel {
            width: 100%;
            min-height: 40px;
            margin-top: 13px;
            border: 0;
            border-radius: 9px;
            background: #f4f6f8;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        @keyframes choiceFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes choiceUp {
            from { opacity: 0; transform: translateY(7px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .photo-help {
            color: #8993a1;
            font-size: 10.5px;
            line-height: 1.5;
            margin: 0;
        }

        .photo-name {
            max-width: 220px;
            margin-top: 9px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #667085;
            font-size: 10.5px;
        }

        .remove-photo-btn {
            width: 100%;
            margin-top: 18px;
            border-radius: 9px;
            min-height: 40px;
            font-size: 11.5px;
            font-weight: 650;
        }

        /* Form panel */
        .form-panel {
            padding: 36px 40px;
            background: rgba(255, 255, 255, .78);
        }

        .form-heading {
            margin-bottom: 27px;
            padding-bottom: 16px;
            border-bottom: 1px solid #edf0f4;
        }

        .form-heading p {
            margin: 5px 0 0;
            color: #8791a0;
            font-size: 11.5px;
        }

        .field {
            margin-bottom: 21px;
        }

        .form-label {
            display: block;
            color: #344054;
            font-size: 12.5px;
            font-weight: 680;
            margin-bottom: 7px;
        }

        .input-shell {
            position: relative;
        }

        .input-group-text {
            min-width: 43px;
            justify-content: center;
            background: #f8fafc;
            color: #667085;
            border-color: #dfe5ec;
            border-radius: 9px 0 0 9px;
        }

        .form-control {
            min-height: 46px;
            border-color: #dfe5ec;
            border-radius: 9px;
            color: #273244;
            font-size: 13.5px;
            padding: 9px 43px 9px 12px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .09);
        }

        .input-group .form-control {
            border-radius: 0 9px 9px 0;
        }

        textarea.form-control {
            min-height: 112px;
            resize: vertical;
            line-height: 1.5;
        }

        /* Validation indicator */
        .validation-icon {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            width: 25px;
            height: 25px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: transparent;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 20;
            pointer-events: auto;
        }

        textarea + .validation-icon {
            top: 18px;
            transform: none;
        }

        .validation-icon.valid {
            display: flex;
            color: #198754;
        }

        .validation-icon.invalid {
            display: flex;
            color: #dc3545;
        }

        .validation-icon:hover {
            background: rgba(220, 53, 69, .08);
        }

        .validation-icon.valid:hover {
            background: rgba(25, 135, 84, .08);
        }

        .validation-icon i {
            font-size: 17px;
            line-height: 1;
        }

        .validation-popover {
            position: absolute;
            right: 8px;
            top: calc(100% + 7px);
            z-index: 20;
            max-width: 255px;
            padding: 9px 11px;
            border-radius: 9px;
            background: #172033;
            color: #fff;
            font-size: 11px;
            line-height: 1.45;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .18);
            display: none;
        }

        .validation-popover.show {
            display: block;
            animation: popIn .16s ease-out;
        }

        .validation-popover::before {
            content: "";
            position: absolute;
            right: 13px;
            top: -5px;
            width: 10px;
            height: 10px;
            background: #172033;
            transform: rotate(45deg);
        }

        @keyframes popIn {
            from { opacity: 0; transform: translateY(-3px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .field.invalid .form-control {
            border-color: #e57373;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, .06);
        }

        .field.valid .form-control {
            border-color: #b7dfc9;
        }

        .action-area {
            display: flex;
            gap: 10px;
            padding-top: 22px;
            margin-top: 5px;
            border-top: 1px solid #edf0f4;
        }

        .save-button,
        .cancel-button {
            min-height: 44px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
        }

        .save-button {
            flex: 1.2;
            border: 0;
            background: linear-gradient(135deg, #2563eb 0%, #1677ff 100%);
            box-shadow: 0 8px 18px rgba(37, 99, 235, .20);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .save-button:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 11px 22px rgba(37, 99, 235, .25);
        }

        .save-button:disabled {
            cursor: not-allowed;
            opacity: .55;
            box-shadow: none;
        }

        .cancel-button {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #475467;
            border: 1px solid #dfe5ec;
            text-decoration: none;
        }

        .cancel-button:hover {
            background: #f8fafc;
            color: #172033;
            border-color: #cfd7e3;
        }

        @media (max-width: 767.98px) {
            .edit-page {
                padding: 28px 14px 45px;
            }

            .edit-grid {
                grid-template-columns: 1fr;
            }

            .photo-panel {
                border-right: 0;
                border-bottom: 1px solid #edf0f4;
                padding: 25px 20px 23px;
            }

            .photo-heading {
                text-align: center;
                margin-bottom: 17px;
            }

            .form-panel {
                padding: 27px 20px;
            }

            .edit-card::before {
                height: 2px;
            }

            .action-area {
                flex-direction: column;
            }

            .save-button,
            .cancel-button {
                width: 100%;
                flex: none;
            }
        }
    
    /* Real camera capture modal */
    .camera-modal {
        position: fixed;
        inset: 0;
        z-index: 3000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(10, 18, 35, 0.72);
        backdrop-filter: blur(10px);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .22s ease, visibility .22s ease;
    }

    .camera-modal.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .camera-card {
        width: min(620px, 100%);
        max-height: 94vh;
        overflow: auto;
        background: #ffffff;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 24px;
        padding: 22px;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .30);
        transform: translateY(12px) scale(.98);
        transition: transform .22s ease;
    }

    .camera-modal.show .camera-card {
        transform: translateY(0) scale(1);
    }

    .camera-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }

    .camera-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 750;
        color: #172033;
    }

    .camera-subtitle {
        margin: 5px 0 0;
        color: #718096;
        font-size: .9rem;
    }

    .camera-close {
        width: 38px;
        height: 38px;
        border: 0;
        border-radius: 50%;
        background: #f3f6fa;
        color: #596579;
        display: grid;
        place-items: center;
        cursor: pointer;
    }

    .camera-preview-wrap {
        position: relative;
        width: 100%;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        border-radius: 18px;
        background: #101827;
    }

    .camera-video {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        transform: scaleX(-1);
    }

    .camera-frame {
        position: absolute;
        inset: 10%;
        border: 2px solid rgba(255, 255, 255, .78);
        border-radius: 22px;
        pointer-events: none;
    }

    .camera-status {
        position: absolute;
        left: 14px;
        bottom: 14px;
        padding: 7px 11px;
        border-radius: 999px;
        background: rgba(12, 20, 34, .72);
        color: #fff;
        font-size: .78rem;
        backdrop-filter: blur(6px);
    }

    .camera-error {
        display: none;
        margin-top: 12px;
        padding: 11px 13px;
        border-radius: 12px;
        background: #fff1f2;
        border: 1px solid #fecdd3;
        color: #be123c;
        font-size: .86rem;
        line-height: 1.45;
    }

    .camera-error.show {
        display: block;
    }

    .camera-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 18px;
        margin-top: 18px;
    }

    .camera-secondary {
        border: 1px solid #d8e0ea;
        background: #fff;
        color: #536174;
        border-radius: 12px;
        padding: 11px 18px;
        font-weight: 650;
        cursor: pointer;
    }

    .capture-button {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border: 0;
        border-radius: 999px;
        padding: 7px 17px 7px 7px;
        background: #1f6feb;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 10px 24px rgba(31, 111, 235, .24);
    }

    .capture-button:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .capture-inner {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff;
        color: #1f6feb;
    }

    .camera-fallback {
        display: block;
        margin: 14px auto 0;
        border: 0;
        background: transparent;
        color: #1f6feb;
        font-size: .82rem;
        font-weight: 650;
        cursor: pointer;
    }

    @media (max-width: 576px) {
        .camera-modal { padding: 10px; }
        .camera-card { padding: 16px; border-radius: 20px; }
        .camera-title { font-size: 1.15rem; }
        .camera-subtitle { font-size: .8rem; }
    }

</style>
</head>

<body>
<div class="edit-page">

    <a href="index.php" class="back-link">
        <i class="bi bi-arrow-left"></i>
        Back to Profile
    </a>

    <div class="eyebrow">
        <i class="bi bi-person-gear"></i>
        Account Settings
    </div>

    <h1 class="page-title">Edit Profile</h1>
    <p class="page-subtitle">Update your personal information and profile photo.</p>

    <div class="edit-card">

        <?php if ($message != ""): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <i class="bi bi-info-circle me-1"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="edit-grid">

            <!-- Profile photo -->
            <aside class="photo-panel">
                <div class="photo-heading">
                    <h2>Profile Photo</h2>
                    <p>Choose a clear photo that represents your profile.</p>
                </div>

                <div class="photo-wrapper">

                    <?php if (!empty($current_photo)): ?>
                        <img
                            id="photoPreview"
                            src="<?php echo htmlspecialchars($current_photo); ?>"
                            alt="Profile Photo"
                            class="profile-photo"
                        >
                    <?php else: ?>
                        <div id="photoPlaceholder" class="photo-placeholder">
                            <?php echo strtoupper(substr($user["name"], 0, 1)); ?>
                        </div>

                        <img
                            id="photoPreview"
                            src=""
                            alt="Photo Preview"
                            class="profile-photo d-none"
                        >
                    <?php endif; ?>

                    <!-- Clicking the camera opens the normal file picker -->
                    <button
                        type="button"
                        class="camera-button"
                        id="cameraButton"
                        title="Change profile photo"
                        aria-label="Change profile photo"
                    >
                        <i class="bi bi-camera-fill"></i>
                    </button>
                </div>

                <div class="photo-action-text">Click the camera to change photo</div>
                <p class="photo-help">JPG, JPEG, PNG or WEBP<br>Maximum file size: 5 MB</p>

                <!-- Hidden: camera button is the user-facing upload control -->
                <input
                    type="file"
                    id="profile_photo"
                    name="profile_photo"
                    accept=".jpg,.jpeg,.png,.webp"
                    form="profileForm"
                    class="d-none"
                >

                <input
                    type="file"
                    id="galleryInput"
                    accept="image/*"
                    class="d-none"
                >

                <div id="photoName" class="photo-name"></div>

                <?php if (!empty($current_photo)): ?>
                    <button
                        type="submit"
                        name="remove_photo"
                        value="1"
                        class="btn btn-outline-danger remove-photo-btn"
                        form="profileForm"
                        onclick="return confirm('Are you sure you want to remove your profile photo?');"
                    >
                        <i class="bi bi-trash3 me-1"></i>
                        Remove Current Photo
                    </button>
                <?php endif; ?>
            </aside>

            <!-- Personal information -->
            <main class="form-panel">
                <div class="form-heading">
                    <h2>Personal Information</h2>
                    <p>Keep your contact details accurate and up to date.</p>
                </div>

                <form id="profileForm" method="POST" enctype="multipart/form-data">

                    <div class="field" id="nameField">
                        <label class="form-label" for="name">Full Name</label>

                        <div class="input-shell">
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

                            <button type="button" class="validation-icon" id="nameIcon" aria-label="Name validation">
                                <i></i>
                            </button>

                            <div class="validation-popover" id="namePopover"></div>
                        </div>
                    </div>

                    <div class="field" id="phoneField">
                        <label class="form-label" for="phone">Phone Number</label>

                        <div class="input-shell">
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

                            <button type="button" class="validation-icon" id="phoneIcon" aria-label="Phone validation">
                                <i></i>
                            </button>

                            <div class="validation-popover" id="phonePopover"></div>
                        </div>
                    </div>

                    <div class="field mb-0" id="addressField">
                        <label class="form-label" for="address">Address</label>

                        <div class="input-shell">
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

                            <button type="button" class="validation-icon" id="addressIcon" aria-label="Address validation">
                                <i></i>
                            </button>

                            <div class="validation-popover" id="addressPopover"></div>
                        </div>
                    </div>

                    <div class="action-area">
                        <button type="submit" id="saveButton" class="btn btn-primary save-button">
                            <i class="bi bi-check2-circle me-1"></i>
                            Save Changes
                        </button>

                        <a href="index.php" class="btn cancel-button">Cancel</a>
                    </div>

                </form>
            </main>

        </div>
    </div>
</div>

<!-- Photo source chooser -->
<div class="photo-choice" id="photoChoice" aria-hidden="true">
    <div class="photo-choice-card" role="dialog" aria-modal="true" aria-labelledby="photoChoiceTitle">

        <div class="photo-choice-head">
            <div>
                <h2 class="photo-choice-title" id="photoChoiceTitle">Change profile photo</h2>
                <p class="photo-choice-subtitle">Choose how you'd like to add your photo.</p>
            </div>

            <button type="button" class="choice-close" id="closePhotoChoice" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <button type="button" class="choice-option" id="takePhotoOption">
            <span class="choice-icon"><i class="bi bi-camera-fill"></i></span>
            <span class="choice-text">
                <strong>Take a photo</strong>
                <span>Use your device camera</span>
            </span>
        </button>

        <button type="button" class="choice-option" id="galleryOption">
            <span class="choice-icon"><i class="bi bi-image-fill"></i></span>
            <span class="choice-text">
                <strong>Choose from gallery</strong>
                <span>Select a photo from your device</span>
            </span>
        </button>

        <button type="button" class="choice-option" id="fileOption">
            <span class="choice-icon"><i class="bi bi-folder2-open"></i></span>
            <span class="choice-text">
                <strong>Choose a file</strong>
                <span>Select an image file manually</span>
            </span>
        </button>

        <button type="button" class="choice-cancel" id="cancelPhotoChoice">Cancel</button>
    </div>
</div>

<!-- Real camera capture modal -->
<div class="camera-modal" id="cameraModal" aria-hidden="true">
    <div class="camera-card" role="dialog" aria-modal="true" aria-labelledby="cameraTitle">
        <div class="camera-head">
            <div>
                <h2 class="camera-title" id="cameraTitle">Take a photo</h2>
                <p class="camera-subtitle">Allow camera access, then position yourself in the frame.</p>
            </div>
            <button type="button" class="camera-close" id="closeCamera" aria-label="Close camera">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="camera-preview-wrap">
            <video id="cameraVideo" class="camera-video" autoplay playsinline muted></video>
            <div class="camera-frame"></div>
            <div class="camera-status" id="cameraStatus">
                <i class="bi bi-camera-video me-1"></i>
                Starting camera...
            </div>
        </div>

        <div class="camera-error" id="cameraError" role="alert"></div>

        <div class="camera-actions">
            <button type="button" class="camera-secondary" id="cancelCamera">Cancel</button>
            <button type="button" class="capture-button" id="capturePhoto">
                <span class="capture-inner"><i class="bi bi-camera-fill"></i></span>
                Capture
            </button>
        </div>

        <button type="button" class="camera-fallback" id="cameraFallback">
            <i class="bi bi-folder2-open me-1"></i>
            Use a file instead
        </button>
    </div>
</div>

<script>
    // Auto-hide PHP flash messages after a short reading time.
    const flashMessage = document.querySelector(".edit-card > .alert");

    if (flashMessage) {
        setTimeout(() => {
            flashMessage.classList.add("alert-fade-out");

            setTimeout(() => {
                flashMessage.remove();
            }, 600);
        }, 3000);
    }

    const profileForm = document.getElementById("profileForm");
    const saveButton = document.getElementById("saveButton");

    const nameInput = document.getElementById("name");
    const phoneInput = document.getElementById("phone");
    const addressInput = document.getElementById("address");

    const profilePhoto = document.getElementById("profile_photo");
    const cameraButton = document.getElementById("cameraButton");
    const photoPreview = document.getElementById("photoPreview");
    const photoPlaceholder = document.getElementById("photoPlaceholder");
    const photoName = document.getElementById("photoName");

    const fields = {
        name: {
            input: nameInput,
            field: document.getElementById("nameField"),
            icon: document.getElementById("nameIcon"),
            iconInner: document.getElementById("nameIcon").querySelector("i"),
            popover: document.getElementById("namePopover")
        },
        phone: {
            input: phoneInput,
            field: document.getElementById("phoneField"),
            icon: document.getElementById("phoneIcon"),
            iconInner: document.getElementById("phoneIcon").querySelector("i"),
            popover: document.getElementById("phonePopover")
        },
        address: {
            input: addressInput,
            field: document.getElementById("addressField"),
            icon: document.getElementById("addressIcon"),
            iconInner: document.getElementById("addressIcon").querySelector("i"),
            popover: document.getElementById("addressPopover")
        }
    };

    let validationState = {
        name: false,
        phone: false,
        address: false
    };

    let touchedState = {
        name: false,
        phone: false,
        address: false
    };

    let popoverTimer = null;

    function setValidation(type, valid, message) {
        const item = fields[type];

        validationState[type] = valid;

        item.field.classList.toggle("valid", valid);
        item.field.classList.toggle("invalid", !valid);

        item.icon.classList.remove("valid", "invalid");

        if (touchedState[type]) {
            item.icon.classList.add(valid ? "valid" : "invalid");
        }

        item.iconInner.className = valid
            ? "bi bi-check-circle-fill"
            : "bi bi-exclamation-circle-fill";

        item.popover.textContent = message;
        item.popover.classList.remove("show");
    }

    function validateName(showPopup = false) {
        const value = nameInput.value.trim();

        if (value === "") {
            setValidation("name", false, "Full name is required.");
        } else if (value.length < 2) {
            setValidation("name", false, "Name must contain at least 2 characters.");
        } else if (!/^[A-Za-z ]+$/.test(value)) {
            setValidation("name", false, "Name should contain only letters and spaces.");
        } else {
            setValidation("name", true, "Name is valid.");
        }

        if (showPopup && !validationState.name) showValidationMessage("name");
    }

    function validatePhone(showPopup = false) {
        const value = phoneInput.value.trim();

        if (value === "") {
            setValidation("phone", false, "Phone number is required.");
        } else if (!/^\d{10}$/.test(value)) {
            setValidation("phone", false, "Phone number must contain exactly 10 digits.");
        } else {
            setValidation("phone", true, "Phone number is valid.");
        }

        if (showPopup && !validationState.phone) showValidationMessage("phone");
    }

    function validateAddress(showPopup = false) {
        const value = addressInput.value.trim();

        if (value === "") {
            setValidation("address", false, "Address is required.");
        } else if (value.length < 5) {
            setValidation("address", false, "Address must contain at least 5 characters.");
        } else {
            setValidation("address", true, "Address is valid.");
        }

        if (showPopup && !validationState.address) showValidationMessage("address");
    }

    function validateAll() {
        validateName();
        validatePhone();
        validateAddress();

        const valid = validationState.name &&
                      validationState.phone &&
                      validationState.address;

        saveButton.disabled = !valid;
        return valid;
    }

    function showValidationMessage(type) {
        const item = fields[type];

        document.querySelectorAll(".validation-popover.show")
            .forEach(pop => pop.classList.remove("show"));

        clearTimeout(popoverTimer);

        if (!validationState[type]) {
            item.popover.classList.add("show");

            popoverTimer = setTimeout(() => {
                item.popover.classList.remove("show");
            }, 3000);
        }
    }

    Object.keys(fields).forEach(type => {
        fields[type].icon.addEventListener("click", function () {
            if (!validationState[type]) {
                showValidationMessage(type);
            }
        });
    });

    nameInput.addEventListener("input", function () {
        touchedState.name = true;
        validateName();
        validateAll();
    });

    phoneInput.addEventListener("input", function () {
        touchedState.phone = true;
        phoneInput.value = phoneInput.value.replace(/\D/g, "").slice(0, 10);
        validatePhone();
        validateAll();
    });

    addressInput.addEventListener("input", function () {
        touchedState.address = true;
        validateAddress();
        validateAll();
    });

    // Also handle autofill, paste, and mobile keyboard changes immediately.
    nameInput.addEventListener("change", function () {
        touchedState.name = true;
        validateName();
        validateAll();
    });

    phoneInput.addEventListener("change", function () {
        touchedState.phone = true;
        phoneInput.value = phoneInput.value.replace(/\D/g, "").slice(0, 10);
        validatePhone();
        validateAll();
    });

    addressInput.addEventListener("change", function () {
        touchedState.address = true;
        validateAddress();
        validateAll();
    });

    profileForm.addEventListener("submit", function (event) {
        if (!validateAll()) {
            event.preventDefault();

            if (!validationState.name) {
                showValidationMessage("name");
            } else if (!validationState.phone) {
                showValidationMessage("phone");
            } else if (!validationState.address) {
                showValidationMessage("address");
            }
        }
    });

    // Photo source chooser.
    const photoChoice = document.getElementById("photoChoice");
    const closePhotoChoice = document.getElementById("closePhotoChoice");
    const cancelPhotoChoice = document.getElementById("cancelPhotoChoice");
    const takePhotoOption = document.getElementById("takePhotoOption");
    const galleryOption = document.getElementById("galleryOption");
    const fileOption = document.getElementById("fileOption");
    const galleryInput = document.getElementById("galleryInput");

    const cameraModal = document.getElementById("cameraModal");
    const cameraVideo = document.getElementById("cameraVideo");
    const closeCamera = document.getElementById("closeCamera");
    const cancelCamera = document.getElementById("cancelCamera");
    const capturePhoto = document.getElementById("capturePhoto");
    const cameraError = document.getElementById("cameraError");
    const cameraStatus = document.getElementById("cameraStatus");
    const cameraFallback = document.getElementById("cameraFallback");

    let cameraStream = null;

    function openPhotoChoice() {
        photoChoice.classList.add("show");
        photoChoice.setAttribute("aria-hidden", "false");
    }

    function closePhotoChoiceMenu() {
        photoChoice.classList.remove("show");
        photoChoice.setAttribute("aria-hidden", "true");
    }

    cameraButton.addEventListener("click", openPhotoChoice);
    closePhotoChoice.addEventListener("click", closePhotoChoiceMenu);
    cancelPhotoChoice.addEventListener("click", closePhotoChoiceMenu);

    photoChoice.addEventListener("click", function (event) {
        if (event.target === photoChoice) {
            closePhotoChoiceMenu();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closePhotoChoiceMenu();
            closeCameraModal();
        }
    });

    takePhotoOption.addEventListener("click", function () {
        closePhotoChoiceMenu();
        openCamera();
    });

    galleryOption.addEventListener("click", function () {
        closePhotoChoiceMenu();
        galleryInput.click();
    });

    fileOption.addEventListener("click", function () {
        closePhotoChoiceMenu();
        profilePhoto.click();
    });

    function handlePhotoSelection(file) {
        if (!file) {
            return;
        }

        photoName.textContent = file.name;

        // Put the selected file into the real form input.
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        profilePhoto.files = dataTransfer.files;

        const reader = new FileReader();

        reader.onload = function (event) {
            if (photoPreview) {
                photoPreview.src = event.target.result;
                photoPreview.classList.remove("d-none");
            }

            if (photoPlaceholder) {
                photoPlaceholder.classList.add("d-none");
            }
        };

        reader.readAsDataURL(file);
    }

    profilePhoto.addEventListener("change", function () {
        handlePhotoSelection(this.files[0]);
    });


    async function openCamera() {
        cameraModal.classList.add("show");
        cameraModal.setAttribute("aria-hidden", "false");
        cameraError.classList.remove("show");
        cameraError.textContent = "";
        cameraStatus.innerHTML = '<i class="bi bi-camera-video me-1"></i> Starting camera...';
        capturePhoto.disabled = true;

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("Your browser does not support direct camera access.");
            }

            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "user",
                    width: { ideal: 1280 },
                    height: { ideal: 1280 }
                },
                audio: false
            });

            cameraVideo.srcObject = cameraStream;
            await cameraVideo.play();

            cameraStatus.innerHTML = '<i class="bi bi-camera-video-fill me-1"></i> Camera ready';
            capturePhoto.disabled = false;

        } catch (error) {
            console.error("Camera error:", error);

            let message = "Could not start the camera.";

            if (error.name === "NotAllowedError" || error.name === "PermissionDeniedError") {
                message = "Camera permission was denied. Allow camera access in your browser and try again.";
            } else if (error.name === "NotFoundError" || error.name === "DevicesNotFoundError") {
                message = "No camera was found on this device.";
            } else if (error.name === "NotReadableError" || error.name === "TrackStartError") {
                message = "The camera may already be in use by another application.";
            } else if (error.message) {
                message = error.message;
            }

            cameraError.textContent = message;
            cameraError.classList.add("show");
            cameraStatus.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Camera unavailable';
        }
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        cameraVideo.srcObject = null;
    }

    function closeCameraModal() {
        stopCamera();
        cameraModal.classList.remove("show");
        cameraModal.setAttribute("aria-hidden", "true");
    }

    function captureCurrentFrame() {
        if (!cameraStream || !cameraVideo.videoWidth || !cameraVideo.videoHeight) {
            cameraError.textContent = "The camera is not ready yet. Please wait a moment.";
            cameraError.classList.add("show");
            return;
        }

        const canvas = document.createElement("canvas");
        canvas.width = cameraVideo.videoWidth;
        canvas.height = cameraVideo.videoHeight;

        const ctx = canvas.getContext("2d");
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(cameraVideo, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {
            if (!blob) {
                cameraError.textContent = "Could not create the photo. Please try again.";
                cameraError.classList.add("show");
                return;
            }

            const file = new File(
                [blob],
                "profile_photo_" + Date.now() + ".jpg",
                { type: "image/jpeg" }
            );

            handlePhotoSelection(file);
            closeCameraModal();
        }, "image/jpeg", 0.92);
    }

    closeCamera.addEventListener("click", closeCameraModal);
    cancelCamera.addEventListener("click", closeCameraModal);
    capturePhoto.addEventListener("click", captureCurrentFrame);

    cameraFallback.addEventListener("click", function () {
        closeCameraModal();
        profilePhoto.click();
    });

    cameraModal.addEventListener("click", function (event) {
        if (event.target === cameraModal) {
            closeCameraModal();
        }
    });

    galleryInput.addEventListener("change", function () {
        handlePhotoSelection(this.files[0]);
    });

    // Initial validation state. Indicators stay hidden until the user edits a field.
    validateAll();
    Object.keys(fields).forEach(type => {
        fields[type].icon.classList.remove("valid", "invalid");
    });
</script>

</body>
</html>
