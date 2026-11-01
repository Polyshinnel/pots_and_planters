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
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'message' => ['required_if:type,contact', 'sometimes', 'nullable', 'string', 'max:5000'],
            'source' => ['sometimes', 'array:pagePath,placement'],
            'source.pagePath' => ['sometimes', 'string', 'max:2048', 'regex:/^\/(?!\/)[^?#]*$/'],
            'source.placement' => ['sometimes', 'string', 'max:200'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'productId' => ['required_if:type,product_enquiry', 'prohibited_unless:type,product_enquiry', 'string', 'max:100'],
            'sku' => ['required_if:type,product_enquiry', 'prohibited_unless:type,product_enquiry', 'string', 'max:100'],
            'quantity' => ['required_if:type,product_enquiry', 'prohibited_unless:type,product_enquiry', 'integer', 'min:1', 'max:1000000'],
            'catalogueId' => ['required_if:type,catalogue_download', 'prohibited_unless:type,catalogue_download', 'string', 'max:64'],
            'role' => ['required_if:type,catalogue_download', 'prohibited_unless:type,catalogue_download', 'string', 'in:'.implode(',', self::ROLES)],
            'roleOther' => ['sometimes', 'nullable', 'prohibited_unless:role,other', 'string', 'max:200'],
            'items' => ['required_if:type,cart_enquiry', 'prohibited_unless:type,cart_enquiry', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:productId,sku,quantity'],
            'items.*.productId' => ['required', 'string', 'max:100', 'distinct'],
            'items.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
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
                'product_enquiry' => [...$common, 'productId', 'sku', 'quantity'],
                'catalogue_download' => [...$common, 'catalogueId', 'role', 'roleOther'],
                'cart_enquiry' => [...$common, 'items'],
                default => [...$common],
            };
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'This field is not allowed for this enquiry type.');
            }
        }];
    }
}
