<?php
namespace Chordsify;

class WriterText implements Writer
{
    public $options = [
        'sections'  => true,
        'chords'    => true,
        'collapse'  => 0,     // 0 = No collapse, 1 = Always collapse, 2+ = Collapse if saves n lines
        'formatted' => true,  // make curly quotes
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    public function song(Song $song, array $sections)
    {
        return implode("\n\n", $sections);
    }

    public function section(Section $section, array $paragraphs)
    {
        $output = '';
        if ($this->options['sections'])
        {
            $output = '['.$section->type.($section->number > 0 ? ' '.$section->number : '')."]\n";
        }

        $output .= implode("\n\n", $paragraphs);
        return $output;
    }

    public function paragraph(Paragraph $paragraph, array $lines)
    {
        $lines = array_filter($lines);
        return implode("\n", $lines);
    }

    public function line(Line $line, array $words)
    {
        return trim(implode($words));
    }

    public function word(Word $word, array $chunks)
    {
        $output = implode($chunks);

        if ($this->options['chords'])
        {
            return $output;
        }

        // Remove the spaces that were only there to separate the chords
        return ltrim($output);
    }

    public function chunk(Chunk $chunk, $chord, $lyrics)
    {
        $output = '';
        if ($this->options['chords'] and $chord) {
            $output = '['.$chord.']';
        }
        return $output.$lyrics;
    }

    public function chord(Chord $chord, array $chordElements)
    {
        return implode($chordElements);
    }

    public function chordRoot(ChordRoot $chordRoot)
    {
        return $chordRoot->root;
    }

    public function chordText(ChordText $chordText)
    {
        return $chordText->content;
    }

    public function lyrics(Lyrics $lyrics)
    {
        if ($this->options['formatted'])
        {
            return $lyrics->formatted_content();
        }

        return $lyrics->content;
    }
}