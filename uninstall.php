<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove the credential sources table and public key resource ID from user meta.
$table_name = $wpdb->prefix . 'pk_credential_sources';
$sql        = "DROP TABLE IF EXISTS $table_name;";
$wpdb->query($sql);
$meta_key = 'pk_credential_id';
$wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key]);
