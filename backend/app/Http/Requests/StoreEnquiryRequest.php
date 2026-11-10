<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEnquiryRequest extends FormRequest
{
    private const ROLES = [
        'interior_designer', 'architect', 'landscape_designer', 'contractor', 'hospitality',
        'procurement', 'developer', 'reseller', 'private_customer', 'other',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:contact,product_enquiry,catalogue_download,cart_enquiry'],
            'apiVersion' => ['required_if:type,product_enquiry,cart_enquiry', 'prohibited_unless:type,product_enquiry,cart_enquiry', 'integer', 'in:2'],
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'message' => ['required_if:type,contact', 'sometimes', 'nullable', 'string', 'max:5000'],
            'source' => ['sometimes', 'array:pagePath,placement'],
            'source.pagePath' => ['sometimes', 'string', 'max:2048', 'regex:/^\/(?!\/)[^?#]*$/'],
            'source.placement' => ['sometimes', 'string', 'max:200'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product' => ['required_if:type,product_enquiry', 'prohibited_unless:type,product_enquiry', 'array:productId,quantity'],
            'product.productId' => ['required_if:type,product_enquiry', 'uuid'],
            'product.quantity' => ['required_if:type,product_enquiry', 'integer', 'min:1', 'max:999999'],
            'catalogueId' => ['required_if:type,catalogue_download', 'prohibited_unless:type,catalogue_download', 'string', 'max:64'],
            'role' => ['required_if:type,catalogue_download', 'prohibited_unless:type,catalogue_download', 'string', 'in:'.implode(',', self::ROLES)],
            'roleOther' => ['sometimes', 'nullable', 'prohibited_unless:role,other', 'string', 'max:200'],
            'items' => ['required_if:type,cart_enquiry', 'prohibited_unless:type,cart_enquiry', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:productId,quantity'],
            'items.*.productId' => ['required', 'uuid', 'distinct:ignore_case'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->input('type');
            if ($this->filled('website')) {
                $validator->errors()->add('website', 'The request could not be accepted.');
            }

            $common = ['type', 'name', 'phone', 'email', 'company', 'message', 'source', 'website'];
            $allowed = match ($type) {
                'product_enquiry' => [...$common, 'apiVersion', 'product'],
                'catalogue_download' => [...$common, 'catalogueId', 'role', 'roleOther'],
                'cart_enquiry' => [...$common, 'apiVersion', 'items'],
                default => [...$common],
            };
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'This field is not allowed for this enquiry type.');
            }

            if (is_array($this->input('items'))) {
                $seen = [];
                foreach ($this->input('items') as $index => $item) {
                    if (! is_array($item) || ! is_string($item['productId'] ?? null)) {
                        continue;
                    }
                    $id = strtolower($item['productId']);
                    if (isset($seen[$id])) {
                        $validator->errors()->add("items.{$index}.productId", 'Product IDs must be unique.');
                    }
                    $seen[$id] = true;
                }
            }
        }];
    }
}
