-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th7 29, 2025 lúc 11:34 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quanly_thuchi`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount` double DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `accounts`
--

INSERT INTO `accounts` (`id`, `user_id`, `name`, `balance`, `amount`) VALUES
(29, 9, 'Bank', 1040000.00, 0),
(30, 9, 'Cash', 800000.00, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `descriptions`
--

CREATE TABLE `descriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','ignored','processed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `feedbacks`
--

INSERT INTO `feedbacks` (`id`, `user_id`, `message`, `image`, `created_at`, `status`) VALUES
(23, 9, 'abc', NULL, '2025-07-19 20:00:49', 'ignored'),
(25, 9, 'add', 'uploads/1752930374_anhloi_1.jpg.png', '2025-07-19 20:06:14', 'ignored'),
(26, 9, 'vbb', 'uploads/1752930382_anhloi_2.jpg.png', '2025-07-19 20:06:22', 'processed'),
(27, 9, 'a', NULL, '2025-07-19 20:06:29', 'pending'),
(28, 9, 'b', NULL, '2025-07-19 20:06:32', 'pending'),
(29, 9, 'Bị lỗi', 'uploads/1753097657_anhloi_3.jpg.png', '2025-07-21 18:34:17', 'pending'),
(30, 16, 'abb', 'uploads/1753255134_anhloi_3.jpg.png', '2025-07-23 14:18:54', 'pending'),
(31, 9, 'bcd', 'uploads/1753776545_anhloi_5.png', '2025-07-29 15:09:05', 'pending');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` tinyint(4) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `remaining_balance` decimal(15,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `transactions`
--

INSERT INTO `transactions` (`id`, `account_id`, `user_id`, `type`, `amount`, `remaining_balance`, `description`, `date`) VALUES
(111, 29, 9, 2, 0.00, NULL, 'Tạo tài khoản mới: Ngân hàng', '2025-07-18 13:58:10'),
(112, 30, 9, 2, 0.00, NULL, 'Tạo tài khoản mới: Tiền mặt', '2025-07-18 13:58:22'),
(113, 29, 9, 0, 3000000.00, 3000000.00, 'Lương', '2025-07-18 18:58:57'),
(114, 29, 9, 1, 1000000.00, 2000000.00, 'Tiền mặt', '2025-07-18 18:59:40'),
(115, 30, 9, 0, 1000000.00, 1000000.00, 'Ngân hàng', '2025-07-18 18:59:54'),
(116, 29, 9, 1, 100000.00, 1900000.00, 'Mua sắm', '2025-07-19 17:58:52'),
(117, 30, 9, 1, 20000.00, 980000.00, 'Ăn sáng', '2025-07-20 13:57:04'),
(118, 29, 9, 1, 500000.00, 1400000.00, 'Mua sắm', '2025-07-20 13:58:06'),
(119, 29, 9, 1, 50000.00, 1350000.00, 'Ăn uống', '2025-07-21 18:32:58'),
(120, 30, 9, 0, 100000.00, 1080000.00, 'Lương', '2025-07-21 18:35:23'),
(123, 29, 9, 1, 200000.00, 1150000.00, 'Ăn uống', '2025-07-21 18:58:45'),
(129, 30, 9, 1, 100000.00, 980000.00, 'Ăn sáng', '2025-07-22 13:56:07'),
(130, 30, 9, 0, 50000.00, 1030000.00, 'Lương', '2025-07-23 14:15:37'),
(138, 30, 9, 0, 50000.00, 1080000.00, 'Lương', '2025-07-23 15:37:46'),
(142, 29, 9, 1, 10000.00, 1140000.00, 'Ăn uống', '2025-07-23 16:03:56'),
(143, 30, 9, 0, 10000.00, 1090000.00, 'Giao dịch thu không có nội dung', '2025-07-23 16:04:05'),
(145, 29, 9, 1, 20000.00, 1120000.00, 'Ăn uống', '2025-07-23 16:06:14'),
(148, 29, 9, 1, 20000.00, 1100000.00, 'Giao dịch chi không có nội dung', '2025-07-25 08:10:21'),
(149, 30, 9, 0, 10000.00, 1100000.00, 'Ngân hàng', '2025-07-25 08:31:50'),
(150, 29, 9, 1, 10000.00, 1090000.00, 'Tiền mặt', '2025-07-25 08:32:02'),
(152, 29, 9, 0, 50000.00, 1140000.00, 'Lương', '2025-07-27 18:35:19'),
(153, 30, 9, 1, 200000.00, 900000.00, 'Quà tặng', '2025-07-27 18:35:44'),
(155, 29, 9, 1, 10000.00, 1130000.00, 'Ăn uống', '2025-07-28 14:37:49'),
(156, 30, 9, 1, 100000.00, 800000.00, 'Quà tặng', '2025-07-28 14:38:09'),
(157, 29, 9, 0, 50000.00, 1180000.00, 'Lương', '2025-07-28 14:38:22'),
(158, 29, 9, 1, 40000.00, 1140000.00, 'Ăn uống', '2025-07-29 15:05:41'),
(160, 29, 9, 2, 0.00, 1140000.00, 'Đổi tên tài khoản từ \'Ngân hàng\' thành \'Bank\'', '2025-07-29 15:07:37'),
(161, 30, 9, 2, 0.00, 800000.00, 'Đổi tên tài khoản từ \'Tiền mặt\' thành \'Cash\'', '2025-07-29 15:07:48'),
(176, 29, 9, 1, 100000.00, 1040000.00, 'Mua sắm', '2025-07-29 15:58:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `birthyear` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `avatar`, `fullname`, `birthyear`, `email`, `role`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'admin', NULL, '$2y$10$QpeGeQZ4LIwI31u9KIxYJOAlLWkWztar5YhHYrhniM4GI6TtO6RL.', '1752843766_OIP (1).webp', '', 0, '', 'admin', NULL, NULL),
(9, 'member1', NULL, '$2y$10$Y1VnfA3rIs68DJj0hK1FeeGqlhMCoQAQMQdLDLzFDscToetML5RAC', '1753098638_1752571403_OIP.webp', 'Phan Thăng', 2000, 'phanthang07bn@gmail.com', 'user', '969692', '2025-07-29 10:00:33');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `descriptions`
--
ALTER TABLE `descriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_description` (`user_id`,`content`);

--
-- Chỉ mục cho bảng `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_account` (`account_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2004;

--
-- AUTO_INCREMENT cho bảng `descriptions`
--
ALTER TABLE `descriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3007;

--
-- AUTO_INCREMENT cho bảng `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1003;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
