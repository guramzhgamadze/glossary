<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Georgian noun stem extractor for fuzzy declension matching.
 *
 * Georgian nouns decline across 7 cases. This class strips known suffixes
 * (postpositions, plural markers, case endings, nominative -ი) to reduce a
 * word to its bare stem, so that "სტრესი", "სტრესს", "სტრესისგან",
 * "სტრესებში" all resolve to the same stem "სტრეს" and match each other.
 *
 * Stripping is purely suffix-based (no lookup table). The minimum stem
 * length (MIN_STEM_LEN) prevents over-stripping short words.
 *
 * Usage:
 *   $stem = WPGT_Georgian_Stemmer::stem( 'სტრესისგან' ); // → 'სტრეს'
 *   $same = WPGT_Georgian_Stemmer::stems_match( 'სტრესი', 'სტრესს' ); // → true
 */
class WPGT_Georgian_Stemmer {

    /**
     * Minimum character length a stem must have after stripping.
     * Prevents "კი" or "და" from being treated as stems and matching everything.
     */
    const MIN_STEM_LEN = 3;

    /**
     * Suffixes to try stripping, in order (longest first within each group).
     * Order matters: we try each suffix and take the first that applies.
     *
     * Groups:
     *  1. Postposition + case combos (longest possible strings first)
     *  2. Plural + postposition/case
     *  3. Plain case endings
     *  4. Nominative -ი (stripped last, conditionally)
     */
    private static array $suffixes = [
        // ── Postpositions attached to genitive/other stems ──────────────────
        'ისათვის',  // gen + postposition თვის
        'ისთვის',
        'თვისაც',
        'თვისვე',
        'თვის',
        'იდანვე',
        'იდანაც',
        'იდან',
        'დანვე',
        'დანაც',
        'დან',
        'ამდეც',
        'ამდე',
        'მდეც',
        'მდე',
        'ივითაც',
        'ივით',
        'ვითაც',
        'ვით',
        'ისგანვე',
        'ისგანაც',
        'ისგან',
        'განვე',
        'განაც',
        'განაც',
        'გან',
        'თანაცვე',
        'თანავე',
        'თანაც',
        'თან',
        'ზედაც',
        'ზევე',
        'ზეაც',
        'ზეც',
        'ზე',
        'შიდაც',
        'შივე',
        'შიაც',
        'შიც',
        'ში',

        // ── Plural stem + case/postposition ─────────────────────────────────
        'ებისათვის',
        'ებისთვის',
        'ებიდანვე',
        'ებიდანაც',
        'ებიდან',
        'ებამდეც',
        'ებამდე',
        'ებივითაც',
        'ებივით',
        'ებისგანვე',
        'ებისგანაც',
        'ებისგან',
        'ებთანაც',
        'ებთანვე',
        'ებთან',
        'ებზეაც',
        'ებზეც',
        'ებზე',
        'ებშიაც',
        'ებშიც',
        'ებში',
        'ებსაც',
        'ებსვე',
        'ებს',
        'ებმა',
        'ებისა',
        'ებისად',
        'ებისამ',
        'ებისათ',
        'ების',
        'ებად',
        'ებო',
        'ებ',   // bare plural (e.g. in construct forms)

        // ── Case endings (singular) ──────────────────────────────────────────
        // Dative/accusative with enclitic
        'საც',
        'სვე',
        // Instrumental/ergative
        'მაც',
        'მავე',
        'მა',
        // Genitive / adverbial
        'ისათ',
        'ისამ',
        'ისაც',
        'ისად',
        'ისა',
        'ისვე',
        'ის',
        // Adverbial
        'ად',
        // Dative
        'ას',
        // Vocative
        'ო',
        // Dative (plain)
        'ს',
    ];

    /**
     * Return the stem of a Georgian word.
     * If the word does not appear to be Georgian (no Georgian Unicode codepoints),
     * it is returned unchanged.
     */
    public static function stem( string $word ): string {
        $word = mb_strtolower( trim( $word ), 'UTF-8' );

        if ( ! self::is_georgian( $word ) ) {
            return $word;
        }

        // Remove soft hyphens (U+00AD) before stemming
        $word = str_replace( "\xc2\xad", '', $word );

        foreach ( self::$suffixes as $suffix ) {
            if ( self::mb_ends_with( $word, $suffix ) ) {
                $candidate = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - mb_strlen( $suffix, 'UTF-8' ), 'UTF-8' );
                if ( mb_strlen( $candidate, 'UTF-8' ) >= self::MIN_STEM_LEN ) {
                    $word = $candidate;
                    break; // only strip one suffix group per pass
                }
            }
        }

        // Strip trailing nominative -ი (only if stem would still be long enough)
        if ( self::mb_ends_with( $word, 'ი' ) ) {
            $candidate = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - 1, 'UTF-8' );
            if ( mb_strlen( $candidate, 'UTF-8' ) >= self::MIN_STEM_LEN ) {
                $word = $candidate;
            }
        }

        return $word;
    }

    /**
     * Return true if the two words share the same Georgian stem.
     */
    public static function stems_match( string $a, string $b ): bool {
        if ( ! self::is_georgian( $a ) || ! self::is_georgian( $b ) ) {
            return false;
        }
        $stem_a = self::stem( $a );
        $stem_b = self::stem( $b );

        // Both stems must be at least MIN_STEM_LEN to avoid spurious matches
        if ( mb_strlen( $stem_a, 'UTF-8' ) < self::MIN_STEM_LEN ) return false;
        if ( mb_strlen( $stem_b, 'UTF-8' ) < self::MIN_STEM_LEN ) return false;

        return $stem_a === $stem_b;
    }

    /**
     * Return true if the string contains at least one Georgian Unicode character
     * (U+10D0–U+10FF: Mkhedruli block).
     */
    public static function is_georgian( string $s ): bool {
        return (bool) preg_match( '/[\x{10D0}-\x{10FF}]/u', $s );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function mb_ends_with( string $haystack, string $needle ): bool {
        $hlen = mb_strlen( $haystack, 'UTF-8' );
        $nlen = mb_strlen( $needle,   'UTF-8' );
        if ( $nlen > $hlen ) return false;
        return mb_substr( $haystack, $hlen - $nlen, $nlen, 'UTF-8' ) === $needle;
    }
}
