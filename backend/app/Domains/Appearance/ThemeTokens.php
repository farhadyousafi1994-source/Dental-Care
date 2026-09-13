<?php
namespace App\Domains\Appearance;
final class ThemeTokens {
    public static function rules(string $prefix='appearance'):array {
        $rules=[$prefix=>'required|array',"$prefix.primary"=>'required|regex:/^#[0-9a-fA-F]{6}$/',"$prefix.background"=>'required|regex:/^#[0-9a-fA-F]{6}$/',"$prefix.text"=>'required|regex:/^#[0-9a-fA-F]{6}$/',"$prefix.font"=>'required|in:Inter,Georgia,Arial',"$prefix.headingFont"=>'sometimes|in:Inter,Georgia,Arial',"$prefix.radius"=>'required|integer|min:0|max:40',"$prefix.container"=>'required|integer|min:800|max:1600',"$prefix.mode"=>'required|in:light,dark,system',"$prefix.fontSize"=>'sometimes|integer|min:12|max:22',"$prefix.lineHeight"=>'sometimes|numeric|min:1.2|max:2.2',"$prefix.spacing"=>'sometimes|integer|min:20|max:100',"$prefix.shadow"=>'sometimes|in:none,soft,raised'];
        foreach(['secondary','accent','surface','heading']as$color)$rules["$prefix.$color"]='sometimes|regex:/^#[0-9a-fA-F]{6}$/';
        return $rules;
    }
}
