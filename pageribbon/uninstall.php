<?php
/**
 * PageRibbon — Désinstallation
 *
 * Supprime toutes les options stockées par le plugin.
 * Appelé uniquement quand l'utilisateur supprime le plugin via l'admin
 * (pas à la simple désactivation).
 *
 * @package PageRibbon
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'pageribbon_settings' );
delete_option( 'pageribbon_bootstrap_done' );
delete_option( 'pageribbon_adoption_done' );
