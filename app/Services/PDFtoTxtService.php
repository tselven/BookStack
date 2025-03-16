<?php
namespace App\Services;
use Smalot\PdfParser\Parser;
class PDFtoTxtService
{
    public function convert($pdfPath)
    {
        $parser = new Parser();
        $pdf = $parser->parseFile(storage_path("app/{$pdfPath}"));

        // Extract text per page
        $pages = $pdf->getPages();
        $pageTexts = [];

        foreach ($pages as $index => $page) {
            $pageTexts['Page ' . ($index + 1)] = trim($page->getText());
        }

        return response()->json([
            'pages' => $pageTexts,
        ]);
    }
}