<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Georgian Noun Declension Engine — complete morphological form generator.
 *
 * THREE DECLENSION CLASSES based on final character:
 *
 * CLASS 1 — Consonant-final (nominative ends in -ი)
 *   stem = remove -ი. All 7 cases use the stem.
 *
 * CLASS 2 — Truncating vowel-final (ends in -ა or -ე)
 *   base = full word (nom/erg/dat/adv/voc)
 *   trunc = drop final vowel (gen/ins use truncated stem)
 *   plural = trunc + ებ + consonant endings
 *
 * CLASS 3 — Non-truncating vowel-final (ends in -ო or -უ)
 *   base never truncates; instrumental = base+თი; gen=dat=base+ს
 *
 * POSTPOSITION RULES (from Georgian grammar):
 *   -ში / -ზე  : dative case, DROP the -ს
 *   -თან       : dative case, KEEP the -ს  (e.g. ასანასთან, სტრესსთან)
 *   -გან/-თვის/-კენ etc : genitive case + postposition
 *   -მდე       : adverbial, DROP the -დ
 *   -ვით       : nominative + ვით
 *   -დან       : instrumental, DROP the final -თ
 *
 * LOANWORDS follow the same rules by final Georgian character:
 *   ა-final (ასანა, ბრაჰმაჩარია, კარმა) → Class 2
 *   ი-final (სტრესი, ბრაჰმაჩარი)        → Class 1
 *   ო-final (სამადჰო, კარგო)             → Class 3
 */
class WPGT_Georgian_Stemmer {

    const MIN_STEM_LEN = 4;

    // ─── PUBLIC API ──────────────────────────────────────────────────────────

    public static function generate_all_forms( string $word ): array {
        $word = str_replace( [ '&shy;', '&#173;', "\xc2\xad" ], '', $word );
        $word = mb_strtolower( trim( $word ), 'UTF-8' );

        if ( ! self::is_georgian( $word ) || mb_strlen( $word, 'UTF-8' ) < 2 ) {
            return [ $word ];
        }

        $last = self::mb_last( $word );

        if ( $last === 'ი' ) {
            $forms = self::class1( $word );
        } elseif ( $last === 'ა' || $last === 'ე' ) {
            $forms = self::class2( $word );
        } elseif ( $last === 'ო' || $last === 'უ' ) {
            $forms = self::class3( $word );
        } else {
            // Ends in consonant — treat whole word as bare stem
            $forms = self::class1_stem( $word );
        }

        $forms = array_filter( $forms, fn( $f ) =>
            mb_strlen( $f, 'UTF-8' ) >= self::MIN_STEM_LEN
        );

        return array_values( array_unique( $forms ) );
    }

    // ─── CLASS 1: consonant-final (word ends in -ი) ──────────────────────────

    private static function class1( string $word ): array {
        $stem = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - 1, 'UTF-8' );
        $f = [];
        self::c1_cases( $f, $stem, $word );
        self::plural( $f, $stem );

