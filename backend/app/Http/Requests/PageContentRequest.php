<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageContentRequest extends FormRequest
{
    public function authorize(): bool { \App\Domains\Access\WebsiteAccess::check((int) $this->route('site'), 'pages'); return true; } // Controller checks website membership + pages capability.

    public function rules(): array
    {
        $isAutosave = $this->routeIs('pages.autosave');
        $slugRules = ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:190'];
        if (! $isAutosave) $slugRules[] = Rule::unique('pages')->where(fn ($q) => $q->where('website_id', $this->route('site'))->where('language', $this->input('language', 'en')))->ignore($this->route('page'));
        return [
            'version' => [$this->route('page') ? 'required' : 'sometimes', 'integer', 'min:1'],
            'autosave_version' => 'sometimes|integer|min:0',
            'title' => 'required|string|max:190', 'slug' => $slugRules,
            'status' => 'required|in:draft,published,scheduled,archived',
            'language' => 'required|exists:languages,code',
            'blocks' => 'required|array|max:100',
            'blocks.*.id' => 'required|string|max:100|distinct',
            'blocks.*.type' => 'required|string|max:40',
            'blocks.*.title' => 'nullable|string|max:500',
            'blocks.*.text' => 'nullable|string|max:20000',
            'blocks.*.image' => 'nullable|string|max:2048',
            'blocks.*.children' => 'sometimes|array',
            'blocks.*.items' => 'sometimes|array',
            'blocks.*.link' => 'nullable|string|max:2048',
            'blocks.*.imageAlt' => 'nullable|string|max:500',
            'blocks.*.imageStyle' => 'sometimes|array',
            'blocks.*.button' => 'nullable|string|max:200',
            'blocks.*.hidden' => 'sometimes|boolean',
            'blocks.*.align' => 'nullable|in:start,center,end',
            'seo' => 'nullable|array:title,description,canonical,robots,og_title,og_description,social_image',
            'seo.canonical' => 'nullable|url:http,https|max:2048',
            'seo.robots' => ['nullable', Rule::in(['index,follow','noindex,follow','noindex,nofollow'])],
            'seo.og_title' => 'nullable|string|max:190', 'seo.og_description' => 'nullable|string|max:500',
            'seo.social_image' => ['nullable','string','max:2048','regex:~^(https?://[^\s]+|/(?!/)[^\s]+)$~'], 'seo.title' => 'nullable|string|max:190', 'seo.description' => 'nullable|string|max:500',
            'publish_at' => $isAutosave ? 'nullable|date' : 'nullable|required_if:status,scheduled|date|after:now',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) { if ($v->errors()->isNotEmpty()) return; try { \App\Domains\Content\BlockSchema::validate($this->input('blocks', [])); } catch (\Illuminate\Validation\ValidationException $e) { foreach ($e->errors() as $messages) foreach ($messages as $message) $v->errors()->add('blocks', $message); } });
    }

    public function content(): array
    {
        return $this->safe()->except(['version', 'autosave_version']);
    }
}
