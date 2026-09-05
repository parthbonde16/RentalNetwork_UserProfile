CREATE DATABASE IF NOT EXISTS rental_network;

USE rental_network;


-- =========================
-- USERS TABLE
-- =========================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    address VARCHAR(255),
    role VARCHAR(20),
    profile_photo VARCHAR(255)
);


-- =========================
-- BOOKINGS TABLE
-- =========================

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    rental_date DATE,
    status VARCHAR(30)
);


-- =========================
-- ITEMS TABLE
-- =========================

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL
);