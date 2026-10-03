<?php

namespace Tests\Base\Ledger\Service;

use Base\Ledger\Exception\LetteringException;
use Base\Ledger\Model\EntryDraft;
use Base\Ledger\Service\Lettering;
use Tests\Base\Ledger\InMemoryLedger;

class LetteringTest extends InMemoryLedger
{
    public function testANoticeAndItsPaymentAreLetteredTogether(): void
    {
        $tenant = $this->party('Jean Dupont', reference: 'U3');
        $notice = $this->notice($tenant, 85000, 'AVIS-1');
        $payment = $this->payment($tenant, 85000, 'p1');

        $letter = $this->lettering()->letter([$notice, $payment]);

        self::assertSame('AAA', $letter);
        self::assertSame('AAA', $notice->getLetter());
        self::assertSame('AAA', $payment->getLetter());
        self::assertNotNull($notice->getLetteredOn());
        self::assertSame([], $this->lettering()->openItems($this->book, $tenant));
    }

    public function testLettersFollowEachOther(): void
    {
        $tenant = $this->party('Jean Dupont', reference: 'U3');
        $this->lettering()->letter([$this->notice($tenant, 100, 'A1'), $this->payment($tenant, 100, 'p1')]);
        self::assertSame('AAB', $this->lettering()->letter([$this->notice($tenant, 200, 'A2'), $this->payment($tenant, 200, 'p2')]));

        self::assertSame('ABA', Lettering::increment('AAZ'));
        self::assertSame('AAAA', Lettering::increment('ZZZ'));
    }

    public function testLinesThatDoNotCancelOutAreRefused(): void
    {
        $tenant = $this->party('Jean Dupont', reference: 'U3');
        $this->expectException(LetteringException::class);
        $this->expectExceptionMessage('balance');
        $this->lettering()->letter([$this->notice($tenant, 85000, 'A1'), $this->payment($tenant, 80000, 'p1')]);
    }

    public function testLinesOfAnotherPartyOrAccountAreRefused(): void
    {
        $one = $this->party('Jean Dupont', reference: 'U3');
        $other = $this->party('Marie Curie', reference: 'U4');
        try {
            $this->lettering()->letter([$this->notice($one, 100, 'A1'), $this->payment($other, 100, 'p1')]);
            self::fail('Lettered two parties together.');
        } catch (LetteringException $e) {
            self::assertStringContainsString('one account', $e->getMessage());
        }

        $supplier = $this->party('Plombier', \Base\Ledger\Enum\PartyKind::SUPPLIER, 'PLB');
        $this->expectException(LetteringException::class);
        $this->lettering()->letter([$this->notice($one, 100, 'A2'), $this->invoice($supplier, 100, 'F1')]);
    }

    public function testALetteredLineIsNotLetteredTwiceButCanBeUnlettered(): void
    {
        $tenant = $this->party('Jean Dupont', reference: 'U3');
        $notice = $this->notice($tenant, 100, 'A1');
        $payment = $this->payment($tenant, 100, 'p1');
        $letter = $this->lettering()->letter([$notice, $payment]);

        try {
            $this->lettering()->letter([$notice, $payment]);
            self::fail('Lettered twice.');
        } catch (LetteringException) {
        }

        self::assertSame(2, $this->lettering()->unletter($letter, $this->book));
        self::assertNull($notice->getLetter());
        self::assertCount(2, $this->lettering()->openItems($this->book, $tenant));
    }

    private function payment(\Base\Ledger\Entity\Party $party, int $amount, string $source)
    {
        $entry = $this->posting()->post((new EntryDraft($this->book, 'BQ', new \DateTimeImmutable('2026-10-05'), 'Virement', $source))
            ->line('512', $amount, 0)
            ->line($party->getAccountNumber(), 0, $amount, $party));

        return $entry->getLines()->last();
    }
}
