<template>
  <Teleport to="body">
    <div
      v-if="ctxMenu.visible"
      class="ctx-menu"
      :style="{ top: ctxMenu.y + 'px', left: ctxMenu.x + 'px' }"
      @click.stop
    >
      <button class="ctx-item" @click="emit('reply', ctxMenu.msg)">
        <i class="pi pi-reply" /> Balas
      </button>
      <!-- Star / Unstar -->
      <button
        v-if="ctxMenu.msg && !ctxMenu.msg.is_deleted"
        class="ctx-item"
        :class="{ 'ctx-starred': ctxMenu.msg?.is_starred }"
        @click="emit('toggleStar', ctxMenu.msg)"
      >
        <i :class="ctxMenu.msg?.is_starred ? 'pi pi-star-fill' : 'pi pi-star'" />
        {{ ctxMenu.msg?.is_starred ? 'Hapus Bintang' : 'Bintangi' }}
      </button>
      <!-- Pin / Unpin -->
      <button
        v-if="ctxMenu.msg && !ctxMenu.msg.is_deleted"
        class="ctx-item"
        :class="{ 'ctx-pinned': isPinned }"
        @click="emit('togglePin', ctxMenu.msg)"
      >
        <i :class="isPinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'" />
        {{ isPinned ? 'Lepas Sematan' : 'Sematkan' }}
      </button>
      <!-- Forward -->
      <button
        v-if="ctxMenu.msg && !ctxMenu.msg.is_deleted"
        class="ctx-item"
        @click="emit('forward', ctxMenu.msg)"
      >
        <i class="pi pi-share-alt" /> Teruskan
      </button>
      <button
        v-if="ctxMenu.msg?.message_type === 'text' && ctxMenu.msg?.sender_id === activeUserId"
        class="ctx-item"
        @click="emit('edit', ctxMenu.msg)"
      >
        <i class="pi pi-pencil" /> Edit
      </button>
      <button
        v-if="ctxMenu.msg?.sender_id === activeUserId"
        class="ctx-item ctx-danger"
        @click="emit('delete', ctxMenu.msg)"
      >
        <i class="pi pi-trash" /> Hapus
      </button>
    </div>
  </Teleport>
</template>

<script setup>
const props = defineProps({
  ctxMenu:      { type: Object, required: true },
  activeUserId: { type: [Number, String], default: null },
  pinnedMsgId:  { type: [Number, String], default: null },
})
const emit = defineEmits(['reply', 'edit', 'delete', 'toggleStar', 'togglePin', 'forward'])

import { computed } from 'vue'
const isPinned = computed(() => props.ctxMenu.msg?.id === props.pinnedMsgId)
</script>
