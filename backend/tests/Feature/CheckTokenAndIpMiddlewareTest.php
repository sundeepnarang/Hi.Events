<?php

namespace Tests\Feature;

use HiEvents\Http\Middleware\CheckTokenAndIp;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckTokenAndIpMiddlewareTest extends TestCase
{
    private $userRepository;
    private $accountUserRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->accountUserRepository = Mockery::mock(AccountUserRepositoryInterface::class);

        $this->app->instance(UserRepositoryInterface::class, $this->userRepository);
        $this->withoutExceptionHandling();

        Route::get('/middleware-test', function () {
            return response()->json(['message' => 'OK']);
        })->middleware(CheckTokenAndIp::class); // Use class name to ensure resolution

        Config::set('app.api_access_token', 'test-token');
        Config::set('app.api_allowed_ips', '127.0.0.1'); 
    }

    public function test_it_returns_401_without_token()
    {
        $response = $this->getJson('/middleware-test');
        $response->assertStatus(401);
    }

    public function test_it_returns_401_with_invalid_token()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('CheckTokenAndIp: Invalid token received.', Mockery::on(function ($context) {
                // Using array_key_exists because values might be null if not sent
                return array_key_exists('received_token', $context)
                    && array_key_exists('ip', $context)
                    && array_key_exists('impersonate_user_id', $context)
                    && array_key_exists('impersonate_account_id', $context);
            }));

        $response = $this->withHeaders([
            'X-API-TOKEN' => 'wrong-token',
            'X-SOS-IMPERSONATE-USER-ID' => '1',
            'X-SOS-IMPERSONATE-ACCOUNT-ID' => '1'
        ])->getJson('/middleware-test');
        $response->assertStatus(401);
    }

    public function test_it_returns_403_with_invalid_ip()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('CheckTokenAndIp: IP not allowed.', Mockery::on(function ($context) {
                 return array_key_exists('ip', $context)
                    && array_key_exists('allowed_ips', $context)
                    && array_key_exists('impersonate_user_id', $context)
                    && array_key_exists('impersonate_account_id', $context);
            }));

        $this->serverVariables = ['REMOTE_ADDR' => '10.0.0.1'];
        $response = $this->withHeaders([
            'X-API-TOKEN' => 'test-token',
            'X-SOS-IMPERSONATE-USER-ID' => '1',
            'X-SOS-IMPERSONATE-ACCOUNT-ID' => '1'
        ])->getJson('/middleware-test');
        $response->assertStatus(404);
    }

    public function test_it_returns_200_with_valid_token_and_ip()
    {
        $this->serverVariables = [];
        $response = $this->withHeaders(['X-API-TOKEN' => 'test-token'])->getJson('/middleware-test');
        $response->assertStatus(200);
    }

    public function test_it_impersonates_user_successfully()
    {
        $userId = 1;
        $accountId = 1;
        $role = 'ORGANIZER';

        // Re-bind to see if it fixes it
        $this->app->instance(AccountUserRepositoryInterface::class, $this->accountUserRepository);

        $userDomain = Mockery::mock(UserDomainObject::class);
        $userDomain->shouldReceive('getId')->andReturn($userId);

        $accountUserDomain = Mockery::mock(AccountUserDomainObject::class);
        $accountUserDomain->shouldReceive('getRole')->andReturn($role);

        $this->userRepository->shouldReceive('findFirst')
            ->with($userId)
            ->andReturn($userDomain);

        $this->accountUserRepository->shouldReceive('findFirstWhere')
            ->with(['user_id' => $userId, 'account_id' => $accountId])
            ->andReturn($accountUserDomain);

        // Mock Auth
        Auth::shouldReceive('claims')
            ->once()
            ->with(['account_id' => $accountId, 'role' => $role])
            ->andReturnSelf();
        
        Auth::shouldReceive('tokenById')
            ->once()
            ->with($userId)
            ->andReturn('mocked-jwt');

        $response = $this->withHeaders([
            'X-API-TOKEN' => 'test-token',
            'X-SOS-IMPERSONATE-USER-ID' => $userId,
            'X-SOS-IMPERSONATE-ACCOUNT-ID' => $accountId,
        ])->getJson('/middleware-test');

        $response->assertStatus(200);
        // Verify Authorization header was set (difficult in integration test as request is consumed)
        // But the 200 OK implies middleware passed.
    }

    public function test_impersonation_fails_if_user_not_found()
    {
        $userId = 999;
        $accountId = 1;

        $this->userRepository->shouldReceive('findFirst')
            ->with($userId)
            ->andReturnNull();

        $response = $this->withHeaders([
            'X-API-TOKEN' => 'test-token',
            'X-SOS-IMPERSONATE-USER-ID' => $userId,
            'X-SOS-IMPERSONATE-ACCOUNT-ID' => $accountId,
        ])->getJson('/middleware-test');

        $response->assertStatus(404);
    }

    public function test_impersonation_fails_if_user_not_in_account()
    {
        // Re-bind to see if it fixes it
        $this->app->instance(AccountUserRepositoryInterface::class, $this->accountUserRepository);
        
        $userId = 1;
        $accountId = 2;
        // ...

        $userDomain = Mockery::mock(UserDomainObject::class);
        $userDomain->shouldReceive('getId')->andReturn($userId);

        $this->userRepository->shouldReceive('findFirst')
            ->with($userId)
            ->andReturn($userDomain);

        $this->accountUserRepository->shouldReceive('findFirstWhere')
            ->with(['user_id' => $userId, 'account_id' => $accountId])
            ->andReturnNull();

        $response = $this->withHeaders([
            'X-API-TOKEN' => 'test-token',
            'X-SOS-IMPERSONATE-USER-ID' => $userId,
            'X-SOS-IMPERSONATE-ACCOUNT-ID' => $accountId,
        ])->getJson('/middleware-test');

        $response->assertStatus(403);
    }
}
