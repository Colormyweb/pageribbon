<?php
/**
 * PageRibbon — Détection automatique des taxonomies
 *
 * Détecte les taxonomies hiérarchiques attachées à des post types publics.
 * Exclut les taxos internes WordPress (link_category, post_format, nav_menu).
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Retourne les taxonomies "colorisables" du site.
 *
 * Conditions :
 *  - hiérarchique (familles/groupes/catégories)
 *  - attachée à au moins un post type public
 *  - pas dans la liste d'exclusion (taxos système WP)
 *
 * @return array Tableau de stdClass taxonomies (objets WP_Taxonomy).
 */
function pageribbon_get_colorable_taxonomies() {

    $excluded = apply_filters( 'pageribbon_excluded_taxonomies', array(
        'link_category',
        'post_format',
        'nav_menu',
        // 'wp_theme', 'wp_template_part_area' sont déjà non hiérarchiques mais on note
    ) );

    $taxonomies = get_taxonomies( array(
        'public'       => true,
        'hierarchical' => true,
    ), 'objects' );

    $result = array();

    foreach ( $taxonomies as $taxo ) {

        if ( in_array( $taxo->name, $excluded, true ) ) {
            continue;
        }

        // On vérifie qu'au moins un post type rattaché est public
        $object_types = (array) $taxo->object_type;
        $has_public_type = false;

        foreach ( $object_types as $pt_name ) {
            $pt = get_post_type_object( $pt_name );
            if ( $pt && ! empty( $pt->public ) ) {
                $has_public_type = true;
                break;
            }
        }

        if ( $has_public_type ) {
            $result[ $taxo->name ] = $taxo;
        }
    }

    return $result;
}


/**
 * Retourne les post types associés à une taxonomie colorisable.
 *
 * @param string $taxonomy_slug Slug de la taxonomie.
 * @return array Tableau d'objets WP_Post_Type.
 */
function pageribbon_get_taxonomy_post_types( $taxonomy_slug ) {
    $taxo = get_taxonomy( $taxonomy_slug );
    if ( ! $taxo ) {
        return array();
    }

    $out = array();
    foreach ( (array) $taxo->object_type as $pt_name ) {
        $pt = get_post_type_object( $pt_name );
        if ( $pt && ! empty( $pt->public ) ) {
            $out[ $pt_name ] = $pt;
        }
    }
    return $out;
}


/**
 * Au premier accès aux réglages, active automatiquement la première taxo trouvée
 * pour que l'utilisateur voie immédiatement quelque chose se passer ("zero-config").
 *
 * @return void
 */
function pageribbon_bootstrap_first_taxonomy() {

    $enabled = pageribbon_get_setting( 'enabled_taxonomies', array() );

    // Si l'utilisateur a déjà fait un choix (même de tout désactiver), on respecte.
    if ( ! empty( $enabled ) ) {
        return;
    }

    // Si une option dédiée nous dit qu'on a déjà fait le bootstrap, on s'arrête.
    if ( get_option( 'pageribbon_bootstrap_done' ) ) {
        return;
    }

    $taxos = pageribbon_get_colorable_taxonomies();
    if ( empty( $taxos ) ) {
        update_option( 'pageribbon_bootstrap_done', 1 );
        return;
    }

    // On prend la 1ère taxo. Préférence pour les taxos rattachées à "page" si dispo.
    $first = null;
    foreach ( $taxos as $taxo ) {
        if ( in_array( 'page', (array) $taxo->object_type, true ) ) {
            $first = $taxo->name;
            break;
        }
    }
    if ( ! $first ) {
        $first = key( $taxos );
    }

    pageribbon_update_setting( 'enabled_taxonomies', array( $first ) );

    // Auto-assignation des couleurs aux termes de cette taxo, cycliquement.
    $terms = get_terms( array(
        'taxonomy'   => $first,
        'hide_empty' => false,
    ) );

    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        $palette_size = count( pageribbon_get_palette() );
        $colors = pageribbon_get_setting( 'term_colors', array() );

        $i = 0;
        foreach ( $terms as $term ) {
            $colors[ $first ][ $term->slug ] = $i % $palette_size;
            $i++;
        }

        pageribbon_update_setting( 'term_colors', $colors );
    }

    update_option( 'pageribbon_bootstrap_done', 1 );
}
