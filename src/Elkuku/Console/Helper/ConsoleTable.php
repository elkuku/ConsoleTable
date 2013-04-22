<?php
/**
 * Utility for printing tables from commandline scripts.
 *
 * PHP versions 4 and 5
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * o Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote products
 *   derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Console
 * @package    Console_Table
 * @author     Richard Heyes <richard@phpguru.org>
 * @author     Jan Schneider <jan@horde.org>
 * @author     Nikolai Plath <github.com/elkuku>
 * @license    http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @copyright  2002-2005 Richard Heyes
 * @copyright  2006-2008 Jan Schneider
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Console_Table
 */

namespace Elkuku\Console\Helper;

/**
 * The main class.
 *
 * @category  Console
 * @package   Console_Table
 * @author    Jan Schneider <jan@horde.org>
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @link      http://pear.php.net/package/Console_Table
 * @since     ¿
 */
class ConsoleTable
{
	const HORIZONTAL_RULE = 1;

	const ALIGN_LEFT = -1;

	const ALIGN_CENTER = -1;

	const ALIGN_RIGHT = 1;

	const BORDER_ASCII = -1;

	/**
	 * The table headers.
	 *
	 * @var array
	 */
	protected $headers = array();

	/**
	 * The data of the table.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * The maximum number of columns in a row.
	 *
	 * @var integer
	 */
	protected $maxCols = 0;

	/**
	 * The maximum number of rows in the table.
	 *
	 * @var integer
	 */
	protected $maxRows = 0;

	/**
	 * Lengths of the columns, calculated when rows are added to the table.
	 *
	 * @var array
	 */
	protected $cellLengths = array();

	/**
	 * Heights of the rows.
	 *
	 * @var array
	 */
	protected $rowHeights = array();

	/**
	 * How many spaces to use to pad the table.
	 *
	 * @var integer
	 */
	protected $padding = 1;

	/**
	 * Column filters.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Columns to calculate totals for.
	 *
	 * @var array
	 */
	protected $calculateTotals;

	/**
	 * Alignment of the columns.
	 *
	 * @var array
	 */
	protected $colAlign = array();

	/**
	 * Default alignment of columns.
	 *
	 * @var integer
	 */
	protected $defaultAlign;

	/**
	 * Character set of the data.
	 *
	 * @var string
	 */
	protected $charset = 'utf-8';

	/**
	 * Border character.
	 *
	 * @var string
	 */
	protected $border = self::BORDER_ASCII;

	/**
	 * Whether the data has ANSI colors.
	 *
	 * @var boolean
	 */
	protected $ansiColor = false;

	protected $colorStripFunction = null;

	/**
	 * Constructor.
	 *
	 * @param   integer     $align               Default alignment. One of
	 *                                           self::ALIGN_LEFT,
	 *                                           self::ALIGN_CENTER or
	 *                                           self::ALIGN_RIGHT.
	 * @param   integer     $border              The character used for table borders or
	 *                                           self::BORDER_ASCII.
	 * @param   integer     $padding             How many spaces to use to pad the table.
	 * @param   string      $charset             A charset supported by the mbstring PHP
	 *                                           extension.
	 * @param   mixed|null  $colorStripFunction  Whether the data contains ansi color codes.
	 *
	 * @throws \RuntimeException
	 */
	public function __construct($align = self::ALIGN_LEFT, $border = self::BORDER_ASCII, $padding = 1, $charset = null,
		$colorStripFunction = null)
	{
		$this->defaultAlign = $align;
		$this->border       = $border;
		$this->padding      = $padding;

		if ($colorStripFunction)
		{
			if (false == is_callable($colorStripFunction))
			{
				throw new \RuntimeException('Invalid color strip function');
			}

			$this->colorStripFunction = $colorStripFunction;
		}

		if ($this->ansiColor)
		{
			// @@@include_once 'Console/Color.php';
		}

		if (!empty($charset))
		{
			$this->setCharset($charset);
		}
	}

