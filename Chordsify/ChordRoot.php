<?php
namespace Chordsify;

class ChordRoot extends UnitLeaf
{
    protected $root;
    protected $relativeValue;

    public function parse($raw = '', array $options = [])
    {
        $this->root = new Key($raw);
        $this->calculateRelativeValue();
        return $this;
    }

    public function calculateRelativeValue()
    {
        $this->relativeValue = $this->root->relativeTo($this->song->originalKey());
        return $this;
    }

    public function transpose($targetKey)
    {
        $this->root->set(($targetKey + $this->relativeValue) % 12);
        return $this;
    }

    public function value()
    {
        return $this->root->value();
    }

    public function relativeValue()
    {
        return $this->relativeValue;
    }

    public function text(array $options = [])
    {
        return $this->root->text($this->song->originalKey()->isFlatScale());
    }

    public function formattedText()
    {
        return $this->root->formattedText($this->song->originalKey()->isFlatScale());
    }
}
