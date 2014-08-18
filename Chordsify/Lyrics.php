<?php
namespace Chordsify;

class Lyrics extends UnitLeaf
{
    public $content = '';

    public function parse($raw = '', array $options = [])
    {
        $this->content = $raw;
        return $this;
    }

    public function formattedContent()
    {
        $content = $this->content;
        $content = str_replace('\'', '’', $content);

        return $content;
    }
}