	/**
	 * Converts an array to a table.
	 *
	 * @param   array    $headers       Headers for the table.
	 * @param   array    $data          A two dimensional array with the table data.
	 * @param   boolean  $returnObject  Whether to return the Console_Table object
	 *                                  instead of the rendered table.
	 *
	 * @static
	 *
	 * @return ConsoleTable|string  A Console_Table object or the generated
	 *                               table.
	 */
	public function fromArray($headers, $data, $returnObject = false)
	{
		if (!is_array($headers) || !is_array($data))
		{
			return false;
		}

		$table = new ConsoleTable;
		$table->setHeaders($headers);

		foreach ($data as $row)
		{
			$table->addRow($row);
		}

		return $returnObject ? $table : $table->getTable();
	}

	/**
	 * Adds a filter to a column.
	 *
	 * Filters are standard PHP callbacks which are run on the data before
	 * table generation is performed. Filters are applied in the order they
	 * are added. The callback function must accept a single argument, which
	 * is a single table cell.
	 *
	 * @param   integer  $col        Column to apply filter to.
	 * @param   mixed    &$callback  PHP callback to apply.
	 *
	 * @return void
	 */
	public function addFilter($col, &$callback)
	{
		$this->filters[] = array($col, &$callback);
	}

	/**
	 * Sets the charset of the provided table data.
	 *
	 * @param   string  $charset  A charset supported by the mbstring PHP extension.
	 *
	 * @return void
	 */
	public function setCharset($charset)
	{
		$locale = setlocale(LC_CTYPE, 0);
		setlocale(LC_CTYPE, 'en_US');
		$this->charset = strtolower($charset);
		setlocale(LC_CTYPE, $locale);
	}

	/**
	 * Sets the alignment for the columns.
	 *
	 * @param   integer  $col_id  The column number.
	 * @param   integer  $align   Alignment to set for this column. One of
	 *                            self::ALIGN_LEFT
	 *                            self::ALIGN_CENTER
	 *                            self::ALIGN_RIGHT.
	 *
	 * @return void
	 */
	public function setAlign($col_id, $align = self::ALIGN_LEFT)
	{
		switch ($align)
		{
			case self::ALIGN_CENTER:
				$pad = STR_PAD_BOTH;
				break;
			case self::ALIGN_RIGHT:
				$pad = STR_PAD_LEFT;
				break;
			default:
				$pad = STR_PAD_RIGHT;
				break;
		}

		$this->colAlign[$col_id] = $pad;
	}

	/**
	 * Specifies which columns are to have totals calculated for them and
	 * added as a new row at the bottom.
	 *
	 * @param   array  $cols  Array of column numbers (starting with 0).
	 *
	 * @return void
	 */
	public function calculateTotalsFor($cols)
	{
		$this->calculateTotals = $cols;
	}

	/**
	 * Sets the headers for the columns.
	 *
	 * @param   array  $headers  The column headers.
	 *
	 * @return void
	 */
	public function setHeaders($headers)
	{
		$this->headers = array(array_values($headers));
		$this->updateRowsCols($headers);
	}

	/**
	 * Adds a row to the table.
	 *
	 * @param   array    $row     The row data to add.
	 * @param   boolean  $append  Whether to append or prepend the row.
	 *
	 * @return void
	 */
	public function addRow($row, $append = true)
	{
		if ($append)
		{
			$this->data[] = array_values($row);
		}
		else
		{
			array_unshift($this->data, array_values($row));
		}

		$this->updateRowsCols($row);
	}

	/**
	 * Inserts a row after a given row number in the table.
	 *
	 * If $row_id is not given it will prepend the row.
	 *
	 * @param   array    $row     The data to insert.
	 * @param   integer  $row_id  Row number to insert before.
	 *
	 * @return void
	 */
	public function insertRow($row, $row_id = 0)
	{
		array_splice($this->data, $row_id, 0, array($row));

		$this->updateRowsCols($row);
	}

