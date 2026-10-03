<?php

namespace Tests\Base\Ledger\Export;

use Base\Ledger\Entity\FiscalYear;
use Base\Ledger\Export\FecWriter;
use Base\Ledger\Model\EntryDraft;
use Tests\Base\Ledger\InMemoryLedger;

class FecWriterTest extends InMemoryLedger
{
    private function writer(): FecWriter
    {
        return new FecWriter($this->entryRepository(), $this->accountRepository());
    }

    public function testTheEighteenColumnsOfEachValidatedLine(): void
    {
        $dupont = $this->party('Jean Dupont', reference: 'U3');
        $notice = $this->notice($dupont, 123456, 'AVIS-2026-10-U3', '2026-10-01');
        $payment = $this->posting()->post((new EntryDraft($this->book, 'BQ', new \DateTimeImmutable('2026-10-06'), "Virement\tDupont | octobre", 'bank:1'))
            ->line('512', 123456, 0)->line('411U3', 0, 123456, $dupont));
        $this->lettering()->letter([$notice, $payment->getLines()->last()], new \DateTimeImmutable('2026-10-07'));
        $this->posting()->post((new EntryDraft($this->book, 'OD', new \DateTimeImmutable('2026-10-08'), 'Brouillon', 'draft:1'))->line('615', 100, 0)->line('512', 0, 100), false);
        $this->posting()->post((new EntryDraft($this->book, 'OD', new \DateTimeImmutable('2027-01-08'), 'Exercice suivant', 'next:1'))->line('615', 100, 0)->line('512', 0, 100));

        $fec = $this->writer()->write($this->book, FiscalYear::calendar($this->book, 2026));
        $rows = explode("\r\n", rtrim($fec, "\r\n"));

        self::assertSame(implode("\t", FecWriter::COLUMNS), $rows[0]);
        self::assertCount(5, $rows); // the header, two entries of two lines; the draft and 2027 left out
        foreach ($rows as $row) {
            self::assertCount(18, explode("\t", $row));
            self::assertStringNotContainsString('|', $row);
        }

        self::assertSame(['VT', 'Ventes', '2026-000001', '20261001', '411', 'Locataires', '411U3', 'Jean Dupont', 'AVIS-2026-10-U3', '20261001', 'Loyer Jean Dupont', '1234,56', '0,00', 'AAA', '20261007', date('Ymd'), '', ''], explode("\t", $rows[1]));
        self::assertSame(['706', 'Loyers', '', ''], \array_slice(explode("\t", $rows[2]), 4, 4));
        $bank = explode("\t", $rows[3]);
        self::assertSame(['BQ', '2026-000002', '512', '', '2026-000002', 'Virement Dupont octobre', '1234,56', '0,00', ''], [$bank[0], $bank[2], $bank[4], $bank[6], $bank[8], $bank[10], $bank[11], $bank[12], $bank[13]]);
    }

    public function testTheFileNameIsTheSirenAndTheClosingDate(): void
    {
        self::assertSame('123456789FEC20261231.txt', FecWriter::filename($this->book, FiscalYear::calendar($this->book, 2026)));
        self::assertSame('1234,56', FecWriter::amount(123456));
        self::assertSame('0,05', FecWriter::amount(5));
        self::assertSame('-12,00', FecWriter::amount(-1200));
    }
}
