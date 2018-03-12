<?php # Csv.php

/**
 * This class formats an array as a CSV file. The primary use is to create CSV files from PHP arrays.
 *
 * @author Aaron Phillips <aaron.t.phillips@gmail.com>
 * @version: 1.1
 * Created: September 3, 2017
 * Last modified: March 11, 2018
 *
 * It uses the rules of value encapsulation and escaping as per RFC 4180 (@see https://www.rfc-editor.org/info/rfc4180):
 * * Values are separate by commas (ASCII #44/%x2C).
 * * Separate records (rows) of data are separated by "\n" newlines (CRLF, a combination of ASCII #13/%x0D and #10/%x0A).
 * * The double quote (ASCII #34/%x22, AKA DQUOTE) is a control character with different functions depending on the context. It is used both to encapsulate values that contain control characters (commas, newlines, and double quotes), and to escape double quote characters in values.
 *
 * Use in practice:
 * This is the format used by Microsoft Excel (probably the most common tool for reading CSV files).
 *
 * Escaping and encapsulation:
 * All values containing commas, double quotes, and newline characters (CRLF) are encapsulated in double quotes (DQUOTE). These are control character with special meaning in the CSV context. Because these characters are all extremely common in most any context, it might be useful to substitute another less common character. Common substitutions are:
 * * Tab characters ("\t") for commas: the tab character (ASCII #09/%x09) is used to separate values. Such files are referred to as TSV (tab-separated value) files, but may otherwise follow the rules of CSV/RFC 4180. Excel uses TSV as a standard format for reading and saving files. This is also common format in Unix environments. Tab character are often used because they refer to tables (tabular) formats so they are often used in the context of databases and forms. Also they are relatively less commonly used than commas, both in computing contexts and generally life (ex: grocery lists).
 * * Backslash character ("\") for escaping: the backslash (ASCII #94/%x5C, AKA reverse solidus) is often used as the escape character, including PHP, Unix environments, and MySQL. This character might be preferable to DQUOTE as an escape character because it is more generally used for escaping and because this reserves DQUOTE for _only_ encapsulating; this requires less awareness of context and makes the file potentially easier to read by humans and by parsing programs. (At least that's my own opinion.)
 *
 * MySQL natively supports exports in CSV, or any other character delimiter, using the SELECT INTO... OUTFILE syntax. The export file is generally used for backup and data dump purposes, so it doesn't have native options for compression or for complex data manipulation prior to writing. 
 *
 * Some notes: 
 * * This loops through a 2-dimensional array and returns the data as a 2-dimensional array in a CSV file (commas encapsulated, etc.)
 *
 * @todo I intended to make every aspect of this customizable. In particular to use separate modes to control whether encapsulation is used vs a simple escape character for all control characters (backslash by default), but in the end I settled for following RFC 4180 with substitute characters. Perhaps someday I'll create a more flexible delimiter separated value class.
 */


class Csv {

	/**
	 * @var array 	$data 				The data array - a 2-dimensional array.
	 * @var string 	$delimiter 			Comma (ASCII #44/%x2C) is the default value delimiter
	 * @var string 	$class 				The HTML class of the element (optional)
	 * @var string 	$encapsulation 		DQUOTE is the default encapsulation character
	 * @var string 	$escape 			DQUOTE is the default escape character
	 * @var array 	$encapsulationArray 	Values containing any of these must be encapsulated. Values include this->delimiter, this->encapsulation, this->escape (may be same as this->encapsulation), and this->lineTerminator.
	 */

	private $data = array();
	private $delimiter = ',';
	private $encapsulation = '"';
	private $escape = '"';
	private $lineTerminator = "\n"; // CRLF ("\n") is the default newline character.
	private $encapsulationArray = array(',', '"', "\n");

	/**
	 * The constructor uses a 2-dimensional array of "rows" with their values
	 *
	 * This only checks whether the variable is an array; it does not test whether it is a 2-dimensional array when the object is instantiated. This could be expensive if it's a big file, so that testing is done row by row.
	 *
	 * @param array $array An array of "row" data
	 *
	 * @throws Exception if $array is not an array
	 */
	function __construct($array) {

		if (!is_array($array)) {
			throw new Exception('This class requires a 2-dimensional array.');
		}

		$this->data = $array; // Set the data
	} // End of __construct()


