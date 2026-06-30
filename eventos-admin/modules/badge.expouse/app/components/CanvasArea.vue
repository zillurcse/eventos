<template>
  <div
    class="w-3/4 bg-gray-200 p-4"
    @dragover.prevent
    @drop="$emit('drop', $event)"
  >
    <div
      class="w-[210mm] h-[297mm] bg-white mx-auto relative"
      tabindex="0"
      ref="canvas"
    >
      <div
        v-for="(element, index) in elements"
        :key="index"
        :style="{
          position: 'absolute',
          left: element.x + 'px',
          top: element.y + 'px',
          width: element.props.w ? element.props.w + 'px' : 'auto',
          height: element.props.h ? element.props.h + 'px' : 'auto',
        }"
        @mousedown="$emit('startDrag', index)"
        :class="{ 'border-2 border-blue-500': selectedElement === index }"
      >
        <component
          :is="element.type"
          v-bind="element.props"
          @click="$emit('selectElement', index)"
        >
          <template v-if="element.type === 'text'">
            <h1
              contenteditable="true"
              @input="$emit('updateText', index, $event.target.innerText)"
              class="p-2 bg-gray-100 border"
              :style="{ minWidth: '10px', minHeight: '20px' }"
            >
              {{ element.props.text || element.props.exampleText }}
            </h1>
          </template>
          <template v-if="element.type === 'qrcode'">
            <img
              :src="
                'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' +
                encodeURIComponent(element.props.content)
              "
              :style="{
                width: element.props.size + 'px',
                height: element.props.size + 'px',
              }"
            />
          </template>
        </component>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps(["elements", "selectedElement"]);
defineEmits(["drop", "startDrag", "selectElement", "updateText"]);
</script>
