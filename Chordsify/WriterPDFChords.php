<?php
namespace Chordsify;

class WriterPDFChords extends Writer
{
    use PDFStyle;

    public $options = [
        'x'          => NULL,
        'y'          => NULL,
        'condensing' => 100,
        'formatted'  => true,
        'style'      => 'left',
        'printID'    => true,
    ];

    protected $songSheet;

    // Origin
    protected $left;
    protected $top;

    // Keep track of width, height & condensing needed
    protected $width = 0;
    protected $height = 0;
    protected $condensing = 100;

    // Current position related to origin
    protected $x = 0;
    protected $y = 0;
    protected $chordX = 0;

    // If current drawing chords
    protected $chords = null;

    public function __construct(\TCPDF $pdf, array $options = [])
    {
        $this->pdf = $pdf;
        $this->options = array_merge($this->options, $options);
        $this->loadStyleSheet($this->options['style']);

        if (is_null($this->options['y'])) {
            $this->top = $this->pdf->GetY();
        } else {
            $this->top = (int) $this->options['y'];
        }

        if (is_null($this->options['x'])) {
            $this->left = $this->pdf->GetX();
        } else {
            $this->left = (int) $this->options['x'];
        }
    }

    public function initSong(Song $song)
    {
        $this->pdf->setFontStretching(100);
        $this->setStyle('title');

        $text = $song->title;
        if ($this->options['printID'] and ! empty($song->id)) {
            $text = $song->id.$this->style['idSeparator'].$text;
        }
        $this->writeCell($this->x, $this->y + $this->style['lineOffset'], $text);

        $afterTitleY = $this->style['lineHeight'];
        if ($this->style['titleBorder']) {
            $this->drawBorderBottom($this->x, $this->y + $this->style['lineOffset']);
            $afterTitleY = $afterTitleY + $this->style['titleBorderSpace'];
        }

        $this->pdf->setFontStretching($this->options['condensing']);
        $this->moveY($afterTitleY);
    }

    public function song(Song $song, array $sections) {
        /*
        $this->pdf->SetDrawColor(238, 102, 102);
        $this->pdf->Rect(
            $this->left, $this->top,
            $this->width, $this->height
        );
        */

        return [ 'width' => $this->width, 'height' => $this->height, 'condensing' => $this->condensing ];
    }

    public function initSection(Section $section)
    {
        $this->setStyle('lyrics.'.$section->type.'.prefix');

        if ( ! empty($this->style['text'])) {
            $y = $this->y + $this->style['lineOffset'];

            $firstParagraph = $section->children()[0];
            if ($firstParagraph->hasChords) {
                $y += $this->style['chordLineHeight'];
                if ($this->chords !== null) {
                    $y += $this->style['chordLineHeight'];
                }
            } else {
                $y += $this->style['lineHeight'];
            }
            $width = $this->textWidth($this->style['text']);
            $this->writeText($this->x + $this->style['indent'] - $this->style['prefixMargin'] - $width, $y, $this->style['text']);
        }

        $this->setStyle('lyrics.'.$section->type);
    }

    public function section(Section $section, array $paragraphs) {}

    public function initParagraph(Paragraph $paragraph)
    {
        if ($this->chords !== null) {
            if ($paragraph->hasChords) {
                $this->moveY($this->style['chordLineHeight']);
            } else {
                $this->moveY($this->style['lineHeight']);
            }
        }
        $this->chords = $paragraph->hasChords;
    }

    public function paragraph(Paragraph $paragraph, array $lines) {
        $this->chordX = $this->x = 0;
    }

    public function initLine(Line $line)
    {
        if ($this->chords) {
            if ($line->chordsOnly) {
                $this->moveY($this->style['chordLineHeight']);
            }

            $this->moveY($this->style['chordLineHeight']);
        }

        $this->chordX = $this->x = $this->style['indent'];
    }

    public function line(Line $line, array $words)
    {
        if ($this->chordX > $this->x) {
            $this->moveX($this->chordX - $this->x);
        }

        if ($line->chordsOnly) {
            $this->moveY($this->style['lineHeight'] - $this->style['chordLineHeight']);
        } else {
            $this->moveY($this->style['lineHeight']);
        }
    }

    // Detect if this word would collapse with previous chord
    public function initWord(Word $word)
    {
        if ( ! $word->hasChords or $this->chordX <= $this->x) {
            return;
        }

        // Find out how much space is needed to offset this word
        $chunks = $word->children();
        $text = '';
        foreach ($chunks as $chunk) {
            $elements = $chunk->children();

            if ( ! empty($elements['chord']))
                break;

            $lyrics = $elements['lyrics'];
            $text .= $this->options['formatted'] ? $lyrics->formattedContent() : $lyrics->content;
        }

        $this->setFont($this->style['font'], $this->style['fontSize']);

        // Needed space
        $space = $this->chordX - $this->x - $this->textWidth($text);
        if ($space > 0) {
            $this->moveX($space);
        }
    }

