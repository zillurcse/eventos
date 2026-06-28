<template>
  <div class="flex min-h-screen">
    <!-- Left Sidebar -->
    <aside
      class="bg-white shadow-md w-20 md:w-24 lg:w-28 xl:w-32 flex flex-col justify-between"
    >
      <nav class="flex-1">
        <ul
          class="flex flex-col items-center space-y-2 md:space-y-3 lg:space-y-4 xl:space-y-5 p-3"
        >
          <li v-for="item in menuItems" :key="item.name">
            <button
              class="flex flex-col items-center justify-center gap-1 text-xs font-medium px-2 py-2 rounded-md transition-all w-full"
              :class="{
                'text-indigo-700 bg-indigo-100': activeLeftItem === item.name,
                'text-gray-700 hover:bg-indigo-50':
                  activeLeftItem !== item.name,
              }"
              @click="handleLeftMenuClick(item.name)"
            >
              <NuxtIcon :name="item.icon" class="text-2xl md:text-3xl" />
              <span class="text-[10px] md:text-xs text-center leading-tight">
                {{ item.name }}
              </span>
            </button>
          </li>
        </ul>
      </nav>

      <!-- Search Button -->
      <div class="p-3 flex justify-center">
        <a
          href="#"
          class="flex flex-col items-center justify-center text-xs text-gray-600 hover:text-indigo-600"
        >
          <NuxtIcon name="mdi:magnify" class="text-2xl md:text-3xl" />
          <span class="text-[10px] md:text-xs">Search</span>
        </a>
      </div>
    </aside>

    <!-- Right Sidebar (menu) -->
    <aside
      v-if="showRightSidebar"
      class="bg-gray-50 shadow-inner w-48 md:w-56 lg:w-64 p-4 flex flex-col"
    >
      <button
        @click="closeRightSidebar"
        class="mt-2 self-end text-gray-500 hover:text-gray-700"
        title="Close Right Sidebar"
      >
        <NuxtIcon name="ph:caret-double-left-duotone" class="text-xl" />
      </button>
      <h3 class="text-lg font-semibold mb-4">Right Sidebar Menu</h3>
      <ul class="space-y-2 flex-1 overflow-auto">
        <li v-for="item in rightSidebarItems" :key="item.name">
          <button
            class="w-full text-left px-3 py-2 rounded-md hover:bg-indigo-100"
            :class="{
              'bg-indigo-200 font-semibold': activeRightItem === item.name,
            }"
            @click="handleRightMenuClick(item.name)"
          >
            {{ item.name }}
          </button>
        </li>
      </ul>
    </aside>

    <!-- Content Panel -->
    <section
      v-if="showContentPanel"
      class="flex-1 bg-white p-6 border-l border-gray-200 overflow-auto"
    >
      <h2 class="text-2xl font-semibold mb-4">{{ activeRightItem }}</h2>
      <div
        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 p-6"
      >
        <!-- Badge Card -->
        <div
          class="bg-gray-100 border border-gray-200 rounded-lg shadow-sm p-6 flex flex-col items-center justify-center relative hover:shadow-md transition-shadow"
        >
          <!-- Badge Preview -->
          <div
            class="w-32 h-48 border border-gray-300 rounded-md bg-white flex flex-col items-center justify-between p-4 relative"
            :class="{ 'w-48 h-32': pageStore.badgeOrientation === 'landscape' }"
          >
            <!-- Badge Header (e.g., Event Name or Type) -->
            <div class="text-center">
              <span class="text-sm font-semibold text-gray-700">{{
                badgeType || "Event Badge"
              }}</span>
            </div>

            <!-- Badge Icon or Barcode -->
            <NuxtIcon name="mdi:barcode" class="text-gray-500 text-4xl mb-2" />

            <!-- Placeholder for Name or ID -->
            <div class="text-center">
              <div class="w-16 h-2 bg-gray-200 mb-1 rounded"></div>
              <div class="w-12 h-2 bg-gray-200 mb-1 rounded"></div>
            </div>

            <!-- QR Code or Access Indicator -->
            <NuxtIcon name="mdi:qrcode-scan" class="text-gray-500 text-3xl" />

            <!-- Optional Lanyard Hole -->
            <div
              class="absolute top-2 left-2 w-3 h-3 rounded-full bg-gray-300 border border-gray-400"
            ></div>
          </div>

          <!-- Badge Info -->
          <div class="mt-4 text-center">
            <p class="text-sm text-gray-600">
              Badge Type: {{ badgeType || "Standard" }}
            </p>
            <p class="text-xs text-gray-500">
              Access: {{ accessLevel || "General" }}
            </p>
          </div>

          <!-- Create Badge Button with Dropdown -->
          <div class="relative mt-4">
            <button
              @click="toggleDropdown"
              class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded focus:outline-none flex items-center transition-colors"
            >
              Create Badge
              <NuxtIcon
                name="mdi:chevron-down"
                class="inline-block ml-2 text-white text-lg transition-transform"
                :class="{ 'rotate-180': dropdownOpen }"
              />
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="dropdownOpen"
              class="absolute top-full mt-2 w-48 bg-white border border-gray-300 rounded shadow-lg z-10"
            >
              <ul>
                <li
                  v-for="(item, index) in dropdownItems"
                  :key="index"
                  class="px-4 py-2 hover:bg-indigo-600 hover:text-white cursor-pointer transition-colors"
                  @click="onSelect(item)"
                >
                  {{ item }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <!-- 
      <p>
        Here is the content for <strong>{{ activeRightItem }}</strong
        >.
      </p>
      <button
        @click="closeContentPanel"
        class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
      >
        Close Content Panel
      </button>
      -->
    </section>

    <!-- Badge Options Modal -->
    <BadgeOptionsModal />
    <!-- 
    :show="pageStore.showModal"
      :badge-size-preset="badgeSizePreset"
      :badge-size="badgeSize"
      :badge-orientation="badgeOrientation"
      :custom-width="customWidth"
      :custom-height="customHeight"
      @update:badge-size-preset="badgeSizePreset = $event"
      @update:badge-size="badgeSize = $event"
      @update:badge-orientation="badgeOrientation = $event"
      @update:custom-width="customWidth = $event"
      @update:custom-height="customHeight = $event"
      @close="closeModal"
      @save="saveBadgeConfig"
      -->
  </div>
</template>

<script setup>
import { usePageStore } from "@badge/stores/usePageStore";

const pageStore = usePageStore();

const menuItems = [
  { name: "Dashboard", icon: "mdi:view-dashboard-outline" },
  { name: "Manage", icon: "mdi:calendar-edit" },
  { name: "Registrations", icon: "mdi:account-plus" },
  { name: "Exhibitors", icon: "mdi:briefcase-outline" },
  { name: "Design", icon: "mdi:vector-square" },
  { name: "Communicate", icon: "mdi:email-outline" },
  { name: "Reports", icon: "mdi:chart-pie" },
  { name: "Event Day", icon: "mdi:calendar-check-outline" },
  { name: "Settings", icon: "mdi:cog-outline" },
];

// Reactive state for badge card
const dropdownOpen = ref(false);
const badgeType = ref(null);
const accessLevel = ref(null);
const dropdownItems = ref([
  "Attendee",
  "Organizer",
  "Staff",
  "Speaker",
  "Sponsor",
  "Exhibitor",
  "VIP Pass",
  "Press Credential",
]);

// Reactive state for modal

// const badgeSizePreset = ref("preset");
// const badgeSize = ref("A6");
// const badgeOrientation = ref("portrait");
// const customWidth = ref(100);
// const customHeight = ref(150);

// Update width and height based on selected preset
// watch([badgeSize, badgeSizePreset], () => {
//   if (badgeSizePreset.value === "preset") {
//     switch (badgeSize.value) {
//       case "A4":
//         customWidth.value = 210;
//         customHeight.value = 297;
//         break;
//       case "A6":
//         customWidth.value = 105;
//         customHeight.value = 148;
//         break;
//       case "A7":
//         customWidth.value = 74;
//         customHeight.value = 105;
//         break;
//     }
//   }
// });

// Methods
const toggleDropdown = () => {
  dropdownOpen.value = !dropdownOpen.value;
};

const onSelect = (item) => {
  badgeType.value = item;
  accessLevel.value = getAccessLevel(item);
  dropdownOpen.value = false;
  pageStore.showModal = true;
};

const getAccessLevel = (item) => {
  const accessLevels = {
    Attendee: "General",
    Organizer: "Full Access",
    Staff: "Restricted",
    Speaker: "Stage Access",
    Sponsor: "VIP",
    Exhibitor: "Booth Access",
    "VIP Pass": "Exclusive",
    "Press Credential": "Media",
  };
  return accessLevels[item] || "General";
};

// Right sidebar menu items
const rightSidebarItems = [
  { name: "Badge" },
  // { name: "Settings" },
  // { name: "Notifications" },
  // { name: "Billing" },
];

const activeLeftItem = ref(null);
const activeRightItem = ref(null);

const showRightSidebar = ref(false);
const showContentPanel = ref(false);

const handleLeftMenuClick = (name) => {
  activeLeftItem.value = name;
  showRightSidebar.value = true;
  activeRightItem.value = null;
  showContentPanel.value = false;
};

const handleRightMenuClick = (name) => {
  activeRightItem.value = name;
  showContentPanel.value = true;
};

const closeRightSidebar = () => {
  showRightSidebar.value = false;
  activeRightItem.value = null;
  showContentPanel.value = false;
};

const closeContentPanel = () => {
  showContentPanel.value = false;
  activeRightItem.value = null;
};
</script>
