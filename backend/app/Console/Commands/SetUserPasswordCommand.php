<?php

namespace HiEvents\Console\Commands;

use Exception;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Hashing\HashManager;
use Psr\Log\LoggerInterface;

class SetUserPasswordCommand extends Command
{
    protected $signature = 'user:set-password {userId : The ID of the user} {password : The new password}';

    protected $description = 'Set a new password for a user by their ID.';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly HashManager             $hashManager,
        private readonly LoggerInterface         $logger,
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->argument('userId');
        $password = $this->argument('password');

        // Validate password strength
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return self::FAILURE;
        }

        try {
            $user = $this->userRepository->findById((int)$userId);
        } catch (Exception $exception) {
            $this->error("Error finding user with ID: $userId" . " Message: " . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Found user: {$user->getFullName()} ({$user->getEmail()})");
        $this->newLine();

        // Check if new password is same as current password
        if ($this->hashManager->check($password, $user->getPassword())) {
            $this->error('New password must be different from the current password.');
            return self::FAILURE;
        }

        if (!$this->confirm('Confirm setting new password for this user?', false)) {
            $this->info('Operation cancelled.');
            return self::FAILURE;
        }

        try {
            $this->userRepository->updateWhere(
                attributes: [
                    'password' => $this->hashManager->make($password),
                ],
                where: [
                    'id' => $userId,
                ]
            );

            $this->logger->info('Password changed via console command', [
                'user_id' => $userId,
                'user_email' => $user->getEmail(),
                'command' => $this->signature,
            ]);

            $this->newLine();
            $this->info("✓ Successfully set new password for user: {$user->getFullName()}");

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error("Failed to update password: " . $exception->getMessage());

            $this->logger->error('Failed to change password via console command', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
