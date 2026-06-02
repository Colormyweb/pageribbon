<?php
/**
 * PageRibbon — Filtres dropdown dans les listes admin
 *
 * Pour chaque taxonomie PageRibbon activée pour la coloration, on ajoute un
 * dropdown de filtre dans la liste admin de chaque post type concerné.
 *
 * WordPress affiche déjà nativement un dropdown pour `category` sur les
 * articles ; on évite le doublon en ne posant nos filtres que sur les taxos
 * non-natives WP.
 *
 * @package PageRibbon
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* ============================================================
 *  1. AFFICHAGE DES DROPDOWNS DE FILTRES
 *  Hook : restrict_manage_posts (au-dessus de la table)
 * ============================================================ */
add_action( 'restrict_manage_posts', 'pageribbon_render_taxonomy_filters' );

function pageribbon_render_taxonomy_filters( $post_type ) {

    // post_type est passé en argument du hook depuis WP 4.6+
    if ( empty( $post_type ) ) {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        $post_type = $screen->post_type;
    }

    $enabled_taxos = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    if ( empty( $enabled_taxos ) ) {
        return;
    }

    // Réordonner pour cohérence avec la priorité de coloration (1.3.1) :
    // Modèle d'abord, puis Famille, puis le reste dans l'ordre des réglages.
    $priority_order = apply_filters(
        'pageribbon_priority_taxonomies',
        array( 'pageribbon_modele', 'pageribbon_famille' ),
        0
    );
    $ordered_taxos = array();
    foreach ( (array) $priority_order as $p ) {
        if ( in_array( $p, $enabled_taxos, true ) ) {
            $ordered_taxos[] = $p;
        }
    }
    foreach ( $enabled_taxos as $t ) {
        if ( ! in_array( $t, $ordered_taxos, true ) ) {
            $ordered_taxos[] = $t;
        }
    }

    foreach ( $ordered_taxos as $taxo_slug ) {

        $taxo = get_taxonomy( $taxo_slug );
        if ( ! $taxo ) {
            continue;
        }

        // Skip si la taxo n'est pas attachée au post type courant
        if ( ! in_array( $post_type, (array) $taxo->object_type, true ) ) {
            continue;
        }

        // Skip si WordPress affiche déjà un filtre natif pour cette taxo
        // (cas de `category` sur les articles : WP le fait tout seul)
        if ( pageribbon_taxonomy_has_native_filter( $taxo_slug, $post_type ) ) {
            continue;
        }

        $terms = get_terms( array(
            'taxonomy'   => $taxo_slug,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        $current_value = isset( $_GET[ $taxo_slug ] ) ? sanitize_key( wp_unslash( $_GET[ $taxo_slug ] ) ) : '';

        /* translators: %s: nom pluriel de la taxonomie */
        $all_label = sprintf( __( 'Tous les %s', 'pageribbon' ), strtolower( $taxo->labels->name ) );

        echo '<select name="' . esc_attr( $taxo_slug ) . '" id="pageribbon-filter-' . esc_attr( $taxo_slug ) . '">';
        echo '<option value="">' . esc_html( $all_label ) . '</option>';

        foreach ( $terms as $term ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $term->slug ),
                selected( $current_value, $term->slug, false ),
                esc_html( $term->name )
            );
        }
        echo '</select>';
    }
}


/**
 * Détecte si WordPress affiche déjà un filtre dropdown natif pour cette
 * taxonomie sur ce post type. C'est le cas de `category` sur `post` par défaut.
 *
 * @param string $taxonomy
 * @param string $post_type
 * @return bool
 */
function pageribbon_taxonomy_has_native_filter( $taxonomy, $post_type ) {

    // category sur post : filtre natif WP toujours présent
    if ( 'category' === $taxonomy && 'post' === $post_type ) {
        return true;
    }

    return false;
}


/* ============================================================
 *  2. APPLICATION DU FILTRE PAR INJECTION D'UN tax_query
 *
 *  Pour les taxos custom, WordPress n'a pas de query_var auto qui matche
 *  le slug de la taxo. Le bon mécanisme est d'injecter un tax_query
 *  via pre_get_posts.
 * ============================================================ */
add_action( 'pre_get_posts', 'pageribbon_apply_taxonomy_filter_to_query' );

function pageribbon_apply_taxonomy_filter_to_query( $query ) {

    if ( ! is_admin() ) {
        return;
    }

    global $pagenow;
    if ( 'edit.php' !== $pagenow ) {
        return;
    }

    if ( ! $query->is_main_query() ) {
        return;
    }

    $enabled_taxos = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    if ( empty( $enabled_taxos ) ) {
        return;
    }

    $extra_clauses = array();

    foreach ( $enabled_taxos as $taxo_slug ) {

        if ( empty( $_GET[ $taxo_slug ] ) ) {
            continue;
        }

        if ( ! taxonomy_exists( $taxo_slug ) ) {
            continue;
        }

        $value = sanitize_key( wp_unslash( $_GET[ $taxo_slug ] ) );
        if ( '' === $value || '0' === $value ) {
            continue;
        }

        $extra_clauses[] = array(
            'taxonomy' => $taxo_slug,
            'field'    => 'slug',
            'terms'    => array( $value ),
        );
    }

    if ( empty( $extra_clauses ) ) {
        return;
    }

    $existing = $query->get( 'tax_query' );
    if ( ! is_array( $existing ) ) {
        $existing = array();
    }

    $merged = array_merge( $existing, $extra_clauses );

    if ( count( $merged ) > 1 ) {
        $merged['relation'] = 'AND';
    }

    $query->set( 'tax_query', $merged );
}
