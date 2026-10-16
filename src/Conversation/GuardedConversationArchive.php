<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use DateTimeImmutable;
use InvalidArgumentException;
use NicheCleverInteractive\Core\Api\V1\Contracts\ConversationArchiveV1;
use NicheCleverInteractive\Core\Api\V1\Value\ArchivedTurnV1;
use RuntimeException;

/**
 * What an extension gets instead of the table: administrator-only, argument-checked, paged.
 */
final class GuardedConversationArchive implements ConversationArchiveV1
{
    private ConversationCriteriaStore $store;
    /** @var callable(): bool */
    private $canManage;

    /** @param callable(): bool|null $canManage Defaults to current_user_can('manage_options'). */
    public function __construct(ConversationCriteriaStore $store, ?callable $canManage = null)
    {
        $this->store = $store;
        $this->canManage = $canManage ?? static fn (): bool => function_exists('current_user_can') && current_user_can('manage_options');
    }

    public function count(?string $profileId, ?string $fromDate, ?string $toDate): int
    {
        $this->authorise();
        [$from, $to] = $this->range($fromDate, $toDate);

        return $this->store->countMatching($this->profile($profileId, true), $from, $to);
    }

    public function page(?string $profileId, ?string $fromDate, ?string $toDate, int $afterId, int $limit): array
    {
        $this->authorise();
        [$from, $to] = $this->range($fromDate, $toDate);
        if ($afterId < 0 || $limit < 1 || $limit > self::MAX_PAGE) {
            throw new InvalidArgumentException('The page request is out of bounds.');
        }

        $turns = [];
        foreach ($this->store->pageMatching($this->profile($profileId, true), $from, $to, $afterId, $limit) as $row) {
            try {
                $turn = $row['turn'];
                $turns[] = new ArchivedTurnV1($row['id'], $turn->conversationId(), $turn->profileId(), $turn->createdAt(), $turn->question(), $turn->answer());
            } catch (InvalidArgumentException $exception) {
                continue;
            }
        }

        return $turns;
    }

    public function delete(string $profileId, ?string $fromDate, ?string $toDate, int $limit): int
    {
        $this->authorise();
        [$from, $to] = $this->range($fromDate, $toDate);
        if ($limit < 1 || $limit > self::MAX_PAGE) {
            throw new InvalidArgumentException('The delete limit is out of bounds.');
        }

        return $this->store->deleteMatching((string) $this->profile($profileId, false), $from, $to, $limit);
    }

    private function authorise(): void
    {
        if (!(bool) call_user_func($this->canManage)) {
            throw new RuntimeException('Managing conversations requires an administrator.');
        }
    }

    private function profile(?string $profileId, bool $nullable): ?string
    {
        if ($profileId === null && $nullable) {
            return null;
        }
        if ($profileId === null || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $profileId) !== 1) {
            throw new InvalidArgumentException('A profile identifier is required and must be well formed.');
        }

        return $profileId;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function range(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate === null ? null : $this->day($fromDate) . ' 00:00:00';
        $to = $toDate === null ? null : $this->day($toDate) . ' 23:59:59';
        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException('The start date is after the end date.');
        }

        return [$from, $to];
    }

    private function day(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Dates use YYYY-MM-DD.');
        }

        return $date;
    }
}
