<?php


namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Http\Request;
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
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.api_access_token', env('API_ACCESS_TOKEN'));
        $allowedIps = config('app.api_allowed_ips', env('API_ALLOWED_IPS'));

        // Check Token
        if ($token && $request->header('X-API-TOKEN') !== $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check IP
        if ($allowedIps) {
            $ips = array_map('trim', explode(',', $allowedIps));
            if (!in_array('*', $ips) && !in_array($request->ip(), $ips)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        // Impersonation
        $impersonateUserId = $request->header('X-SOS-IMPERSONATE-USER-ID');
        $impersonateAccountId = $request->header('X-SOS-IMPERSONATE-ACCOUNT-ID');

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
            ])->loginUsingId($user->getId());

            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
