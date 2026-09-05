<?php

require "database/connection.php";

$user_id = 1;


// =====================================================
// GET USER INFORMATION
// =====================================================

$sql = "SELECT name, email, phone, address, role, profile_photo
        FROM users
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();


// =====================================================
// GET RENTAL HISTORY
// =====================================================

$sql = "SELECT item_name, rental_date, status
        FROM bookings
        WHERE user_id = ?
        ORDER BY rental_date DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$bookings_result = $stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);

$stmt->close();


// =====================================================
// GET LISTED ITEMS
// =====================================================

$sql = "SELECT item_name, price_per_day
        FROM items
        WHERE owner_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$items_result = $stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

$stmt->close();


// =====================================================
// STATISTICS
// =====================================================

$total_rentals = count($bookings);

$active_rentals = 0;

foreach ($bookings as $booking) {

    if (strtolower(trim($booking["status"])) == "active") {
        $active_rentals++;
    }

}

$total_listed_items = count($items);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile | Rental Network</title>


    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">


    <!-- Bootstrap Icons -->

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: #f6f8fc;

            color: #212529;

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;

        }


        /* =====================================================
           MAIN CONTAINER
           ===================================================== */

        .profile-container {

            max-width: 1080px;

            margin: 45px auto;

        }


        /* =====================================================
           PAGE HEADING
           ===================================================== */

        .page-title {

            font-size: 30px;

            font-weight: 700;

            letter-spacing: -0.5px;

            margin-bottom: 4px;

        }


        .page-subtitle {

            color: #6c757d;

            font-size: 15px;

            margin-bottom: 25px;

        }


        /* =====================================================
           PROFILE HEADER
           ===================================================== */

        .profile-header {

            background: #ffffff;

            border: 1px solid #e9edf3;

            border-radius: 18px;

            padding: 30px;

            box-shadow: 0 8px 25px rgba(33, 37, 41, 0.05);

        }


        .profile-photo {

            width: 125px;

            height: 125px;

            object-fit: cover;

            border-radius: 50%;

            border: 4px solid #ffffff;

            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.14);

        }


        .profile-placeholder {

            width: 125px;

            height: 125px;

            border-radius: 50%;

            background: #212529;

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 48px;

            font-weight: 600;

            margin: auto;

            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.14);

        }


        .profile-name {

            font-size: 27px;

            font-weight: 700;

            margin-bottom: 8px;

        }


        .role-badge {

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;

        }


        .profile-detail {

            color: #68717d;

            font-size: 14px;

            margin-bottom: 7px;

        }


        .profile-detail i {

            width: 22px;

            color: #495057;

        }


        /* =====================================================
           EDIT BUTTON
           ===================================================== */

        .edit-button {

            border-radius: 9px;

            padding: 9px 17px;

            font-weight: 500;

            box-shadow: none;

        }


        /* =====================================================
           STATISTICS
           ===================================================== */

        .stats-row {

            margin-top: 20px;

        }


        .stat-card {

            background: #ffffff;

            border: 1px solid #e9edf3;

            border-radius: 14px;

            padding: 21px;

            height: 100%;

            text-align: center;

            transition: 0.2s ease;

        }


        .stat-card:hover {

            transform: translateY(-2px);

            box-shadow: 0 7px 20px rgba(33, 37, 41, 0.07);

        }


        .stat-icon {

            width: 42px;

            height: 42px;

            margin: 0 auto 10px;

            border-radius: 11px;

            background: #f1f4f8;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 19px;

        }


        .stat-number {

            font-size: 28px;

            font-weight: 700;

            line-height: 1.2;

        }


        .stat-label {

            color: #747c86;

            font-size: 13px;

            margin-top: 4px;

        }


        /* =====================================================
           SECTION CARD
           ===================================================== */

        .section-card {

            background: #ffffff;

            border: 1px solid #e9edf3;

            border-radius: 16px;

            padding: 25px;

            box-shadow: 0 5px 20px rgba(33, 37, 41, 0.04);

        }


        .section-title {

            font-size: 19px;

            font-weight: 650;

            margin-bottom: 18px;

        }


        .section-title i {

            margin-right: 7px;

            color: #495057;

        }


        /* =====================================================
           RENTAL HISTORY
           ===================================================== */

        .rental-item {

            border: 1px solid #edf0f4;

            border-radius: 11px;

            padding: 16px 18px;

            margin-bottom: 10px;

            transition: 0.2s ease;

        }


        .rental-item:hover {

            border-color: #dce2e9;

            transform: translateY(-1px);

            box-shadow: 0 5px 15px rgba(33, 37, 41, 0.06);

        }


        .rental-item:last-child {

            margin-bottom: 0;

        }


        .rental-name {

            font-size: 15px;

            font-weight: 600;

        }


        .rental-name i {

            color: #6c757d;

            margin-right: 5px;

        }


        .rental-date {

            color: #737b85;

            font-size: 13px;

        }


        .rental-date i {

            margin-right: 5px;

        }


        /* =====================================================
           STATUS BADGES
           ===================================================== */

        .status-badge {

            display: inline-block;

            padding: 5px 11px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

        }


        .status-active {

            background: #e7f0ff;

            color: #2463c5;

        }


        .status-completed {

            background: #e6f7ed;

            color: #23834a;

        }


        .status-other {

            background: #f0f2f4;

            color: #626b75;

        }


        /* =====================================================
           LISTED ITEMS
           ===================================================== */

        .listed-item {

            border: 1px solid #edf0f4;

            border-radius: 11px;

            padding: 15px 18px;

            margin-bottom: 10px;

            transition: 0.2s ease;

        }


        .listed-item:hover {

            border-color: #dce2e9;

            transform: translateY(-1px);

            box-shadow: 0 5px 15px rgba(33, 37, 41, 0.06);

        }


        .listed-item:last-child {

            margin-bottom: 0;

        }


        .item-icon {

            width: 43px;

            height: 43px;

            border-radius: 10px;

            background: #f1f4f8;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #495057;

            font-size: 18px;

        }


        .item-name {

            font-size: 15px;

            font-weight: 600;

        }


        .price-label {

            color: #89919a;

            font-size: 11px;

            margin-top: 2px;

        }


        .item-price {

            font-size: 16px;

            font-weight: 700;

        }


        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty-state {

            text-align: center;

            padding: 30px;

            color: #737b85;

        }


        .empty-state i {

            display: block;

            font-size: 35px;

            margin-bottom: 8px;

        }


        /* =====================================================
           MOBILE
           ===================================================== */

        @media (max-width: 768px) {

            .profile-container {

                margin: 25px 15px;

            }


            .page-title {

                font-size: 26px;

            }


            .profile-header {

                padding: 25px 20px;

                text-align: center;

            }


            .profile-photo,

            .profile-placeholder {

                margin-bottom: 18px;

            }


            .profile-name {

                font-size: 24px;

            }


            .edit-button {

                width: 100%;

                margin-top: 15px;

            }


            .section-card {

                padding: 20px 16px;

            }


            .rental-item {

                padding: 15px;

            }


            .rental-item .text-md-end {

                text-align: left !important;

            }

        }

    </style>

