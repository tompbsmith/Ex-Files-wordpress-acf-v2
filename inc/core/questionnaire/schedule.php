<?php
/**
 * The "ASSETS" sheet of the asset schedule, laid out like the firm's Composite
 * Schedule of Assets template: grand totals at the top, then property, bank
 * accounts, investments, business interests, chattels, other assets, liabilities,
 * the non-pension total, pensions, the total including pensions and annual incomes.
 *
 * Each section has Client, Ex and Joint columns, each split into "Client's case"
 * and "Ex's case". The client's answers go in the Client's case columns (their
 * own assets under Client, what they say about their ex's under Ex, joint ones
 * under Joint). The Ex's case columns are left for the solicitor to fill in, and
 * every total, cost of sale and net equity is a formula so it updates as she does.
 *
 * Liabilities and deductions are entered as negative numbers, as in the template.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the ASSETS sheet. Rows are 1-based; columns A–M are 0–12.
 */
class Exf_Asset_Schedule {

	const A = 0;
	const B = 1;
	const D = 3;
	const E = 4;
	const F = 5;
	const H = 7;
	const K = 10;
	const L = 11;
	const M = 12;

	const MONEY = '"£"#,##0_);[Red]\("£"#,##0\)';
	const GREY  = 'F2F2F2';
	const BLUE  = 'DCE6F2';
	const LILAC = 'E5E0EC';
	const CREAM = 'FFFFCC';
	const PEACH = 'FDE9D9';
	const EST   = 'FBEFD5';
	const DK    = 'F3DCD6';

	/**
	 * Where each party's "Client's case" column is (the Ex's case is the next one).
	 *
	 * @var array
	 */
	const COLS = array(
		'client' => self::E,
		'ex'     => self::H,
		'joint'  => self::K,
	);

	/**
	 * Cells: row => col => [ v, f, d (style definition) ].
	 *
	 * @var array
	 */
	private $g = array();

	/**
	 * Row heights.
	 *
	 * @var array
	 */
	private $h = array();

	/**
	 * Merged ranges.
	 *
	 * @var array
	 */
	private $merges = array();

	/**
	 * Next free row.
	 *
	 * @var int
	 */
	private $r = 17;

	/**
	 * Questionnaire answers.
	 *
	 * @var array
	 */
	private $d;

	/**
	 * Questionnaire ID.
	 *
	 * @var int
	 */
	private $q;

	/**
	 * Set up.
	 *
	 * @param int $q Questionnaire.
	 */
	public function __construct( $q ) {
		$this->q = (int) $q;
		$this->d = exf_q_data( $q );
	}

	/* ---------------------------------------------------------------------
	 * Cells and styles.
	 * ------------------------------------------------------------------ */

	/**
	 * Column letter.
	 *
	 * @param int $c Column.
	 */
	private static function c( $c ) {
		return Exf_Xlsx::col( $c );
	}

	/**
	 * Whether a column holds amounts.
	 *
	 * @param int $c Column.
	 */
	private static function is_money( $c ) {
		return in_array( $c, array( 4, 5, 7, 8, 10, 11 ), true );
	}

	/**
	 * The plain style for a cell in the body of the schedule: the grey, boxed
	 * amount columns, the boxed label columns A–D and the notes column.
	 *
	 * @param int $c Column.
	 */
	private static function base( $c ) {
		$d = array(
			'font' => array(
				'name' => 'Arial',
				'sz'   => 10,
			),
			'v'    => 'center',
		);
		if ( self::is_money( $c ) ) {
			$d['fmt']    = self::MONEY;
			$d['fill']   = self::GREY;
			$d['h']      = 'right';
			$d['border'] = in_array( $c, array( 4, 7, 10 ), true ) ? array( 'l' => 'thin' ) : array(
				'l' => 'thin',
				'r' => 'thin',
			);
		} elseif ( self::A === $c ) {
			$d['border'] = array( 'l' => 'thin' );
			$d['wrap']   = true;
		} elseif ( self::D === $c ) {
			$d['border'] = array( 'r' => 'thin' );
		} elseif ( self::M === $c ) {
			$d['wrap'] = true;
			$d['v']    = 'top';
		}
		return $d;
	}

	/**
	 * Merge style changes into a definition.
	 *
	 * @param array $d   Definition.
	 * @param array $chg Changes (font and border are merged, not replaced).
	 */
	private static function with( array $d, array $chg ) {
		foreach ( $chg as $k => $v ) {
			$d[ $k ] = ( in_array( $k, array( 'font', 'border' ), true ) && isset( $d[ $k ] ) ) ? array_merge( $d[ $k ], $v ) : $v;
		}
		return $d;
	}

	/**
	 * Style a cell (keeps its value).
	 *
	 * @param int   $r   Row.
	 * @param int   $c   Column.
	 * @param array $chg Style changes.
	 */
	private function style( $r, $c, array $chg ) {
		$cell                = $this->g[ $r ][ $c ] ?? array( 'd' => self::base( $c ) );
		$cell['d']           = self::with( $cell['d'], $chg );
		$this->g[ $r ][ $c ] = $cell;
	}

	/**
	 * Set a cell's value (or formula, if it starts with "=").
	 *
	 * @param int        $r   Row.
	 * @param int        $c   Column.
	 * @param mixed      $v   Value.
	 * @param array|null $chg Style changes.
	 */
	private function put( $r, $c, $v, array $chg = array() ) {
		$cell = $this->g[ $r ][ $c ] ?? array( 'd' => self::base( $c ) );
		unset( $cell['v'], $cell['f'] );
		if ( is_string( $v ) && '' !== $v && '=' === $v[0] ) {
			$cell['f'] = substr( $v, 1 );
		} elseif ( null !== $v ) {
			$cell['v'] = $v;
		}
		$cell['d']           = self::with( $cell['d'], $chg );
		$this->g[ $r ][ $c ] = $cell;
	}

	/**
	 * Give a whole row (A–L, and M) its plain body style, then any changes.
	 *
	 * @param int   $r     Row.
	 * @param array $chg   Changes for every cell.
	 * @param bool  $gaps  Style the gap columns G and J too (for shaded bands).
	 */
	private function row( $r, array $chg = array(), $gaps = false ) {
		foreach ( range( 0, 12 ) as $c ) {
			if ( ! $gaps && in_array( $c, array( 6, 9 ), true ) ) {
				continue;
			}
			if ( self::M === $c && $chg ) {
				$this->style( $r, $c, array() );
				continue;
			}
			$this->style( $r, $c, $chg );
		}
	}

	/**
	 * Note in column M, made taller to fit if it's long.
	 *
	 * @param int    $r    Row.
	 * @param string $note Note.
	 * @param bool   $flag Highlight it (something to check).
	 */
	private function note( $r, $note, $flag = false ) {
		$note = trim( (string) $note );
		if ( '' === $note ) {
			return;
		}
		$old = isset( $this->g[ $r ][ self::M ]['v'] ) ? $this->g[ $r ][ self::M ]['v'] . ' ' : '';
		$this->put( $r, self::M, $old . $note, $flag ? array( 'fill' => self::DK ) : array() );
		$this->fit( $r, $old . $note, 40 );
	}

