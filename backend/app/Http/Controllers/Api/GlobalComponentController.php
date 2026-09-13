<?php
namespace App\Http\Controllers\Api;

use App\Domains\Access\{Access, WebsiteAccess};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GlobalComponentController extends Controller
{
    public function save(Request $request, int $site)
    {
        WebsiteAccess::check($site, 'appearance');
        $data = $request->validate([
            'name' => ['required', Rule::in(['Header', 'Footer', 'Announcement Bar', 'Global CTA'])],
            'content' => 'required|array:enabled,title,text,logo,link,button',
            'content.enabled' => 'sometimes|boolean',
            'content.title' => 'nullable|string|max:190',
            'content.text' => 'nullable|string|max:2000',
            'content.logo' => ['nullable', 'string', 'max:2048', 'regex:~^(https?://[^\s]+|/(?!/)[^\s]+)$~'],
            'content.link' => ['nullable', 'string', 'max:2048', 'regex:~^(https?://[^\s]+|mailto:[^\s]+|#[a-zA-Z0-9_-]+|/?[a-zA-Z0-9_/-]+)$~'],
            'content.button' => 'nullable|string|max:100',
        ]);
        DB::transaction(function () use ($site, $data) {
            $old = DB::table('global_components')->where('website_id', $site)->where('name', $data['name'])->first();
            DB::table('global_components')->updateOrInsert(['website_id' => $site, 'name' => $data['name']], ['content' => json_encode($data['content']), 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
            Access::audit('updated global component', $data['name'], $site);
        });
        return ['ok' => true];
    }
}