</head>


<body>


<div class="container profile-container">


    <!-- =====================================================
         PAGE HEADING
         ===================================================== -->

    <h1 class="page-title">

        My Profile

    </h1>


    <p class="page-subtitle">

        Manage your profile and rental activity

    </p>



    <!-- =====================================================
         PROFILE HEADER
         ===================================================== -->

    <div class="profile-header">


        <div class="row align-items-center">


            <!-- Profile Photo -->

            <div class="col-md-3 text-center">

                <?php if (!empty($user["profile_photo"])): ?>

                    <img src="<?php echo htmlspecialchars($user["profile_photo"]); ?>"
                         alt="Profile Photo"
                         class="profile-photo">

                <?php else: ?>

                    <div class="profile-placeholder">

                        <?php echo strtoupper(
                            substr($user["name"], 0, 1)
                        ); ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- User Information -->

            <div class="col-md-6">

                <div class="profile-name">

                    <?php echo htmlspecialchars($user["name"]); ?>

                </div>


                <div class="mb-3">

                    <span class="badge bg-primary role-badge">

                        <?php echo htmlspecialchars($user["role"]); ?>

                    </span>

                </div>


                <div class="profile-detail">

                    <i class="bi bi-envelope"></i>

                    <?php echo htmlspecialchars($user["email"]); ?>

                </div>


                <div class="profile-detail">

                    <i class="bi bi-telephone"></i>

                    <?php echo htmlspecialchars($user["phone"]); ?>

                </div>


                <div class="profile-detail">

                    <i class="bi bi-geo-alt"></i>

                    <?php echo htmlspecialchars($user["address"]); ?>

                </div>

            </div>


            <!-- Edit -->

            <div class="col-md-3 text-md-end text-center">

                <a href="edit_profile.php"
                   class="btn btn-primary edit-button">

                    <i class="bi bi-pencil"></i>

                    Edit Profile

                </a>

            </div>

        </div>

    </div>



    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="row g-3 stats-row mb-4">


        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-box-seam"></i>

                </div>

                <div class="stat-number">

                    <?php echo $total_rentals; ?>

                </div>

                <div class="stat-label">

                    Total Rentals

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-arrow-repeat"></i>

                </div>

                <div class="stat-number">

                    <?php echo $active_rentals; ?>

                </div>

                <div class="stat-label">

                    Active Rentals

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-tags"></i>

                </div>

                <div class="stat-number">

                    <?php echo $total_listed_items; ?>

                </div>

                <div class="stat-label">

                    Listed Items

                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         RENTAL HISTORY
         ===================================================== -->

    <div class="section-card mb-4">

        <div class="section-title">

            <i class="bi bi-clock-history"></i>

            Rental History

        </div>


        <?php if (count($bookings) > 0): ?>


            <?php foreach ($bookings as $booking): ?>


                <?php

                $status = strtolower(trim($booking["status"]));

                if ($status == "active") {

                    $status_class = "status-active";

                } elseif ($status == "completed") {

                    $status_class = "status-completed";

                } else {

                    $status_class = "status-other";

                }

                ?>


                <div class="rental-item">

                    <div class="row align-items-center">


                        <div class="col-md-5">

                            <div class="rental-name">

                                <i class="bi bi-box"></i>

                                <?php echo htmlspecialchars(
                                    $booking["item_name"]
                                ); ?>

                            </div>

                        </div>


                        <div class="col-md-4 mt-2 mt-md-0">

                            <div class="rental-date">

                                <i class="bi bi-calendar3"></i>

                                <?php

                                echo date(
                                    "M d, Y",
                                    strtotime($booking["rental_date"])
                                );

                                ?>

                            </div>

                        </div>


                        <div class="col-md-3 mt-2 mt-md-0 text-md-end">

                            <span class="status-badge <?php echo $status_class; ?>">

                                <?php echo htmlspecialchars(
                                    $booking["status"]
                                ); ?>

                            </span>

                        </div>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-state">

                <i class="bi bi-calendar-x"></i>

                <p class="mb-0">

                    You don't have any rental history yet.

                </p>

            </div>


        <?php endif; ?>

    </div>



    <!-- =====================================================
         LISTED ITEMS
         ===================================================== -->

    <div class="section-card mb-5">

        <div class="section-title">

            <i class="bi bi-tags"></i>

            My Listed Items

        </div>


        <?php if (count($items) > 0): ?>


            <?php foreach ($items as $item): ?>


                <div class="listed-item">

                    <div class="row align-items-center">


                        <div class="col-auto">

                            <div class="item-icon">

                                <i class="bi bi-box"></i>

                            </div>

                        </div>


                        <div class="col">

                            <div class="item-name">

                                <?php echo htmlspecialchars(
                                    $item["item_name"]
                                ); ?>

                            </div>

                            <div class="price-label">

                                Rental price

                            </div>

                        </div>


                        <div class="col-auto text-end">

                            <div class="item-price">

                                ₹<?php echo htmlspecialchars(
                                    $item["price_per_day"]
                                ); ?>

                            </div>

                            <div class="price-label">

                                per day

                            </div>

                        </div>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-state">

                <i class="bi bi-box-seam"></i>

                <p class="mb-0">

                    You haven't listed any items yet.

                </p>

            </div>


        <?php endif; ?>

    </div>


</div>


</body>

</html>