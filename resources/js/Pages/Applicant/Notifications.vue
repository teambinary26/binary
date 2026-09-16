<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    notifications: { type: Object, required: true },
});

const markRead = (id) => {
    useForm({}).post(route('applicant.notifications.read', id));
};
</script>

<template>
    <ApplicantLayout>
        <Head title="Notifications" />
        <PageHeader title="Notification center" kicker="Application updates" />
        <div class="panel divide-y divide-gov-border">
            <form
                v-for="notification in notifications.data"
                :key="notification.id"
                class="flex flex-col gap-3 px-3 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-4"
                :class="{ 'bg-gov-light': notification.unread }"
                @submit.prevent="markRead(notification.id)"
            >
                <div class="min-w-0">
                    <p class="break-words text-[11px] font-bold uppercase tracking-wide text-gov-blue">{{ notification.type }} · {{ notification.created_at }}</p>
                    <p class="break-words font-bold">{{ notification.title }}</p>
                    <p class="break-words text-sm">{{ notification.body }}</p>
                </div>
                <button class="btn-ghost btn-sm w-full shrink-0 sm:w-auto" type="submit">{{ notification.unread ? 'Mark read' : 'Open' }}</button>
            </form>
            <p v-if="!notifications.data.length" class="p-4 text-sm text-gov-muted">No notifications.</p>
        </div>
        <Pagination :paginator="notifications" />
    </ApplicantLayout>
</template>
