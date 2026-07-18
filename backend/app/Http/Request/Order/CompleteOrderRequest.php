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

    public function rules(CompleteOrderValidator $orderValidator): array
    {
        return $orderValidator->rules();
    }

    public function messages(): array
    {
        return app(CompleteOrderValidator::class)->messages();
    }
}