        $sy = self::syncopate( $stem );
        if ( $sy && $sy !== $stem ) {
            self::c1_cases( $f, $sy, $sy . 'ი' );
            self::plural( $f, $sy );
        }
        return $f;
    }

    private static function class1_stem( string $stem ): array {
        $f = [];
        self::c1_cases( $f, $stem, $stem . 'ი' );
        self::plural( $f, $stem );
        $sy = self::syncopate( $stem );
        if ( $sy && $sy !== $stem ) {
            self::c1_cases( $f, $sy, $sy . 'ი' );
            self::plural( $f, $sy );
        }
        return $f;
    }

    private static function c1_cases( array &$f, string $s, string $nom ): void {
        // Seven cases
        $erg = $s . 'მა';
        $dat = $s . 'ს';
        $gen = $s . 'ის';
        $ins = $s . 'ით';
        $adv = $s . 'ად';
        $voc = $s . 'ო';
        self::am( $f, [ $nom, $erg, $dat, $gen, $ins, $adv, $voc ] );

        // Postpositions
        self::am( $f, [
            $s  . 'ში',             // -ში: dat drop -ს
            $s  . 'ზე',             // -ზე: dat drop -ს
            $dat . 'თან',           // -თან: dat KEEP -ს → stem+ს+თან
            $dat . 'ახლოს',        // near
            $dat . 'თვის',          // -სთვის: dative+სთვის e.g. ბჰაკტსთვის
            $s  . 'ამდე',           // -მდე: adv(stem+ად) drop -დ → stem+ა+მდე
            $nom . 'ვით',           // -ვით: nominative + ვით
            $gen . 'ავით',          // -ვით alt: gen+ა+ვით
            $s  . 'იდან',           // -დან: ins(stem+ით) drop -თ → stem+ი+დან
        ] );

        // Genitive-based postpositions
        foreach ( [ 'გან','თვის','კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ','მიუხედავად','მაგივრად' ] as $p ) {
            self::a( $f, $gen . $p );
        }

        // Enclitics -ც (also) on common forms
        self::am( $f, [
            $nom . 'ც',             // nom+ც
            $dat . 'ც',             // dat+ც
            $dat . 'აც',            // dat+ა+ც (standard euphonic form)
            $gen . 'ც',
            $s   . 'შიც',
            $s   . 'ზეც',
            $dat . 'თანაც',
        ] );

        // Enclitics -ვე (same/very)
        self::am( $f, [
            $nom . 'ვე',
            $s   . 'შივე',
            $dat . 'ვე',
            $gen . 'ვე',
        ] );
    }

    // ─── CLASS 2: truncating vowel-final (ends in -ა or -ე) ─────────────────

    private static function class2( string $word ): array {
        $base  = $word;
        $trunc = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - 1, 'UTF-8' );
        $f = [];

        self::c2_cases( $f, $base, $trunc );
        self::plural( $f, $trunc );

        $sy = self::syncopate( $trunc );
        if ( $sy && $sy !== $trunc ) {
            // Syncopated forms for genitive/instrumental and their derived postpositions
            $sy_gen = $sy . 'ის';
            $sy_ins = $sy . 'ით';
            self::am( $f, [ $sy_gen, $sy_ins, $sy . 'იდან' ] );
            foreach ( [ 'გან','თვის','კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ' ] as $p ) {
                self::a( $f, $sy_gen . $p );
            }
            self::plural( $f, $sy );
        }
        return $f;
    }

    private static function c2_cases( array &$f, string $base, string $trunc ): void {
        // Seven cases
        $erg = $base  . 'მ';
        $dat = $base  . 'ს';
        $gen = $trunc . 'ის';   // TRUNCATED
        $ins = $trunc . 'ით';   // TRUNCATED
        $adv = $base  . 'დ';
        $voc = $base;
        self::am( $f, [ $base, $erg, $dat, $gen, $ins, $adv, $voc ] );

        // Postpositions
        self::am( $f, [
            $base . 'ში',           // -ში: dat drop -ს → base+ში
            $base . 'ზე',           // -ზე: dat drop -ს → base+ზე
            $dat  . 'თან',          // -თან: KEEP -ს → base+ს+თან ✓
            $dat  . 'ახლოს',
            $dat  . 'თვის',         // -სთვის: dative+სთვის e.g. ბჰოგასთვის
            $base . 'მდე',          // -მდე: adv(base+დ) drop -დ → base+მდე
            $base . 'ვით',          // -ვით: nom(=base)+ვით
            $gen  . 'ავით',         // -ვით alt: gen+ა+ვით
            $trunc . 'იდან',        // -დან: ins drop -თ → trunc+ი+დან
            $base  . 'დან',         // -დან loanword variant: keep base vowel
        ] );

        foreach ( [ 'გან','თვის','კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ','მიუხედავად','მაგივრად' ] as $p ) {
            self::a( $f, $gen . $p );
        }

        // Enclitics -ც
        self::am( $f, [
            $base . 'ც',
            $dat  . 'ც',
            $dat  . 'აც',
            $gen  . 'ც',
            $base . 'შიც',
            $base . 'ზეც',
            $dat  . 'თანაც',
        ] );

        // Enclitics -ვე
        self::am( $f, [
            $base . 'ვე',
            $base . 'შივე',
            $dat  . 'ვე',
            $gen  . 'ვე',
        ] );
    }

    // ─── CLASS 3: non-truncating vowel-final (ends in -ო or -უ) ─────────────

    private static function class3( string $word ): array {
        $base = $word;
        $last = self::mb_last( $word );
        $f    = [];

        self::c3_cases( $f, $base );
        self::plural( $f, $base );

        // For -ო words: also generate consonant-declension forms (ო disappears)
        if ( $last === 'ო' ) {
            $stem_bare = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - 1, 'UTF-8' );
            if ( mb_strlen( $stem_bare, 'UTF-8' ) >= self::MIN_STEM_LEN ) {
                self::c1_cases( $f, $stem_bare, $stem_bare . 'ი' );
                self::plural( $f, $stem_bare );
            }
        }

        return $f;
    }

    private static function c3_cases( array &$f, string $base ): void {
        // Seven cases — genitive = dative = base+ს; instrumental = base+თი
        $erg = $base . 'მ';
        $dat = $base . 'ს';   // dative
        $gen = $base . 'ს';   // genitive = same as dative
        $ins = $base . 'თი';  // +თი (not +ით)
        $adv = $base . 'დ';
        $voc = $base;
        self::am( $f, [ $base, $erg, $dat, $ins, $adv, $voc ] );

        // Postpositions
        self::am( $f, [
            $base . 'ში',           // dat drop -ს → base+ში
            $base . 'ზე',           // dat drop -ს → base+ზე
            $dat  . 'თან',          // KEEP -ს: base+ს+თან
            $dat  . 'ახლოს',
            $base . 'მდე',          // adv drop -დ → base+მდე
            $base . 'ვით',          // nom(=base)+ვით
            $gen  . 'ავით',         // gen+ა+ვით
            $base . 'დან',          // -დან: simpler base+დან form
            $base . 'თიდან',        // -დან: from instrumental base+თი
        ] );

        foreach ( [ 'გან','თვის','კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ','მიუხედავად','მაგივრად' ] as $p ) {
            self::a( $f, $gen . $p );
        }

        // Enclitics
        self::am( $f, [
            $base . 'ც', $dat . 'ც', $dat . 'აც', $base . 'შიც',
            $base . 'ზეც', $dat . 'თანაც', $base . 'ვე', $base . 'შივე', $dat . 'ვე',
        ] );
    }

    // ─── PLURAL (shared) ─────────────────────────────────────────────────────

    /**
     * Append plural forms from a given stem.
     * The stem passed in is:
     *   Class 1 → consonant stem (no -ი)
     *   Class 2 → truncated stem (no -ა/-ე)
     *   Class 3 → full base word
     * In all cases: plural base = stem + ებ, then decline as consonant-final.
     */
    private static function plural( array &$f, string $stem ): void {
        $pl  = $stem . 'ებ';        // plural base (ends in consonant ბ)
        $nom = $pl   . 'ი';         // plural nominative
        self::c1_cases( $f, $pl, $nom );
    }

    // ─── SYNCOPE ─────────────────────────────────────────────────────────────

    /**
     * Remove the last syncopable vowel (ა, ე, or ო) from a stem.
     * The vowel must NOT be the final character and must have characters on both sides.
     * Returns null if no valid syncope exists.
     *
     * Examples: წყალ→წყლ  მგელ→მგლ  ასან→ასნ  პეპელ→პეპლ
     */
    /**
     * Georgian vowels (Mkhedruli) — used for consonant-run detection.
     */
    private static array $VOWELS = [ 'ა', 'ე', 'ი', 'ო', 'უ' ];

    public static function syncopate( string $stem ): ?string {
        $chars = preg_split( '//u', $stem, -1, PREG_SPLIT_NO_EMPTY );
        $len   = count( $chars );
        if ( $len < 3 ) return null;

        for ( $i = $len - 2; $i >= 1; $i-- ) {
            if ( in_array( $chars[ $i ], [ 'ა', 'ე', 'ო' ], true ) ) {
                $candidate_chars = array_merge(
                    array_slice( $chars, 0, $i ),
                    array_slice( $chars, $i + 1 )
                );
                $candidate = implode( '', $candidate_chars );

                if ( mb_strlen( $candidate, 'UTF-8' ) < self::MIN_STEM_LEN ) continue;

                // Reject if the candidate contains 3+ consecutive consonants.
                // Native Georgian syncopation never produces such clusters (e.g. წყლ
                // is fine — it's 3 chars but Georgian მ/ნ/ლ/რ are sonorants).
                // Sanskrit loanwords like ბჰაკტ → ბჰკტ would have 4 consonants in a
                // row, which is not a real Georgian stem — guard against that.
                if ( self::max_consonant_run( $candidate_chars ) >= 4 ) continue;

                return $candidate;
            }
        }
        return null;
    }

    /**
     * Return the length of the longest consecutive consonant run in a char array.
     */
    private static function max_consonant_run( array $chars ): int {
        $max = 0; $run = 0;
        foreach ( $chars as $c ) {
            if ( ! in_array( $c, self::$VOWELS, true ) ) {
                $run++;
                if ( $run > $max ) $max = $run;
            } else {
                $run = 0;
            }
        }
        return $max;
    }


    // ═══════════════════════════════════════════════════════════════════════
    // LOANWORD DECLENSION ENGINE
    // Rules: no syncope ever; truncation only for -ა stems in Gen/Ins;
    //        -ე/-ო/-უ stems never truncate; postpositions per provided spec.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Public entry point — mirrors generate_all_forms() but for loanwords.
     * Call this instead of generate_all_forms() when _wpgt_is_loanword = 1.
     */
    public static function generate_loanword_forms( string $word ): array {
        $word = str_replace( [ '&shy;', '&#173;', "\xc2\xad" ], '', $word );
        $word = mb_strtolower( trim( $word ), 'UTF-8' );

        if ( ! self::is_georgian( $word ) || mb_strlen( $word, 'UTF-8' ) < 2 ) {
            return [ $word ];
        }

        $last = self::mb_last( $word );

        if ( $last === 'ი' ) {
            $forms = self::lw_consonant( $word );
        } elseif ( $last === 'ა' ) {
            $forms = self::lw_a_stem( $word );
        } elseif ( $last === 'ე' ) {
            $forms = self::lw_eou_stem( $word, 'ე' );
        } elseif ( $last === 'ო' ) {
            $forms = self::lw_eou_stem( $word, 'ო' );
        } elseif ( $last === 'უ' ) {
            $forms = self::lw_eou_stem( $word, 'უ' );
        } else {
            // Already ends in consonant — treat as consonant stem directly
            $forms = self::lw_consonant_stem( $word );
        }

        $forms = array_filter( $forms, fn( $f ) =>
            mb_strlen( $f, 'UTF-8' ) >= self::MIN_STEM_LEN
        );

        return array_values( array_unique( $forms ) );
    }

    // ── Consonant-ending loanword (nom ends in -ი) ───────────────────────
    // Core stem = word minus final -ი.  NO syncope ever.

    private static function lw_consonant( string $word ): array {
        $stem = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - 1, 'UTF-8' );
        $f    = [];
        self::lw_c_cases( $f, $stem, $word );
        self::lw_plural_consonant( $f, $stem );   // plural stem = stem+ებ
        return $f;
    }

    private static function lw_consonant_stem( string $stem ): array {
        $f = [];
        self::lw_c_cases( $f, $stem, $stem . 'ი' );
        self::lw_plural_consonant( $f, $stem );
        return $f;
    }

    /**
     * Seven cases + postpositions for a consonant-stem loanword.
     *
     * Cases (from spec §4):
     *   Nom: stem+ი   Erg: stem+მა  Dat: stem+ს
     *   Gen: stem+ის  Ins: stem+ით  Adv: stem+ად  Voc: stem+ო
     *
     * Postpositions (from spec §5):
     *   -ში/-ზე  → stem+ში, stem+ზე
     *   -თვის    → gen+თვის  (stem+ის+თვის)
     *   -გან     → gen+გან   (stem+ის+გან)
     *   -თან     → stem+თან  (attach to bare stem, spec §5)
     *   -ვით     → nom+ვით   (stem+ი+ვით)
     */
    private static function lw_c_cases( array &$f, string $s, string $nom ): void {
        $erg = $s . 'მა';
        $dat = $s . 'ს';
        $gen = $s . 'ის';
        $ins = $s . 'ით';
        $adv = $s . 'ად';
        $voc = $s . 'ო';
        self::am( $f, [ $nom, $erg, $dat, $gen, $ins, $adv, $voc ] );

        // Core postpositions
        self::am( $f, [
            $s   . 'ში',            // -ში: bare stem
            $s   . 'ზე',            // -ზე: bare stem
            $s   . 'თან',           // -თან: bare stem (spec: consonant stem)
            $dat . 'თვის',          // -სთვის: dat+თვის (also valid)
            $gen . 'თვის',          // -ისთვის: gen+თვის
            $gen . 'გან',           // -ისგან: gen+გან
            $nom . 'ვით',           // -ვით: nom+ვით
            $s   . 'იდან',          // -იდან: ins drop -თ
            $s   . 'ამდე',          // -მდე
            $dat . 'ახლოს',
        ] );

        // Full genitive postposition set
        foreach ( [ 'კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ','მიუხედავად','მაგივრად' ] as $p ) {
            self::a( $f, $gen . $p );
        }

        // Enclitics -ც / -ვე
        self::am( $f, [
            $nom . 'ც', $dat . 'ც', $dat . 'აც', $gen . 'ც',
            $s   . 'შიც', $s . 'ზეც', $dat . 'თანაც',
            $nom . 'ვე', $s . 'შივე', $dat . 'ვე', $gen . 'ვე',
        ] );
    }

    // ── -ა stem loanword ─────────────────────────────────────────────────
    // e.g. კამერა, ასანა, კარმა
    // base = full word; trunc = drop -ა (for Gen/Ins only)

    private static function lw_a_stem( string $word ): array {
        $base  = $word;
        $trunc = mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - 1, 'UTF-8' );
        $f     = [];
        self::lw_a_cases( $f, $base, $trunc );
        self::lw_plural_consonant( $f, $trunc );  // plural: trunc+ებ
        return $f;
    }

    /**
     * Seven cases + postpositions for an -ა stem loanword.
     *
     * Cases (spec §4):
     *   Nom: base      Erg: base+მ   Dat: base+ს
     *   Gen: trunc+ის  Ins: trunc+ით Adv: base+დ  Voc: base
     *
     * Postpositions (spec §5):
     *   -ში/-ზე  → base+ში, base+ზე   (full base, not truncated)
     *   -თვის    → gen+თვის = trunc+ის+თვის
     *   -გან     → gen+გან  = trunc+ის+გან
     *   -თან     → dat+თან  = base+ს+თან   (spec: Vowel → dative+სთან)
     *   -ვით     → dat+ა+ვით = base+ს+ა+ვით (spec: Vowel → dative+"ა"+ვით)
     */
    private static function lw_a_cases( array &$f, string $base, string $trunc ): void {
        $erg = $base  . 'მ';
        $dat = $base  . 'ს';
        $gen = $trunc . 'ის';   // TRUNCATED
        $ins = $trunc . 'ით';   // TRUNCATED
        $adv = $base  . 'დ';
        $voc = $base;
        self::am( $f, [ $base, $erg, $dat, $gen, $ins, $adv, $voc ] );

        self::am( $f, [
            $base  . 'ში',              // -ში: base+ში
            $base  . 'ზე',              // -ზე: base+ზე
            $dat   . 'თვის',            // -სთვის: dative+თვის (base+ს+თვის)
            $gen   . 'თვის',            // -ისთვის: gen+თვის (trunc+ის+თვის)
            $gen   . 'გან',             // -ისგან:  gen+გან  (trunc+ის+გან)
            $dat   . 'თან',             // -თან: dative+თან (base+ს+თან)
            $dat   . 'ახლოს',
            $dat   . 'ა' . 'ვით',      // -ვით: dative+ა+ვით (base+ს+ა+ვით)
            $trunc . 'იდან',            // -იდან: trunc+იდან
            $base  . 'მდე',             // -მდე
        ] );

        foreach ( [ 'კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ','მიუხედავად','მაგივრად' ] as $p ) {
            self::a( $f, $gen . $p );
        }

        self::am( $f, [
            $base  . 'ც', $dat . 'ც', $dat . 'აც', $gen . 'ც',
            $base  . 'შიც', $base . 'ზეც', $dat . 'თანაც',
            $base  . 'ვე', $base . 'შივე', $dat . 'ვე', $gen . 'ვე',
        ] );
    }

    // ── -ე / -ო / -უ stem loanword ───────────────────────────────────────
    // e.g. კაფე, რადიო, მენიუ  — NO truncation anywhere
    // Gen = base+სი  Ins = base+თი  (not truncated)

    private static function lw_eou_stem( string $word, string $ending ): array {
        $base = $word;
        $f    = [];
        self::lw_eou_cases( $f, $base );
        self::lw_plural_consonant( $f, $base );   // plural: base+ებ (e.g. რადიოები)
        return $f;
    }

    /**
     * Seven cases + postpositions for -ე/-ო/-უ stem loanwords.
     *
     * Cases (spec §4 — no truncation):
     *   Nom: base   Erg: base+მ  Dat: base+ს
     *   Gen: base+სი            (NOTE: -სი not -ის, no truncation)
     *   Ins: base+თი            (NOTE: -თი not -ით, no truncation)
     *   Adv: base+დ   Voc: base
     *
     * Postpositions (spec §5):
     *   -ში/-ზე  → base+ში, base+ზე
     *   -თვის    → base+ს+თვის  (drop final ი from Gen base+სი → base+ს, then +თვის)
     *   -გან     → base+ს+გან   (same: drop ი from სი)
     *   -თან     → base+ს+თან   (dative+თან)
     *   -ვით     → base+ს+ა+ვით (dative+ა+ვით)
     */
    private static function lw_eou_cases( array &$f, string $base ): void {
        $erg    = $base . 'მ';
        $dat    = $base . 'ს';
        $gen    = $base . 'სი';     // -სი (not -ის)
        $gen_pp = $base . 'ს';      // for postpositions: drop final ი from სი
        $ins    = $base . 'თი';     // -თი (not -ით)
        $adv    = $base . 'დ';
        $voc    = $base;
        self::am( $f, [ $base, $erg, $dat, $gen, $ins, $adv, $voc ] );

        self::am( $f, [
            $base   . 'ში',             // -ში
            $base   . 'ზე',             // -ზე
            $dat    . 'თვის',           // -სთვის: dat+თვის (= gen_pp+თვის)
            $dat    . 'გან',            // -სგან:  dat+გან
            $dat    . 'თან',            // -თან:   dative+თან
            $dat    . 'ახლოს',
            $dat    . 'ა' . 'ვით',     // -ვით:   dative+ა+ვით (base+ს+ა+ვით)
            $base   . 'დან',            // -დან:   base+დან
            $base   . 'მდე',            // -მდე
        ] );

        foreach ( [ 'კენ','გარეშე','გამო','შესახებ','შორის','წინ','შემდეგ','გარდა','მიერ','მიუხედავად','მაგივრად' ] as $p ) {
            self::a( $f, $gen_pp . $p );  // attach to gen_pp (without final ი)
        }

        self::am( $f, [
            $base  . 'ც', $dat . 'ც', $dat . 'აც', $gen . 'ც',
            $base  . 'შიც', $base . 'ზეც', $dat . 'თანაც',
            $base  . 'ვე', $base . 'შივე', $dat . 'ვე', $gen . 'ვე',
        ] );
    }

    // ── Loanword plural (shared) ─────────────────────────────────────────
    // Plural stem = passed_stem + ებ, then declined as consonant-final.
    // NO syncope on the plural stem.

    private static function lw_plural_consonant( array &$f, string $stem ): void {
        $pl  = $stem . 'ებ';
        $nom = $pl   . 'ი';
        // Use the same lw_c_cases — the plural base is already a consonant stem
        self::lw_c_cases( $f, $pl, $nom );
    }

    // ─── LEGACY API ──────────────────────────────────────────────────────────

    public static function stem( string $word ): string {
        return mb_strtolower( str_replace( "\xc2\xad", '', trim( $word ) ), 'UTF-8' );
    }

    public static function stems_match( string $a, string $b ): bool {
        return self::stem( $a ) === self::stem( $b );
    }

    public static function is_georgian( string $s ): bool {
        return (bool) preg_match( '/[\x{10D0}-\x{10FF}]/u', $s );
    }

    public static function get_suffixes(): array { return []; }

    // ─── INTERNAL HELPERS ────────────────────────────────────────────────────

    private static function a( array &$f, string $s ): void {
        if ( mb_strlen( $s, 'UTF-8' ) >= self::MIN_STEM_LEN ) $f[] = $s;
    }

    private static function am( array &$f, array $items ): void {
        foreach ( $items as $s ) self::a( $f, $s );
    }

    private static function mb_last( string $s ): string {
        $l = mb_strlen( $s, 'UTF-8' );
        return $l ? mb_substr( $s, $l - 1, 1, 'UTF-8' ) : '';
    }
}
