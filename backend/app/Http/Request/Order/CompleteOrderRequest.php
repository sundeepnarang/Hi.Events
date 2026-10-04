<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\CompleteOrderValidator;

class CompleteOrderRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $order = $this->input('order', []);
        if (isset($order['email']) && is_string($order['email'])) {
            $order['email'] = strtolower(trim($order['email']));
        }
        if (isset($order['email_confirmation']) && is_string($order['email_confirmation'])) {
            $order['email_confirmation'] = strtolower(trim($order['email_confirmation']));
        }

        $products = $this->input('products', []);
        if (is_array($products)) {
            foreach ($products as &$product) {
                if (isset($product['email']) && is_string($product['email'])) {
                    $product['email'] = strtolower(trim($product['email']));
                }
                if (isset($product['email_confirmation']) && is_string($product['email_confirmation'])) {
                    $product['email_confirmation'] = strtolower(trim($product['email_confirmation']));
                }
            }
        }

        $this->merge([
            'order' => $order,
            'products' => $products,
        ]);
    }

    public function rules(): array
    {
        if ($this->route() === null) {
            return [
                'order.first_name' => ['required', 'string', 'max:40'],
                'order.last_name' => ['required', 'string', 'max:40'],
                'order.email' => ['required', 'email'],
                'order.email_confirmation' => ['required', 'email', 'same:order.email'],
                'order.questions' => ['array'],
                'order.address' => ['array'],
                'order.address.address_line_1' => ['nullable', 'string', 'max:255'],
                'order.address.address_line_2' => ['nullable', 'string', 'max:255'],
                'order.address.city' => ['nullable', 'string', 'max:85'],
                'order.address.state_or_region' => ['nullable', 'string', 'max:85'],
                'order.address.zip_or_postal_code' => ['nullable', 'string', 'max:85'],
                'order.address.country' => ['nullable', 'string', 'max:2'],
                'products' => ['array'],
                'products.*.seat_uid' => ['nullable', 'string', 'max:24'],
            ];
        }

        return app(CompleteOrderValidator::class)->rules();
    }

    public function messages(): array
    {
        return app(CompleteOrderValidator::class)->messages();
    }
}
