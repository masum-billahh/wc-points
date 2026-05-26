<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_DB {

    public static function install() {
        global $wpdb;
        $table   = $wpdb->prefix . PCP_TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT(20) UNSIGNED NOT NULL,
            points      INT(11)             NOT NULL,
            type        VARCHAR(50)         NOT NULL,
            description TEXT                        ,
            order_id    BIGINT(20) UNSIGNED          DEFAULT NULL,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at  DATETIME                     DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type    (type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store plugin version
        update_option( 'pcp_db_version', PCP_VERSION );
    }
}