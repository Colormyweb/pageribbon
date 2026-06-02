<?php
/**
 * PageRibbon — Palette de couleurs
 *
 * 10 couleurs pastels accessibles WCAG AA :
 *   - Fond pastel doux (bg)
 *   - Bordure plus saturée (border)
 *   - Texte foncé contraste élevé sur le bg (text)
 *
 * Toutes les combinaisons text/bg ont été vérifiées >= 4.5:1 (WCAG AA texte normal).
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retourne la palette complète.
 *
 * Format : index numérique => array( label, bg, border, text )
 *
 * @return array
 */
function pageribbon_get_palette() {

    $palette = array(
        0 => array(
            'label'  => __( 'Bleu', 'pageribbon' ),
            'bg'     => '#cadcf3',
            'border' => '#3b82f6',
            'text'   => '#1e3a8a',
        ),
        1 => array(
            'label'  => __( 'Vert', 'pageribbon' ),
            'bg'     => '#cdead8',
            'border' => '#22c55e',
            'text'   => '#14532d',
        ),
        2 => array(
            'label'  => __( 'Ambre', 'pageribbon' ),
            'bg'     => '#fae3be',
            'border' => '#f59e0b',
            'text'   => '#7c2d12',
        ),
        3 => array(
            'label'  => __( 'Violet', 'pageribbon' ),
            'bg'     => '#dccef1',
            'border' => '#8b5cf6',
            'text'   => '#4c1d95',
        ),
        4 => array(
            'label'  => __( 'Rose', 'pageribbon' ),
            'bg'     => '#f8c7dc',
            'border' => '#ec4899',
            'text'   => '#831843',
        ),
        5 => array(
            'label'  => __( 'Turquoise', 'pageribbon' ),
            'bg'     => '#c5ecea',
            'border' => '#14b8a6',
            'text'   => '#134e4a',
        ),
        6 => array(
            'label'  => __( 'Corail', 'pageribbon' ),
            'bg'     => '#fbc9ba',
            'border' => '#f97316',
            'text'   => '#7c2d12',
        ),
        7 => array(
            'label'  => __( 'Indigo', 'pageribbon' ),
            'bg'     => '#c8ceee',
            'border' => '#6366f1',
            'text'   => '#312e81',
        ),
        8 => array(
            'label'  => __( 'Olive', 'pageribbon' ),
            'bg'     => '#e1e8c1',
            'border' => '#84cc16',
            'text'   => '#365314',
        ),
        9 => array(
            'label'  => __( 'Gris', 'pageribbon' ),
            'bg'     => '#d9dde4',
            'border' => '#64748b',
            'text'   => '#1e293b',
        ),
    );

    // Filtre pour permettre une personnalisation avancée (cas d'agence par ex).
    return apply_filters( 'pageribbon_palette', $palette );
}


/**
 * Retourne une couleur de la palette par son index.
 * Fallback : index 9 (gris neutre) si l'index demandé n'existe pas.
 *
 * @param int|null $index Index dans la palette.
 * @return array { label, bg, border, text }
 */
function pageribbon_get_color( $index ) {
    $palette = pageribbon_get_palette();
    if ( null === $index || ! isset( $palette[ (int) $index ] ) ) {
        return $palette[9]; // Gris neutre par défaut
    }
    return $palette[ (int) $index ];
}


/**
 * Retourne la couleur associée à un terme donné, en lisant les réglages.
 *
 * @param string $taxonomy  Slug de la taxonomie.
 * @param string $term_slug Slug du terme.
 * @return array|null Couleur, ou null si pas de couleur assignée.
 */
function pageribbon_get_term_color( $taxonomy, $term_slug ) {
    $colors = pageribbon_get_setting( 'term_colors', array() );
    if ( ! isset( $colors[ $taxonomy ][ $term_slug ] ) ) {
        return null;
    }
    return pageribbon_get_color( $colors[ $taxonomy ][ $term_slug ] );
}


/**
 * Retourne la couleur attribuée à un post (basée sur les termes de ses taxos activées).
 *
 * Ordre de priorité (1.3.1) :
 * 1. pageribbon_modele — la mise en page caractérise individuellement chaque page
 * 2. pageribbon_famille — le groupe sémantique caractérise un ensemble de pages
 * 3. autres taxos activées (category, taxos custom, etc.) dans l'ordre des réglages
 *
 * Décision produit : le modèle prime sur la famille parce qu'il identifie mieux
 * une page seule. La famille reste visible via la colonne "Familles" de la liste admin
 * et via le filtre dropdown.
 *
 * @param int $post_id ID du post.
 * @return array|null Couleur, ou null si rien à colorer.
 */
function pageribbon_get_post_color( $post_id ) {

    $enabled_taxos = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    if ( empty( $enabled_taxos ) ) {
        return null;
    }

    // Ordre explicite de priorité : on construit une liste ordonnée des taxos
    // à interroger, en mettant en premier les taxos prioritaires de PageRibbon.
    //
    // Filtre `pageribbon_priority_taxonomies` : permet aux devs tiers de modifier
    // l'ordre de priorité (par exemple si un thème custom veut prioriser sa propre taxo).
    $priority_order = apply_filters(
        'pageribbon_priority_taxonomies',
        array( 'pageribbon_modele', 'pageribbon_famille' ),
        $post_id
    );
    $ordered_taxos = array();

    // 1. D'abord les taxos prioritaires (si elles sont activées)
    foreach ( (array) $priority_order as $priority_taxo ) {
        if ( in_array( $priority_taxo, $enabled_taxos, true ) ) {
            $ordered_taxos[] = $priority_taxo;
        }
    }

    // 2. Puis toutes les autres taxos activées, dans leur ordre de réglages
    foreach ( $enabled_taxos as $taxo ) {
        if ( ! in_array( $taxo, $ordered_taxos, true ) ) {
            $ordered_taxos[] = $taxo;
        }
    }

    $result = null;
    foreach ( $ordered_taxos as $taxo ) {
        $terms = get_the_terms( $post_id, $taxo );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            continue;
        }
        // Ordre déterministe : si une page/un article a plusieurs termes colorés
        // dans la même taxonomie (ex. deux catégories), on tranche toujours de la
        // même façon — le premier par ordre alphabétique du nom. Sans ce tri,
        // get_the_terms() ne garantit pas un ordre stable et la couleur affichée
        // pourrait varier.
        usort( $terms, function ( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );
        foreach ( $terms as $term ) {
            $color = pageribbon_get_term_color( $taxo, $term->slug );
            if ( $color ) {
                $color['term_label'] = $term->name;
                $color['term_slug']  = $term->slug;
                $color['taxonomy']   = $taxo;
                $result = $color;
                break 2;
            }
        }
    }

    /**
     * Filtre la couleur finalement retournée pour un post.
     *
     * @param array|null $result  Couleur (avec keys bg, border, label, term_label, term_slug, taxonomy) ou null.
     * @param int        $post_id ID du post.
     */
    return apply_filters( 'pageribbon_get_post_color', $result, $post_id );
}
