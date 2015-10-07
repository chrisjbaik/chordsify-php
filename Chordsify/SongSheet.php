<?php
namespace Chordsify;

class SongSheet
{
    use PDFStyle;

    public $debug = false;
    protected $options = [
        // For layout
        'copies'     => 'auto',   // Auto: 2 copies for 1-2 columns, 1 copies for 3+
        'columns'    => 2,        // Columns per page
        'size'       => 'Letter', // Size of the paper (e.g. A4 or Letter)
        'autonumber' => false,
        'pagenumber' => 'auto',

        // Text options
        'chords'    => false,
        'formatted' => true,     // make curly quotes
    ];

    protected $pdf;
    protected $songs = [];

    protected $pageWidth;        // Page width
    protected $pageHeight;       // Page height
    protected $topY;             // Top Y for the content
    protected $bottomY;          // Lower Y for the content
    protected $gutter;           // Column gutter

    protected $column;           // Current column
    protected $y;                // Current Y position

    protected $generated = false;

    public function __construct(array $options = [])
    {
        if (isset($options['debug'])) {
            $this->debug = (bool) $this->debug;
            unset($options['debug']);
        }
        $this->options = array_merge($this->options, $options);
        if (empty($this->options['style'])) {
            $this->options['style'] = $this->options['chords'] ? 'left' : 'center';
        }

        $this->loadStyleSheet($this->options['style']);
        $this->setStyle('');

        // Initialize PDF
        $pdf = new \TCPDF('P', 'pt' /* unit */, $this->options['size'], true, 'UTF-8', false);

        // Set up info
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Psalted.com');
        $pdf->setLanguageArray(array( // English
            'a_meta_charset'  => 'UTF-8',
            'a_meta_dir'      => 'ltr',
            'a_meta_language' => 'en',
            'w_page'          => 'page',
        ));

        // Read page dimensions
        $this->pageWidth  = $pdf->getPageWidth();
        $this->pageHeight = $pdf->getPageHeight();
        $this->topY       = $this->style['pageMargin'];
        $this->bottomY    = $this->pageHeight - $this->style['pageMargin'];

        // Set up page
        $pdf->setCellPaddings(0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setPageOrientation('P', false /* No auto page-break */, $this->topY /* top margin */);

        // Set up columns
        $columns = $this->calculateColumns();
        $pdf->setColumnsArray($columns);
        $pdf->SetMargins($this->gutter, 0);

        // Only embed characters in used

        // Turns out we cannot use font-subsetting since it causes problems to some printers (See chrisjbaik/psalted #19)
        $pdf->setFontSubsetting(false);

        $this->pdf = $pdf;
    }

    protected function addPage()
    {
        $this->pdf->AddPage();

        if ($this->debug) {
            $this->drawGrid();
        }

        $this->pdf->selectColumn(0);
        $this->column = 0;
        $this->y = $this->style['pageMargin'];

        if ($this->options['pagenumber']) {
            $this->writePageNumber();
        }

        $this->pdf->SetX($this->gutter);
        $this->pdf->SetY($this->y);
        return $this;
    }

    // Calculate column array
    protected function calculateColumns()
    {
        $columns = (int) $this->options['columns'];

        // Shifts the page for odd and even pages for 3 hole punching margin
        if (!empty($this->style['binderMargin']) && $this->style['binderMargin']) {
          $colSpace = ($this->pageWidth - $this->style['binderMargin']) / $columns;
          $gutter = ($colSpace - $this->style['columnWidth']) / 2;
        } else {
          $colSpace = $this->pageWidth / $columns;
          $gutter = ($colSpace - $this->style['columnWidth']) / 2;
        }
        $this->gutter = $gutter;

        $cols = array();

        for ($i = 0; $i < $columns; $i++) {
            $cols[] = [
                'w' => $this->style['columnWidth'],
                's' => ($i == $columns-1) ? $gutter : $gutter * 2,
                'y' => 0,
            ];
        }

        return $cols;
    }

    public function add($song)
    {
        if ( ! $song instanceof Song)
            throw new Exception('Not a Chordsify\Song object');

        $this->songs[] = $song;
        return $this;
    }

    public function songs()
    {
        return $this->songs;
    }

    protected function nextColumn()
    {
        $this->column++;

        if ($this->column >= $this->options['columns']) {
            $this->addPage();
        } else {
            $this->pdf->selectColumn($this->column);
            $this->y = $this->topY;
        }

        return $this;
    }

    public function writeSong($song, $options) {
        $colWidth = $this->style['columnWidth'] + ($this->gutter*2);

        // Extend default options
        $options = array_merge([
            'x'          => $this->column * $colWidth + $this->gutter,
            'y'          => $this->y,
            'collapse'   => 0,
            'condensing' => 100,
            'chords'     => $this->options['chords'],
            'formatted'  => $this->options['formatted'],
            'style'      => $this->options['style'],
        ], $options);

        // Shifts the page for odd and even pages for 3 hole punching margin
        if (!empty($this->style['binderMargin']) && $this->style['binderMargin']) {
          if ($this->pdf->PageNo() % 2) {
            // Only for odd pages, give left margin
            $options['x'] = $this->style['binderMargin'] + ($this->column * $colWidth + $this->gutter);
          }
        }

        $options['y'] += $this->topY;

        if ($this->options['chords']) {
            $writer = new WriterPDFChords($this->pdf, $options);
        } else {
            $writer = new WriterPDF($this->pdf, $options);
        }
        $song->write($writer);
    }

    protected function writePageNumber()
    {
        $this->setStyle('pagenumber');
        $this->pdf->Text(
            /*       x */ $this->pageWidth / 2,
            /*       y */ $this->bottomY,
            /*    text */ $this->pdf->PageNo(),
            /* fstroke */ false,
            /*   fclip */ false,
            /*   ffill */ true,
            /*  border */ 0,
            /*      ln */ '',
            /*   align */ 'C',
            /*    fill */ false,
            /*    link */ '',
            /* stretch */ 0,
                          true // ignore min-height
        );
    }

    // For debugging
    protected function drawGrid()
    {
        // Column boxes
        $this->pdf->SetDrawColor(238, 102, 102);
        for ($i=0; $i < $this->options['columns']; $i++) {
            $x = ($i * $this->style['columnWidth']) + ($this->gutter * ($i * 2 + 1));
            $this->pdf->Rect(
                $x, $this->topY,
                $this->style['columnWidth'], $this->bottomY
            );
        }
    }

    protected function countPages()
    {
        $fitter = new SongSheetFitter($this->pdf(), $this->bottomY - $this->topY, $this->options);
        $printSongs = $fitter->fit($this->songs);
        return (int) ceil(count($printSongs) / $this->options['columns']);
    }

    protected function generate()
    {
        $fitter = new SongSheetFitter($this->pdf(), $this->bottomY - $this->topY, $this->options);
        $printSongs = $fitter->fit($this->songs);

        if ($this->options['autonumber']) {
            $autonumber = $this->options['autonumber'];
            $i = 1;
            foreach ($printSongs as $col => $colSongs) {
                foreach ($colSongs as $songData) {
                    if (is_callable($autonumber)) {
                        $id = $autonumber($i, $col, $songData['song']);
                    } else {
                        $id = $i;
                    }
                    $songData['song']->id = $id;
                    $i++;
                }
            }
        }

        $columns = count($printSongs);

        // Calculate auto copies
        // 2 copies for results with 1-2 columns
        if ($this->options['copies'] == 'auto') {
            $copies = $columns < 3 ? 2 : 1;
        } else {
            $copies = (int) $this->options['copies'];
        }

        // Auto page-numbering
        // Enables for 3 or more pages
        if ($this->options['pagenumber'] === 'auto') {
            $this->options['pagenumber'] = ($columns > 4);
        } elseif ($columns <= 2) {
            $this->options['pagenumber'] = false;
        }

        // Duplicate columns for single column result
        if ($columns == 1 and $copies > 1) {
            while (count($printSongs) < $copies) {
                $printSongs[] = $printSongs[0];
            }
        }

        $this->addPage();
        foreach ($printSongs as $col => $colSongs) {
            if ($col > 0) {
                $this->nextColumn();
            }

            foreach ($colSongs as $songData) {
                $this->writeSong($songData['song'], $songData);
            }
        }

        // Make copies of the page
        if ($columns > 1 and $copies > 1) {
            $lastPage = $this->pdf->PageNo();

            for ($i = 1; $i < $copies; $i++) {
                for ($p = 1; $p <= $lastPage; $p++) {
                    $this->pdf->copyPage($p);
                }
            }
        }

        $this->generated = true;
        return $this;
    }

    public function setFontStretching($percent = 100)
    {
        $this->pdf->setFontStretching((int) $percent);
        return $this;
    }

    public function pdf()
    {
        return $this->pdf;
    }

    public function pdfOutput($dest = 'I', $filename = 'songsheet.pdf')
    {
        if ( ! $this->generated) {
            $this->generate();
        }
        return $this->pdf->Output($filename, $dest);
    }
}
