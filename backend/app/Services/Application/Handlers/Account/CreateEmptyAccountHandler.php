<?php

namespace HiEvents\Services\Application\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountConfigurationDoesNotExist;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountRegistrationDisabledException;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use NumberFormatter;
use Throwable;

class  CreateEmptyAccountHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface              $accountRepository,
        private readonly DatabaseManager                         $databaseManager,
        private readonly AccountConfigurationRepositoryInterface $accountConfigurationRepository,
        private readonly LoggerInterface                         $logger,
        private readonly Repository                              $config,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateAccountDTO $accountData, string $clientIp ): AccountDomainObject
    {

        $allowedIp = $this->config->get('app.account_registration_allowed_ip');

        if($clientIp == null || $clientIp != $allowedIp ){
            $this->logger->warning('Client IP address is not allowed to create an account', [
                '$clientIp' => $clientIp,
                '$$allowedIp' => $allowedIp
            ]);
            throw new AccountRegistrationDisabledException();
        }

        $isSaasMode = $this->config->get('app.saas_mode_enabled');

        return $this->databaseManager->transaction(function () use ($isSaasMode, $accountData) {
            $account = $this->accountRepository->create([
                'timezone' => $this->getTimezone($accountData),
                'currency_code' => $this->getCurrencyCode($accountData),
                'name' => $accountData->first_name . ($accountData->last_name ? ' ' . $accountData->last_name : ''),
                'email' => strtolower($accountData->email),
                'short_id' => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
                // If the app is not running in SaaS mode, we can immediately verify the account.
                // Same goes for the email verification below.
                'account_verified_at' => $isSaasMode ? null : now()->toDateTimeString(),
                'account_configuration_id' => $this->getAccountConfigurationId($accountData),
            ]);

            return $account;
        });
    }

    private function getTimezone(CreateAccountDTO $accountData): ?string
    {
        return $accountData->timezone ?? $this->config->get('app.default_timezone');
    }

    private function getCurrencyCode(CreateAccountDTO $accountData): string
    {
        $defaultCurrency = $this->config->get('app.default_currency_code');

        if ($accountData->currency_code !== null) {
            return $accountData->currency_code;
        }

        if ($accountData->locale !== null) {
            $numberFormatter = new NumberFormatter($accountData->locale, NumberFormatter::CURRENCY);
            $guessedCode = $numberFormatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);

            // 'XXX' denotes an unknown currency
            if ($guessedCode && $guessedCode !== 'XXX') {
                return $guessedCode;
            }
        }

        return $defaultCurrency;
    }

    /**
     * @throws AccountConfigurationDoesNotExist
     */
    private function getAccountConfigurationId(CreateAccountDTO $accountData): int
    {
        $defaultConfiguration = $this->accountConfigurationRepository->findFirstWhere([
            'is_system_default' => true,
        ]);

        if ($defaultConfiguration === null) {
            $this->logger->error('No default account configuration found');
            throw new AccountConfigurationDoesNotExist(
                __('There is no default account configuration available')
            );
        }

        return $defaultConfiguration->getId();
    }
}
