<?php
/**
 * A small .xlsx writer: enough for a formatted schedule (text, numbers, dates,
 * £ amounts, formulas, fonts, fills, borders, merged cells, frozen rows, column
 * widths and row heights). Needs PHP's zip
 * extension, which standard WordPress hosting has.
 *
 * $x = new Exf_Xlsx();
 * $x->sheet( 'Assets', $rows, array( 30, 14 ) );   // rows of cells
 * $bytes = $x->output();
 *
 * A cell is a scalar, null, or [ 'v' => value, 's' => style, 'f' => formula ].
 * Built-in styles: bold, money, money_bold, title, header, estimate, dont_know,
 * date, wrap. Others: $name = $x->add_style( [ 'font' => …, 'fill' => …, … ] ).
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal xlsx writer.
 */
class Exf_Xlsx {

	/**
	 * The built-in styles, by name. A style is an array of:
	 *  font   => [ 'b' => bool, 'i' => bool, 'sz' => size, 'name' => font, 'color' => 'RRGGBB' ]
	 *  fill   => 'RRGGBB'
	 *  fmt    => number format code ('date' => true for a date format)
	 *  border => [ 'l' => 'thin'|'medium', 'r' => …, 't' => …, 'b' => … ]
	 *  h      => horizontal alignment, v => vertical alignment, wrap => bool
	 *
	 * @var array
	 */
	const STYLES = array(
		''           => array( 'v' => 'top' ),
		'bold'       => array(
			'font' => array( 'b' => true ),
			'v'    => 'top',
		),
		'money'      => array(
			'fmt' => '"£"#,##0.00',
			'v'   => 'top',
		),
		'money_bold' => array(
			'fmt'  => '"£"#,##0.00',
			'font' => array( 'b' => true ),
			'v'    => 'top',
		),
		'title'      => array( 'font' => array( 'b' => true, 'sz' => 15 ) ),
		'header'     => array(
			'font' => array( 'b' => true ),
			'fill' => 'DED4CA',
			'v'    => 'top',
			'wrap' => true,
		),
		'estimate'   => array(
			'fmt'  => '"£"#,##0.00',
			'fill' => 'FBEFD5',
			'v'    => 'top',
		),
		'dont_know'  => array(
			'fill' => 'F3DCD6',
			'v'    => 'top',
		),
		'date'       => array(
			'fmt'  => 'd mmm yyyy',
			'date' => true,
			'h'    => 'left',
			'v'    => 'top',
		),
		'wrap'       => array(
			'v'    => 'top',
			'wrap' => true,
		),
	);

	/**
	 * Every style in use: key => definition. Built-ins first, then add_style() ones.
	 *
	 * @var array
	 */
	private $styles = array();

	/**
	 * Sheets: [ name, rows, widths, options ].
	 *
	 * @var array
	 */
	private $sheets = array();

	/**
	 * Start with the built-in styles.
	 */
	public function __construct() {
		$this->styles = self::STYLES;
	}

	/**
	 * Register a style (see STYLES for the keys) and get the name to use as a cell's 's'.
	 *
	 * @param array $def Style.
	 * @return string
	 */
	public function add_style( array $def ) {
		$key = 'x' . md5( wp_json_encode( $def ) );
		if ( ! isset( $this->styles[ $key ] ) ) {
			$this->styles[ $key ] = $def;
		}
		return $key;
	}

	/**
	 * Add a sheet.
	 *
	 * @param string $name    Name (max 31 characters).
	 * @param array  $rows    Rows of cells.
	 * @param array  $widths  Column widths in characters.
	 * @param bool   $first   Put it before the other sheets.
	 * @param array  $options merges (e.g. [ 'A1:B1' ]), freeze (rows kept at the top),
	 *                        heights ([ row number => points ]), portrait (bool),
	 *                        zoom (percent).
	 */
	public function sheet( $name, array $rows, array $widths = array(), $first = false, array $options = array() ) {
		$name  = substr( str_replace( array( '\\', '/', '?', '*', '[', ']', ':' ), '', $name ), 0, 31 );
		$sheet = array( $name, $rows, $widths, $options );
		if ( $first ) {
			array_unshift( $this->sheets, $sheet );
		} else {
			$this->sheets[] = $sheet;
		}
	}

