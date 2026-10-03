<?php

namespace Tests\Base\Ledger\Report;

use Base\Ledger\Model\EntryDraft;
use Base\Ledger\Report\GeneralLedger;
use Base\Ledger\Report\OpenItems;
use Base\Ledger\Report\TrialBalance;
use Tests\Base\Ledger\InMemoryLedger;

class ReportsTest extends InMemoryLedger
{
    public function testTheTrialBalanceTotalsAndTheResult(): void
    {
        $report = TrialBalance::fromTotals([
            ['number' => '706', 'label' => 'Loyers', 'type' => 'income', 'debit' => 0, 'credit' => 170000],
            ['number' => '411U3', 'label' => 'Dupont', 'type' => 'asset', 'debit' => 170000, 'credit' => 85000],
            ['number' => '512', 'label' => 'Banque', 'type' => 'asset', 'debit' => 85000, 'credit' => 24000],
            ['number' => '615', 'label' => 'Entretien', 'type' => 'expense', 'debit' => 24000, 'credit' => 0],
        ]);

        self::assertSame(['411U3', '512', '615', '706'], array_column($report['rows'], 'number'));
        self::assertSame(['debit' => 279000, 'credit' => 279000, 'debitBalance' => 170000, 'creditBalance' => 170000], $report['totals']);
        self::assertTrue($report['balanced']);
        self::assertSame(146000, $report['result']);
        self::assertSame(-170000, $report['classes']['7']['balance']);
    }

    public function testTheGeneralLedgerRunsTheBalance(): void
    {
        $dupont = $this->party('Jean Dupont', reference: 'U3');
        $this->notice($dupont, 85000, 'A1', '2026-09-01');
        $this->notice($dupont, 85000, 'A2', '2026-10-01');
        $this->posting()->post((new EntryDraft($this->book, 'BQ', new \DateTimeImmutable('2026-10-05'), 'Virement', 'bank:1'))->line('512', 85000, 0)->line('411U3', 0, 85000, $dupont));

        $ledger = GeneralLedger::fromLines(array_values(array_filter($this->allLines(), fn ($l) => '411U3' === $l->getAccount()->getNumber())));

        self::assertCount(1, $ledger);
        self::assertSame([85000, 170000, 85000], array_column($ledger[0]['lines'], 'balance'));
        self::assertSame(85000, $ledger[0]['balance']);
    }

    public function testOpenItemsByParty(): void
    {
        $dupont = $this->party('Jean Dupont', reference: 'U3');
        $martin = $this->party('Claire Martin', reference: 'U4');
        $this->notice($dupont, 85000, 'A1', '2026-09-01');
        $this->notice($dupont, 85000, 'A2', '2026-10-01');
        $this->notice($martin, 62000, 'A3', '2026-10-01');

        $report = OpenItems::fromLines($this->lettering()->openItems($this->book));

        self::assertSame(232000, $report['total']);
        self::assertSame(['Jean Dupont', 'Claire Martin'], array_column($report['parties'], 'name'));
        self::assertSame('2026-09-01', $report['parties'][0]['oldest']->format('Y-m-d'));
    }
}
