<script setup lang="ts">
withDefaults(defineProps<{items:any[];resolveLink:(url:string)=>string;nested?:boolean}>(),{nested:false})
</script>
<template><ul :class="['public-menu',{nested}]"><li v-for="(item,index) in items" :key="item.key||index"><details v-if="item.children?.length"><summary>{{ item.label }}</summary><div class="submenu-panel"><a class="menu-parent-link" :href="resolveLink(item.url)" :target="item.new_tab?'_blank':undefined" :rel="item.new_tab?'noopener noreferrer':undefined">{{ item.label }} ↗</a><PublicMenu :items="item.children" :resolve-link="resolveLink" nested/></div></details><a v-else :href="resolveLink(item.url)" :target="item.new_tab?'_blank':undefined" :rel="item.new_tab?'noopener noreferrer':undefined">{{ item.label }}</a></li></ul></template>