	/**
	 * Adds a column to the table.
	 *
	 * @param   array    $col_data  The data of the column.
	 * @param   integer  $col_id    The column index to populate.
	 * @param   integer  $row_id    If starting row is not zero, specify it here.
	 *
	 * @return void
	 */
	public function addCol($col_data, $col_id = 0, $row_id = 0)
	{
		foreach ($col_data as $col_cell)
		{
			$this->data[$row_id++][$col_id] = $col_cell;
		}

		$this->updateRowsCols();
		$this->maxCols = max($this->maxCols, $col_id + 1);
	}

	/**
	 * Adds data to the table.
	 *
	 * @param   array    $data    A two dimensional array with the table data.
	 * @param   integer  $col_id  Starting column number.
	 * @param   integer  $row_id  Starting row number.
	 *
	 * @return void
	 */
	public function addData($data, $col_id = 0, $row_id = 0)
	{
		foreach ($data as $row)
		{
			if ($row === self::HORIZONTAL_RULE)
			{
				$this->data[$row_id] = self::HORIZONTAL_RULE;
				$row_id++;
				continue;
			}

			$starting_col = $col_id;

			foreach ($row as $cell)
			{
				$this->data[$row_id][$starting_col++] = $cell;
			}

			$this->updateRowsCols();
			$this->maxCols = max($this->maxCols, $starting_col);
			$row_id++;
		}
	}

	/**
	 * Adds a horizontal seperator to the table.
	 *
	 * @return void
	 */
	public function addSeparator()
	{
		$this->data[] = self::HORIZONTAL_RULE;
	}

	/**
	 * Returns the generated table.
	 *
	 * @return string  The generated table.
	 */
	public function getTable()
	{
		$this->applyFilters();
		$this->calculateTotals();
		$this->validateTable();

		return $this->buildTable();
	}

	/**
	 * Calculates totals for columns.
	 *
	 * @return void
	 */
	public function calculateTotals()
	{
		if (empty($this->calculateTotals))
		{
			return;
		}

		$this->addSeparator();

		$totals = array();

		foreach ($this->data as $row)
		{
			if (is_array($row))
			{
				foreach ($this->calculateTotals as $columnID)
				{
					$totals[$columnID] += $row[$columnID];
				}
			}
		}

		$this->data[] = $totals;
		$this->updateRowsCols();
	}

	/**
	 * Applies any column filters to the data.
	 *
	 * @return void
	 */
	protected function applyFilters()
	{
		if (empty($this->filters))
		{
			return;
		}

		foreach ($this->filters as $filter)
		{
			$column   = $filter[0];
			$callback = $filter[1];

			foreach ($this->data as $row_id => $row_data)
			{
				if ($row_data !== self::HORIZONTAL_RULE)
				{
					$this->data[$row_id][$column] = call_user_func($callback, $row_data[$column]);
				}
			}
		}
	}

	/**
	 * Ensures that column and row counts are correct.
	 *
	 * @return void
	 */
	protected function validateTable()
	{
		if (!empty($this->headers))
		{
			$this->calculateRowHeight(-1, $this->headers[0]);
		}

		for ($i = 0; $i < $this->maxRows; $i++)
		{
			for ($j = 0; $j < $this->maxCols; $j++)
			{
				if (!isset($this->data[$i][$j])
					&& (!isset($this->data[$i])
					|| $this->data[$i] !== self::HORIZONTAL_RULE))
				{
					$this->data[$i][$j] = '';
				}
			}

			$this->calculateRowHeight($i, $this->data[$i]);

			if ($this->data[$i] !== self::HORIZONTAL_RULE)
			{
				ksort($this->data[$i]);
			}
		}

		$this->splitMultilineRows();

		// Update cell lengths.
		for ($i = 0; $i < count($this->headers); $i++)
		{
			$this->calculateCellLengths($this->headers[$i]);
		}

		for ($i = 0; $i < $this->maxRows; $i++)
		{
			$this->calculateCellLengths($this->data[$i]);
		}

		ksort($this->data);
	}

