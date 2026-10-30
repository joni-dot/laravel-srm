<?php

namespace App\Http\Requests\Admin;

use App\Models\RatingCriterion;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RatingCriterionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $criterion = $this->route('ratingCriterion');

        return [
            'name' => [
                'required', 'string', 'max:100',
                new UniqueIgnoringCase(RatingCriterion::class, 'name', ignoreId: $criterion instanceof RatingCriterion ? $criterion->id : null),
            ],
        ];
    }
}
