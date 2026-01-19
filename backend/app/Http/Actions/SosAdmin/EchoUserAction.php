<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\CreateUserRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\User\CreateUserHandler;
use HiEvents\Services\Application\Handlers\User\DTO\CreateUserDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class EchoUserAction extends BaseAction
{
    public function __construct()
    {
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            resource: UserResource::class,
            data: $this->getAuthenticatedUser(),
            statusCode: ResponseCodes::HTTP_OK
        );
    }
}