	/**
	 * Set the delimiter character
	 *
	 * By default the comma is the delimiter (as CSV format), but this class accepts any single character as a delimiter.
	 *
	 * @param string $character The (single) character deliminating values in each row.
	 *
	 * @throws Exception if the parameter is not a string.
	 * @throws Exception of the 
	 */
	function setDelimiter ($character) {
		if (!is_string($character)) {
			throw new Exception("The value delimiter must be a string.", 1);
		}

		if (strlen($character) != 1) {
			throw new Exception("The value delimiter must be a single character.", 1);
		}

		$this->delimiter = $character;
	} // End of setDelimiter()


	/**
	 * Set the encapsulation character
	 *
	 * By default DQUOTE is the delimiter, but this class accepts any single character as a delimiter.
	 *
	 * @param string $character The character used to encapsulate values
	 *
	 * @throws Exception if the parameter is not a single character.
	 */
	function setEncapsulation ($character) {
		if (!is_string($character) OR (strlen($character) != 1) ) {
			throw new Exception("The encapsulation character must be a single character.", 1);
		}

		$this->encapsulation = $character;
	} // End of setEncapsulation()


	/**
	 * Set the escape character
	 *
	 * By default DQUOTE is the escape character, but this class accepts any single character as an escape character.
	 *
	 * @param string $character The escape character.
	 *
	 * @throws Exception if the parameter is not a single character.
	 */
	function setEscape ($character) {
		if (!is_string($character) OR (strlen($character) != 1) ) {
			throw new Exception("The encapsulation character must be a single character.", 1);
		}

		$this->escape = $character;
	} // End of setEscape()


	/**
	 * Set line terminator character
	 *
	 * By default CRLF ("\n") is the line terminator (newline) character, but this class accepts any single character as a delimiter.
	 *
	 * @param string $character The line terminator character.
	 *
	 * @throws Exception if the parameter is not a single character.
	 */
	function setLineTerminator ($character) {
		if (!is_string($character) OR (strlen($character) != 1) ) {
			throw new Exception("The encapsulation character must be a single character.", 1);
		}

		$this->lineTerminator = $character;
	} // End of setLineTerminator()


	/**
	 * Scan the array to confirm it has the proper format and data types.
	 * 
	 * This method reads though the array data to test whether each 'row' of data is an array, and each value within the row is scalar (single-value). An array of errors is returned as at the end; an empty array = no errors.
	 *
	 * @return array An array of illegal values by "row".
	 */
	function illegalValueScan () {

		$errors = array();

		foreach ($this->data AS $key => $row) {

			if (!is_array($row)) {

				// If the row is not an array, add it to the $errors.
				$errors[] = "$key does not contain an array of values.";

			} else {
				foreach ($row AS $value) {

					// PHP does not consider NULL to be scalar (string or integer), so a separate test must be made for NULL values. Get the type of the bad value.
					if (!is_scalar($value) && !is_null($value)) {
						$errors[] = "$key contains an illegal value (type " . gettype($value) . ').';
					}
				} // End of $row FOREACH loop
			}
		} // End of $this->array FOREACH loop

		return $errors;
	} // End of illegalValueScan()


