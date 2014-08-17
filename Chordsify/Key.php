<?php
namespace Chordsify;

class Key
{
	static public $sharp_keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
	static public $flat_keys = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];
	static public $map = [
		"C#" => 1,
		"C"  => 0,
		"Db" => 1,
		"D#" => 3,
		"D"  => 2,
		"Eb" => 3,
		"E"  => 4,
		"F#" => 6,
		"F"  => 5,
		"Gb" => 6,
		"G#" => 8,
		"G"  => 7,
		"Ab" => 8,
		"A#" => 10,
		"A"  => 9,
		"Bb" => 10,
		"B"  => 11
	];
	protected $_value = null;

	public static function value($k)
	{
		if (is_null($k))
			return null;

		if (is_numeric($k))
			return (int) $k % 12;

		if (is_object($k) and get_class($k) == __CLASS__)
			return $k->_value;

		$k = ucwords(trim($k));

		if (array_key_exists($k, self::$map))
			return self::$map[$k];

		// Invalid key
		return null;
	}

	public static function text($value, $flat = false)
	{
		$keys = $flat ? self::$flat_keys : self::$sharp_keys;
		return $keys[$value];
	}

	public function set($k)
	{
		$k = $this->value($k);
		$this->_value = $k;

		return $this;
	}

	public function relativeTo($original_key)
	{
		if (is_null($this->_value) or is_null($original_key->_value)) {
			return $this->_value;
		}

		return ((12 + $this->_value) - $original_key->_value) % 12;
	}

	function __construct($k)
	{
		$this->set($k);
	}

	public static function factory($k)
	{
		return new self($k);
	}

	public function __toString()
	{
		return $this->text($this->_value);
	}
}
