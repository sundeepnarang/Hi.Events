<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands;

use Exception;
use HiEvents\Services\Application\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateAccountCommand extends Command
{
    protected $signature = 'account:create
        {email : Account owner email}
        {password : Account owner password}
        {first_name : Account owner first name}
        {--last_name= : Account owner last name}
        {--timezone= : Account timezone}
        {--currency_code= : Account currency code}
        {--locale= : Locale (e.g. en_US)}
        {--invite_token= : Encrypted invite token}';

    protected $description = 'Create a new account and owner user';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function handle(CreateAccountHandler $handler): int
    {
        $email = strtolower($this->argument('email'));
        $password = (string) $this->argument('password');
        $firstName = (string) $this->argument('first_name');

        // Basic validation (mirrors console password rules)
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return self::FAILURE;
        }

        $this->info('Account details:');
        $this->line("  Email: {$email}");
        $this->line("  Name: {$firstName} {$this->option('last_name')}");
        $this->line("  Timezone: " . ($this->option('timezone') ?? 'default'));
        $this->line("  Currency: " . ($this->option('currency_code') ?? 'auto'));
        $this->newLine();

        if (!$this->confirm('Create this account?', false)) {
            $this->info('Operation cancelled.');
            return self::FAILURE;
        }

        try {
            $account = $handler->handle(new CreateAccountDTO(
                email: $email,
                password: $password,
                first_name: $firstName,
                locale: $this->option('locale') ?? config('app.locale'),
                last_name: $this->option('last_name'),
                timezone: $this->option('timezone'),
                currency_code: $this->option('currency_code'),
                invite_token: $this->option('invite_token'),
            ));

            $this->logger->info('Account created via console command', [
                'account_id' => $account->getId(),
                'account_email' => $account->getEmail(),
                'command' => $this->getName(),
            ]);

            $this->newLine();
            $this->info('✓ Account successfully created');
            $this->line("  Account ID: {$account->getId()}");
            $this->line("  Account Name: {$account->getName()}");
            $this->line("  Account Email: {$account->getEmail()}");

            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error("{$field}: {$message}");
                }
            }
        } catch (Throwable $e) {
            $this->error('Failed to create account: ' . $e->getMessage());

            $this->logger->error('Account creation failed via console command', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return self::FAILURE;
    }
}
