<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\Money;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The whole sale as one spreadsheet: a row per creator, plus totals grouped by
 * payout currency. Internal use -- this is the file our team keeps, not
 * anything a creator sees.
 */
final class AdminSaleReportExport
{
    private const INK = '18191F';

    private const ACCENT = '2D66AE';

    private const MUTED = '6B7280';

    public function __construct(
        private readonly AdminSaleOverview $overview,
    ) {}

    public function write(Sale $sale): string
    {
        $stub = tempnam(sys_get_temp_dir(), 'sale-overview-');
        $path = $stub.'.xlsx';
        @unlink($stub);

        $options = new Options;
        $options->setColumnWidth(26, 1);
        $options->setColumnWidth(30, 2);
        $options->setColumnWidth(24, 3);
        $options->setColumnWidth(10, 4);
        $options->setColumnWidth(10, 5);
        $options->setColumnWidth(10, 6);
        $options->setColumnWidth(18, 7);
        $options->setColumnWidth(10, 8);
        $options->setColumnWidth(18, 9);
        $options->setColumnWidth(20, 10);

        $writer = new Writer($options);
        $writer->openToFile($path);

        $data = $this->overview->for($sale);

        $writer->addRow(Row::fromValues(['Pitch Innovations — Sale Overview (internal)'], $this->titleStyle()));
        $writer->addRow(Row::fromValues([$sale->name], $this->subtitleStyle()));
        $writer->addRow(Row::fromValues([sprintf(
            '%s to %s  ·  %s',
            $sale->starts_at->format('j M Y'),
            $sale->ends_at->format('j M Y'),
            $data['locked'] ? 'Closed out — figures final' : ($sale->isLive() ? 'Live — figures still moving' : 'Ended — not yet closed out')
        )], $this->mutedStyle()));
        $writer->addRow(Row::fromValues(['']));

        $writer->addRow(Row::fromValues([
            'Creator', 'Email', 'Codes used', 'Units', 'Refunded',
            'Rate', 'Gross', 'Currency', 'Payout', 'Status',
        ], $this->headerStyle()));

        foreach ($data['rows'] as $row) {
            $writer->addRow(Row::fromValues([
                $row['name'],
                $row['email'],
                implode(', ', $row['codes']),
                $row['units'],
                $row['refunded'],
                rtrim(rtrim(number_format($row['rate'] * 100, 2, '.', ''), '0'), '.').'%',
                Money::plain($row['gross'], $row['currency']),
                $row['currency'],
                Money::plain($row['payout'], $row['currency']),
                $row['status'],
            ], $this->bodyStyle()));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['Totals by payout currency'], $this->sectionStyle()));

        /*
         | Grouped by currency rather than summed into one figure: creators are
         | paid in INR or USD depending on where they are (spec 5.4), and a
         | single combined total would be meaningless.
         */
        foreach ($data['totals'] as $currency => $totals) {
            $writer->addRow(Row::fromValues([
                $currency.' creators: '.$totals['creators'],
                'Units: '.$totals['units'],
                'Refunded: '.$totals['refunded'],
                'Gross: '.Money::plain($totals['gross'], $currency),
                'Payout: '.Money::plain($totals['payout'], $currency),
            ], $this->totalStyle()));
        }

        $writer->close();

        return $path;
    }

    public function filename(Sale $sale): string
    {
        return str($sale->name)->slug().'-overview.xlsx';
    }

    private function titleStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(16)->setFontColor(self::INK);
    }

    private function subtitleStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(13)->setFontColor(self::ACCENT);
    }

    private function sectionStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(11)->setFontColor(self::INK);
    }

    private function headerStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(10)
            ->setFontColor(Color::WHITE)->setBackgroundColor(self::INK);
    }

    private function bodyStyle(): Style
    {
        return (new Style)->setFontSize(10)->setFontColor(self::INK);
    }

    private function mutedStyle(): Style
    {
        return (new Style)->setFontSize(10)->setFontColor(self::MUTED);
    }

    private function totalStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(10)->setFontColor(self::INK);
    }
}
