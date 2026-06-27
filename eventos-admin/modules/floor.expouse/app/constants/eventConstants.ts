const route = useRoute();

const queryEventId = ref(route.query.event as string);
const queryToken = ref(route.query.token as string);

watch(
  () => route.query,
  (newQuery) => {
    queryEventId.value = newQuery.event as string;
    queryToken.value = newQuery.token as string;
  },
  { immediate: true }
);

const event_id = atob(queryEventId.value);
const token = atob(queryToken.value);

export const EVENT_QUERY_DATA = {
  EVENT_ID: event_id,
  TOKEN: token,
};
