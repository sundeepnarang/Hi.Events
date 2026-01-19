<?php


namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAndIp
{
    public function __construct(
        private readonly UserRepositoryInterface        $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    )
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.api_access_token', env('API_ACCESS_TOKEN'));
        $allowedIps = config('app.api_allowed_ips', env('API_ALLOWED_IPS'));

        if ($token && $request->header('X-API-TOKEN') !== $token) {
            Log::warning('CheckTokenAndIp: Invalid token received.', [
                'received_token' => $request->header('X-API-TOKEN'),
                'ip' => $request->ip(),
                'impersonate_user_id' => $request->header('X-SOS-IMPERSONATE-USER-ID'),
                'impersonate_account_id' => $request->header('X-SOS-IMPERSONATE-ACCOUNT-ID'),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check IP
        if ($allowedIps) {
            $ips = array_map('trim', explode(',', $allowedIps));
            if (!in_array('*', $ips) && !in_array($request->ip(), $ips)) {
                Log::warning('CheckTokenAndIp: IP not allowed.', [
                    'ip' => $request->ip(),
                    'allowed_ips' => $allowedIps,
                    'impersonate_user_id' => $request->header('X-SOS-IMPERSONATE-USER-ID'),
                    'impersonate_account_id' => $request->header('X-SOS-IMPERSONATE-ACCOUNT-ID'),
                ]);
                return response()->json(['message' => 'Not Found'], 404);
            }
        }

        // Impersonation
        $impersonateUserId = $request->header('X-SOS-IMPERSONATE-USER-ID');
        $impersonateAccountId = $request->header('X-SOS-IMPERSONATE-ACCOUNT-ID');

        Log::debug('CheckTokenAndIp: Impersonation requested.', [
            'impersonate_user_id' => $impersonateUserId,
            'impersonate_account_id' => $impersonateAccountId,
            'ip' => $request->ip()
        ]);

        if ($impersonateUserId && $impersonateAccountId) {
            $user = $this->userRepository->findFirst((int)$impersonateUserId);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $accountUser = $this->accountUserRepository->findFirstWhere([
                'user_id' => $user->getId(),
                'account_id' => $impersonateAccountId
            ]);

            if (!$accountUser) {
                return response()->json(['message' => 'User does not belong to this account'], 403);
            }
            
            // We need the User model for login, but UserRepository returns a DomainObject.
            // However, we can use the ID to login via Auth::loginUsingId which might fetch the user again using the provider.
            // Or we can assume that if we validated the user exists, we can use loginUsingId.
            // But loginUsingId hits the DB.
            
            // If we want to avoid DB hits in tests, we should mock Auth::login or Auth::loginUsingId.
            // But Auth facade is hard to mock partially.
            
            // Let's stick to using the repositories for validation, and for login we will use the logic.
            // If we use repository, we get DomainObject.
            
            $token = auth()->claims([
                'account_id' => (int)$impersonateAccountId,
                'role' => $accountUser->getRole(),
            ])->tokenById($user->getId());

            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
