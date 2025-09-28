<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\User;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateUserInAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|min:1',
            'last_name' => 'min:1|nullable',
            'role' => Rule::in(Role::valuesArray()),
            'email' => [
                'required',
                'email',
            ],
            'account_id' => 'required|min:1',
        ];
    }
}
