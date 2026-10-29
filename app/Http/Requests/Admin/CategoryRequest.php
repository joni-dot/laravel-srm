<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', $this->uniqueIgnoringCase(...)],
        ];
    }

    /**
     * "IT" and "it" are the same category, so compare names without case.
     */
    private function uniqueIgnoringCase(string $attribute, mixed $value, Closure $fail): void
    {
        $category = $this->route('category');
        $currentCategoryId = $category instanceof Category ? $category->id : null;

        $nameIsTaken = Category::query()
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->when($currentCategoryId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($nameIsTaken) {
            $fail('A category with this name already exists.');
        }
    }
}
