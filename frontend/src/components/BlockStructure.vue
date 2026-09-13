<script setup lang="ts">
import {t} from "../i18n"
import draggable from'vuedraggable'
import type{Block}from'../types'
import Icon from'./Icon.vue'
const model=defineModel<Block[]>({required:true})
defineProps<{selected:string|null}>()
const emit=defineEmits<{select:[id:string];change:[]}>()
</script>
<template><draggable v-model="model" item-key="id" handle=".drag-handle" @start="emit('change')"><template #item="{element}"><div><button :class="['structure-item',{active:selected===element.id}]" @click="emit('select',element.id)"><Icon name="GripVertical" :size="15" class="drag-handle"/><span>{{ $t(element.type) }}</span><Icon v-if="element.hidden" name="EyeOff" :size="14"/></button><div v-for="(column,i) in element.children||[]" :key="i" class="structure-column"><small>{{ $t("Column") }} {{ Number(i)+1 }}</small><BlockStructure v-model="element.children[i]" :selected="selected" @select="emit('select',$event)" @change="emit('change')"/></div></div></template></draggable></template>
