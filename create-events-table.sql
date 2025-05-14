CREATE TABLE IF NOT EXISTS wp_status_sentry_events (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    feature varchar(50) NOT NULL,
    hook varchar(100) NOT NULL,
    data longtext NOT NULL,
    event_time datetime NOT NULL,
    processed_time datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY feature (feature),
    KEY hook (hook),
    KEY event_time (event_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
