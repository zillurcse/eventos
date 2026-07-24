<script setup lang="ts">
import type { LoungeOccupant } from '~/stores/loungeTables'

withDefaults(defineProps<{
  occupant: LoungeOccupant | null
  isMe: boolean
  full: boolean
  rotate?: number
}>(), { rotate: 0 })
defineEmits<{ click: [] }>()
</script>

<template>
  <div class="seatunit" :class="{ taken: occupant, me: isMe, empty: !occupant && !full }"
    :title="occupant ? occupant.name : (full ? 'Table full' : 'Sit here')" @click="$emit('click')">
    <div class="chairgfx" :style="{ transform: `rotate(${rotate}deg)` }">
      <img src="/lounge/seat-left.svg" class="arm left" alt="">
      <img src="/lounge/seat-middle.svg" class="base" alt="">
      <img src="/lounge/seat-right.svg" class="arm right" alt="">
      <img src="/lounge/seat-top.svg" class="back" alt="">
    </div>
    <UserAvatar v-if="occupant" :src="occupant.avatar_url" :name="occupant.name" class="occ"
      :class="{ top: rotate === 0, bottom: rotate === 180, left: rotate === 90, right: rotate === -90 }" />
  </div>
</template>

<style scoped>
.seatunit {
  position: relative;
  width: 78px;
  height: 66px;
  flex: 0 0 auto;
  cursor: default;
}

.seatunit.empty {
  cursor: pointer;
}

.seatunit.empty:hover .chairgfx {
  opacity: .8;
}

.seatunit.me {
  cursor: pointer;
}

.chairgfx {
  position: absolute;
  inset: 0;
}

.base {
  position: absolute;
  top: 14px;
  left: 50%;
  transform: translateX(-50%);
  width: 55px;
  height: 50px;
  object-fit: contain;
}

.back {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 56px;
  height: 22px;
  object-fit: contain;
  z-index: 1;
}

.arm {
  position: absolute;
  top: 6px;
  width: 12px;
  height: 54px;
  object-fit: contain;
  z-index: 2;
}

.arm.left {
  left: 0;
}

.arm.right {
  right: 0;
}

.occ {
  position: absolute;
  top: 40%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(15, 23, 42, .25);
  z-index: 3;
}

.occ.top {
  top: 64%;
  left: 50%;
}

.occ.bottom {
  top: 36%;
  left: 50%;
}

.occ.left {
  top: 52%;
  left: 36%;
}

.occ.right {
  top: 52%;
  left: 62%;
}
</style>
