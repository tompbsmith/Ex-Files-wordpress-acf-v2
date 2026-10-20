<?php
/**
 * A small .xlsx writer: enough for a formatted schedule (text, numbers, dates,
 * £ amounts, formulas, bold/highlighted cells, column widths). Needs PHP's zip
 * extension, which standard WordPress hosting has.
 *
 * $x = new Exf_Xlsx();
 * $x->sheet( 'Assets', $rows, array( 30, 14 ) );   // rows of cells
 * $bytes = $x->output();
 *
 * A cell is a scalar, null, or [ 'v' => value, 's' => style, 'f' => formula ].
 * Styles: bold, money, money_bold, title, header, estimate, dont_know, date, wrap.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal xlsx writer.
 */
class Exf_Xlsx {

	/**
	 * Style name => cellXfs index (see styles()).
	 *
	 * @var array
	 */
	const STYLES = array(
		''           => 0,
		'bold'       => 1,
		'money'      => 2,
		'money_bold' => 3,
		'title'      => 4,
		'header'     => 5,
		'estimate'   => 6,
		'dont_know'  => 7,
		'date'       => 8,
		'wrap'       => 9,
	);

	/**
	 * Sheets: [ name, rows, widths ].
	 *
	 * @var array
	 */
	private $sheets = array();

	/**
	 * Add a sheet.
	 *
	 * @param string $name   Name (max 31 characters).
	 * @param array  $rows   Rows of cells.
	 * @param array  $widths Column widths in characters.
	 * @param bool   $first  Put it before the other sheets.
	 */
	public function sheet( $name, array $rows, array $widths = array(), $first = false ) {
		$name  = substr( str_replace( array( '\\', '/', '?', '*', '[', ']', ':' ), '', $name ), 0, 31 );
		$sheet = array( $name, $rows, $widths );
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
	 * One worksheet's XML.
	 *
	 * @param array $rows   Rows.
	 * @param array $widths Widths.
	 */
	private function sheet_xml( array $rows, array $widths ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		if ( $widths ) {
			$xml .= '<cols>';
			foreach ( $widths as $i => $w ) {
				$xml .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (float) $w . '" customWidth="1"/>';
			}
			$xml .= '</cols>';
		}
		$xml .= '<sheetData>';
		foreach ( array_values( $rows ) as $r => $row ) {
			$xml .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( (array) $row ) as $c => $cell ) {
				if ( null === $cell || '' === $cell ) {
					continue;
				}
				$cell  = is_array( $cell ) ? $cell : array( 'v' => $cell );
				$ref   = self::col( $c ) . ( $r + 1 );
				$style = self::STYLES[ $cell['s'] ?? '' ] ?? 0;
				$s     = $style ? ' s="' . $style . '"' : '';
				$v     = $cell['v'] ?? '';
				if ( isset( $cell['f'] ) ) {
					$xml .= '<c r="' . $ref . '"' . $s . '><f>' . self::x( $cell['f'] ) . '</f></c>';
				} elseif ( 'date' === ( $cell['s'] ?? '' ) && is_string( $v ) && self::serial( $v ) ) {
					$xml .= '<c r="' . $ref . '"' . $s . '><v>' . self::serial( $v ) . '</v></c>';
				} elseif ( is_int( $v ) || is_float( $v ) ) {
					$xml .= '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
				} else {
					$xml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::x( $v ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		return $xml . '</sheetData><pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>';
	}

	/**
	 * The styles part.
	 */
	private function styles() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;£&quot;#,##0.00"/><numFmt numFmtId="165" formatCode="d mmm yyyy"/></numFmts>'
			. '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="15"/><name val="Calibri"/></font></fonts>'
			. '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFDED4CA"/><bgColor indexed="64"/></patternFill></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFFBEFD5"/><bgColor indexed="64"/></patternFill></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFF3DCD6"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="10">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="164" fontId="0" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top" horizontal="left"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
			. '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
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
			$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', $this->sheet_xml( $sheet[1], $sheet[2] ) );
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