	/**
	 * Column letter for a 0-based index.
	 *
	 * @param int $i Index.
	 */
	public static function col( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = intdiv( $i - 1, 26 ) ) {
			$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	/**
	 * Excel serial number for a Y-m-d date.
	 *
	 * @param string $ymd Date.
	 */
	public static function serial( $ymd ) {
		$ts = strtotime( $ymd . ' 00:00:00 UTC' );
		return false === $ts ? null : $ts / 86400 + 25569;
	}

	/**
	 * XML-escape.
	 *
	 * @param string $s Text.
	 */
	private static function x( $s ) {
		$s = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $s );
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * cellXfs index for a style name.
	 *
	 * @param string $name Style.
	 */
	private function style_index( $name ) {
		$i = array_search( (string) $name, array_keys( $this->styles ), true );
		return false === $i ? 0 : $i;
	}

	/**
	 * One worksheet's XML.
	 *
	 * @param array $rows    Rows.
	 * @param array $widths  Widths.
	 * @param array $options See sheet().
	 */
	private function sheet_xml( array $rows, array $widths, array $options ) {
		$xml    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$freeze = (int) ( $options['freeze'] ?? 0 );
		$zoom   = (int) ( $options['zoom'] ?? 0 );
		$fit    = empty( $options['portrait'] ) ? '' : '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
		$xml   .= $fit . '<sheetViews><sheetView workbookViewId="0"' . ( $zoom ? ' zoomScale="' . $zoom . '" zoomScaleNormal="' . $zoom . '"' : '' ) . '>';
		if ( $freeze ) {
			$xml .= '<pane ySplit="' . $freeze . '" topLeftCell="A' . ( $freeze + 1 ) . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . ( $freeze + 1 ) . '" sqref="A' . ( $freeze + 1 ) . '"/>';
		}
		$xml .= '</sheetView></sheetViews>';
		if ( $widths ) {
			$xml .= '<cols>';
			foreach ( $widths as $i => $w ) {
				$xml .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (float) $w . '" customWidth="1"/>';
			}
			$xml .= '</cols>';
		}
		$heights = (array) ( $options['heights'] ?? array() );
		$xml    .= '<sheetData>';
		foreach ( array_values( $rows ) as $r => $row ) {
			$h    = isset( $heights[ $r + 1 ] ) ? ' ht="' . (float) $heights[ $r + 1 ] . '" customHeight="1"' : '';
			$xml .= '<row r="' . ( $r + 1 ) . '"' . $h . '>';
			foreach ( array_values( (array) $row ) as $c => $cell ) {
				if ( null === $cell || '' === $cell ) {
					continue;
				}
				$cell  = is_array( $cell ) ? $cell : array( 'v' => $cell );
				$ref   = self::col( $c ) . ( $r + 1 );
				$name  = (string) ( $cell['s'] ?? '' );
				$style = $this->style_index( $name );
				$s     = $style ? ' s="' . $style . '"' : '';
				$v     = $cell['v'] ?? '';
				if ( isset( $cell['f'] ) ) {
					$xml .= '<c r="' . $ref . '"' . $s . '><f>' . self::x( $cell['f'] ) . '</f></c>';
				} elseif ( ! empty( $this->styles[ $name ]['date'] ) && is_string( $v ) && self::serial( $v ) ) {
					$xml .= '<c r="' . $ref . '"' . $s . '><v>' . self::serial( $v ) . '</v></c>';
				} elseif ( is_int( $v ) || is_float( $v ) ) {
					$xml .= '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
				} elseif ( '' === $v ) {
					if ( $style ) {
						$xml .= '<c r="' . $ref . '"' . $s . '/>';
					}
				} else {
					$xml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::x( $v ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		$xml   .= '</sheetData>';
		$merges = (array) ( $options['merges'] ?? array() );
		if ( $merges ) {
			$xml .= '<mergeCells count="' . count( $merges ) . '">';
			foreach ( $merges as $m ) {
				$xml .= '<mergeCell ref="' . self::x( $m ) . '"/>';
			}
			$xml .= '</mergeCells>';
		}
		$page = empty( $options['portrait'] ) ? '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>' : '<pageSetup orientation="portrait" paperSize="9" fitToWidth="1" fitToHeight="0"/>';
		return $xml . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>' . $page . '</worksheet>';
	}

	/**
	 * The styles part, built from every style in use.
	 */
	private function styles() {
		$fonts   = array( '<font><sz val="11"/><name val="Calibri"/></font>' );
		$fills   = array( '<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>' );
		$borders = array( '<border><left/><right/><top/><bottom/><diagonal/></border>' );
		$fmts    = array();
		$xfs     = array();
		$id      = function ( array &$list, $xml ) {
			$i = array_search( $xml, $list, true );
			if ( false === $i ) {
				$list[] = $xml;
				$i      = count( $list ) - 1;
			}
			return $i;
		};
		foreach ( $this->styles as $def ) {
			$f    = (array) ( $def['font'] ?? array() );
			$font = '<font>' . ( empty( $f['b'] ) ? '' : '<b/>' ) . ( empty( $f['i'] ) ? '' : '<i/>' ) . '<sz val="' . (float) ( $f['sz'] ?? 11 ) . '"/>' . ( empty( $f['color'] ) ? '' : '<color rgb="FF' . self::x( $f['color'] ) . '"/>' ) . '<name val="' . self::x( $f['name'] ?? 'Calibri' ) . '"/></font>';
			$fo   = $id( $fonts, $font );
			$fi   = empty( $def['fill'] ) ? 0 : $id( $fills, '<fill><patternFill patternType="solid"><fgColor rgb="FF' . self::x( $def['fill'] ) . '"/><bgColor indexed="64"/></patternFill></fill>' );
			$b    = (array) ( $def['border'] ?? array() );
			$side = function ( $tag, $k ) use ( $b ) {
				return empty( $b[ $k ] ) ? '<' . $tag . '/>' : '<' . $tag . ' style="' . self::x( $b[ $k ] ) . '"><color auto="1"/></' . $tag . '>';
			};
			$bo   = $b ? $id( $borders, '<border>' . $side( 'left', 'l' ) . $side( 'right', 'r' ) . $side( 'top', 't' ) . $side( 'bottom', 'b' ) . '<diagonal/></border>' ) : 0;
			$nf   = 0;
			if ( ! empty( $def['fmt'] ) ) {
				$nf = 164 + $id( $fmts, (string) $def['fmt'] );
			}
			$align = '';
			if ( ! empty( $def['h'] ) || ! empty( $def['v'] ) || ! empty( $def['wrap'] ) ) {
				$align = '<alignment' . ( empty( $def['h'] ) ? '' : ' horizontal="' . self::x( $def['h'] ) . '"' ) . ( empty( $def['v'] ) ? '' : ' vertical="' . self::x( $def['v'] ) . '"' ) . ( empty( $def['wrap'] ) ? '' : ' wrapText="1"' ) . '/>';
			}
			$xfs[] = '<xf numFmtId="' . $nf . '" fontId="' . $fo . '" fillId="' . $fi . '" borderId="' . $bo . '" xfId="0"' . ( $nf ? ' applyNumberFormat="1"' : '' ) . ( $fo ? ' applyFont="1"' : '' ) . ( $fi ? ' applyFill="1"' : '' ) . ( $bo ? ' applyBorder="1"' : '' ) . ( $align ? ' applyAlignment="1">' . $align . '</xf>' : '/>' );
		}
		$numfmts = '';
		foreach ( $fmts as $i => $code ) {
			$numfmts .= '<numFmt numFmtId="' . ( 164 + $i ) . '" formatCode="' . self::x( $code ) . '"/>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. ( $fmts ? '<numFmts count="' . count( $fmts ) . '">' . $numfmts . '</numFmts>' : '' )
			. '<fonts count="' . count( $fonts ) . '">' . implode( '', $fonts ) . '</fonts>'
			. '<fills count="' . count( $fills ) . '">' . implode( '', $fills ) . '</fills>'
			. '<borders count="' . count( $borders ) . '">' . implode( '', $borders ) . '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="' . count( $xfs ) . '">' . implode( '', $xfs ) . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	/**
	 * The workbook as bytes.
	 *
	 * @return string|WP_Error
	 */
	public function output() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'exf_no_zip', 'The zip extension is missing.' );
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( 'exf-xlsx' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'exf_zip', 'Could not create the file.' );
		}
		$types  = '';
		$sheets = '';
		$rels   = '';
		foreach ( $this->sheets as $i => $sheet ) {
			$n = $i + 1;
			$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', $this->sheet_xml( $sheet[1], $sheet[2], $sheet[3] ) );
			$types  .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$sheets .= '<sheet name="' . self::x( $sheet[0] ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		}
		$n = count( $this->sheets ) + 1;
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $types . '</Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $this->styles() );
		$zip->close();
		$bytes = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $tmp );
		return false === $bytes ? new WP_Error( 'exf_zip', 'Could not read the file.' ) : $bytes;
	}
}
