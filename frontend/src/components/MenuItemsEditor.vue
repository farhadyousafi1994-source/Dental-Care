<script setup lang="ts">
import {t} from "../i18n"
import draggable from 'vuedraggable'
import Icon from './Icon.vue'
export interface MenuItem { key:string; label:string; url:string; new_tab:boolean; children:MenuItem[] }
const model=defineModel<MenuItem[]>({required:true})
const props=withDefaults(defineProps<{depth?:number}>(),{depth:1})
const emit=defineEmits<{outdent:[index:number]}>()
const create=():MenuItem=>({key:crypto.randomUUID(),label:'New link',url:'home',new_tab:false,children:[]})
function height(item:MenuItem):number{return 1+Math.max(0,...item.children.map(height))}
function indent(index:number){const item=model.value[index];if(index<1||props.depth+height(item)>3)return;model.value[index-1].children.push(item);model.value.splice(index,1)}
function outdentChild(parent:number,child:number){const item=model.value[parent].children.splice(child,1)[0];model.value.splice(parent+1,0,item)}
</script>
<template><draggable v-model="model" item-key="key" handle=".drag-handle" class="menu-editor-level"><template #item="{element,index}"><div class="menu-tree-item"><div class="menu-tree-row"><Icon name="GripVertical" class="drag-handle" :size="17"/><q-input outlined dense v-model="element.label" :label="$t('Link label')" maxlength="100"/><q-input outlined dense v-model="element.url" :label="$t('Page slug or URL')" maxlength="2048"/><q-btn flat dense round :aria-label="$t('Link actions')"><Icon name="Ellipsis" :size="18"/><q-menu><q-list><q-item clickable v-close-popup v-if="depth<3" @click="element.children.push(create())"><q-item-section>{{ $t("Add child link") }}</q-item-section></q-item><q-item clickable v-close-popup :disable="index===0 || depth+height(element)>3" @click="indent(index)"><q-item-section>{{ $t("Indent under previous link") }}</q-item-section></q-item><q-item clickable v-close-popup v-if="depth>1" @click="emit('outdent',index)"><q-item-section>{{ $t("Move up one level") }}</q-item-section></q-item><q-item clickable v-close-popup @click="element.new_tab=!element.new_tab"><q-item-section>{{ $t(element.new_tab?'Open in same tab':'Open in new tab') }}</q-item-section></q-item><q-item clickable v-close-popup @click="model.splice(index,1)"><q-item-section class="text-negative">{{ $t("Delete link and children") }}</q-item-section></q-item></q-list></q-menu></q-btn></div><small v-if="element.new_tab" class="new-tab-note">{{ $t("Opens in a new tab") }}</small><MenuItemsEditor v-if="element.children.length" v-model="element.children" :depth="depth+1" @outdent="outdentChild(index,$event)"/></div></template></draggable></template>
