<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

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
     * 入力チェックの枠。項目とルールは API定義書 6.3 に従う
     * （企業名は必須・100字以内、業界は必須・区分のいずれか、
     * 募集要項は任意・5,000字以内）。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
