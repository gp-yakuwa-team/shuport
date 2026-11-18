<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntrySheetRequest extends FormRequest
{
    /**
     * 認証は B3・B4 の認証基盤で確かめる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 入力チェックの枠。項目とルールは API定義書 6.4 に従う
     * （設問は30件まで、設問の最大文字数は2,000までをサーバーの入力チェックで確かめる）。
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
