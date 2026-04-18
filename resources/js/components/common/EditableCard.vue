<template>

    <div class="glass-card p-4">

        <div v-if="editing">
            <input v-model="localTitle" class="input mb-2" />
            <textarea v-model="localBody" class="textarea"></textarea>

            <button class="button is-success is-small mt-2" @click="save">
                Save
            </button>
        </div>

        <div v-else>
            <p class="title is-6">{{ title }}</p>
            <p class="subtitle is-7">{{ body }}</p>

            <button class="button is-small is-info mt-2" @click="editing = true">
                Edit
            </button>
        </div>

    </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
    title: String,
    body: String
});

const emit = defineEmits(["update"]);

const editing = ref(false);

const localTitle = ref(props.title);
const localBody = ref(props.body);

watch(() => props.title, (v) => localTitle.value = v);
watch(() => props.body, (v) => localBody.value = v);

function save() {
    emit("update", {
        title: localTitle.value,
        body: localBody.value
    });

    editing.value = false;
}
</script>
