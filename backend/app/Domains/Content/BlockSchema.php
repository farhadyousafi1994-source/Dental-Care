<?php
namespace App\Domains\Content;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class BlockSchema {
    public const TYPES=['Hero','Text','Image','Image + Text','Video','Gallery','Slider','Banner','Cards','Services','Features','Testimonials','Team','Statistics','Pricing','FAQ','CTA','Contact','Newsletter','Logos','Events','Announcements','Social Media','Section','Columns'];
    public static function validate(array $blocks): void {
        $count=0;$itemCount=0;$ids=[];
        $link=['nullable','string','max:2048','regex:~^(https?://[^\s]+|mailto:[^\s]+|tel:[+0-9 ()-]+|#[a-zA-Z0-9_-]+|/(?!/)[^\s]*)$~'];
        $image=['nullable','string','max:2048','regex:~^(https?://[^\s]+|/(?!/)[^\s]+)$~'];
        $fail=fn($message)=>throw ValidationException::withMessages(['blocks'=>[$message]]);
        $walk=function($list,$depth)use(&$walk,&$count,&$ids,&$itemCount,$fail,$link,$image){
            if($depth>6)$fail('Layouts support up to six nesting levels.');
            foreach($list as $block){
                if(++$count>100)$fail('A page supports at most 100 blocks, including nested content.');
                if(!is_array($block))$fail('Every block must be an object.');
                validator($block,[
                    'id'=>'required|string|max:100','type'=>['required',Rule::in(self::TYPES)],'title'=>'nullable|string|max:500','text'=>'nullable|string|max:20000',
                    'image'=>$image,'imageAlt'=>'nullable|string|max:500','link'=>$link,'button'=>'nullable|string|max:200','hidden'=>'sometimes|boolean','align'=>'nullable|in:start,center,end',
                    'imageStyle'=>'sometimes|array:fit,position,radius,opacity','imageStyle.fit'=>'sometimes|in:cover,contain','imageStyle.position'=>'sometimes|in:center,top,bottom,left,right','imageStyle.radius'=>'sometimes|integer|min:0|max:100','imageStyle.opacity'=>'sometimes|numeric|min:0|max:1',
                    'items'=>'sometimes|array|max:100','children'=>'sometimes|array|min:1|max:4','children.*'=>'array|max:100',
                ])->validate();
                if(in_array($block['id'],$ids,true))$fail('Block IDs must be unique across the whole page.');$ids[]=$block['id'];
                $itemIds=[];
                foreach($block['items']??[] as $item){
                    if(!is_array($item))$fail('Every content item must be an object.');
                    if(++$itemCount>500)$fail('A page supports at most 500 content items.');
                    validator($item,['id'=>'required|string|max:100','title'=>'nullable|string|max:500','text'=>'nullable|string|max:5000','image'=>$image,'label'=>'nullable|string|max:100','link'=>$link,'role'=>'nullable|string|max:150','price'=>'nullable|string|max:150'])->validate();
                    if(in_array($item['id'],$itemIds,true))$fail('Content item IDs must be unique within their block.');$itemIds[]=$item['id'];
                }
                if(isset($block['children'])){if(!in_array($block['type'],['Section','Columns']))$fail('Only Section and Columns blocks may contain child blocks.');foreach($block['children']as$column)$walk($column,$depth+1);}
            }
        };
        $walk($blocks,1);
        if(strlen(json_encode($blocks))>2000000)$fail('Page blocks must be under 2 MB.');
    }
}
