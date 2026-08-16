<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The Excel download from spec section 5.7: "the summary plus the full orders
 * list, matching the on-screen columns."
 *
 * Both halves come from the same services the dashboard renders from
 * (SaleSummaryService and OrderTable), so a creator who downloads the file
 * cannot end up with different numbers or different masking than the page they
 * downloaded it from.
 *
 * Written with openspout rather than PhpSpreadsheet: it streams row by row, so
 * a creator with a few thousand orders does not build the whole workbook in
 * memory on a shared host.
 */
final class SaleReportExport
{
    /** Pitch Innovations brand ink, from the logo and the website. */
    private const INK = '18191F';

    private const ACCENT = '2D66AE';

    private const MUTED = '6B7280';

    private const RULE = 'D9DBE1';

    public function __construct(
        private readonly SaleSummaryService $summaries,
        private readonly OrderTable $table,
    ) {}

    /**
     * Write the report to a temporary file and return its path. The caller is
     * responsible for sending it and cleaning it up.
     */
    public function write(User $user, Sale $sale): string
    {
        // tempnam creates the file it names; since the writer needs a .xlsx
        // path, the placeholder is removed so it does not linger in temp.
        $stub = tempnam(sys_get_temp_dir(), 'affiliate-report-');
        $path = $stub.'.xlsx';
        @unlink($stub);

        $options = new Options;
        $options->setColumnWidth(6, 1);   // S No
        $options->setColumnWidth(18, 2);  // Order ID
        $options->setColumnWidth(20, 3);  // Order Date/Time
        $options->setColumnWidth(22, 4);  // Name
        $options->setColumnWidth(16, 5);  // Code
        $options->setColumnWidth(18, 6);  // Country
        $options->setColumnWidth(18, 7);  // State
        $options->setColumnWidth(18, 8);  // Plugin
        $options->setColumnWidth(10, 9);  // Currency
        $options->setColumnWidth(14, 10); // Amount

        $writer = new Writer($options);
        $writer->openToFile($path);

        $this->writeHeader($writer, $user, $sale);
        $this->writeSummary($writer, $user, $sale);
        $this->writeOrders($writer, $user, $sale);

        $writer->close();

        return $path;
    }

    /**
     * The filename a creator sees in their downloads folder.
     */
    public function filename(User $user, Sale $sale): string
    {
        return sprintf(
            '%s-affiliate-report-%s.xlsx',
            str($sale->name)->slug(),
            str($user->firstName())->slug(),
        );
    }

    private function writeHeader(Writer $writer, User $user, Sale $sale): void
    {
        $writer->addRow(Row::fromValues(['Pitch Innovations — Affiliate Report'], $this->titleStyle()));
        $writer->addRow(Row::fromValues([$sale->name], $this->subtitleStyle()));
        $writer->addRow(Row::fromValues([
            sprintf(
                '%s to %s  ·  %s',
                $sale->starts_at->format('j M Y'),
                $sale->ends_at->format('j M Y'),
                $sale->hasEnded() ? 'Ended — final' : 'Live — figures still moving'
            ),
        ], $this->mutedStyle()));
        $writer->addRow(Row::fromValues(['Affiliate: '.$user->name], $this->mutedStyle()));
        $writer->addRow(Row::fromValues(['']));
    }

    private function writeSummary(Writer $writer, User $user, Sale $sale): void
    {
        $summary = $this->summaries->for($user, $sale);

        $writer->addRow(Row::fromValues(['Summary'], $this->sectionStyle()));

        foreach ($summary->rows() as $row) {
            $style = $row['emphasis']
                ? $this->totalStyle()
                : ($row['muted'] ? $this->mutedStyle() : $this->bodyStyle());

            $writer->addRow(Row::fromValues([$row['label'], $row['value']], $style));
        }

        $writer->addRow(Row::fromValues(['']));
    }

    private function writeOrders(Writer $writer, User $user, Sale $sale): void
    {
        $writer->addRow(Row::fromValues(['Orders'], $this->sectionStyle()));
        $writer->addRow(Row::fromValues(OrderTable::COLUMNS, $this->tableHeaderStyle()));

        $serial = 1;

        /*
         | Chunked so the memory footprint stays flat no matter how big the
         | sale was. Ordered oldest first here -- a report reads better
         | chronologically than the newest-first the live dashboard uses.
         */
        Order::query()
            ->with('couponCode')
            ->where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->orderBy('placed_at')
            ->orderBy('id')
            ->chunk(500, function ($orders) use ($writer, &$serial) {
                foreach ($this->table->rows($orders, $serial) as $row) {
                    $writer->addRow(Row::fromValues([
                        $row['serial'],
                        $row['order_id'],
                        $row['placed_at'],
                        $row['name'],
                        $row['code'],
                        $row['country'],
                        $row['state'],
                        $row['plugin'],
                        $row['currency'],
                        $row['amount'],
                    ], $row['is_refunded'] ? $this->refundedStyle() : $this->bodyStyle()));

                    $serial++;
                }
            });
    }

    // --- Styles ----------------------------------------------------------

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
        return (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(self::INK)
            ->setBorder(new Border(
                new BorderPart(Border::TOP, self::RULE, Border::WIDTH_THIN, Border::STYLE_SOLID)
            ));
    }

    private function tableHeaderStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(self::INK)
            ->setCellAlignment(CellAlignment::LEFT);
    }

    /** Refunded rows are greyed rather than hidden -- they are still part of the record. */
    private function refundedStyle(): Style
    {
        return (new Style)->setFontSize(10)->setFontColor(self::MUTED)->setFontItalic();
    }
}