    // Return the hanging length of the last chunk
    public function word(Word $word, array $chunks)
    {
        $lastChunk = array_pop($chunks);
        return $lastChunk;
    }

    public function chunk(Chunk $chunk, $chord, $lyrics)
    {
        if ($chunk->last) {
            $this->chordX = $this->x + $chord;
            $this->moveX($lyrics);
        } else {
            if ($lyrics < $chord) {
                $len = $chord - $lyrics;

                // for very small space, just ignore the dash
                if ($len > 1) {
                    // for small space, add more to fit the dash
                    if ($len < 4) {
                        $chord += (4 - $len);
                        $len = 4;
                    }
                    // draw dash
                    $this->dash($this->x + $lyrics, $this->y, $len);
                }
            }

            $this->chordX = $this->x + $chord;
            $this->moveX(max($lyrics, $chord));
        }
    }

    public function chord(Chord $chord, array $chordElements)
    {
        if ( ! $this->chords) return 0;

        $text = implode('', $chordElements);
        $this->setFont($this->style['chordFont'], $this->style['chordFontSize']);
        $y = $this->y + $this->style['chordShift'];
        $this->writeText($this->x, $y, $text);
        return $this->textWidth($text) + $this->style['chordMargin'];
    }

    public function chordRoot(ChordRoot $chordRoot)
    {
        return $chordRoot->formattedText();
    }

    public function chordText(ChordText $chordText)
    {
        return $chordText->content;
    }

    public function lyrics(Lyrics $lyrics)
    {
        $text = $this->options['formatted'] ? $lyrics->formattedContent() : $lyrics->content;
        $this->setFont($this->style['font'], $this->style['fontSize']);
        $y = $this->y + $this->style['lineOffset'];
        $this->writeText($this->x, $y, $text);
        return $this->textWidth($text);
    }

    protected function textWidth($text)
    {
        return $this->pdf->GetStringWidth($text);
    }

    public function pdf()
    {
        return $this->pdf;
    }

    public function moveX($amount) {
        $this->x += $amount;
        if ($this->x > $this->width) {
            $this->width = $this->x;
            $indent = $this->style['indent'];
            $this->condensing = min(
                $this->condensing,
                (int) (($this->style['columnWidth'] - $indent) * 100 / ($this->x - $indent))
            );
        }
        return $this;
    }

    public function moveY($amount) {
        $this->y += $amount;
        if ($this->y > $this->height) {
            $this->height = $this->y;
        }
        return $this;
    }

    public function dash($x, $y, $len) {
        $y += $this->style['lineOffset'] - ($this->style['fontSize']*0.28);

        // Add 20% margin on each side but no more than 1.5pt
        if ($len > 7.5) {
            $margin = 1.5;
        } else {
            $margin = 0.2*$len;
        }
        $x += $margin;
        $len = $len - ($margin*2);

        $dashCount = (int) round($len / 4);

        if ($dashCount < 3) {
            $dash = 0;
        } else {
            $dash = ($len / ($dashCount % 2 == 0 ? $dashCount+1 : $dashCount));
        }

        $this->pdf->Line($this->left + $x, $this->top + $y,
            $this->left + $x + $len, $this->top + $y,
            ['width'=>0.3, 'dash'=>$dash ]);
    }

    // Note: writeText only writes left-aligned
    public function writeText($x, $y, $text) {
        $this->pdf->Text(
            /*                 x */ $this->left + $x,
            /*                 y */ $this->top + $y,
            /*               txt */ $text,
            /*           fstroke */ false,
            /*             fclip */ false,
            /*             ffill */ true,
            /*            border */ 0,
            /*                ln */ 0,
            /*             align */ 'L',
            /*              fill */ false,
            /*              link */ '',
            /*           stretch */ 0,
            /* ignore_min_height */ true,
            /*            calign */ 'L'    // align to font baseline
        );
    }

    public function writeCell($x, $y, $text)
    {
        $this->pdf->SetY($this->top + $y);
        $this->pdf->SetX($this->left + $x);
        $this->pdf->Cell(
            0,                     // width
            0,                     // height
            $text,                 // text
            0,                     // border
            0,                     // cursor after
            $this->style['align'], // align
            false,                 // fill
            '',                    // link
            1,                     // stretch
            true,                  // ignore min-height
            'L'                    // align cell to font baseline
        );
    }

    public function drawBorderBottom($x, $y)
    {
        $borderXBegin = $this->left + $x;
        $borderXEnd = $borderXBegin + $this->style['columnWidth'];
        $borderY = $this->top + $y + $this->style['titleBorderSpace'];
        $this->pdf->Line($borderXBegin, $borderY, $borderXEnd, $borderY, array('color' => array(100, 100, 100)));
    }
}