	/**
	 * Splits multiline rows into many smaller one-line rows.
	 *
	 * @return void
	 */
	protected function splitMultilineRows()
	{
		ksort($this->data);
		$sections          = array(&$this->headers, &$this->data);
		$max_rows          = array(count($this->headers), $this->maxRows);
		$row_height_offset = array(-1, 0);

		for ($s = 0; $s <= 1; $s++)
		{
			$inserted = 0;
			$new_data = $sections[$s];

			for ($i = 0; $i < $max_rows[$s]; $i++)
			{
				// Process only rows that have many lines.
				$height = $this->rowHeights[$i + $row_height_offset[$s]];

				if ($height > 1)
				{
					// Split column data into one-liners.
					$split = array();

					for ($j = 0; $j < $this->maxCols; $j++)
					{
						$split[$j] = preg_split('/\r?\n|\r/', $sections[$s][$i][$j]);
					}

					$new_rows = array();

					// Construct new 'virtual' rows - insert empty strings for
					// columns that have less lines that the highest one.
					for ($i2 = 0; $i2 < $height; $i2++)
					{
						for ($j = 0; $j < $this->maxCols; $j++)
						{
							$new_rows[$i2][$j] = !isset($split[$j][$i2])
								? ''
								: $split[$j][$i2];
						}
					}

					// Replace current row with smaller rows.  $inserted is
					// used to take account of bigger array because of already inserted rows.
					array_splice($new_data, $i + $inserted, 1, $new_rows);
					$inserted += count($new_rows) - 1;
				}
			}

			// Has the data been modified?
			if ($inserted > 0)
			{
				$sections[$s] = $new_data;
				$this->updateRowsCols();
			}
		}
	}

	/**
	 * Builds the table.
	 *
	 * @return string  The generated table string.
	 */
	protected function buildTable()
	{
		if (!count($this->data))
		{
			return '';
		}

		$rule      = $this->border == self::BORDER_ASCII ? '|' : $this->border;
		$separator = $this->getSeparator();

		$return = array();

		for ($i = 0; $i < count($this->data); $i++)
		{
			for ($j = 0; $j < count($this->data[$i]); $j++)
			{
				if ($this->data[$i] !== self::HORIZONTAL_RULE
					&& $this->strlen($this->data[$i][$j]) < $this->cellLengths[$j])
				{
					$this->data[$i][$j] = $this->strpad(
						$this->data[$i][$j],
						$this->cellLengths[$j],
						' ',
						$this->colAlign[$j]
					);
				}
			}

			if ($this->data[$i] !== self::HORIZONTAL_RULE)
			{
				$row_begin    = $rule . str_repeat(' ', $this->padding);
				$row_end      = str_repeat(' ', $this->padding) . $rule;
				$implode_char = str_repeat(' ', $this->padding) . $rule . str_repeat(' ', $this->padding);
				$return[]     = $row_begin . implode($implode_char, $this->data[$i]) . $row_end;
			}
			elseif (!empty($separator))
			{
				$return[] = $separator;
			}
		}

		$return = implode("\r\n", $return);

		if (!empty($separator))
		{
			$return = $separator . "\r\n" . $return . "\r\n" . $separator;
		}

		$return .= "\r\n";

		if (!empty($this->headers))
		{
			$return = $this->getHeaderLine() . "\r\n" . $return;
		}

		return $return;
	}

