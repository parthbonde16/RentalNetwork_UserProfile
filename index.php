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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Rental Network</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f5f7fb;
            color: #172033;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 52px 24px 70px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .eyebrow i { color: #2563eb; }

        .page-title {
            margin: 0;
            font-size: clamp(30px, 4vw, 38px);
            line-height: 1.1;
            font-weight: 750;
            letter-spacing: -.9px;
        }

        .page-subtitle {
            margin: 9px 0 30px;
            color: #718096;
            font-size: 14px;
        }

        /* Main profile card */
        .hero {
            position: relative;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e6eaf0;
            border-radius: 24px;
            padding: 32px 34px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .055);
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            right: -85px;
            top: -105px;
            border-radius: 50%;
            background: rgba(37, 99, 235, .045);
            pointer-events: none;
        }

        .avatar-wrap {
            display: flex;
            justify-content: center;
        }

        .avatar {
            width: 122px;
            height: 122px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .14);
        }

        .avatar-placeholder {
            width: 122px;
            height: 122px;
            border-radius: 50%;
            background: linear-gradient(145deg, #1e293b, #334155);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 46px;
            font-weight: 700;
            border: 5px solid #fff;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .14);
        }

        .identity {
            padding-left: 8px;
        }

        .name {
            margin: 0 0 8px;
            font-size: 29px;
            line-height: 1.15;
            font-weight: 750;
            letter-spacing: -.45px;
        }

        .role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 11px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
        }

        .details {
            display: flex;
            flex-wrap: wrap;
            gap: 9px 24px;
            margin-top: 20px;
        }

        .detail {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #657184;
            font-size: 13.5px;
        }

        .detail i {
            color: #475569;
            font-size: 14px;
        }

        .edit-btn {
            position: relative;
            z-index: 2;
            border: 0;
            border-radius: 10px;
            padding: 10px 17px;
            font-size: 13.5px;
            font-weight: 700;
            box-shadow: 0 5px 13px rgba(13, 110, 253, .18);
        }

        /* Stats */
        .stats {
            margin-top: 20px;
        }

        .stat {
            height: 100%;
            background: #fff;
            border: 1px solid #e6eaf0;
            border-radius: 16px;
            padding: 20px 21px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 7px 23px rgba(15, 23, 42, .035);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .075);
        }

        .stat-icon {
            flex: 0 0 45px;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            font-size: 19px;
        }

        .stat-number {
            font-size: 26px;
            line-height: 1;
            font-weight: 750;
            letter-spacing: -.3px;
        }

        .stat-label {
            margin-top: 5px;
            color: #7b8798;
            font-size: 12px;
        }

        /* Content cards */
        .content-card {
            background: #fff;
            border: 1px solid #e6eaf0;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 7px 24px rgba(15, 23, 42, .035);
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            font-size: 18px;
            font-weight: 720;
            letter-spacing: -.2px;
        }

        .section-heading i {
            color: #2563eb;
            font-size: 17px;
        }

        .section-count {
            color: #8a94a3;
            font-size: 12px;
            font-weight: 600;
        }

        .view-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0;
            background: transparent;
            color: #2563eb;
            padding: 5px 0;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: gap .18s ease, color .18s ease;
        }

        .view-all-btn:hover {
            color: #1d4ed8;
            gap: 9px;
        }

        .view-all-btn:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .18);
            outline-offset: 3px;
            border-radius: 5px;
        }

        /* Clickable rows inside the full-list modal */
        .detail-list-row.clickable-modal-row {
            cursor: pointer;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }

        .detail-list-row.clickable-modal-row:hover {
            background: #f8fbff;
            border-color: #d7e3f3;
            transform: translateX(2px);
        }

        .detail-list-row.clickable-modal-row:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .18);
            outline-offset: 2px;
        }

        .detail-list-row.clickable-modal-row .modal-row-arrow {
            color: #a1acba;
            opacity: 0;
            transform: translateX(-3px);
            transition: opacity .18s ease, transform .18s ease, color .18s ease;
        }

        .detail-list-row.clickable-modal-row:hover .modal-row-arrow,
        .detail-list-row.clickable-modal-row:focus-visible .modal-row-arrow {
            opacity: 1;
            color: #2563eb;
            transform: translateX(0);
        }

        .list {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .list-row {
            min-height: 64px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 11px 13px;
            border: 1px solid #edf0f4;
            border-radius: 12px;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }

        .list-row:hover {
            background: #fafcff;
            border-color: #dfe5ec;
            transform: translateX(2px);
        }

        .row-icon {
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f6fa;
            color: #526174;
            font-size: 17px;
        }

        .row-main {
            min-width: 0;
            flex: 1;
        }

        .row-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 680;
            color: #263244;
        }

        .row-meta {
            margin-top: 3px;
            color: #8590a0;
            font-size: 11.5px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-active {
            background: #eff6ff;
            color: #2563eb;
        }

        .status-completed {
            background: #ecfdf3;
            color: #198754;
        }

        .status-other {
            background: #f1f3f5;
            color: #687386;
        }

        .price {
            text-align: right;
            min-width: 88px;
        }

        .price strong {
            display: block;
            color: #1f2937;
            font-size: 14px;
            font-weight: 750;
        }

        .price span {
            color: #8a94a3;
            font-size: 10.5px;
        }

        .empty {
            padding: 34px 12px 20px;
            text-align: center;
            color: #7b8798;
        }

        .empty i {
            display: block;
            margin-bottom: 8px;
            font-size: 32px;
            color: #a4afbd;
        }

        .empty p {
            margin: 0;
            font-size: 13.5px;
        }

        @media (max-width: 767.98px) {
            .page {
                padding: 28px 14px 45px;
            }

            .hero {
                padding: 24px 18px;
                text-align: center;
            }

            .avatar-wrap { margin-bottom: 16px; }

            .identity { padding-left: 0; }

            .name { font-size: 24px; }

            .details {
                justify-content: center;
                gap: 7px 15px;
            }

            .detail { font-size: 12.5px; }

            .edit-btn {
                width: 100%;
                margin-top: 20px;
            }

            .stat {
                padding: 16px;
            }

            .content-card {
                padding: 19px 14px;
            }

            .section-heading { font-size: 17px; }

            .list-row {
                align-items: flex-start;
                padding: 12px;
            }

            .status {
                margin-left: auto;
            }

            .price {
                min-width: 76px;
            }
        }
    
        /* Clickable statistics */
        .stat-clickable {
            width: 100%;
            border: 1px solid #e6eaf0;
            color: inherit;
            text-align: left;
            cursor: pointer;
            position: relative;
            padding-right: 45px;
            font: inherit;
        }

        .stat-clickable:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .20);
            outline-offset: 2px;
        }

        .stat-arrow {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            opacity: 0;
            transition: opacity .18s ease, transform .18s ease, color .18s ease;
        }

        .stat-clickable:hover .stat-arrow {
            opacity: 1;
            color: #2563eb;
            transform: translate(2px, -50%);
        }

        .stat-content {
            min-width: 0;
        }

        /* Statistics detail modal */
        .detail-modal {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(7px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .2s ease, visibility .2s ease;
        }

        .detail-modal.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .detail-modal-card {
            width: min(560px, 100%);
            max-height: 82vh;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #e6eaf0;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(15, 23, 42, .22);
            transform: translateY(12px) scale(.98);
            transition: transform .2s ease;
        }

        .detail-modal.show .detail-modal-card {
            transform: translateY(0) scale(1);
        }

        .detail-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding: 21px 22px 17px;
            border-bottom: 1px solid #edf0f4;
        }

        .detail-modal-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .detail-modal-icon {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: #eff6ff;
            color: #2563eb;
        }

        .detail-modal-title {
            margin: 0;
            font-size: 17px;
            font-weight: 750;
            color: #172033;
        }

        .detail-modal-subtitle {
            margin: 3px 0 0;
            color: #8a94a3;
            font-size: 11.5px;
        }

        .detail-modal-close {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 50%;
            background: #f3f6fa;
            color: #64748b;
            display: grid;
            place-items: center;
            cursor: pointer;
        }

        .detail-modal-close:hover {
            background: #e8edf4;
            color: #172033;
        }

        .detail-modal-body {
            padding: 18px 22px 22px;
        }

        .detail-list {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .detail-list-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid #edf0f4;
            border-radius: 12px;
        }

        .detail-list-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #f3f6fa;
            color: #526174;
        }

        .detail-list-main {
            min-width: 0;
            flex: 1;
        }

        .detail-list-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #263244;
            font-size: 13px;
            font-weight: 680;
        }

        .detail-list-meta {
            margin-top: 3px;
            color: #8590a0;
            font-size: 10.5px;
        }

        .detail-empty {
            padding: 28px 10px 20px;
            text-align: center;
            color: #7b8798;
        }

        .detail-empty i {
            display: block;
            margin-bottom: 8px;
            font-size: 30px;
            color: #a4afbd;
        }

        .detail-empty p {
            margin: 0;
            font-size: 13px;
        }

        @media (max-width: 576px) {
            .detail-modal {
                padding: 12px;
            }

            .detail-modal-head {
                padding: 17px 16px 14px;
            }

            .detail-modal-body {
                padding: 14px 16px 18px;
            }

            .detail-modal-title {
                font-size: 15px;
            }
        }


        /* Clickable rental and item rows */
        .detail-row {
            cursor: pointer;
            user-select: none;
        }

        .detail-row:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .18);
            outline-offset: 2px;
        }

        .row-arrow {
            flex: 0 0 auto;
            margin-left: 2px;
            color: #a1acba;
            font-size: 13px;
            opacity: 0;
            transform: translateX(-3px);
            transition: opacity .18s ease, transform .18s ease, color .18s ease;
        }

        .detail-row:hover .row-arrow,
        .detail-row:focus-visible .row-arrow {
            opacity: 1;
            color: #2563eb;
            transform: translateX(0);
        }

        .detail-row:hover {
            background: #f8fbff;
            border-color: #d7e3f3;
        }

        /* Item/rental detail modal */
        .entity-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .entity-detail-box {
            padding: 13px 14px;
            border: 1px solid #edf0f4;
            border-radius: 12px;
            background: #fbfcfe;
        }

        .entity-detail-label {
            margin-bottom: 5px;
            color: #8a94a3;
            font-size: 10.5px;
            font-weight: 650;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .entity-detail-value {
            color: #263244;
            font-size: 13px;
            font-weight: 680;
            word-break: break-word;
        }

        .entity-detail-banner {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 14px;
            padding: 14px;
            border-radius: 14px;
            background: #f7faff;
            border: 1px solid #e4edf9;
        }

        .entity-detail-banner-icon {
            width: 45px;
            height: 45px;
            flex: 0 0 45px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 19px;
        }

        .entity-detail-banner-title {
            margin: 0;
            color: #172033;
            font-size: 16px;
            font-weight: 750;
        }

        .entity-detail-banner-subtitle {
            margin: 3px 0 0;
            color: #7b8798;
            font-size: 11px;
        }

        @media (max-width: 576px) {
            .entity-detail-grid {
                grid-template-columns: 1fr;
            }

            .row-arrow {
                opacity: 1;
                transform: none;
            }
        }

</style>
</head>

<body>
<div class="page">

    <div class="eyebrow">
        <i class="bi bi-person-circle"></i>
        Account
    </div>

    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage your profile and keep track of your rental activity.</p>

    <!-- Profile -->
    <section class="hero">
        <div class="row align-items-center g-4">

            <div class="col-md-2">
                <div class="avatar-wrap">
                    <?php if (!empty($user["profile_photo"])): ?>
                        <img src="<?php echo htmlspecialchars($user["profile_photo"]); ?>"
                             alt="Profile Photo"
                             class="avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($user["name"], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-7">
                <div class="identity">
                    <h2 class="name"><?php echo htmlspecialchars($user["name"]); ?></h2>

                    <span class="role">
                        <i class="bi bi-person-fill"></i>
                        <?php echo htmlspecialchars($user["role"]); ?>
                    </span>

                    <div class="details">
                        <span class="detail">
                            <i class="bi bi-envelope"></i>
                            <?php echo htmlspecialchars($user["email"]); ?>
                        </span>

                        <span class="detail">
                            <i class="bi bi-telephone"></i>
                            <?php echo htmlspecialchars($user["phone"]); ?>
                        </span>

                        <span class="detail">
                            <i class="bi bi-geo-alt"></i>
                            <?php echo htmlspecialchars($user["address"]); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-3 text-md-end">
                <a href="edit_profile.php" class="btn btn-primary edit-btn">
                    <i class="bi bi-pencil-square me-1"></i>
                    Edit Profile
                </a>
            </div>

        </div>
    </section>

    <!-- Statistics -->
    <div class="row g-3 stats mb-4">
        <div class="col-md-4">
            <button type="button" class="stat stat-clickable"
                    data-stat-type="total-rentals"
                    aria-label="View total rentals">
                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_rentals; ?></div>
                    <div class="stat-label">Total Rentals</div>
                </div>
                <i class="bi bi-arrow-up-right stat-arrow"></i>
            </button>
        </div>

        <div class="col-md-4">
            <button type="button" class="stat stat-clickable"
                    data-stat-type="active-rentals"
                    aria-label="View active rentals">
                <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $active_rentals; ?></div>
                    <div class="stat-label">Active Rentals</div>
                </div>
                <i class="bi bi-arrow-up-right stat-arrow"></i>
            </button>
        </div>

        <div class="col-md-4">
            <button type="button" class="stat stat-clickable"
                    data-stat-type="listed-items"
                    aria-label="View listed items">
                <div class="stat-icon"><i class="bi bi-tags"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_listed_items; ?></div>
                    <div class="stat-label">Listed Items</div>
                </div>
                <i class="bi bi-arrow-up-right stat-arrow"></i>
            </button>
        </div>
    </div>

    <!-- Rental History -->
    <section class="content-card mb-4">
        <div class="section-head">
            <h2 class="section-heading">
                <i class="bi bi-clock-history"></i>
                Rental History
            </h2>

            <?php if ($total_rentals > 3): ?>
                <button type="button"
                        class="view-all-btn"
                        id="viewAllRentalsBtn"
                        aria-label="View all <?php echo $total_rentals; ?> rentals">
                    View all <?php echo $total_rentals; ?> rentals
                    <i class="bi bi-arrow-right"></i>
                </button>
            <?php else: ?>
                <span class="section-count">
                    <?php echo $total_rentals; ?> rental<?php echo $total_rentals == 1 ? '' : 's'; ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (count($bookings) > 0): ?>
            <div class="list">
                <?php foreach (array_slice($bookings, 0, 3) as $booking): ?>
                    <?php
                        $rental_index = array_search($booking, $bookings, true);
                        $status = strtolower(trim($booking["status"]));

                        if ($status == "active") {
                            $status_class = "status-active";
                            $status_icon = "bi-circle-fill";
                        } elseif ($status == "completed") {
                            $status_class = "status-completed";
                            $status_icon = "bi-check-circle-fill";
                        } else {
                            $status_class = "status-other";
                            $status_icon = "bi-dot";
                        }
                    ?>

                    <div class="list-row detail-row rental-detail-row"
                         data-rental-index="<?php echo $rental_index; ?>"
                         tabindex="0"
                         role="button"
                         aria-label="View rental details for <?php echo htmlspecialchars($booking["item_name"]); ?>">
                        <div class="row-icon">
                            <i class="bi bi-box"></i>
                        </div>

                        <div class="row-main">
                            <div class="row-title">
                                <?php echo htmlspecialchars($booking["item_name"]); ?>
                            </div>
                            <div class="row-meta">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo date("M d, Y", strtotime($booking["rental_date"])); ?>
                            </div>
                        </div>

                        <span class="status <?php echo $status_class; ?>">
                            <i class="bi <?php echo $status_icon; ?>"></i>
                            <?php echo htmlspecialchars($booking["status"]); ?>
                        </span>

                        <i class="bi bi-chevron-right row-arrow" aria-hidden="true"></i>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <i class="bi bi-calendar-x"></i>
                <p>You don't have any rental history yet.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Listed Items -->
    <section class="content-card">
        <div class="section-head">
            <h2 class="section-heading">
                <i class="bi bi-tags"></i>
                My Listed Items
            </h2>

            <?php if ($total_listed_items > 3): ?>
                <button type="button"
                        class="view-all-btn"
                        id="viewAllItemsBtn"
                        aria-label="View all <?php echo $total_listed_items; ?> listed items">
                    View all <?php echo $total_listed_items; ?> items
                    <i class="bi bi-arrow-right"></i>
                </button>
            <?php else: ?>
                <span class="section-count">
                    <?php echo $total_listed_items; ?> item<?php echo $total_listed_items == 1 ? '' : 's'; ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (count($items) > 0): ?>
            <div class="list">
                <?php foreach (array_slice($items, 0, 3) as $item): ?>
                    <?php $item_index = array_search($item, $items, true); ?>

                    <div class="list-row detail-row item-detail-row"
                         data-item-index="<?php echo $item_index; ?>"
                         tabindex="0"
                         role="button"
                         aria-label="View item details for <?php echo htmlspecialchars($item["item_name"]); ?>">
                        <div class="row-icon">
                            <i class="bi bi-box"></i>
                        </div>

                        <div class="row-main">
                            <div class="row-title">
                                <?php echo htmlspecialchars($item["item_name"]); ?>
                            </div>
                            <div class="row-meta">Available for rental</div>
                        </div>

                        <div class="price">
                            <strong>₹<?php echo htmlspecialchars($item["price_per_day"]); ?></strong>
                            <span>per day</span>
                        </div>

                        <i class="bi bi-chevron-right row-arrow" aria-hidden="true"></i>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <i class="bi bi-box-seam"></i>
                <p>You haven't listed any items yet.</p>
            </div>
        <?php endif; ?>
    </section>

</div>


<!-- Rental / Item detail modal -->
<div class="detail-modal" id="entityDetailModal" aria-hidden="true">
    <div class="detail-modal-card" role="dialog" aria-modal="true" aria-labelledby="entityModalTitle">

        <div class="detail-modal-head">
            <div class="detail-modal-title-wrap">
                <div class="detail-modal-icon" id="entityModalIcon">
                    <i class="bi bi-box"></i>
                </div>
                <div>
                    <h2 class="detail-modal-title" id="entityModalTitle">Details</h2>
                    <p class="detail-modal-subtitle" id="entityModalSubtitle">Information</p>
                </div>
            </div>

            <button type="button" class="detail-modal-close" id="closeEntityModal" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="detail-modal-body" id="entityModalBody"></div>
    </div>
</div>

<!-- Statistics detail modal -->
<div class="detail-modal" id="statDetailModal" aria-hidden="true">
    <div class="detail-modal-card" role="dialog" aria-modal="true" aria-labelledby="statModalTitle">

        <div class="detail-modal-head">
            <div class="detail-modal-title-wrap">
                <div class="detail-modal-icon" id="statModalIcon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div>
                    <h2 class="detail-modal-title" id="statModalTitle">Details</h2>
                    <p class="detail-modal-subtitle" id="statModalSubtitle">Your rental activity</p>
                </div>
            </div>

            <button type="button" class="detail-modal-close" id="closeStatModal" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="detail-modal-body" id="statModalBody"></div>
    </div>
</div>

<script>
    const statDetailModal = document.getElementById("statDetailModal");
    const closeStatModal = document.getElementById("closeStatModal");
    const statModalTitle = document.getElementById("statModalTitle");
    const statModalSubtitle = document.getElementById("statModalSubtitle");
    const statModalIcon = document.getElementById("statModalIcon");
    const statModalBody = document.getElementById("statModalBody");

    const rentalData = <?php echo json_encode($bookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const activeRentalData = rentalData.filter(function (rental) {
        return String(rental.status).trim().toLowerCase() === "active";
    });

    const itemData = <?php echo json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatDate(dateValue) {
        if (!dateValue) return "Date not available";

        const date = new Date(dateValue + "T00:00:00");

        if (Number.isNaN(date.getTime())) {
            return escapeHtml(dateValue);
        }

        return date.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }

    function statusClass(status) {
        const value = String(status).trim().toLowerCase();

        if (value === "active") return "status-active";
        if (value === "completed") return "status-completed";
        return "status-other";
    }

    function openStatModal(type) {
        let title = "";
        let subtitle = "";
        let icon = "";
        let html = "";

        if (type === "total-rentals") {
            title = "Total Rentals";
            subtitle = rentalData.length + (rentalData.length === 1 ? " rental" : " rentals");
            icon = "bi-box-seam";

            if (rentalData.length === 0) {
                html = '<div class="detail-empty"><i class="bi bi-calendar-x"></i><p>You don\'t have any rental history yet.</p></div>';
            } else {
                html = '<div class="detail-list">';

                rentalData.forEach(function (rental) {
                    html += `
                        <div class="detail-list-row clickable-modal-row"
                             data-modal-entity-type="rental"
                             data-modal-entity-index="${rentalData.indexOf(rental)}"
                             tabindex="0"
                             role="button"
                             aria-label="View rental details for ${escapeHtml(rental.item_name)}">
                            <div class="detail-list-icon">
                                <i class="bi bi-box"></i>
                            </div>
                            <div class="detail-list-main">
                                <div class="detail-list-title">${escapeHtml(rental.item_name)}</div>
                                <div class="detail-list-meta">
                                    ${formatDate(rental.rental_date)}
                                </div>
                            </div>
                            <span class="status ${statusClass(rental.status)}">
                                <i class="bi ${String(rental.status).trim().toLowerCase() === "active" ? "bi-circle-fill" : String(rental.status).trim().toLowerCase() === "completed" ? "bi-check-circle-fill" : "bi-dot"}"></i>
                                ${escapeHtml(rental.status)}
                            </span>
                            <i class="bi bi-chevron-right modal-row-arrow" aria-hidden="true"></i>
                        </div>
                    `;
                });

                html += "</div>";
            }

        } else if (type === "active-rentals") {
            title = "Active Rentals";
            subtitle = activeRentalData.length + (activeRentalData.length === 1 ? " active rental" : " active rentals");
            icon = "bi-arrow-repeat";

            if (activeRentalData.length === 0) {
                html = '<div class="detail-empty"><i class="bi bi-check2-circle"></i><p>You currently have no active rentals.</p></div>';
            } else {
                html = '<div class="detail-list">';

                activeRentalData.forEach(function (rental) {
                    html += `
                        <div class="detail-list-row clickable-modal-row"
                             data-modal-entity-type="rental"
                             data-modal-entity-index="${rentalData.indexOf(rental)}"
                             tabindex="0"
                             role="button"
                             aria-label="View rental details for ${escapeHtml(rental.item_name)}">
                            <div class="detail-list-icon">
                                <i class="bi bi-arrow-repeat"></i>
                            </div>
                            <div class="detail-list-main">
                                <div class="detail-list-title">${escapeHtml(rental.item_name)}</div>
                                <div class="detail-list-meta">
                                    Rented on ${formatDate(rental.rental_date)}
                                </div>
                            </div>
                            <span class="status status-active">
                                <i class="bi bi-circle-fill"></i>
                                Active
                            </span>
                            <i class="bi bi-chevron-right modal-row-arrow" aria-hidden="true"></i>
                        </div>
                    `;
                });

                html += "</div>";
            }

        } else if (type === "listed-items") {
            title = "Listed Items";
            subtitle = itemData.length + (itemData.length === 1 ? " listed item" : " listed items");
            icon = "bi-tags";

            if (itemData.length === 0) {
                html = '<div class="detail-empty"><i class="bi bi-box-seam"></i><p>You haven\'t listed any items yet.</p></div>';
            } else {
                html = '<div class="detail-list">';

                itemData.forEach(function (item) {
                    html += `
                        <div class="detail-list-row clickable-modal-row"
                             data-modal-entity-type="item"
                             data-modal-entity-index="${itemData.indexOf(item)}"
                             tabindex="0"
                             role="button"
                             aria-label="View item details for ${escapeHtml(item.item_name)}">
                            <div class="detail-list-icon">
                                <i class="bi bi-box"></i>
                            </div>
                            <div class="detail-list-main">
                                <div class="detail-list-title">${escapeHtml(item.item_name)}</div>
                                <div class="detail-list-meta">Available for rental</div>
                            </div>
                            <div class="price">
                                <strong>₹${escapeHtml(item.price_per_day)}</strong>
                                <span>per day</span>
                            </div>
                            <i class="bi bi-chevron-right modal-row-arrow" aria-hidden="true"></i>
                        </div>
                    `;
                });

                html += "</div>";
            }
        }

        statModalTitle.textContent = title;
        statModalSubtitle.textContent = subtitle;
        statModalIcon.innerHTML = '<i class="bi ' + icon + '"></i>';
        statModalBody.innerHTML = html;

        statDetailModal.classList.add("show");
        statDetailModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeStatDetailModal() {
        statDetailModal.classList.remove("show");
        statDetailModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    document.querySelectorAll(".stat-clickable").forEach(function (card) {
        card.addEventListener("click", function () {
            openStatModal(this.dataset.statType);
        });
    });

    closeStatModal.addEventListener("click", closeStatDetailModal);

    // View-all buttons use the same full-list modal as the statistics cards.
    const viewAllRentalsBtn = document.getElementById("viewAllRentalsBtn");
    if (viewAllRentalsBtn) {
        viewAllRentalsBtn.addEventListener("click", function () {
            openStatModal("total-rentals");
        });
    }

    const viewAllItemsBtn = document.getElementById("viewAllItemsBtn");
    if (viewAllItemsBtn) {
        viewAllItemsBtn.addEventListener("click", function () {
            openStatModal("listed-items");
        });
    }

    // Rows created inside the full-list modal are also fully clickable.
    statModalBody.addEventListener("click", function (event) {
        const row = event.target.closest(".clickable-modal-row");

        if (!row) return;

        const type = row.dataset.modalEntityType;
        const index = Number(row.dataset.modalEntityIndex);

        closeStatDetailModal();
        openEntityModal(type, index);
    });

    statModalBody.addEventListener("keydown", function (event) {
        const row = event.target.closest(".clickable-modal-row");

        if (!row) return;

        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();

            const type = row.dataset.modalEntityType;
            const index = Number(row.dataset.modalEntityIndex);

            closeStatDetailModal();
            openEntityModal(type, index);
        }
    });

    statDetailModal.addEventListener("click", function (event) {
        if (event.target === statDetailModal) {
            closeStatDetailModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeStatDetailModal();
            closeEntityDetailModal();
        }
    });

    // -----------------------------------------------------
    // Rental / Item row detail modal
    // -----------------------------------------------------

    const entityDetailModal = document.getElementById("entityDetailModal");
    const closeEntityModal = document.getElementById("closeEntityModal");
    const entityModalTitle = document.getElementById("entityModalTitle");
    const entityModalSubtitle = document.getElementById("entityModalSubtitle");
    const entityModalIcon = document.getElementById("entityModalIcon");
    const entityModalBody = document.getElementById("entityModalBody");

    function openEntityModal(type, index) {
        if (type === "rental") {
            const rental = rentalData[index];

            if (!rental) return;

            const currentStatus = String(rental.status || "").trim();
            const normalizedStatus = currentStatus.toLowerCase();

            entityModalTitle.textContent = rental.item_name || "Rental";
            entityModalSubtitle.textContent = "Rental information";
            entityModalIcon.innerHTML = '<i class="bi bi-box"></i>';

            entityModalBody.innerHTML = `
                <div class="entity-detail-banner">
                    <div class="entity-detail-banner-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h3 class="entity-detail-banner-title">${escapeHtml(rental.item_name)}</h3>
                        <p class="entity-detail-banner-subtitle">Rental record from your account</p>
                    </div>
                    <span class="status ${statusClass(currentStatus)} ms-auto">
                        <i class="bi ${normalizedStatus === "active" ? "bi-circle-fill" : normalizedStatus === "completed" ? "bi-check-circle-fill" : "bi-dot"}"></i>
                        ${escapeHtml(currentStatus)}
                    </span>
                </div>

                <div class="entity-detail-grid">
                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Item</div>
                        <div class="entity-detail-value">${escapeHtml(rental.item_name)}</div>
                    </div>

                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Status</div>
                        <div class="entity-detail-value">${escapeHtml(currentStatus)}</div>
                    </div>

                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Rental Date</div>
                        <div class="entity-detail-value">${formatDate(rental.rental_date)}</div>
                    </div>

                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Record</div>
                        <div class="entity-detail-value">Rental #${Number(index) + 1}</div>
                    </div>
                </div>
            `;

        } else if (type === "item") {
            const item = itemData[index];

            if (!item) return;

            entityModalTitle.textContent = item.item_name || "Listed Item";
            entityModalSubtitle.textContent = "Item listing information";
            entityModalIcon.innerHTML = '<i class="bi bi-tags"></i>';

            entityModalBody.innerHTML = `
                <div class="entity-detail-banner">
                    <div class="entity-detail-banner-icon">
                        <i class="bi bi-box"></i>
                    </div>
                    <div>
                        <h3 class="entity-detail-banner-title">${escapeHtml(item.item_name)}</h3>
                        <p class="entity-detail-banner-subtitle">Your listed item</p>
                    </div>
                    <div class="price ms-auto">
                        <strong>₹${escapeHtml(item.price_per_day)}</strong>
                        <span>per day</span>
                    </div>
                </div>

                <div class="entity-detail-grid">
                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Item</div>
                        <div class="entity-detail-value">${escapeHtml(item.item_name)}</div>
                    </div>

                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Rental Price</div>
                        <div class="entity-detail-value">₹${escapeHtml(item.price_per_day)} / day</div>
                    </div>

                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Availability</div>
                        <div class="entity-detail-value">Available for rental</div>
                    </div>

                    <div class="entity-detail-box">
                        <div class="entity-detail-label">Owner</div>
                        <div class="entity-detail-value">${escapeHtml(<?php echo json_encode($user["name"]); ?>)}</div>
                    </div>
                </div>
            `;
        }

        entityDetailModal.classList.add("show");
        entityDetailModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeEntityDetailModal() {
        entityDetailModal.classList.remove("show");
        entityDetailModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    document.querySelectorAll(".rental-detail-row").forEach(function (row) {
        row.addEventListener("click", function () {
            openEntityModal("rental", Number(this.dataset.rentalIndex));
        });

        row.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openEntityModal("rental", Number(this.dataset.rentalIndex));
            }
        });
    });

    document.querySelectorAll(".item-detail-row").forEach(function (row) {
        row.addEventListener("click", function () {
            openEntityModal("item", Number(this.dataset.itemIndex));
        });

        row.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openEntityModal("item", Number(this.dataset.itemIndex));
            }
        });
    });

    closeEntityModal.addEventListener("click", closeEntityDetailModal);

    entityDetailModal.addEventListener("click", function (event) {
        if (event.target === entityDetailModal) {
            closeEntityDetailModal();
        }
    });

</script>

</body>
</html>
