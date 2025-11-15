<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Console\Command;

class ListUsersCommand extends Command
{
    protected $signature = 'user:list
                            {--active-only : Only show active users}
                            {--with-roles : Show current account user role}
                            {--json : Output as JSON}';

    protected $description = 'List all users with optional flags for active status, roles, and JSON output';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $perPage = 1000;
        $search = null;

        // Get paginated Eloquent models
        $usersPaginator = $this->userRepository->getAllUsersWithAccounts($search, $perPage);
        $usersModels = $usersPaginator->getCollection();

        // Convert to UserDomainObject manually
        $users = $usersModels->map(function ($userModel) {
            /** @var \HiEvents\Models\User $userModel */
            $user = new UserDomainObject();

            $user->setId($userModel->id);
            $user->setFirstName($userModel->first_name);
            $user->setLastName($userModel->last_name);
            $user->setEmail($userModel->email);

            // Set accounts if loaded
            if ($userModel->relationLoaded('accounts')) {
                $user->setAccounts($userModel->accounts);
            }

            // Set current account user if relation loaded
            if (method_exists($userModel, 'currentAccountUser') && $userModel->relationLoaded('currentAccountUser')) {
                $user->setCurrentAccountUser($userModel->currentAccountUser);
            }

            return $user;
        });

        // Filter active users if flag set
        if ($this->option('active-only')) {
            $users = $users->filter(fn(UserDomainObject $user) => $user->getStatus() === 'active');
        }

        // Prepare output
        $output = $users->map(function (UserDomainObject $user) {
            $data = [
                'id' => $user->getId(),
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
            ];

            if ($this->option('with-roles')) {
                $currentAccountUser = $user->getCurrentAccountUser();
                $data['role'] = $currentAccountUser ? $currentAccountUser->role : null;
            }

            return $data;
        });

        // Output JSON or table
        if ($this->option('json')) {
            $this->line($output->toJson(JSON_PRETTY_PRINT));
        } else {
            $headers = ['ID', 'Name', 'Email', 'Status'];
            if ($this->option('with-roles')) {
                $headers[] = 'Role';
            }

            $rows = $output->map(fn($user) => array_values($user))->toArray();
            $this->table($headers, $rows);
        }

        return self::SUCCESS;
    }
}
