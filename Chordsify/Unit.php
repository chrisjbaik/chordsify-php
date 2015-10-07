<?php
namespace Chordsify;

abstract class Unit {
    protected $children = array();
    protected $song;

    abstract public function parse($raw = '', array $options = []);

    // Note: $parent is optional for Song
    public function __construct($raw = '', $parent = null, array $options = [])
    {
        if ($parent instanceof Unit) {
            if ($parent instanceof Song) {
                $this->song = $parent;
            } elseif ($parent->song instanceof Song) {
                $this->song = $parent->song;
            }
        } else {
            if (is_array($parent) and empty($options)) {
                // Assuming parent is skipped
                $options = $parent;
            }
        }

        $this->parse($raw, $options);
    }

    public function transpose($targetKey)
    {
        foreach ($this->children as $child) {
            $child->transpose($targetKey);
        }
        return $this;
    }

    public function write(Writer $writer)
    {
        $classes = explode('\\', get_class($this));
        $unitName = end($classes);
        if ($writer->{'init'.$unitName}($this) === false)
            return NULL;

        $unitName = lcfirst($unitName);

        if ($this instanceof UnitLeaf)
            return $writer->$unitName($this);

        $children = array();
        foreach ($this->children as $child)
        {
            $children[] = $child->write($writer);
        }

        return $writer->$unitName($this, $children);
    }

    public function text(array $options = [])
    {
        return $this->write(new WriterText($options));
    }

    public function children()
    {
        return $this->children;
    }

    public function __toString()
    {
        return $this->text();
    }
}
