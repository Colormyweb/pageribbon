<?php
/**
 * Plugin Name:       PageRibbon
 * Plugin URI:        https://designpress.fr/pageribbon
 * Description:       La cartographie visuelle de vos pages WordPress par famille et modèle, pour mieux les gérer au quotidien. Désactivable en un clic.
 * Version:           1.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Frédérique Game
 * Author URI:        https://colormyweb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pageribbon
 * Domain Path:       /languages
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 *  CONSTANTES
 * ============================================================ */
define( 'PAGERIBBON_VERSION', '1.6.0' );
define( 'PAGERIBBON_FILE', __FILE__ );
define( 'PAGERIBBON_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAGERIBBON_URL', plugin_dir_url( __FILE__ ) );
define( 'PAGERIBBON_BASENAME', plugin_basename( __FILE__ ) );

// Clé unique pour l'option stockée en DB. Une seule option pour tout le plugin.
define( 'PAGERIBBON_OPTION_KEY', 'pageribbon_settings' );


/* ============================================================
 *  CHARGEMENT DES FICHIERS
 *  Pas d'autoloader Composer pour rester simple et autonome.
 * ============================================================ */
require_once PAGERIBBON_DIR . 'includes/palette.php';
require_once PAGERIBBON_DIR . 'includes/detector.php';
require_once PAGERIBBON_DIR . 'includes/taxonomies.php';
require_once PAGERIBBON_DIR . 'includes/settings.php';
require_once PAGERIBBON_DIR . 'includes/admin-list.php';
require_once PAGERIBBON_DIR . 'includes/admin-filters.php';
require_once PAGERIBBON_DIR . 'includes/gutenberg.php';
require_once PAGERIBBON_DIR . 'includes/onboarding.php';


/* ============================================================
 *  INTERNATIONALISATION
 * ============================================================ */
add_action( 'init', function () {
    load_plugin_textdomain( 'pageribbon', false, dirname( PAGERIBBON_BASENAME ) . '/languages' );
} );


/* ============================================================
 *  ACTIVATION
 *  Initialise l'option par défaut si elle n'existe pas.
 *  Ne touche à rien si elle existe déjà (réactivation préservant les choix).
 * ============================================================ */
register_activation_hook( __FILE__, 'pageribbon_activate' );

function pageribbon_activate() {
    $existing = get_option( PAGERIBBON_OPTION_KEY );

    if ( false === $existing ) {
        // Installation fraîche
        $defaults = array(
            'enabled'           => true,   // Toggle global "mode conception"
            'enabled_taxonomies'=> array(), // Slugs des taxos activées (rempli au premier accès aux réglages)
            'term_colors'       => array(), // Map [taxonomy][term_slug] => index palette
            'show_in_admin_list'=> true,   // Coloration en liste admin
            'show_in_editor'    => true,   // Bandeau + panel Gutenberg
            // Sous-toggles de la liste admin (tous actifs par défaut = mode chantier max)
            'admin_list_show_stripe' => true,
            'admin_list_show_dot'    => true,
            'admin_list_show_bg'     => true,
            // Labels des 2 taxos natives (vides = défauts via helpers)
            // Depuis la 1.4.1 : PageRibbon est Pages-only. Les post types ciblés ne sont
            // plus stockés en option mais résolus à la volée via le filter `pageribbon_taxo_post_types`.
            'taxo_labels'            => array(),
        );
        add_option( PAGERIBBON_OPTION_KEY, $defaults );
    } else {
        // Migration : on rajoute les clés manquantes sans toucher aux choix existants
        $missing_defaults = array(
            'admin_list_show_stripe' => true,
            'admin_list_show_dot'    => true,
            'admin_list_show_bg'     => true,
            'taxo_labels'            => array(),
        );
        $changed = false;
        foreach ( $missing_defaults as $key => $value ) {
            if ( ! array_key_exists( $key, $existing ) ) {
                $existing[ $key ] = $value;
                $changed = true;
            }
        }
        if ( $changed ) {
            update_option( PAGERIBBON_OPTION_KEY, $existing );
        }
    }

    // Force l'enregistrement des taxos pour que les URL d'admin
    // edit-tags.php?taxonomy=pageribbon_famille soient valides dès maintenant.
    if ( function_exists( 'pageribbon_register_taxonomies' ) ) {
        pageribbon_register_taxonomies();
    }
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'pageribbon_deactivate' );

function pageribbon_deactivate() {
    flush_rewrite_rules();
}


/* ============================================================
 *  HELPERS GLOBAUX (utilisables partout dans le plugin)
 * ============================================================ */

/**
 * Récupère un réglage du plugin.
 *
 * @param string $key     Clé du réglage.
 * @param mixed  $default Valeur par défaut si la clé n'existe pas.
 * @return mixed
 */
function pageribbon_get_setting( $key, $default = null ) {
    $settings = get_option( PAGERIBBON_OPTION_KEY, array() );
    return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Met à jour un réglage.
 *
 * @param string $key   Clé du réglage.
 * @param mixed  $value Nouvelle valeur.
 * @return bool
 */
function pageribbon_update_setting( $key, $value ) {
    $settings = get_option( PAGERIBBON_OPTION_KEY, array() );
    $settings[ $key ] = $value;
    return update_option( PAGERIBBON_OPTION_KEY, $settings );
}

/**
 * Le mode conception est-il actif globalement ?
 * Utilisé partout pour la kill-switch en 1 clic.
 *
 * @return bool
 */
function pageribbon_is_enabled() {
    return (bool) pageribbon_get_setting( 'enabled', true );
}