	/**
	 * Creates a horizontal separator for header separation and table
	 * start/end etc.
	 *
	 * @return string  The horizontal separator.
	 */
	protected function getSeparator()
	{
		if (!$this->border)
		{
			return '';
		}

		if ($this->border == self::BORDER_ASCII)
		{
			$rule = '-';
			$sect = '+';
		}
		else
		{
			$rule = $sect = $this->border;
		}

		$return = array();

		foreach ($this->cellLengths as $cl)
		{
			$return[] = str_repeat($rule, $cl);
		}

		$row_begin    = $sect . str_repeat($rule, $this->padding);
		$row_end      = str_repeat($rule, $this->padding) . $sect;
		$implode_char = str_repeat($rule, $this->padding) . $sect . str_repeat($rule, $this->padding);

		return $row_begin . implode($implode_char, $return) . $row_end;
	}

	/**
	 * Returns the header line for the table.
	 *
	 * @return string  The header line of the table.
	 */
	protected function getHeaderLine()
	{
		// Make sure column count is correct
		for ($j = 0; $j < count($this->headers); $j++)
		{
			for ($i = 0; $i < $this->maxCols; $i++)
			{
				if (!isset($this->headers[$j][$i]))
				{
					$this->headers[$j][$i] = '';
				}
			}
		}

		for ($j = 0; $j < count($this->headers); $j++)
		{
			for ($i = 0; $i < count($this->headers[$j]); $i++)
			{
				if ($this->strlen($this->headers[$j][$i]) < $this->cellLengths[$i])
				{
					$this->headers[$j][$i] = $this->strpad(
						$this->headers[$j][$i],
						$this->cellLengths[$i],
						' ',
						$this->colAlign[$i]
					);
				}
			}
		}

		$rule         = $this->border == self::BORDER_ASCII ? '|' : $this->border;
		$rowBegin    = $rule . str_repeat(' ', $this->padding);
		$rowEnd      = str_repeat(' ', $this->padding) . $rule;
		$implodeChar = str_repeat(' ', $this->padding) . $rule . str_repeat(' ', $this->padding);

		$separator = $this->getSeparator();

		$return = array();

		if (!empty($separator))
		{
			$return[] = $separator;
		}

		for ($j = 0; $j < count($this->headers); $j++)
		{
			$return[] = $rowBegin . implode($implodeChar, $this->headers[$j]) . $rowEnd;
		}

		return implode("\r\n", $return);
	}

	/**
	 * Updates values for maximum columns and rows.
	 *
	 * @param   array  $rowData  Data array of a single row.
	 *
	 * @return void
	 */
	protected function updateRowsCols($rowData = null)
	{
		// Update maximum columns.
		$this->maxCols = max($this->maxCols, count($rowData));

		// Update maximum rows.
		ksort($this->data);
		$keys            = array_keys($this->data);
		$this->maxRows = end($keys) + 1;

		switch ($this->defaultAlign)
		{
			case self::ALIGN_CENTER:
				$pad = STR_PAD_BOTH;
				break;
			case self::ALIGN_RIGHT:
				$pad = STR_PAD_LEFT;
				break;
			default:
				$pad = STR_PAD_RIGHT;
				break;
		}

		// Set default column alignments
		for ($i = count($this->colAlign); $i < $this->maxCols; $i++)
		{
			$this->colAlign[$i] = $pad;
		}
	}

	/**
	 * Calculates the maximum length for each column of a row.
	 *
	 * @param   array  $row  The row data.
	 *
	 * @return void
	 */
	protected function calculateCellLengths($row)
	{
		for ($i = 0; $i < count($row); $i++)
		{
			if (!isset($this->cellLengths[$i]))
			{
				$this->cellLengths[$i] = 0;
			}

			$this->cellLengths[$i] = max($this->cellLengths[$i], $this->strlen($row[$i]));
		}
	}

