<?php

namespace Tests\Base\Ledger\EventListener;

use Base\Ledger\Entity\Entry;
use Base\Ledger\Entity\EntryLine;
use Base\Ledger\EventListener\ValidatedEntryGuard;
use Base\Ledger\Exception\LockedEntryException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Tests\Base\Ledger\InMemoryLedger;

class ValidatedEntryGuardTest extends InMemoryLedger
{
    /**
     * @param list<object>                $updates
     * @param array<int, array>           $changes   spl_object_id => change set
     * @param list<object>                $insertions
     * @param list<object>                $deletions
     */
    private function flush(array $updates = [], array $changes = [], array $insertions = [], array $deletions = [], array $validatedBefore = []): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn($deletions);
        $unitOfWork->method('getEntityChangeSet')->willReturnCallback(fn (object $entity) => $changes[spl_object_id($entity)] ?? []);
        $unitOfWork->method('isScheduledForInsert')->willReturnCallback(fn (object $entity) => \in_array($entity, $insertions, true));
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(fn (object $entity) => ['validatedAt' => \in_array($entity, $validatedBefore, true) ? new \DateTimeImmutable() : null]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        (new ValidatedEntryGuard())->onFlush(new OnFlushEventArgs($em));
    }

    private function entry(bool $validate = true): Entry
    {
        $tenant = $this->party('Jean Dupont', reference: 'U3');

        return $this->notice($tenant, 100, 'A'.\count($this->entries))->getEntry();
    }

    public function testLetteringAValidatedEntrysLinesIsAllowed(): void
    {
        $entry = $this->entry();
        $line = $entry->getLines()->first();
        $this->flush([$line], [spl_object_id($line) => ['letter' => [null, 'AAA'], 'letteredOn' => [null, new \DateTimeImmutable()]]], validatedBefore: [$entry]);
        $this->addToAssertionCount(1);
    }

    public function testChangingAValidatedEntrysLineIsRefused(): void
    {
        $entry = $this->entry();
        $line = $entry->getLines()->first();
        $this->expectException(LockedEntryException::class);
        $this->flush([$line], [spl_object_id($line) => ['debit' => [100, 200]]], validatedBefore: [$entry]);
    }

    public function testChangingOrDeletingAValidatedEntryIsRefused(): void
    {
        $entry = $this->entry();
        try {
            $this->flush([$entry], [spl_object_id($entry) => ['label' => ['a', 'b']]], validatedBefore: [$entry]);
            self::fail('Changed a validated entry.');
        } catch (LockedEntryException) {
        }
        try {
            $this->flush(deletions: [$entry->getLines()->first()], validatedBefore: [$entry]);
            self::fail('Deleted a validated line.');
        } catch (LockedEntryException) {
        }
        $this->expectException(LockedEntryException::class);
        $this->flush(deletions: [$entry], validatedBefore: [$entry]);
    }

    public function testAddingALineToAValidatedEntryIsRefused(): void
    {
        $entry = $this->entry();
        $line = new EntryLine($entry, $this->accounts['512'], 1, 0);
        $this->expectException(LockedEntryException::class);
        $this->flush(insertions: [$line], validatedBefore: [$entry]);
    }

    public function testADraftOrAnEntryBeingWrittenIsFree(): void
    {
        $entry = $this->entry();
        $line = $entry->getLines()->first();
        // A draft (validatedAt was null when loaded), being validated now.
        $this->flush([$entry, $line], [spl_object_id($entry) => ['validatedAt' => [null, new \DateTimeImmutable()]], spl_object_id($line) => ['debit' => [1, 2]]]);
        // A new entry and its lines.
        $this->flush(insertions: [$entry, $line]);
        $this->addToAssertionCount(1);
    }
}
