--
-- Table structure for table `linked_providers`
--

CREATE TABLE `linked_providers` (
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `external_user_id` int(11) NOT NULL,
  `access_token` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `refresh_token` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `software` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `branch` varchar(16) COLLATE utf8mb4_bin NOT NULL,
  `version` varchar(16) COLLATE utf8mb4_bin NOT NULL,
  `last_sent_user` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `session_id` varchar(36) COLLATE utf8mb4_bin NOT NULL,
  `session_ip` varchar(45) COLLATE utf8mb4_bin NOT NULL,
  `session_created` int(11) NOT NULL,
  `form_token` varchar(36) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `user_id` int(11) NOT NULL,
  `software` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `branch` varchar(16) COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `subscription_email` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `subscriber_token` varchar(36) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `version_info`
--

CREATE TABLE `version_info` (
  `id` int(11) NOT NULL,
  `software` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `branch` varchar(16) COLLATE utf8mb4_bin NOT NULL,
  `version` varchar(16) COLLATE utf8mb4_bin NOT NULL,
  `announcement` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `release_date` datetime NOT NULL,
  `last_check` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estimated` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `linked_providers`
--
ALTER TABLE `linked_providers`
  ADD UNIQUE KEY `idx` (`user_id`,`provider_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD UNIQUE KEY `user_id` (`user_id`,`software`,`branch`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `version_info`
--
ALTER TABLE `version_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `v` (`software`,`branch`,`version`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `version_info`
--
ALTER TABLE `version_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