	/**
	 * Convert the array of data to a CSV-formatted string
	 *
	 * This method converts a 2-dimensional tree to a CSV-formatted string. To mirror the organization of a table, each array represents a row of data and is separated by CRLF.
	 *
	 * @return string The CSV-formatted string to export, write to file, etc.
	 *
	 * @throws Exception if "row" of data is not an array.
	 */
	function formatToCsv () {

		// This $csv variable stores the "rows" of data
		$csv = array();

		foreach ($this->data AS $row) {

			// Test to confirm each row is an array, identify the index for the "row" if not.
			if (!is_array($row)) {
				$msg = 'Expecting array for second-level (row) data, error with ' . key($row);
				throw new Exception($msg, 1);
			}

			// This $line variable stores the values for each line
			$line = array();

			foreach ($row AS $value) {
				// Add the formatted value to the $line array
				$line[] = $this->formatValue($value);
			}

			// Create a string out of the array, with each value separated by the value delimiter. By default this is a comma (',').
			$formattedLine = implode($this->delimiter, $line);

			// Push each formatted line to the $cvs array
			$csv[] = $formattedLine;
		}

		// Return a string out of the $csv array (containing each line), separated by the line terminator. By default this is CRLF ("\n").
		return implode($this->lineTerminator, $csv);

	} // End of formatToCsv()


	/**
	 * Format the value.
	 *
	 * Test whether there are any special characters. 
	 * * If not, return the value as is.
	 * * If so, encapsulate the value. If necessary, escape control characters.
	 *
	 * The escape character and the encapsulation character (ex: both DQUOTE by default) must be itself escaped with a preceding escape character: 
	 * * Example with DQUOTE escape: 'It was "really" good.' => "It was ""really"" good."
	 * * Example with backslash escape: 'CRLF is often represented as "\n."' => "CRLF is often represented as \"\\n.\""
	 * 
	 * This could be done with string manipulation functions, but this solution splits the string into an array at each escape character, and glues them together as a string using the escape character at each edge.
	 *
	 * @param string $value The value to test for special characters (and format as necessary).
	 *
	 * @return string The CSV-formatted value.
	 */
	private function formatValue ($value) {

		// If there are no special characters, return this value as is
		if (!$this->findSpecial($value)) { 
			return $value;

		} else { // If there is a special character, encapsulate the value.

			/*
			// Test whether there are any escape characters (which must themselves be escaped prior to encapsulation).
			if (strpos($value, $this->escape) !== FALSE) { 

				// Explode the value into pieces at the escape character, then glue it together with the two escape character (the first character indicating that the second is a string literal).
				$vPieces = explode($this->escape, $value);
				$value = implode($this->escape . $this->escape, $vPieces);
			}
			*/

			// Test whether there are any encapsulation characters (which must be escaped prior to encapsulation).
			if (strpos($value, $this->encapsulation) !== FALSE) { 

				// Explode the value into pieces at the escape character, then glue it together with the two escape character (the first character indicating that the second is a string literal).
				$vPieces = explode($this->encapsulation, $value);
				$value = implode($this->escape . $this->encapsulation, $vPieces); // Note: escape character precedes encapsulation.
			}

			// Encapsulate the value in double quotes (or other encapsulation character) and return it
			return $this->encapsulation . $value . $this->encapsulation;
		}
	} // End of formatValue()


	/**
	 * Test whether a scalar value contains a special character
	 * 
	 * Special character are control characters used in formatting the CSV.
	 *
	 * @param string $value A NULL, integer, or string value.
	 *
	 * @return boolean Returns FALSE if no special characters a present.
	 */
	private function findSpecial ($value) {

		if (is_null($value)) { // Test for NULL
			return FALSE;
		}

		if (is_int($value) or is_numeric($value)) { // Test for integer type or numeric string
			return FALSE;
		}

		if (!is_string($value)) { // If not a NULL or and integer, $value must be a string; throw exception if wrong data type.
			// Throw an exception for non-scalar values
			throw new Exception('Expecting a string, integer, or NULL value', 1);
		}

		// Set the values for the encapsulation array (special character that must be encapsulated).
		$this->encapsulationArray = array(
			$this->delimiter,
			$this->encapsulation,
			$this->escape,
			$this->lineTerminator
		);

		// Loop through the special characters in the encapsulation array and test the (string) $value whether any is present. If so, return TRUE; if not, $value contains no special characters, so return FALSE.
		foreach ($this->encapsulationArray AS $special) {

			if (strpos($value, $special) !== FALSE) {
				return TRUE; // $value contains a special character
			}
		}
		return FALSE; // If none of the special characters are present in $value
	} // End of findSpecial()

} // End of CSV class
