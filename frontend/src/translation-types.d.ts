import 'vue'
declare module 'vue' { interface ComponentCustomProperties { $t: (message:string|null|undefined)=>string; $translatedOptions:(options:any[])=>any[] } }