	/**
	 * Make a row tall enough for wrapped text.
	 *
	 * @param int    $r     Row.
	 * @param string $text  Text.
	 * @param int    $width Characters per line.
	 */
	private function fit( $r, $text, $width ) {
		$lines = 0;
		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			$lines += max( 1, (int) ceil( mb_strlen( $line ) / $width ) );
		}
		if ( $lines > 1 ) {
			$this->h[ $r ] = max( $this->h[ $r ] ?? 0, 13 * $lines );
		}
	}

	/* ---------------------------------------------------------------------
	 * Answers.
	 * ------------------------------------------------------------------ */

	/**
	 * Whether a section's gate question was answered Yes.
	 *
	 * @param string $section Section.
	 */
	private function yes( $section ) {
		return 'Yes' === ( $this->d[ $section ]['_gate'] ?? '' );
	}

	/**
	 * Items in a repeater, if the section applies.
	 *
	 * @param string $section Section.
	 * @param string $key     Repeater.
	 */
	private function items( $section, $key ) {
		return $this->yes( $section ) ? array_values( array_filter( (array) ( $this->d[ $section ][ $key ] ?? array() ), 'is_array' ) ) : array();
	}

	/**
	 * Which column an owner answer belongs in, and a note if it needs checking.
	 *
	 * @param string $owner Answer.
	 * @return array [ 'client'|'ex'|'joint', note, flag ]
	 */
	private static function party( $owner ) {
		switch ( (string) $owner ) {
			case 'Me':
				return array( 'client', '', false );
			case 'My spouse/partner':
				return array( 'ex', '', false );
			case 'Both of us':
			case 'Me and my spouse/partner':
				return array( 'joint', '', false );
			case 'Me and somebody else':
				return array( 'client', __( 'Owned with somebody else.', 'exfiles' ), true );
			case 'My spouse/partner and somebody else':
				return array( 'ex', __( 'With somebody else.', 'exfiles' ), true );
			case 'Someone else':
				return array( 'joint', __( 'Client says someone else owns this: check.', 'exfiles' ), true );
			default:
				return array( 'joint', __( "Client doesn't know who owns this: check.", 'exfiles' ), true );
		}
	}

	/**
	 * A money answer as a cell value and style changes.
	 *
	 * @param mixed $m      Money answer.
	 * @param bool  $negate Enter it as a negative (deductions and debts).
	 * @param float $times  Multiply by (e.g. to make it annual).
	 * @return array [ value or null, style changes ]
	 */
	private static function amount( $m, $negate = false, $times = 1.0 ) {
		$m = is_array( $m ) ? $m : array();
		$c = $m['certainty'] ?? '';
		if ( "Don't know" === $c ) {
			return array( "Don't know", array( 'fill' => self::DK ) );
		}
		if ( 'None' === $c ) {
			return array( 0, array() );
		}
		if ( ! isset( $m['amount'] ) || '' === $m['amount'] || ! is_numeric( $m['amount'] ) ) {
			return array( null, array() );
		}
		$v = round( (float) $m['amount'] * $times, 2 );
		return array( $negate ? -$v : $v, 'Estimated current' === $c ? array( 'fill' => self::EST ) : array() );
	}

	/**
	 * What to say about where a figure came from: "Value: estimate, 1 Mar 2026 (Zoopla)."
	 *
	 * @param string $label Label.
	 * @param mixed  $m     Money answer.
	 */
	private static function source( $label, $m ) {
		$m = is_array( $m ) ? $m : array();
		$c = $m['certainty'] ?? '';
		if ( ! $c || 'None' === $c ) {
			return '';
		}
		$bits = array( 'Estimated current' === $c ? __( 'estimate', 'exfiles' ) : ( "Don't know" === $c ? __( "doesn't know", 'exfiles' ) : __( 'known', 'exfiles' ) ) );
		if ( ! empty( $m['as_at'] ) ) {
			$bits[] = exf_q_date( $m['as_at'] );
		}
		$text = $label . ': ' . implode( ', ', $bits );
		return $text . ( empty( $m['basis'] ) ? '' : ' (' . $m['basis'] . ')' ) . '.';
	}

	/**
	 * Notes joined up.
	 *
	 * @param array $bits Notes.
	 */
	private static function join( array $bits ) {
		return trim( implode( ' ', array_filter( array_map( 'trim', array_map( 'strval', $bits ) ) ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Sections.
	 * ------------------------------------------------------------------ */

	/**
	 * A section heading (shaded A–D), like "BANK ACCOUNTS / CASH".
	 *
	 * @param string $title Title.
	 * @param string $note  Section notes from the client.
	 */
	private function heading( $title, $note = '' ) {
		$r = $this->r++;
		$this->row( $r );
		foreach ( range( 0, 3 ) as $c ) {
			$this->style(
				$r,
				$c,
				array(
					'fill'   => self::BLUE,
					'border' => array( 't' => 'thin' ),
				)
			);
		}
		$this->put(
			$r,
			self::A,
			$title,
			array(
				'font' => array(
					'b'  => true,
					'sz' => 16,
				),
				'wrap' => false,
			)
		);
		$this->h[ $r ] = 20;
		$this->note( $r, $note );
		return $r;
	}

	/**
	 * A blank body row.
	 */
	private function blank() {
		$r = $this->r++;
		$this->row( $r );
		return $r;
	}

	/**
	 * A total row: the section's sums in every amount column.
	 *
	 * @param int    $from  First row of the section.
	 * @param string $label Label.
	 * @param array  $refs  Instead of a SUM over the section: column => cells to add up.
	 */
	private function total( $from, $label, array $refs = array() ) {
		$r = $this->r++;
		$this->row( $r, array( 'border' => array( 'b' => 'thin' ) ) );
		$this->put(
			$r,
			self::A,
			$label,
			array(
				'font' => array(
					'b'  => true,
					'sz' => 12,
				),
			)
		);
		foreach ( array( 4, 5, 7, 8, 10, 11 ) as $c ) {
			$col = self::c( $c );
			if ( $refs ) {
				$f = isset( $refs[ $c ] ) && $refs[ $c ] ? '=' . implode( '+', $refs[ $c ] ) : 0;
			} else {
				$f = '=SUM(' . $col . $from . ':' . $col . ( $r - 1 ) . ')';
			}
			$this->put(
				$r,
				$c,
				$f,
				array(
					'font'   => array(
						'b'  => true,
						'sz' => 12,
					),
					'border' => array(
						't' => 'thin',
						'b' => 'thin',
					),
				)
			);
		}
		$this->h[ $r ] = 16;
		return $r;
	}

	/**
	 * One property: gross value down to the value of the interest.
	 *
	 * @param string     $party  client, ex or joint.
	 * @param array|null $p      The property, or null for an empty block to fill in.
	 * @param string     $label  What goes in column B (e.g. FAMILY HOME, Joint names).
	 * @return int Row of the "Value of interest".
	 */
	private function property( $party, $p, $label ) {
		$col  = self::COLS[ $party ];
		$p    = is_array( $p ) ? $p : array();
		$addr = trim( (string) ( $p['address'] ?? '' ) );
		$name = $addr ? trim( preg_split( '/,|\R/', $addr )[0] ) : __( 'PROPERTY NAME', 'exfiles' );

		$r = $this->r++;
		$this->row( $r );
		$this->put(
			$r,
			self::A,
			$name,
			array(
				'font' => array(
					'b'  => true,
					'sz' => 12,
				),
			)
		);
		$this->put(
			$r,
			self::B,
			$label,
			array(
				'font' => array(
					'b'  => true,
					'sz' => 11,
				),
				'h'    => 'center',
			)
		);
		$this->h[ $r ] = 16;
		$this->fit( $r, $name, 22 );
		if ( $p ) {
			list( , $who, $flag ) = self::party( $p['owner'] ?? '' );
			$this->note(
				$r,
				self::join(
					array(
						preg_replace( '/\s*\R\s*/', ', ', $addr ) . '.',
						empty( $p['title'] ) ? '' : sprintf( /* translators: %s: names */ __( 'Legal title: %s.', 'exfiles' ), $p['title'] ),
						$who,
					)
				),
				$flag
			);
		}

		$lines = array(
			array( __( 'Gross Value', 'exfiles' ), 'value', false ),
			array( __( 'Less Mortgage', 'exfiles' ), 'mortgage', true ),
			array( __( 'Less ERP', 'exfiles' ), 'erc', true ),
			array( __( 'Less Costs of Sale (3%)', 'exfiles' ), '', true ),
			array( 'joint' === $party ? __( 'Less CGT (combined)', 'exfiles' ) : __( 'Less CGT', 'exfiles' ), 'cgt', true ),
		);
		$gross = $this->r;
		$notes = array();
		foreach ( $lines as $i => $line ) {
			$r = $this->r++;
			$this->row( $r );
			$this->put( $r, self::A, $line[0], $i ? array() : array( 'font' => array( 'sz' => 11 ) ) );
			if ( ! $i ) {
				$this->style( $r, $col, array( 'border' => array( 't' => 'thin' ) ) );
				$this->style( $r, $col + 1, array( 'border' => array( 't' => 'thin' ) ) );
			}
			if ( '' === $line[1] ) {
				foreach ( array( $col, $col + 1 ) as $c ) {
					$this->put( $r, $c, '=-N(' . self::c( $c ) . $gross . ')*3%' );
				}
				continue;
			}
			if ( $p ) {
				list( $v, $chg ) = self::amount( $p[ $line[1] ] ?? null, $line[2] );
				$this->put( $r, $col, $v, $chg );
				$notes[] = self::source( 'value' === $line[1] ? __( 'Value', 'exfiles' ) : ( 'mortgage' === $line[1] ? __( 'Mortgage', 'exfiles' ) : ( 'erc' === $line[1] ? __( 'ERP', 'exfiles' ) : __( 'CGT', 'exfiles' ) ) ), $p[ $line[1] ] ?? null );
			}
		}
		$last = $this->r - 1;
		$net  = $this->r++;
		$this->row( $net );
		$this->put( $net, self::A, __( 'Net equity', 'exfiles' ) );
		$int = $this->r++;
		$this->row( $int );
		$this->put(
			$int,
			self::A,
			__( 'Value of interest', 'exfiles' ),
			array(
				'font' => array(
					'b'  => true,
					'sz' => 11,
				),
			)
		);
		foreach ( array( $col, $col + 1 ) as $c ) {
			$l = self::c( $c );
			$this->put( $net, $c, '=SUM(' . $l . $gross . ':' . $l . $last . ')' );
			$this->put(
				$int,
				$c,
				'=' . $l . $net,
				array(
					'font'   => array(
						'b'  => true,
						'sz' => 11,
					),
					'border' => array(
						't' => 'thin',
						'b' => 'thin',
					),
				)
			);
		}
		if ( $p ) {
			$this->note( $gross, self::join( $notes ) );
			$this->note( $int, (string) ( $p['additional'] ?? '' ) );
		}
		$this->blank();
		return $int;
	}

	/**
	 * The property section.
	 *
	 * @return int Total row.
	 */
	private function properties() {
		$from = $this->r;
		$r    = $this->r++;
		$this->row( $r );
		foreach ( range( 0, 3 ) as $c ) {
			$this->style( $r, $c, array( 'fill' => self::BLUE ) );
		}
		$this->put(
			$r,
			self::A,
			__( 'PROPERTY (Legal Title)', 'exfiles' ),
			array(
				'font' => array(
					'b'  => true,
					'sz' => 16,
				),
				'wrap' => false,
			)
		);
		$this->h[ $r ] = 20;
		$this->note( $r, (string) ( $this->yes( 'properties' ) ? ( $this->d['properties']['additional'] ?? '' ) : '' ) );
		$this->blank();

		$groups = array(
			'home'   => array(),
			'client' => array(),
			'ex'     => array(),
			'joint'  => array(),
		);
		foreach ( $this->items( 'properties', 'properties' ) as $p ) {
			list( $party ) = self::party( $p['owner'] ?? '' );
			$groups[ 'Yes' === ( $p['home'] ?? '' ) ? 'home' : $party ][] = $p;
		}
		$labels = array(
			'client' => __( "Client's sole name", 'exfiles' ),
			'ex'     => __( "Ex's sole name", 'exfiles' ),
			'joint'  => __( 'Joint names', 'exfiles' ),
		);
		$refs   = array();
		foreach ( $groups as $group => $props ) {
			// Always one block per kind, as in the template, so the other side's
			// figures can be added even where the client listed nothing.
			foreach ( $props ? $props : array( null ) as $p ) {
				$party        = 'home' === $group ? ( $p ? self::party( $p['owner'] ?? '' )[0] : 'joint' ) : $group;
				$int          = $this->property( $party, $p, 'home' === $group ? __( 'FAMILY HOME', 'exfiles' ) : $labels[ $group ] );
				$col          = self::COLS[ $party ];
				$refs[ $col ][]     = self::c( $col ) . $int;
				$refs[ $col + 1 ][] = self::c( $col + 1 ) . $int;
			}
		}
		return $this->total( $from, __( 'Total property', 'exfiles' ), $refs + array_fill_keys( array( 4, 5, 7, 8, 10, 11 ), array() ) );
	}

	/**
	 * A section of Client / Ex / Joint lists, like bank accounts.
	 *
	 * @param string $title   Heading.
	 * @param string $note    Section notes.
	 * @param array  $rows    Lines: [ party, description, column B value, value, style changes, note, flag ].
	 * @param array  $opts    sub (column B heading), parties (which groups to show), b_style.
	 * @return int Total row.
	 */
	private function section( $title, $note, array $rows, array $opts = array() ) {
		$from = $this->heading( $title, $note );
		$this->blank();
		if ( ! empty( $opts['sub'] ) ) {
			$r = $this->blank();
			$this->put(
				$r,
				self::B,
				$opts['sub'],
				array(
					'font' => array( 'b' => true ),
					'h'    => 'center',
				)
			);
		}
		$parties = $opts['parties'] ?? array( 'client', 'ex', 'joint' );
		foreach ( $rows as $line ) {
			if ( ! in_array( $line[0], $parties, true ) ) {
				$parties[] = $line[0];
			}
		}
		$names = array(
			'client' => __( 'Client', 'exfiles' ),
			'ex'     => __( 'Ex', 'exfiles' ),
			'joint'  => __( 'Joint', 'exfiles' ),
		);
		foreach ( array_keys( $names ) as $party ) {
			if ( ! in_array( $party, $parties, true ) ) {
				continue;
			}
			$r = $this->blank();
			$this->put(
				$r,
				self::A,
				$names[ $party ],
				array(
					'font' => array( 'b' => true ),
					'h'    => 'left',
				)
			);
			$mine = array_values(
				array_filter(
					$rows,
					function ( $line ) use ( $party ) {
						return $line[0] === $party;
					}
				)
			);
			foreach ( $mine as $line ) {
				$r = $this->blank();
				$this->put( $r, self::A, $line[1] );
				$this->fit( $r, (string) $line[1], 22 );
				if ( null !== $line[2] && '' !== $line[2] ) {
					$this->put( $r, self::B, $line[2], array_merge( array( 'h' => 'center' ), $opts['b_style'] ?? array() ) );
				}
				$this->put( $r, self::COLS[ $party ], $line[3], $line[4] );
				$this->note( $r, $line[5], ! empty( $line[6] ) );
			}
			// Room to add more, like the template's spare rows.
			foreach ( range( 1, max( 1, 2 - count( $mine ) ) ) as $i ) {
				$this->blank();
			}
		}
		return $this->total( $from, __( 'Total', 'exfiles' ) );
	}

	/**
	 * A shaded band of three rows: party headings, case headings and a total.
	 *
	 * @param string $fill    Colour.
	 * @param string $label   Total label.
	 * @param array  $formula Column => formula.
	 * @param bool   $joint   Include the Joint columns.
	 * @return int Total row.
	 */
	private function band( $fill, $label, array $formula, $joint = true ) {
		$top = $this->r;
		$this->heads( $joint, $fill );
		$r = $this->r++;
		$this->row(
			$r,
			array(
				'fill'   => $fill,
				'border' => array( 'b' => 'thin' ),
			),
			true
		);
		$this->put(
			$r,
			self::A,
			$label,
			array(
				'font' => array(
					'b'  => true,
					'sz' => 14,
				),
				'wrap' => false,
			)
		);
		foreach ( $formula as $c => $f ) {
			$this->put(
				$r,
				$c,
				$f,
				array(
					'font' => array(
						'b'  => true,
						'sz' => 12,
					),
				)
			);
		}
		foreach ( range( $top, $r ) as $row ) {
			$this->style( $row, self::A, array( 'border' => array( 'l' => 'thin' ) ) );
		}
		$this->h[ $r ] = 18;
		return $r;
	}

	/**
	 * Two heading rows over the amount columns: "Client / Ex / Joint", then
	 * "Client's case / Ex's case" under each.
	 *
	 * @param bool   $joint Include Joint.
	 * @param string $fill  Shade the whole rows (bands), or '' for the plain grey.
	 * @param string $title A section title in column A of the first row.
	 * @return int First row.
	 */
	private function heads( $joint = true, $fill = '', $title = '' ) {
		$r1 = $this->r++;
		$r2 = $this->r++;
		foreach ( array( $r1, $r2 ) as $r ) {
			$this->row( $r, $fill ? array( 'fill' => $fill ) : array(), (bool) $fill );
		}
		if ( $title ) {
			foreach ( range( 0, 3 ) as $c ) {
				$this->style(
					$r1,
					$c,
					array(
						'fill'   => self::BLUE,
						'border' => array( 't' => 'thin' ),
					)
				);
			}
			$this->put(
				$r1,
				self::A,
				$title,
				array(
					'font' => array(
						'b'  => true,
						'sz' => 16,
					),
					'wrap' => false,
				)
			);
			$this->h[ $r1 ] = 20;
		}
		$names = array(
			self::E => __( 'Client', 'exfiles' ),
			self::H => __( 'Ex', 'exfiles' ),
		);
		if ( $joint ) {
			$names[ self::K ] = __( 'Joint', 'exfiles' );
		}
		$bold = array(
			'font' => array(
				'b'  => true,
				'sz' => 11,
			),
			'h'    => 'center',
		);
		foreach ( $names as $c => $name ) {
			$this->put( $r1, $c, $name, $bold + array( 'border' => array( 't' => 'thin' ) ) );
			$this->style( $r1, $c + 1, array( 'border' => array( 't' => 'thin' ) ) );
			$this->merges[] = self::c( $c ) . $r1 . ':' . self::c( $c + 1 ) . $r1;
			$this->put( $r2, $c, __( "Client's case", 'exfiles' ), array_merge( $bold, array( 'font' => array( 'b' => true ) ), array( 'border' => array( 'b' => 'thin' ) ) ) );
			$this->put( $r2, $c + 1, __( "Ex's case", 'exfiles' ), array_merge( $bold, array( 'font' => array( 'b' => true ) ), array( 'border' => array( 'b' => 'thin' ) ) ) );
		}
		return $r1;
	}

	/* ---------------------------------------------------------------------
	 * Each section's lines.
	 * ------------------------------------------------------------------ */

	/**
	 * Bank accounts.
	 */
	private function accounts() {
		$rows = array();
		foreach ( $this->items( 'accounts', 'accounts' ) as $a ) {
			list( $party, $who, $flag ) = self::party( $a['owner'] ?? '' );
			list( $v, $chg )            = self::amount( $a['balance'] ?? null );
			$rows[]                     = array( $party, self::join( array( $a['bank'] ?? '', empty( $a['kind'] ) ? '' : '(' . $a['kind'] . ')' ) ), '', $v, $chg, self::join( array( self::source( __( 'Balance', 'exfiles' ), $a['balance'] ?? null ), $who, $a['additional'] ?? '' ) ), $flag );
		}
		return $this->section( __( 'BANK ACCOUNTS / CASH', 'exfiles' ), $this->yes( 'accounts' ) ? ( $this->d['accounts']['additional'] ?? '' ) : '', $rows, array( 'sub' => __( 'Account No.', 'exfiles' ) ) );
	}

	/**
	 * Investments and policies.
	 */
	private function investments() {
		$rows = array();
		foreach ( $this->items( 'investments', 'investments' ) as $i ) {
			list( $party, $who, $flag ) = self::party( $i['owner'] ?? '' );
			list( $v, $chg )            = self::amount( $i['value'] ?? null );
			$maturity                   = isset( $i['maturity']['amount'] ) && '' !== $i['maturity']['amount'] ? sprintf( /* translators: %s: amount */ __( 'Maturity value £%s', 'exfiles' ), number_format( (float) $i['maturity']['amount'] ) ) . ( empty( $i['maturity_date'] ) ? '' : ' ' . sprintf( /* translators: %s: date */ __( 'on %s', 'exfiles' ), exf_q_date( $i['maturity_date'] ) ) ) . '.' : '';
			$rows[]                     = array( $party, $i['description'] ?? '', '', $v, $chg, self::join( array( self::source( __( 'Value', 'exfiles' ), $i['value'] ?? null ), $maturity, $who, $i['additional'] ?? '' ) ), $flag );
		}
		return $this->section( __( 'INVESTMENTS / POLICIES (inc. CGT)', 'exfiles' ), $this->yes( 'investments' ) ? ( $this->d['investments']['additional'] ?? '' ) : '', $rows );
	}

	/**
	 * Business interests, after any CGT.
	 */
	private function businesses() {
		$rows = array();
		foreach ( $this->items( 'business', 'businesses' ) as $b ) {
			list( $party, $who, $flag ) = self::party( $b['owner'] ?? '' );
			$value                      = 'Yes' === ( $b['know_value'] ?? '' ) ? ( $b['value'] ?? null ) : array( 'certainty' => "Don't know" );
			list( $v, $chg )            = self::amount( $value );
			$cgt                        = self::amount( $b['cgt'] ?? null )[0];
			if ( is_float( $v ) && is_float( $cgt ) && $cgt ) {
				// Show the sum, so both figures are visible in the cell.
				$v = '=' . $v . '-' . $cgt;
			}
			$structure = empty( $b['structure'] ) ? '' : ( 'Other' === $b['structure'] ? ( $b['structure_other'] ?? __( 'Other', 'exfiles' ) ) : $b['structure'] );
			$rows[]    = array(
				$party,
				self::join( array( $b['name'] ?? '', $structure ? '(' . $structure . ')' : '' ) ),
				isset( $b['percent'] ) && is_numeric( $b['percent'] ) ? (float) $b['percent'] / 100 : '',
				$v,
				$chg,
				self::join(
					array(
						self::source( __( 'Value', 'exfiles' ), $value ),
						empty( $b['value_source'] ) ? '' : sprintf( /* translators: %s: source */ __( 'Value from: %s.', 'exfiles' ), $b['value_source'] ),
						self::source( __( 'CGT', 'exfiles' ), $b['cgt'] ?? null ),
						$who,
						$b['additional'] ?? '',
					)
				),
				$flag,
			);
		}
		return $this->section(
			__( 'BUSINESS INTERESTS (inc. CGT)', 'exfiles' ),
			$this->yes( 'business' ) ? ( $this->d['business']['additional'] ?? '' ) : '',
			$rows,
			array(
				'sub'     => __( '% share', 'exfiles' ),
				'parties' => array( 'client', 'ex' ),
				'b_style' => array( 'fmt' => '0.0%' ),
			)
		);
	}

	/**
	 * Chattels: the questionnaire's "other assets" (vehicles, jewellery, art…).
	 */
	private function chattels() {
		$rows = array();
		foreach ( $this->items( 'assets', 'assets' ) as $a ) {
			list( $party, $who, $flag ) = self::party( $a['owner'] ?? '' );
			list( $v, $chg )            = self::amount( $a['value'] ?? null );
			$rows[]                     = array( $party, $a['description'] ?? '', '', $v, $chg, self::join( array( self::source( __( 'Value', 'exfiles' ), $a['value'] ?? null ), $who, $a['additional'] ?? '' ) ), $flag );
		}
		return $this->section( __( 'CHATTELS', 'exfiles' ), $this->yes( 'assets' ) ? ( $this->d['assets']['additional'] ?? '' ) : '', $rows );
	}

	/**
	 * Liabilities, as negative amounts.
	 */
	private function liabilities() {
		$rows = array();
		foreach ( $this->items( 'liabilities', 'liabilities' ) as $l ) {
			list( $party, $who, $flag ) = self::party( $l['liable'] ?? '' );
			list( $v, $chg )            = self::amount( $l['balance'] ?? null, true );
			$monthly                    = self::amount( $l['monthly'] ?? null )[0];
			$rows[]                     = array(
				$party,
				$l['creditor'] ?? '',
				'',
				$v,
				$chg,
				self::join(
					array(
						self::source( __( 'Balance', 'exfiles' ), $l['balance'] ?? null ),
						is_float( $monthly ) ? sprintf( /* translators: %s: amount */ __( 'Repaying £%s a month.', 'exfiles' ), number_format( $monthly, 2 ) ) : '',
						empty( $l['names'] ) ? '' : sprintf( /* translators: %s: names */ __( 'Names on the agreement: %s.', 'exfiles' ), $l['names'] ),
						'Yes' === ( $l['supplementary'] ?? '' ) ? __( 'Someone is only a supplementary cardholder.', 'exfiles' ) : '',
						$who,
						$l['additional'] ?? '',
					)
				),
				$flag,
			);
		}
		return $this->section( __( 'LIABILITIES', 'exfiles' ), $this->yes( 'liabilities' ) ? ( $this->d['liabilities']['additional'] ?? '' ) : '', $rows );
	}

	/**
	 * Pensions (CETVs), Client and Ex only.
	 *
	 * @return int Total row.
	 */
	private function pensions() {
		$from = $this->heads( false, '', __( 'PENSION ASSETS', 'exfiles' ) );
		$this->note( $from, (string) ( $this->yes( 'pensions' ) ? ( $this->d['pensions']['additional'] ?? '' ) : '' ) );
		$lines = array(
			'client' => array(),
			'ex'     => array(),
		);
		foreach ( $this->items( 'pensions', 'pensions' ) as $p ) {
			$party             = 'My spouse/partner' === ( $p['owner'] ?? '' ) ? 'ex' : 'client';
			$lines[ $party ][] = $p;
		}
		foreach ( $lines as $party => $list ) {
			$r = $this->blank();
			$this->put( $r, self::A, 'ex' === $party ? __( 'Ex', 'exfiles' ) : __( 'Client', 'exfiles' ), array( 'font' => array( 'b' => true ) ) );
			foreach ( $list as $p ) {
				$cetv            = 'Yes' === ( $p['know_cetv'] ?? '' ) ? ( $p['cetv'] ?? null ) : array( 'certainty' => "Don't know" );
				list( $v, $chg ) = self::amount( $cetv );
				$r               = $this->blank();
				$this->put( $r, self::A, (string) ( $p['provider'] ?? '' ) );
				$this->fit( $r, (string) ( $p['provider'] ?? '' ), 22 );
				$this->put( $r, self::COLS[ $party ], $v, $chg );
				$this->note(
					$r,
					self::join(
						array(
							empty( $p['kind'] ) ? '' : $p['kind'] . '.',
							self::source( __( 'CETV', 'exfiles' ), $cetv ),
							empty( $p['in_payment'] ) ? '' : sprintf( /* translators: %s: Yes/No/Partially */ __( 'In payment or drawdown: %s.', 'exfiles' ), $p['in_payment'] ),
							$p['additional'] ?? '',
						)
					)
				);
			}
			foreach ( range( 1, max( 1, 2 - count( $list ) ) ) as $i ) {
				$this->blank();
			}
		}
		return $this->total( $from, __( 'Total', 'exfiles' ) );
	}

	/**
	 * Annual incomes: the client's own under Client, what they say of their ex's under Ex.
	 *
	 * @return int Total row.
	 */
	private function incomes() {
		$from = $this->heads( false, '', __( 'ANNUAL INCOMES', 'exfiles' ) );
		$r    = $this->blank();
		$this->put(
			$r,
			self::B,
			__( 'Gross or Net?', 'exfiles' ),
			array(
				'font' => array( 'b' => true ),
				'h'    => 'center',
			)
		);
		$income = $this->d['income'] ?? array();
		foreach ( array(
			'client' => __( 'Client', 'exfiles' ),
			'spouse' => __( 'Ex', 'exfiles' ),
		) as $who => $label ) {
			$party = 'spouse' === $who ? 'ex' : 'client';
			$r     = $this->blank();
			$this->put( $r, self::A, $label, array( 'font' => array( 'b' => true ) ) );
			$has = $income[ $who . '_has' ] ?? '';
			if ( 'No' === $has ) {
				$this->note( $r, __( 'No income.', 'exfiles' ) );
			} elseif ( "Don't know" === $has ) {
				$this->note( $r, __( "Client doesn't know their ex's income.", 'exfiles' ), true );
			}
			$lines = array();
			if ( 'Yes' === $has ) {
				$g   = $income[ $who ] ?? array();
				$add = function ( $source, $gn, $m, $extra = '' ) use ( &$lines ) {
					$lines[] = array( $source, $gn, is_array( $m ) ? $m : array(), $extra );
				};
				foreach ( (array) ( $g['employment'] ?? array() ) as $e ) {
					$job = trim( (string) ( $e['occupation'] ?? '' ) );
					$job = $job ? sprintf( /* translators: %s: occupation */ __( 'Salary: %s', 'exfiles' ), $job ) : __( 'Salary', 'exfiles' );
					$net = is_array( $e['net'] ?? null ) && ! empty( $e['net']['certainty'] ) ? $e['net'] : null;
					if ( $net ) {
						$gross = self::annual( $e['gross'] ?? null );
						$add( $job, __( 'Net', 'exfiles' ), $net, is_float( $gross ) ? sprintf( /* translators: %s: amount */ __( 'Gross £%s a year.', 'exfiles' ), number_format( $gross ) ) : '' );
					} else {
						$add( $job, __( 'Gross', 'exfiles' ), $e['gross'] ?? null );
					}
				}
				foreach ( (array) ( $g['dividends'] ?? array() ) as $e ) {
					$add( self::join( array( __( 'Dividends', 'exfiles' ), empty( $e['business'] ) ? '' : '(' . $e['business'] . ')' ) ), '', $e['amount'] ?? null );
				}
				foreach ( (array) ( $g['benefits'] ?? array() ) as $e ) {
					$add( (string) ( $e['benefit'] ?? __( 'Benefit', 'exfiles' ) ), '', $e['amount'] ?? null );
				}
				if ( ! empty( $g['maintenance']['certainty'] ) && 'None' !== $g['maintenance']['certainty'] ) {
					$add( __( 'Child maintenance received', 'exfiles' ), '', $g['maintenance'] );
				}
				foreach ( (array) ( $g['pension_inc'] ?? array() ) as $e ) {
					$add( (string) ( $e['kind'] ?? __( 'Pension income', 'exfiles' ) ), '', $e['amount'] ?? null );
				}
				foreach ( (array) ( $g['other_inc'] ?? array() ) as $e ) {
					$add( (string) ( $e['source'] ?? __( 'Other income', 'exfiles' ) ), '', $e['amount'] ?? null );
				}
			}
			foreach ( $lines as $line ) {
				$m        = $line[2];
				$per_year = exf_q_frequencies()[ $m['frequency'] ?? '' ] ?? 0;
				$r        = $this->blank();
				$this->put( $r, self::A, $line[0] );
				$this->fit( $r, $line[0], 22 );
				if ( $line[1] ) {
					$this->put( $r, self::B, $line[1], array( 'h' => 'center' ) );
				}
				$how     = '';
				$unknown = false;
				if ( "Don't know" !== ( $m['certainty'] ?? '' ) && 'None' !== ( $m['certainty'] ?? '' ) && ! $per_year ) {
					// No usable frequency: show the amount as given and say so.
					$per_year = 1;
					$unknown  = true;
					$how      = __( 'Amount as given: how often it is paid is not known.', 'exfiles' );
				} elseif ( $per_year > 1 && is_numeric( $m['amount'] ?? '' ) ) {
					/* translators: 1: amount, 2: frequency */
					$how = sprintf( __( '£%1$s %2$s.', 'exfiles' ), number_format( (float) $m['amount'], 2 ), strtolower( (string) $m['frequency'] ) );
				}
				list( $v, $chg ) = self::amount( $m, false, (float) $per_year );
				$this->put( $r, self::COLS[ $party ], $v, $chg );
				$this->note( $r, self::join( array( $how, self::source( __( 'Amount', 'exfiles' ), $m ), $line[3] ) ), $unknown );
			}
			foreach ( range( 1, max( 1, 3 - count( $lines ) ) ) as $i ) {
				$this->blank();
			}
		}
		$total = $this->total( $from, __( 'Total', 'exfiles' ) );
		$this->blank();
		$r1 = $this->blank();
		$r2 = $this->r++;
		$this->row( $r2, array( 'border' => array( 'b' => 'thin' ) ) );
		foreach ( array( self::E, self::H ) as $c ) {
			$this->put(
				$r1,
				$c,
				__( 'Per month', 'exfiles' ),
				array(
					'font'   => array( 'b' => true ),
					'h'      => 'center',
					'border' => array( 't' => 'thin' ),
				)
			);
			$this->style( $r1, $c + 1, array( 'border' => array( 't' => 'thin' ) ) );
			$this->merges[] = self::c( $c ) . $r1 . ':' . self::c( $c + 1 ) . $r1;
			foreach ( array( $c, $c + 1 ) as $cc ) {
				$this->put( $r2, $cc, '=' . self::c( $cc ) . $total . '/12' );
			}
		}
		return $total;
	}

	/**
	 * A money answer as a yearly figure, or null.
	 *
	 * @param mixed $m Money answer.
	 */
	private static function annual( $m ) {
		$m = is_array( $m ) ? $m : array();
		$n = exf_q_frequencies()[ $m['frequency'] ?? '' ] ?? 0;
		return ( $n && is_numeric( $m['amount'] ?? '' ) && "Don't know" !== ( $m['certainty'] ?? '' ) ) ? round( (float) $m['amount'] * $n, 2 ) : null;
	}

	/* ---------------------------------------------------------------------
	 * The header: names and grand totals (rows 1–16, kept in view).
	 * ------------------------------------------------------------------ */

	/**
	 * Header rows.
	 *
	 * @param array $t Total rows: property, bank, investments, business, chattels, other, liabilities, pensions.
	 */
	private function header( array $t ) {
		$ex_file = (int) get_post_meta( $this->q, 'ex_file_id', true );
		$vars    = $ex_file ? exf_ex_file_email_vars( $ex_file ) : array(
			'client_name' => '',
			'ex_name'     => '',
		);
		$arial   = function ( $sz, $b = false ) {
			return array(
				'font' => array(
					'name' => 'Arial',
					'sz'   => $sz,
					'b'    => $b,
				),
				'v'    => 'center',
			);
		};
		$set     = function ( $r, $c, $v, array $d ) {
			$cell = array( 'd' => $d );
			if ( is_string( $v ) && '' !== $v && '=' === $v[0] ) {
				$cell['f'] = substr( $v, 1 );
			} elseif ( null !== $v ) {
				$cell['v'] = $v;
			}
			$this->g[ $r ][ $c ] = $cell;
		};

		$set( 1, self::A, $vars['client_name'] ? strtoupper( $vars['client_name'] ) : __( 'CLIENT NAME', 'exfiles' ), $arial( 14, true ) );
		$this->merges[] = 'A1:B1';
		$set( 2, self::A, __( 'COMPOSITE SCHEDULE OF ASSETS', 'exfiles' ), $arial( 11, true ) );
		$set( 4, self::A, __( 'CLIENT:', 'exfiles' ), $arial( 12, true ) + array( 'h' => 'right' ) );
		$set( 5, self::A, __( 'EX:', 'exfiles' ), $arial( 12, true ) + array( 'h' => 'right' ) );
		$set( 4, self::B, $vars['client_name'], $arial( 12 ) + array( 'h' => 'left' ) );
		$set( 5, self::B, $vars['ex_name'], $arial( 12 ) + array( 'h' => 'left' ) );
		$this->merges[] = 'B4:D4';
		$this->merges[] = 'B5:D5';

		// Key and source, in the space under the names.
		$at = (int) get_post_meta( $this->q, 'q_submitted_at', true );
		$source = implode(
			', ',
			array_filter(
				array(
					exf_q_service_name( $this->q ),
					/* translators: %s: date */
					$at ? sprintf( __( 'submitted %s', 'exfiles' ), wp_date( 'j M Y', $at ) ) : '',
				)
			)
		);
		$set( 7, self::A, __( "Client's estimate", 'exfiles' ), $arial( 9 ) + array( 'fill' => self::EST ) );
		$set( 8, self::A, __( "Client doesn't know", 'exfiles' ), $arial( 9 ) + array( 'fill' => self::DK ) );
		$set(
			10,
			self::A,
			$source ? sprintf(
				/* translators: %s: e.g. "Check Your Deal, submitted 3 Oct 2026" */
				__( "Client's case: from the client's questionnaire (%s). Not verified. Ex's case: to complete.", 'exfiles' ),
				$source
			) : __( "Client's case: from the client's questionnaire. Not verified. Ex's case: to complete.", 'exfiles' ),
			$arial( 9 ) + array(
				'wrap' => true,
				'v'    => 'top',
			)
		);
		$this->merges[] = 'A10:D12';

		// Grand totals box.
		$grey   = self::GREY;
		$labels = array(
			2  => array( __( 'Properties (net)', 'exfiles' ), $t[0] ),
			3  => array( __( 'Bank accounts', 'exfiles' ), $t[1] ),
			4  => array( __( 'Investments/Policies', 'exfiles' ), $t[2] ),
			5  => array( __( 'Business Interests', 'exfiles' ), $t[3] ),
			6  => array( __( 'Chattels', 'exfiles' ), $t[4] ),
			7  => array( __( 'Other', 'exfiles' ), $t[5] ),
			8  => array( __( 'Liabilities', 'exfiles' ), $t[6] ),
		);
		$set( 1, self::E, __( 'GRAND TOTALS', 'exfiles' ), $arial( 12, true ) + array( 'fill' => $grey, 'h' => 'left', 'border' => array( 'l' => 'medium', 't' => 'medium', 'b' => 'medium' ) ) );
		foreach ( array( 5, 6, 9 ) as $c ) {
			$set( 1, $c, null, array( 'fill' => $grey, 'border' => array( 't' => 'medium', 'b' => 'medium' ) ) );
		}
		$set( 1, self::H, __( "Client's case", 'exfiles' ), $arial( 12, true ) + array( 'fill' => $grey, 'h' => 'center', 'border' => array( 'l' => 'thin', 't' => 'medium', 'b' => 'medium' ) ) );
		$set( 1, 8, null, array( 'fill' => $grey, 'border' => array( 'r' => 'thin', 't' => 'medium', 'b' => 'medium' ) ) );
		$set( 1, self::K, __( "Ex's case", 'exfiles' ), $arial( 12, true ) + array( 'fill' => $grey, 'h' => 'center', 'border' => array( 'l' => 'thin', 't' => 'medium', 'b' => 'medium' ) ) );
		$set( 1, self::L, null, array( 'fill' => $grey, 'border' => array( 'r' => 'medium', 't' => 'medium', 'b' => 'medium' ) ) );
		$this->merges[] = 'H1:I1';
		$this->merges[] = 'K1:L1';

		$money = function ( $sz, $b, array $border, $fill = '' ) use ( $arial ) {
			return $arial( $sz, $b ) + array_filter(
				array(
					'fmt'    => self::MONEY,
					'h'      => 'right',
					'border' => $border,
					'fill'   => $fill,
				)
			);
		};
		$line = function ( $r, $label, $client, $ex, array $o = array() ) use ( $set, $arial, $money ) {
			$b    = ! empty( $o['bold'] );
			$fill = $o['fill'] ?? '';
			$t    = $o['t'] ?? '';
			$bt   = $o['b'] ?? '';
			$edge = array_filter(
				array(
					't' => $t,
					'b' => $bt,
				)
			);
			$set( $r, self::E, $label, $arial( 12, $b ) + array_filter( array( 'h' => 'left', 'fill' => $fill, 'border' => array( 'l' => 'medium' ) + $edge ) ) );
			foreach ( array( 5, 6, 9 ) as $c ) {
				if ( $fill || $edge ) {
					$set( $r, $c, null, array_filter( array( 'fill' => $fill, 'border' => $edge ) ) );
				}
			}
			$set( $r, self::H, $client, $money( 12, $b, array( 'l' => 'thin' ) + $edge, $fill ) );
			$set( $r, 8, null, array_filter( array( 'fill' => $fill, 'border' => array( 'r' => 'thin' ) + $edge ) ) );
			$set( $r, self::K, $ex, $money( 12, $b, array( 'l' => 'thin' ) + $edge, $fill ) );
			$set( $r, self::L, null, array_filter( array( 'fill' => $fill, 'border' => array( 'r' => 'medium' ) + $edge ) ) );
			$this->merges[] = 'H' . $r . ':I' . $r;
			$this->merges[] = 'K' . $r . ':L' . $r;
		};
		foreach ( $labels as $r => $l ) {
			$line( $r, $l[0], '=SUM(E' . $l[1] . '+H' . $l[1] . '+K' . $l[1] . ')', '=SUM(F' . $l[1] . '+I' . $l[1] . '+L' . $l[1] . ')', 8 === $r ? array( 'b' => 'thin' ) : array() );
		}
		$line(
			9,
			__( 'TOTAL NON-PENSION', 'exfiles' ),
			'=SUM(H2:I8)',
			'=SUM(K2:L8)',
			array(
				'bold' => true,
				'fill' => self::CREAM,
				'b'    => 'thin',
			)
		);
		$line( 10, '', null, null );
		$line( 11, __( 'Pensions', 'exfiles' ), '=SUM(E' . $t[7] . '+H' . $t[7] . ')', '=SUM(F' . $t[7] . '+I' . $t[7] . ')', array( 'b' => 'thin' ) );
		$line(
			12,
			__( 'TOTAL INC. PENSIONS', 'exfiles' ),
			'=H9+H11',
			'=K9+K11',
			array(
				'bold' => true,
				'fill' => self::PEACH,
				'b'    => 'medium',
			)
		);

		// Column headings over the schedule.
		foreach ( array( 14, 15, 16 ) as $r ) {
			$set( $r, self::A, __( 'currency = £', 'exfiles' ), $arial( 12 ) + array( 'fill' => self::LILAC, 'h' => 'right', 'border' => array_filter( array( 'l' => 'medium', 't' => 14 === $r ? 'medium' : '', 'b' => 16 === $r ? 'medium' : '' ) ) ) );
			$set( $r, self::B, 0, $arial( 11 ) + array( 'fill' => self::LILAC, 'fmt' => '#,##0.0000_);\(#,##0.0000\)', 'h' => 'left', 'border' => array_filter( array( 'r' => 'medium', 't' => 14 === $r ? 'medium' : '', 'b' => 16 === $r ? 'medium' : '' ) ) ) );
		}
		$this->h += array(
			1  => 19,
			2  => 16,
			3  => 16,
			4  => 15.75,
			5  => 15.75,
			6  => 15.75,
			7  => 16,
			8  => 16,
			9  => 16,
			10 => 16,
			11 => 16,
			12 => 18,
			13 => 6,
			14 => 23,
			15 => 18,
			16 => 18,
		);
		foreach ( array(
			self::E => __( 'CLIENT', 'exfiles' ),
			self::H => __( 'EX', 'exfiles' ),
			self::K => __( 'JOINT', 'exfiles' ),
		) as $c => $name ) {
			$right = self::K === $c ? 'medium' : 'thin';
			$set( 14, $c, $name, $arial( 18, true ) + array( 'fill' => $grey, 'h' => 'center', 'border' => array( 'l' => 'thin', 't' => 'medium' ) ) );
			$set( 14, $c + 1, null, array( 'fill' => $grey, 'border' => array( 'r' => $right, 't' => 'medium' ) ) );
			$set( 15, $c, null, array( 'fill' => $grey, 'border' => array( 'l' => 'thin' ) ) );
			$set( 15, $c + 1, null, array( 'fill' => $grey, 'border' => array( 'l' => 'thin', 'r' => $right ) ) );
			$set( 16, $c, __( "CLIENT'S CASE", 'exfiles' ), $arial( 11, true ) + array( 'fill' => $grey, 'h' => 'center', 'border' => array( 'l' => 'thin', 'b' => 'medium' ) ) );
			$set( 16, $c + 1, __( "EX'S CASE", 'exfiles' ), $arial( 11, true ) + array( 'fill' => $grey, 'h' => 'center', 'border' => array( 'l' => 'thin', 'r' => $right, 'b' => 'medium' ) ) );
			$this->merges[] = self::c( $c ) . '14:' . self::c( $c + 1 ) . '14';
		}
		$set( 15, self::M, __( 'Additional Information', 'exfiles' ), $arial( 14, true ) + array( 'h' => 'center' ) );
	}

	/* ---------------------------------------------------------------------
	 * Put it together.
	 * ------------------------------------------------------------------ */

	/**
	 * Add the sheet to a workbook.
	 *
	 * @param Exf_Xlsx $x Workbook.
	 */
	public function add_to( Exf_Xlsx $x ) {
		$t   = array();
		$t[] = $this->properties();
		$t[] = $this->accounts();
		$t[] = $this->investments();
		$t[] = $this->businesses();
		$t[] = $this->chattels();
		$t[] = $this->section( __( 'OTHER', 'exfiles' ), '', array() );
		$t[] = $this->liabilities();
		$this->blank();

		$sum = array();
		foreach ( array( 4, 5, 7, 8, 10, 11 ) as $c ) {
			$l         = self::c( $c );
			$sum[ $c ] = '=SUM(' . implode(
				',',
				array_map(
					function ( $row ) use ( $l ) {
						return $l . $row;
					},
					array_reverse( $t )
				)
			) . ')';
		}
		$non = $this->band( self::CREAM, __( 'TOTAL NON-PENSION ASSETS (net)', 'exfiles' ), $sum );
		$this->blank();
		$this->blank();

		$t[] = $this->pensions();
		$this->blank();
		$this->blank();
		$p   = end( $t );
		$all = array();
		foreach ( array( 4, 5, 7, 8 ) as $c ) {
			$all[ $c ] = '=SUM(' . self::c( $c ) . $non . '+' . self::c( $c ) . $p . ')';
		}
		$all[10] = '=SUM(K' . $non . ')';
		$all[11] = '=SUM(L' . $non . ')';
		$this->band( self::PEACH, __( 'TOTAL INCLUDING PENSIONS', 'exfiles' ), $all );
		$this->blank();
		$this->blank();
		$this->blank();
		$this->incomes();

		$this->header( $t );

		// Into rows of cells.
		$rows = array();
		$last = max( array_keys( $this->g ) );
		for ( $r = 1; $r <= $last; $r++ ) {
			$row = array_fill( 0, 13, null );
			foreach ( $this->g[ $r ] ?? array() as $c => $cell ) {
				$out = array( 's' => $x->add_style( $cell['d'] ) );
				if ( isset( $cell['f'] ) ) {
					$out['f'] = $cell['f'];
				} elseif ( isset( $cell['v'] ) ) {
					$out['v'] = $cell['v'];
				}
				$row[ $c ] = $out;
			}
			$rows[] = $row;
		}
		$x->sheet(
			'ASSETS',
			$rows,
			array( 24.33, 16.5, 5, 5, 14.66, 13.83, 2.66, 14.16, 13.83, 2.66, 13.33, 13.5, 36 ),
			true,
			array(
				'merges'   => $this->merges,
				'freeze'   => 16,
				'heights'  => $this->h,
				'portrait' => true,
			)
		);
	}
}
