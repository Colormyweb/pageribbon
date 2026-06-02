<?php
/**
 * PageRibbon — Coloration de la liste admin
 *
 * Trois marqueurs visuels, indépendamment activables :
 *   1. Filet vertical épais à gauche de la ligne (couleur "border" vive)
 *   2. Pastille ronde collée devant le titre de la page (couleur "border" vive)
 *   3. Fond pastel sur toute la ligne (couleur "bg")
 *
 * Tout passe par CSS injecté + un filtre sur le titre.
 * Aucun ajout de colonne (compact, fonctionne avec WooCommerce, ACF, etc.).
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* ============================================================
 *  1. AJOUT D'ATTRIBUTS DATA SUR LES LIGNES <tr>
 *  On ajoute un data-pageribbon-color sur la ligne via post_class
 *  (qui pose des classes/attributs sur <tr> en WP admin).
 *
 *  En pratique : post_class ne pose que des classes, pas d'attributs.
 *  Donc on génère une classe spécifique par terme coloré, et le CSS
 *  injecté plus bas définit le ::before sur ces classes.
 * ============================================================ */

// Pas de filtre the_title : la pastille est dessinée 100% en CSS,
// via le pseudo-element ::before sur td.column-title strong.
// Voir la section "3. CSS DYNAMIQUE" ci-dessous.


/* ============================================================
 *  2. INJECTION DU CSS POUR LE FILET GAUCHE ET LE FOND
 *  On génère le CSS dynamiquement à partir des couleurs configurées
 *  car on ne peut pas styler ligne par ligne sans un attribut.
 *  On ajoute donc une classe `pageribbon-pt-{slug}` sur chaque <tr>
 *  via le filtre post_class.
 * ============================================================ */
add_filter( 'post_class', 'pageribbon_add_post_classes', 10, 3 );

function pageribbon_add_post_classes( $classes, $css_class, $post_id ) {

    if ( ! is_admin() ) {
        return $classes;
    }

    if ( ! pageribbon_is_enabled() ) {
        return $classes;
    }

    if ( ! pageribbon_get_setting( 'show_in_admin_list', true ) ) {
        return $classes;
    }

    $color = pageribbon_get_post_color( $post_id );
    if ( ! $color ) {
        return $classes;
    }

    // Ajoute une classe utilisable en CSS : pageribbon-term-{taxonomy}-{slug}
    $classes[] = 'pageribbon-row';
    $classes[] = sanitize_html_class( 'pageribbon-term-' . $color['taxonomy'] . '-' . $color['term_slug'] );

    return $classes;
}


/* ============================================================
 *  3. CSS DYNAMIQUE INJECTÉ DANS LE <HEAD> DE L'ADMIN
 *  Construction CSS optimisée : on génère une règle par terme coloré.
 * ============================================================ */
add_action( 'admin_head-edit.php', 'pageribbon_inject_admin_list_css' );

function pageribbon_inject_admin_list_css() {

    if ( ! pageribbon_is_enabled() ) {
        return;
    }

    if ( ! pageribbon_get_setting( 'show_in_admin_list', true ) ) {
        return;
    }

    $enabled_taxos = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    if ( empty( $enabled_taxos ) ) {
        return;
    }

    // On n'injecte le CSS que si le post type listé a au moins une taxo activée.
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }
    $current_post_type = $screen->post_type;
    $relevant_taxos = array();
    foreach ( $enabled_taxos as $taxo_slug ) {
        $taxo = get_taxonomy( $taxo_slug );
        if ( $taxo && in_array( $current_post_type, (array) $taxo->object_type, true ) ) {
            $relevant_taxos[] = $taxo_slug;
        }
    }
    if ( empty( $relevant_taxos ) ) {
        return;
    }

    $term_colors = (array) pageribbon_get_setting( 'term_colors', array() );

    $show_stripe = (bool) pageribbon_get_setting( 'admin_list_show_stripe', true );
    $show_bg     = (bool) pageribbon_get_setting( 'admin_list_show_bg', true );
    $show_dot    = (bool) pageribbon_get_setting( 'admin_list_show_dot', true );

    // --- CSS de base (structure des pseudo-elements de pastille)
    $css = "\n/* PageRibbon admin list styles */\n";

    if ( $show_dot ) {
        // La pastille est dessinée via ::before sur le <strong> qui entoure le titre.
        // C'est 100% CSS : aucun HTML injecté, donc aucun risque d'échappement.
        $css .= ".wp-list-table tr.pageribbon-row td.column-title strong::before{";
        $css .= "content:'';display:inline-block;width:9px;height:9px;border-radius:50%;";
        $css .= "margin-right:8px;vertical-align:1px;box-shadow:0 0 0 1px rgba(0,0,0,.05);";
        $css .= "background:var(--pageribbon-dot-color,transparent);";
        $css .= "}\n";
    }

    // --- CSS par terme : on parcourt seulement les taxos pertinentes pour ce post type
    foreach ( $relevant_taxos as $taxo ) {
        if ( empty( $term_colors[ $taxo ] ) ) {
            continue;
        }

        $terms = get_terms( array(
            'taxonomy'   => $taxo,
            'hide_empty' => false,
        ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            if ( ! isset( $term_colors[ $taxo ][ $term->slug ] ) ) {
                continue;
            }
            $color = pageribbon_get_color( $term_colors[ $taxo ][ $term->slug ] );
            $class = sanitize_html_class( 'pageribbon-term-' . $taxo . '-' . $term->slug );

            // On définit les CSS custom properties sur la ligne, puis
            // chaque marqueur (fond, filet, pastille) lit la bonne propriété.
            $css .= ".wp-list-table tr.{$class}{";
            $css .= "--pageribbon-bg:" . esc_attr( $color['bg'] ) . ";";
            $css .= "--pageribbon-border:" . esc_attr( $color['border'] ) . ";";
            $css .= "--pageribbon-dot-color:" . esc_attr( $color['border'] ) . ";";
            $css .= "}\n";

            if ( $show_bg ) {
                $css .= ".wp-admin .wp-list-table tr.{$class} td,";
                $css .= ".wp-admin .wp-list-table tr.{$class} th{background:" . esc_attr( $color['bg'] ) . " !important;}\n";
            }

            if ( $show_stripe ) {
                // Filet vertical 4px à gauche de la première cellule (la checkbox)
                $css .= ".wp-admin .wp-list-table tr.{$class} th.check-column{";
                $css .= "box-shadow: inset 4px 0 0 0 " . esc_attr( $color['border'] ) . ";";
                $css .= "}\n";
            }
        }
    }

    echo "<style id='pageribbon-admin-list-css'>{$css}</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
