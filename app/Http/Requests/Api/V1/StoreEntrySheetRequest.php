<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EsAnswer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'company_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:50'],
            'questions' => ['required', 'array', 'min:1', 'max:30'],
            'questions.*.order' => ['required', 'integer'],
            'questions.*.type' => ['required', 'string', 'in:'.implode(',', array_keys(EsAnswer::ITEM_TYPES))],
            'questions.*.question_text' => ['required', 'string', 'max:500'],
            'questions.*.max_chars' => ['required', 'integer', 'min:1', 'max:2000'],
            'tone' => ['nullable', 'string', 'in:formal,confident,passionate'],
        ];
    }

    /**
     * 設問の order は同一 ES 内で重複不可（API定義書 v18 6.4）。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $orders = collect($this->input('questions', []))->pluck('order');

            if ($orders->count() !== $orders->unique()->count()) {
                $validator->errors()->add('questions', '設問の順序（order）が重複しています');
            }
        });
    }
}
