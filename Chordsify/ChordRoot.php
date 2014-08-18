<?php
namespace Chordsify;

class ChordRoot extends UnitLeaf
{
    public $root;
    public $relativeRoot;

    public function parse($raw = '', array $options = [])
    {
        $this->root = new Key($raw);
        $this->relativeRoot = $this->root->relativeTo($this->song->originalKey());
        return $this;
    }

    public function transpose($targetKey)
    {
        $this->root->set(($targetKey + $this->relativeRoot) % 12);
        return $this;
    }
}
