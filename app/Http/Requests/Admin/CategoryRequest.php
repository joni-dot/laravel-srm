<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:100',
                new UniqueIgnoringCase(Category::class, 'name', ignoreId: $category instanceof Category ? $category->id : null),
            ],
        ];
    }
}
