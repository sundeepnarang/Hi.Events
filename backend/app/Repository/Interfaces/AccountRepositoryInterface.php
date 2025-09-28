<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AccountDomainObject>
 */
interface AccountRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): AccountDomainObject;

    public function findAllAccounts(): Collection;
}
