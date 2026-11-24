<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    /**
     * 認証は B3・B4 の認証基盤で確かめる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 入力チェック（API定義書 6.3: 企業名は必須・100字以内、
     * 業界は必須・区分のいずれか、募集要項は任意・5,000字以内）。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:100'],
            'industry' => ['required', 'string', Rule::in(Company::INDUSTRIES)],
            'job_info_text' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