	/**
	 * Calculates the maximum height for all columns of a row.
	 *
	 * @param   integer  $row_number  The row number.
	 * @param   array    $row         The row data.
	 *
	 * @return void
	 */
	protected function calculateRowHeight($row_number, $row)
	{
		if (!isset($this->rowHeights[$row_number]))
		{
			$this->rowHeights[$row_number] = 1;
		}

		// Do not process horizontal rule rows.
		if ($row === self::HORIZONTAL_RULE)
		{
			return;
		}

		for ($i = 0, $c = count($row); $i < $c; ++$i)
		{
			$lines                           = preg_split('/\r?\n|\r/', $row[$i]);
			$this->rowHeights[$row_number] = max($this->rowHeights[$row_number], count($lines));
		}
	}

	/**
	 * Returns the character length of a string.
	 *
	 * @param   string  $str  A multibyte or singlebyte string.
	 *
	 * @return integer  The string length.
	 */
	protected function strlen($str)
	{
		static $mbstring;

		// Strip ANSI color codes if requested.
		if ($this->colorStripFunction)
		{
			$str = call_user_func($this->colorStripFunction, $str);
		}

		// Cache expensive function_exists() calls.
		if (!isset($mbstring))
		{
			$mbstring = function_exists('mb_strwidth');
		}

		if ($mbstring)
		{
			return mb_strwidth($str, $this->charset);
		}

		return strlen($str);
	}

	/**
	 * Returns part of a string.
	 *
	 * @param   string   $string  The string to be converted.
	 * @param   integer  $start   The part's start position, zero based.
	 * @param   integer  $length  The part's length.
	 *
	 * @return string  The string's part.
	 */
	protected function substr($string, $start, $length = null)
	{
		static $mbstring;

		// Cache expensive function_exists() calls.
		if (!isset($mbstring))
		{
			$mbstring = function_exists('mb_substr');
		}

		if (is_null($length))
		{
			$length = $this->strlen($string);
		}

		if ($mbstring)
		{
			$ret = @mb_substr($string, $start, $length, $this->charset);

			if (!empty($ret))
			{
				return $ret;
			}
		}

		return substr($string, $start, $length);
	}

	/**
	 * Returns a string padded to a certain length with another string.
	 *
	 * This method behaves exactly like str_pad but is multibyte safe.
	 *
	 * @param   string   $input   The string to be padded.
	 * @param   integer  $length  The length of the resulting string.
	 * @param   string   $pad     The string to pad the input string with. Must
	 *                            be in the same charset like the input string.
	 * @param   integer  $type    The padding type. One of STR_PAD_LEFT,
	 *                            STR_PAD_RIGHT, or STR_PAD_BOTH.
	 *
	 * @return string  The padded string.
	 */
	protected function strpad($input, $length, $pad = ' ', $type = STR_PAD_RIGHT)
	{
		$mb_length  = $this->strlen($input);
		$sb_length  = strlen($input);
		$pad_length = $this->strlen($pad);

		/* Return if we already have the length. */
		if ($mb_length >= $length)
		{
			return $input;
		}

		/* Shortcut for single byte strings. */
		if ($mb_length == $sb_length && $pad_length == strlen($pad))
		{
			return str_pad($input, $length, $pad, $type);
		}

		$output = '';

		switch ($type)
		{
			case STR_PAD_LEFT:
				$left   = $length - $mb_length;
				$output = $this->substr(
					str_repeat($pad, ceil($left / $pad_length)),
					0, $left, $this->charset
				) . $input;
				break;
			case STR_PAD_BOTH:
				$left   = floor(($length - $mb_length) / 2);
				$right  = ceil(($length - $mb_length) / 2);
				$output = $this->substr(
					str_repeat($pad, ceil($left / $pad_length)),
					0, $left, $this->charset
				) .
					$input .
					$this->substr(
						str_repeat($pad, ceil($right / $pad_length)),
						0, $right, $this->charset
					);
				break;
			case STR_PAD_RIGHT:
				$right  = $length - $mb_length;
				$output = $input .
					$this->substr(
						str_repeat($pad, ceil($right / $pad_length)),
						0, $right, $this->charset
					);
				break;
		}

		return $output;
	}
}
